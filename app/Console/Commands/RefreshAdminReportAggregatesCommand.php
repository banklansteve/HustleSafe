<?php

namespace App\Console\Commands;

use App\Enums\QuestStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAdminReportAggregatesCommand extends Command
{
    protected $signature = 'admin-reports:refresh-aggregates {--from=} {--to=}';

    protected $description = 'Refresh daily aggregate tables used by advanced admin reporting.';

    public function handle(): int
    {
        [$from, $to] = $this->range();

        $this->purge($from, $to);
        $this->refreshPlatform($from, $to);
        $this->refreshUsers($from, $to);
        $this->refreshCategories($from, $to);
        $this->refreshLocations($from, $to);
        $this->refreshRevenue($from, $to);

        $this->info("Admin report aggregates refreshed from {$from->toDateString()} to {$to->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(): array
    {
        $from = $this->option('from')
            ? CarbonImmutable::parse((string) $this->option('from'))->startOfDay()
            : CarbonImmutable::parse(DB::table('users')->min('created_at') ?: now()->subYear())->startOfDay();

        $to = $this->option('to')
            ? CarbonImmutable::parse((string) $this->option('to'))->endOfDay()
            : now()->toImmutable()->endOfDay();

        return [$from, $to];
    }

    private function purge(CarbonImmutable $from, CarbonImmutable $to): void
    {
        foreach ([
            'admin_report_platform_daily_metrics',
            'admin_report_user_daily_metrics',
            'admin_report_category_daily_metrics',
            'admin_report_location_daily_metrics',
            'admin_report_revenue_daily_metrics',
        ] as $table) {
            DB::table($table)->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])->delete();
        }
    }

    private function refreshPlatform(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $newUsers = $this->countByDate('users', 'created_at', $from, $to);
        $activeUsers = $this->countByDate('users', 'last_active_at', $from, $to);
        $jobsPosted = $this->countByDate('quests', 'created_at', $from, $to);
        $jobsCompleted = $this->countByDate('quests', 'completed_at', $from, $to);
        $messages = $this->countByDate('quest_conversation_messages', 'created_at', $from, $to);
        $escrowFunded = $this->sumByDate('quests', 'escrow_funded_at', 'budget_amount_minor', $from, $to);
        $escrowReleased = $this->sumByDate('quests', 'completed_at', 'paid_out_minor', $from, $to);

        $rows = [];
        foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
            $key = $date->toDateString();
            $rows[] = [
                'metric_date' => $key,
                'new_users' => $newUsers[$key] ?? 0,
                'active_users' => $activeUsers[$key] ?? 0,
                'jobs_posted' => $jobsPosted[$key] ?? 0,
                'jobs_completed' => $jobsCompleted[$key] ?? 0,
                'messages_sent' => $messages[$key] ?? 0,
                'escrow_funded_minor' => $escrowFunded[$key] ?? 0,
                'escrow_released_minor' => $escrowReleased[$key] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->bulkInsert('admin_report_platform_daily_metrics', $rows);
    }

    private function refreshUsers(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = [];

        $completedQuests = DB::table('quests')
            ->join('users as freelancers', 'freelancers.id', '=', 'quests.freelancer_id')
            ->join('users as clients', 'clients.id', '=', 'quests.client_id')
            ->whereNotNull('quests.completed_at')
            ->whereBetween('quests.completed_at', [$from, $to])
            ->selectRaw('date(quests.completed_at) as metric_date, quests.freelancer_id, quests.client_id, coalesce(quests.quest_category_id, 0) as category_id')
            ->selectRaw('max(freelancers.state_id) as freelancer_state_id, max(freelancers.local_government_id) as freelancer_lga_id')
            ->selectRaw('max(clients.state_id) as client_state_id, max(clients.local_government_id) as client_lga_id')
            ->selectRaw('count(*) as jobs_completed, sum(case when quests.dispute_opened = 1 then 1 else 0 end) as jobs_disputed, coalesce(sum(quests.paid_out_minor), 0) as paid_out_minor')
            ->groupBy('metric_date', 'quests.freelancer_id', 'quests.client_id', 'category_id')
            ->get();

        foreach ($completedQuests as $row) {
            $this->mergeUserRow($rows, (array) $row, 'freelancer', (int) $row->freelancer_id, [
                'state_id' => $row->freelancer_state_id,
                'local_government_id' => $row->freelancer_lga_id,
                'jobs_completed' => (int) $row->jobs_completed,
                'jobs_disputed' => (int) $row->jobs_disputed,
                'earnings_minor' => (int) $row->paid_out_minor,
            ]);
            $this->mergeUserRow($rows, (array) $row, 'client', (int) $row->client_id, [
                'state_id' => $row->client_state_id,
                'local_government_id' => $row->client_lga_id,
                'jobs_completed' => (int) $row->jobs_completed,
                'jobs_disputed' => (int) $row->jobs_disputed,
                'spend_minor' => (int) $row->paid_out_minor,
            ]);
        }

        $offers = DB::table('quest_offers')
            ->join('quests', 'quests.id', '=', 'quest_offers.quest_id')
            ->join('users', 'users.id', '=', 'quest_offers.freelancer_id')
            ->whereBetween('quest_offers.created_at', [$from, $to])
            ->selectRaw('date(quest_offers.created_at) as metric_date, quest_offers.freelancer_id, coalesce(quests.quest_category_id, 0) as category_id')
            ->selectRaw('max(users.state_id) as state_id, max(users.local_government_id) as local_government_id')
            ->selectRaw('count(*) as proposals_sent')
            ->selectRaw('sum(case when quest_offers.client_view_count > 0 then 1 else 0 end) as proposals_viewed')
            ->selectRaw('sum(case when quest_offers.shortlisted_at is not null then 1 else 0 end) as proposals_shortlisted')
            ->selectRaw('sum(case when quest_offers.accepted_at is not null or quests.accepted_quest_offer_id = quest_offers.id then 1 else 0 end) as proposals_accepted')
            ->groupBy('metric_date', 'quest_offers.freelancer_id', 'category_id')
            ->get();

        foreach ($offers as $row) {
            $this->mergeUserRow($rows, (array) $row, 'freelancer', (int) $row->freelancer_id, [
                'state_id' => $row->state_id,
                'local_government_id' => $row->local_government_id,
                'proposals_sent' => (int) $row->proposals_sent,
                'proposals_viewed' => (int) $row->proposals_viewed,
                'proposals_shortlisted' => (int) $row->proposals_shortlisted,
                'proposals_accepted' => (int) $row->proposals_accepted,
            ]);
        }

        $reviews = DB::table('reviews')
            ->join('users', 'users.id', '=', 'reviews.reviewee_id')
            ->whereBetween('reviews.created_at', [$from, $to])
            ->whereNotNull('reviews.rating')
            ->selectRaw('date(reviews.created_at) as metric_date, reviews.reviewee_id as user_id')
            ->selectRaw('max(users.state_id) as state_id, max(users.local_government_id) as local_government_id, coalesce(max(users.account_type), "user") as user_type')
            ->selectRaw('sum(reviews.rating) as rating_sum, count(*) as rating_count')
            ->groupBy('metric_date', 'reviews.reviewee_id')
            ->get();

        foreach ($reviews as $row) {
            $this->mergeUserRow($rows, [
                'metric_date' => $row->metric_date,
                'category_id' => 0,
            ], (string) ($row->user_type ?: 'user'), (int) $row->user_id, [
                'state_id' => $row->state_id,
                'local_government_id' => $row->local_government_id,
                'rating_sum' => (float) $row->rating_sum,
                'rating_count' => (int) $row->rating_count,
            ]);
        }

        $this->bulkInsert('admin_report_user_daily_metrics', array_values($rows));
    }

    private function refreshCategories(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = [];

        $quests = DB::table('quests')
            ->whereBetween('quests.created_at', [$from, $to])
            ->selectRaw('date(quests.created_at) as metric_date, coalesce(quests.quest_category_id, 0) as category_id, coalesce(quests.state_id, 0) as state_id, coalesce(quests.local_government_id, 0) as local_government_id')
            ->selectRaw('count(*) as jobs_posted')
            ->selectRaw('sum(case when quests.status = ? then 1 else 0 end) as jobs_completed', [QuestStatus::Completed->value])
            ->selectRaw('sum(case when quests.accepted_quest_offer_id is not null then 1 else 0 end) as hires')
            ->selectRaw('coalesce(sum(quests.budget_amount_minor), 0) as budget_sum_minor')
            ->selectRaw('coalesce(sum(quests.paid_out_minor), 0) as revenue_minor')
            ->selectRaw('sum(case when quests.dispute_opened = 1 then 1 else 0 end) as disputes')
            ->groupBy('metric_date', 'category_id', 'state_id', 'local_government_id')
            ->get();

        foreach ($quests as $row) {
            $key = $this->categoryKey($row);
            $rows[$key] = $this->categoryBase($row) + [
                'jobs_posted' => (int) $row->jobs_posted,
                'jobs_completed' => (int) $row->jobs_completed,
                'hires' => (int) $row->hires,
                'proposal_volume' => 0,
                'freelancer_availability' => 0,
                'budget_sum_minor' => (int) $row->budget_sum_minor,
                'revenue_minor' => (int) $row->revenue_minor,
                'disputes' => (int) $row->disputes,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $proposalRows = DB::table('quest_offers')
            ->join('quests', 'quests.id', '=', 'quest_offers.quest_id')
            ->whereBetween('quest_offers.created_at', [$from, $to])
            ->selectRaw('date(quest_offers.created_at) as metric_date, coalesce(quests.quest_category_id, 0) as category_id, coalesce(quests.state_id, 0) as state_id, coalesce(quests.local_government_id, 0) as local_government_id')
            ->selectRaw('count(*) as proposal_volume, count(distinct quest_offers.freelancer_id) as freelancer_availability')
            ->groupBy('metric_date', 'category_id', 'state_id', 'local_government_id')
            ->get();

        foreach ($proposalRows as $row) {
            $key = $this->categoryKey($row);
            $rows[$key] ??= $this->emptyCategoryRow($row);
            $rows[$key]['proposal_volume'] += (int) $row->proposal_volume;
            $rows[$key]['freelancer_availability'] += (int) $row->freelancer_availability;
        }

        $this->bulkInsert('admin_report_category_daily_metrics', array_values($rows));
    }

    private function refreshLocations(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = [];

        $users = DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->whereBetween('users.created_at', [$from, $to])
            ->selectRaw('date(users.created_at) as metric_date, coalesce(users.state_id, 0) as state_id, coalesce(users.local_government_id, 0) as local_government_id')
            ->selectRaw('sum(case when roles.slug = "freelancer" or users.account_type = "freelancer" then 1 else 0 end) as freelancers')
            ->selectRaw('sum(case when roles.slug = "client" or users.account_type = "client" then 1 else 0 end) as clients')
            ->groupBy('metric_date', 'state_id', 'local_government_id')
            ->get();

        foreach ($users as $row) {
            $key = $this->locationKey($row);
            $rows[$key] = $this->emptyLocationRow($row);
            $rows[$key]['freelancers'] += (int) $row->freelancers;
            $rows[$key]['clients'] += (int) $row->clients;
        }

        $quests = DB::table('quests')
            ->whereBetween('quests.created_at', [$from, $to])
            ->selectRaw('date(quests.created_at) as metric_date, coalesce(quests.state_id, 0) as state_id, coalesce(quests.local_government_id, 0) as local_government_id')
            ->selectRaw('count(*) as jobs_posted')
            ->selectRaw('sum(case when quests.status = ? then 1 else 0 end) as jobs_completed', [QuestStatus::Completed->value])
            ->selectRaw('coalesce(sum(quests.paid_out_minor), 0) as spend_minor')
            ->groupBy('metric_date', 'state_id', 'local_government_id')
            ->get();

        foreach ($quests as $row) {
            $key = $this->locationKey($row);
            $rows[$key] ??= $this->emptyLocationRow($row);
            $rows[$key]['jobs_posted'] += (int) $row->jobs_posted;
            $rows[$key]['jobs_completed'] += (int) $row->jobs_completed;
            $rows[$key]['spend_minor'] += (int) $row->spend_minor;
        }

        $this->bulkInsert('admin_report_location_daily_metrics', array_values($rows));
    }

    private function refreshRevenue(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = [];

        $quests = DB::table('quests')
            ->whereBetween('quests.completed_at', [$from, $to])
            ->where('quests.paid_out_minor', '>', 0)
            ->selectRaw('date(quests.completed_at) as metric_date, coalesce(quests.quest_category_id, 0) as category_id, coalesce(quests.state_id, 0) as state_id')
            ->selectRaw('coalesce(sum(quests.paid_out_minor), 0) as paid_out_minor')
            ->groupBy('metric_date', 'category_id', 'state_id')
            ->get();

        foreach ($quests as $row) {
            $this->mergeRevenueRow($rows, $row, 'commission', 'all', (int) round(((int) $row->paid_out_minor) * 0.1));
            $this->mergeRevenueRow($rows, $row, 'subscription', 'all', 0);
        }

        $boosts = DB::table('featured_quest_listings')
            ->join('quests', 'quests.id', '=', 'featured_quest_listings.quest_id')
            ->whereBetween('featured_quest_listings.created_at', [$from, $to])
            ->selectRaw('date(featured_quest_listings.created_at) as metric_date, coalesce(quests.quest_category_id, 0) as category_id, coalesce(quests.state_id, 0) as state_id')
            ->selectRaw('coalesce(sum(featured_quest_listings.amount_paid_minor), 0) as boost_revenue_minor')
            ->groupBy('metric_date', 'category_id', 'state_id')
            ->get();

        foreach ($boosts as $row) {
            $this->mergeRevenueRow($rows, $row, 'boosts', 'all', (int) $row->boost_revenue_minor);
        }

        $this->bulkInsert('admin_report_revenue_daily_metrics', array_values($rows));
    }

    private function countByDate(string $table, string $column, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->whereBetween($column, [$from, $to])
            ->selectRaw("date({$column}) as metric_date, count(*) as aggregate")
            ->groupBy('metric_date')
            ->pluck('aggregate', 'metric_date')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function sumByDate(string $table, string $dateColumn, string $sumColumn, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table($table)
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$from, $to])
            ->selectRaw("date({$dateColumn}) as metric_date, coalesce(sum({$sumColumn}), 0) as aggregate")
            ->groupBy('metric_date')
            ->pluck('aggregate', 'metric_date')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function mergeUserRow(array &$rows, array $source, string $userType, int $userId, array $values): void
    {
        $categoryId = (int) ($source['category_id'] ?? 0);
        $key = implode(':', [$source['metric_date'], $userId, $userType, $categoryId]);
        $rows[$key] ??= [
            'metric_date' => $source['metric_date'],
            'user_id' => $userId,
            'user_type' => $userType,
            'category_id' => $categoryId,
            'state_id' => null,
            'local_government_id' => null,
            'jobs_started' => 0,
            'jobs_completed' => 0,
            'jobs_disputed' => 0,
            'proposals_sent' => 0,
            'proposals_viewed' => 0,
            'proposals_shortlisted' => 0,
            'proposals_accepted' => 0,
            'earnings_minor' => 0,
            'spend_minor' => 0,
            'rating_sum' => 0,
            'rating_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($values as $column => $value) {
            if (in_array($column, ['state_id', 'local_government_id'], true)) {
                $rows[$key][$column] = $value ?: $rows[$key][$column];
            } else {
                $rows[$key][$column] += $value;
            }
        }
    }

    private function categoryKey(object $row): string
    {
        return implode(':', [$row->metric_date, (int) $row->category_id, (int) $row->state_id, (int) $row->local_government_id]);
    }

    private function categoryBase(object $row): array
    {
        return [
            'metric_date' => $row->metric_date,
            'category_id' => (int) $row->category_id,
            'state_id' => (int) $row->state_id,
            'local_government_id' => (int) $row->local_government_id,
        ];
    }

    private function emptyCategoryRow(object $row): array
    {
        return $this->categoryBase($row) + [
            'jobs_posted' => 0,
            'jobs_completed' => 0,
            'hires' => 0,
            'proposal_volume' => 0,
            'freelancer_availability' => 0,
            'budget_sum_minor' => 0,
            'revenue_minor' => 0,
            'disputes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function locationKey(object $row): string
    {
        return implode(':', [$row->metric_date, (int) $row->state_id, (int) $row->local_government_id]);
    }

    private function emptyLocationRow(object $row): array
    {
        return [
            'metric_date' => $row->metric_date,
            'state_id' => (int) $row->state_id,
            'local_government_id' => (int) $row->local_government_id,
            'freelancers' => 0,
            'clients' => 0,
            'jobs_posted' => 0,
            'jobs_completed' => 0,
            'spend_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function mergeRevenueRow(array &$rows, object $row, string $feeType, string $segment, int $minor): void
    {
        $key = implode(':', [$row->metric_date, $feeType, (int) $row->category_id, (int) $row->state_id, $segment]);
        $rows[$key] ??= [
            'metric_date' => $row->metric_date,
            'fee_type' => $feeType,
            'category_id' => (int) $row->category_id,
            'state_id' => (int) $row->state_id,
            'user_segment' => $segment,
            'revenue_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $rows[$key]['revenue_minor'] += $minor;
    }

    private function bulkInsert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            if ($chunk !== []) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}
