<?php

namespace App\Services\Operations;

use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\StaffFreelancerQualityFlag;
use App\Models\User;
use App\Models\UserTrustMetric;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffFreelancerQualityService
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
        private readonly StaffUserManagementService $userManagement,
    ) {}

    public function listing(): array
    {
        $thresholds = config('operations.quality_thresholds', []);
        $freelancers = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->with('trustMetrics')
            ->get();

        $flagged = [];
        foreach ($freelancers as $freelancer) {
            $metrics = $this->metricsFor($freelancer);
            $reasons = $this->failingReasons($metrics, $thresholds);
            if ($reasons === []) {
                continue;
            }

            $flagged[] = [
                'freelancer_id' => $freelancer->id,
                'name' => $freelancer->name,
                'email' => $freelancer->email,
                'avatar_url' => $freelancer->avatar_url,
                'metrics' => $metrics,
                'reasons' => $reasons,
                'reason_labels' => array_map(fn (string $r) => Str::headline(str_replace('_', ' ', $r)), $reasons),
                'trend' => $this->trendFor($freelancer),
            ];
        }

        usort($flagged, fn ($a, $b) => count($b['reasons']) <=> count($a['reasons']));

        return ['items' => array_slice($flagged, 0, 100)];
    }

    public function detail(User $freelancer): array
    {
        $metrics = $this->metricsFor($freelancer);

        $openFlag = StaffFreelancerQualityFlag::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        return [
            'freelancer' => [
                'id' => $freelancer->id,
                'name' => $freelancer->name,
                'email' => $freelancer->email,
            ],
            'metrics' => $metrics,
            'trend' => $this->trendFor($freelancer),
            'open_flag' => $openFlag ? [
                'id' => $openFlag->id,
                'trigger_reason' => $openFlag->trigger_reason,
                'staff_notes' => $openFlag->staff_notes,
            ] : null,
        ];
    }

    public function contact(User $staff, User $freelancer, array $data, Request $request): void
    {
        $freelancer->notify(new AdminUserMessageNotification($data['subject'], $data['body']));
        if (($data['channel'] ?? 'both') !== 'in_app' && $freelancer->email) {
            Mail::raw($data['body'], fn ($m) => $m->to($freelancer->email)->subject($data['subject']));
        }
        $this->logger->log($staff, 'operations.quality.contact', User::class, $freelancer->id, $data, $request);
    }

    public function warning(User $staff, User $freelancer, array $data, Request $request): void
    {
        $this->userManagement->issueWarning($freelancer, $staff, [
            'reason_code' => 'performance',
            'notes' => $data['notes'],
        ], $request);
    }

    public function restrictHighValue(User $staff, User $freelancer, array $data, Request $request): void
    {
        StaffFreelancerQualityFlag::query()->updateOrCreate(
            ['freelancer_id' => $freelancer->id, 'status' => 'open'],
            [
                'staff_user_id' => $staff->id,
                'trigger_reason' => 'high_value_bid_restricted',
                'staff_notes' => $data['notes'] ?? 'Restricted from high-value Quest bids by staff.',
                'metrics_snapshot' => $this->metricsFor($freelancer),
            ],
        );

        $this->logger->log($staff, 'operations.quality.restrict_high_value', User::class, $freelancer->id, $data, $request);
    }

    public function referForReview(User $staff, User $freelancer, array $data, Request $request): void
    {
        if (Schema::hasTable('admin_tasks')) {
            $super = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
            if ($super) {
                AdminTask::query()->create([
                    'created_by_admin_id' => $staff->id,
                    'assigned_to_admin_id' => $super->id,
                    'source_type' => User::class,
                    'source_id' => $freelancer->id,
                    'title' => 'Freelancer quality review · '.$freelancer->name,
                    'description' => $data['notes'] ?? 'Staff referred freelancer for account review.',
                    'priority' => 'high',
                    'status' => 'todo',
                    'due_at' => now()->addDays(2),
                ]);
            }
        }

        $this->userManagement->flagForReview($freelancer, $staff, ['notes' => $data['notes'] ?? ''], $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsFor(User $freelancer): array
    {
        $trust = $freelancer->trustMetrics ?? UserTrustMetric::query()->where('user_id', $freelancer->id)->first();
        $offersTotal = QuestOffer::query()->where('freelancer_id', $freelancer->id)->count();
        $offersCompleted = QuestOffer::query()
            ->where('freelancer_id', $freelancer->id)
            ->whereHas('quest', fn ($q) => $q->where('status', 'completed'))
            ->count();
        $disputes = QuestDispute::query()
            ->whereHas('quest', fn ($q) => $q->where('freelancer_id', $freelancer->id))
            ->count();
        $reviews = Review::query()->where('reviewee_id', $freelancer->id)->where('status', 'published')->count();
        $removedProposals = QuestOffer::query()
            ->where('freelancer_id', $freelancer->id)
            ->whereIn('admin_status', ['flagged', 'restricted', 'suspended'])
            ->count();

        $completionRate = $offersTotal > 0 ? round(($offersCompleted / $offersTotal) * 100, 1) : 100;
        $disputeRate = $offersTotal > 0 ? round(($disputes / max(1, $offersTotal)) * 100, 1) : 0;
        $removalRate = $offersTotal > 0 ? round(($removedProposals / max(1, $offersTotal)) * 100, 1) : 0;

        return [
            'avg_rating' => round((float) ($trust?->avg_rating_as_freelancer ?? 0), 2),
            'ratings_count' => (int) ($trust?->ratings_count_as_freelancer ?? $reviews),
            'completion_rate_percent' => $completionRate,
            'dispute_rate_percent' => $disputeRate,
            'proposal_removal_rate_percent' => $removalRate,
            'contracts_total' => $offersTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<string>
     */
    private function failingReasons(array $metrics, array $thresholds): array
    {
        $reasons = [];
        if (($metrics['avg_rating'] ?? 5) < ($thresholds['min_rating'] ?? 3.8) && ($metrics['ratings_count'] ?? 0) >= 3) {
            $reasons[] = 'declining_rating';
        }
        if (($metrics['dispute_rate_percent'] ?? 0) > ($thresholds['max_dispute_rate_percent'] ?? 15)) {
            $reasons[] = 'high_dispute_rate';
        }
        if (($metrics['completion_rate_percent'] ?? 100) < ($thresholds['min_completion_rate_percent'] ?? 70)) {
            $reasons[] = 'low_completion_rate';
        }
        if (($metrics['proposal_removal_rate_percent'] ?? 0) > ($thresholds['max_proposal_removal_rate_percent'] ?? 20)) {
            $reasons[] = 'proposal_removals';
        }

        return $reasons;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function trendFor(User $freelancer): array
    {
        $since = now()->subDays(90);

        return Review::query()
            ->where('reviewee_id', $freelancer->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, AVG(rating) as avg_rating, COUNT(*) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'avg_rating' => round((float) $row->avg_rating, 2),
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
