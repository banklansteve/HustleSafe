<?php

namespace App\Services\Moderation;

use App\Models\ModerationCase;
use App\Models\ModerationCaseTrigger;
use App\Models\ModerationKeyword;
use App\Models\ModerationSetting;
use App\Models\Portfolio;
use App\Models\DisputeMessage;
use App\Models\Quest;
use App\Models\QuestConversationMessage;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\User;
use App\Support\PlainText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContentModerationScannerService
{
    public function __construct(private readonly CloudinaryMediaModerationService $cloudinary) {}

    public function scan(Model $model, ?User $actor = null, string $source = 'automated'): ?ModerationCase
    {
        $payload = $this->payload($model);
        if ($payload === null) {
            return null;
        }

        $text = trim((string) ($payload['text'] ?? ''));
        $triggers = collect()
            ->merge($this->keywordTriggers($text))
            ->merge($this->linkTriggers($text))
            ->merge($this->newAccountTriggers($payload['subject_user'] ?? null))
            ->merge($this->duplicateTriggers($payload['content_type'], $text, $model))
            ->merge($this->budgetTriggers($model))
            ->merge($this->reviewTriggers($model, $text))
            ->merge($this->imageTriggers($model));

        if ($triggers->isEmpty()) {
            return null;
        }

        $severity = $triggers->contains(fn ($trigger) => ($trigger['severity'] ?? 'warning') === 'critical') ? 'critical' : 'warning';
        $confidence = (int) $triggers->max('confidence');

        $case = ModerationCase::query()->updateOrCreate(
            [
                'moderatable_type' => $model::class,
                'moderatable_id' => $model->getKey(),
                'status' => 'open',
            ],
            [
                'subject_user_id' => $payload['subject_user']?->id,
                'content_type' => $payload['content_type'],
                'queue' => $payload['queue'],
                'severity' => $severity,
                'visibility_state' => $this->visibilityState($payload['content_type'], $severity),
                'source' => $source,
                'confidence' => $confidence,
                'title' => PlainText::from($payload['title']),
                'excerpt' => PlainText::from($text, 240),
                'snapshot' => [
                    'text' => PlainText::from($text),
                    'title' => PlainText::from($payload['title']),
                    'content_type' => $payload['content_type'],
                    'url' => $payload['url'] ?? null,
                ],
                'entered_queue_at' => now(),
            ],
        );

        $case->triggers()->delete();
        $triggers->each(fn (array $trigger) => $case->triggers()->create($trigger));

        return $case->fresh(['triggers', 'subjectUser']);
    }

    public function createFromReport(Model $reportable, User $reporter, string $reason, ?string $details, string $severity = 'warning'): ModerationCase
    {
        $payload = $this->payload($reportable) ?? [
            'content_type' => class_basename($reportable),
            'queue' => 'quest',
            'title' => class_basename($reportable).' #'.$reportable->getKey(),
            'text' => $details ?? $reason,
            'subject_user' => null,
        ];

        $case = ModerationCase::query()->create([
            'moderatable_type' => $reportable::class,
            'moderatable_id' => $reportable->getKey(),
            'subject_user_id' => $payload['subject_user']?->id,
            'reporter_user_id' => $reporter->id,
            'content_type' => $payload['content_type'],
            'queue' => $payload['queue'],
            'status' => 'open',
            'severity' => $severity === 'critical' ? 'critical' : 'warning',
            'visibility_state' => 'live_under_review',
            'source' => 'user_report',
            'confidence' => 100,
            'title' => PlainText::from($payload['title']),
            'excerpt' => PlainText::from((string) ($details ?: $reason), 240),
            'snapshot' => [
                'text' => PlainText::from($payload['text']),
                'report_reason' => PlainText::from($reason),
                'report_details' => PlainText::from($details),
            ],
            'entered_queue_at' => now(),
        ]);

        $case->triggers()->create([
            'rule_key' => 'user_report',
            'rule_type' => 'report',
            'category' => $reason,
            'severity' => $severity === 'critical' ? 'critical' : 'warning',
            'confidence' => 100,
            'matched_text' => $reason,
            'context' => $details,
        ]);

        return $case;
    }

    private function payload(Model $model): ?array
    {
        if ($model instanceof Quest) {
            $model->loadMissing(['client', 'questCategory']);

            return [
                'content_type' => 'quest',
                'queue' => 'quest',
                'title' => $model->title,
                'text' => trim($model->title."\n".$model->description."\n".$model->city),
                'subject_user' => $model->client,
                'url' => route('admin.management.show', ['resource' => 'quests', 'record' => $model->id]),
            ];
        }

        if ($model instanceof QuestOffer) {
            $model->loadMissing(['freelancer', 'quest']);

            return [
                'content_type' => 'proposal',
                'queue' => 'quest',
                'title' => 'Proposal for '.$model->quest?->title,
                'text' => trim($model->pitch."\n".$model->scope_detail."\n".$model->warranty_terms."\n".json_encode($model->materials)),
                'subject_user' => $model->freelancer,
                'url' => route('admin.management.show', ['resource' => 'quest_offers', 'record' => $model->id]),
            ];
        }

        if ($model instanceof Portfolio) {
            $model->loadMissing(['user', 'files']);

            return [
                'content_type' => 'portfolio',
                'queue' => 'profile_portfolio',
                'title' => $model->title,
                'text' => trim($model->title."\n".$model->description),
                'subject_user' => $model->user,
                'url' => route('admin.management.show', ['resource' => 'portfolios', 'record' => $model->id]),
            ];
        }

        if ($model instanceof Review) {
            $model->loadMissing(['reviewer', 'reviewee', 'quest']);

            return [
                'content_type' => 'review',
                'queue' => 'review',
                'title' => 'Review #'.$model->id,
                'text' => trim($model->title."\n".$model->comment),
                'subject_user' => $model->reviewer,
                'url' => route('admin.management.show', ['resource' => 'reviews', 'record' => $model->id]),
            ];
        }

        if ($model instanceof User) {
            return [
                'content_type' => 'profile',
                'queue' => 'profile_portfolio',
                'title' => $model->name,
                'text' => trim($model->headline."\n".$model->bio."\n".$model->profession."\n".$model->company_name),
                'subject_user' => $model,
                'url' => route('admin.users.index', ['q' => $model->email]),
            ];
        }

        if ($model instanceof QuestConversationMessage) {
            $model->loadMissing(['user', 'thread.quest']);

            return [
                'content_type' => 'message',
                'queue' => 'message',
                'title' => 'Quest message on '.$model->thread?->quest?->title,
                'text' => (string) $model->body,
                'subject_user' => $model->user,
                'url' => null,
            ];
        }

        if ($model instanceof DisputeMessage) {
            $model->loadMissing(['user', 'dispute.quest']);

            return [
                'content_type' => 'message',
                'queue' => 'message',
                'title' => 'Dispute message on '.$model->dispute?->quest?->title,
                'text' => (string) $model->body,
                'subject_user' => $model->user,
                'url' => null,
            ];
        }

        return null;
    }

    private function keywordTriggers(string $text): Collection
    {
        if ($text === '') {
            return collect();
        }

        $lower = Str::lower($text);

        return ModerationKeyword::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ModerationKeyword $keyword) => str_contains($lower, Str::lower($keyword->phrase)))
            ->map(fn (ModerationKeyword $keyword) => [
                'rule_key' => 'keyword:'.$keyword->id,
                'rule_type' => 'keyword',
                'category' => $keyword->category,
                'severity' => $keyword->severity,
                'confidence' => $keyword->severity === 'critical' ? 95 : 80,
                'matched_text' => $keyword->phrase,
                'context' => $this->contextAround($text, $keyword->phrase),
                'meta' => ['keyword_id' => $keyword->id],
            ])
            ->values();
    }

    private function linkTriggers(string $text): Collection
    {
        preg_match_all('#https?://[^\s<>"\']+|(?:www\.)[^\s<>"\']+#i', $text, $matches);
        $links = collect($matches[0] ?? [])->unique()->values();
        if ($links->isEmpty()) {
            return collect();
        }

        $whitelist = collect(ModerationSetting::value('allowed_external_domains', ['linkedin.com', 'github.com', 'behance.net', 'dribbble.com']))
            ->map(fn ($domain) => Str::lower((string) $domain));

        return $links
            ->filter(function (string $link) use ($whitelist): bool {
                $host = parse_url(str_starts_with($link, 'www.') ? 'https://'.$link : $link, PHP_URL_HOST);
                $host = Str::lower((string) $host);

                return $host !== '' && ! $whitelist->contains(fn ($domain) => $host === $domain || str_ends_with($host, '.'.$domain));
            })
            ->map(fn (string $link) => [
                'rule_key' => 'external_link',
                'rule_type' => 'link',
                'category' => 'suspicious_external_link',
                'severity' => 'warning',
                'confidence' => 75,
                'matched_text' => $link,
                'context' => $this->contextAround($text, $link),
            ])
            ->values();
    }

    private function newAccountTriggers(?User $user): Collection
    {
        $hours = (int) ModerationSetting::value('new_account_review_hours', 48);
        if ($user === null || $user->created_at === null || $user->created_at->lt(now()->subHours($hours))) {
            return collect();
        }

        return collect([[
            'rule_key' => 'new_account_review_window',
            'rule_type' => 'account_age',
            'category' => 'new_account_risk',
            'severity' => 'warning',
            'confidence' => 70,
            'matched_text' => 'Account age: '.$user->created_at->diffForHumans(),
            'context' => 'Content submitted inside the configured new-account review window.',
        ]]);
    }

    private function duplicateTriggers(string $contentType, string $text, Model $model): Collection
    {
        if (mb_strlen($text) < 40) {
            return collect();
        }

        $recent = ModerationCase::query()
            ->where('content_type', $contentType)
            ->where('moderatable_type', $model::class)
            ->where('moderatable_id', '!=', $model->getKey())
            ->where('created_at', '>=', now()->subHours(72))
            ->limit(50)
            ->get();

        foreach ($recent as $case) {
            $other = (string) data_get($case->snapshot, 'text', '');
            if ($other === '') {
                continue;
            }
            similar_text(Str::lower($text), Str::lower($other), $percent);
            if ($percent >= 80) {
                return collect([[
                    'rule_key' => 'near_duplicate_content',
                    'rule_type' => 'spam',
                    'category' => 'duplicate_content',
                    'severity' => 'warning',
                    'confidence' => (int) round($percent),
                    'matched_text' => Str::limit($text, 120),
                    'context' => 'More than 80% similar to moderation case #'.$case->id.' within 72 hours.',
                    'meta' => ['matched_case_id' => $case->id],
                ]]);
            }
        }

        return collect();
    }

    private function budgetTriggers(Model $model): Collection
    {
        if (! $model instanceof Quest || ! $model->quest_category_id || ! $model->budget_amount_minor) {
            return collect();
        }

        $budgets = Quest::query()
            ->where('quest_category_id', $model->quest_category_id)
            ->where('id', '!=', $model->id)
            ->where('budget_amount_minor', '>', 0)
            ->orderBy('budget_amount_minor')
            ->pluck('budget_amount_minor')
            ->values();

        if ($budgets->count() < 10) {
            return collect();
        }

        $p10 = (int) $budgets[(int) floor(($budgets->count() - 1) * 0.10)];
        $p99 = (int) $budgets[(int) floor(($budgets->count() - 1) * 0.99)];
        $budget = (int) $model->budget_amount_minor;

        if ($budget < $p10 || $budget > $p99) {
            return collect([[
                'rule_key' => 'budget_anomaly',
                'rule_type' => 'budget',
                'category' => $budget < $p10 ? 'unusually_low_budget' : 'unusually_high_budget',
                'severity' => 'warning',
                'confidence' => 72,
                'matched_text' => 'Budget '.$budget,
                'context' => 'Budget is outside historical category percentile thresholds.',
                'meta' => ['p10' => $p10, 'p99' => $p99],
            ]]);
        }

        return collect();
    }

    private function reviewTriggers(Model $model, string $text): Collection
    {
        if (! $model instanceof Review) {
            return collect();
        }

        $triggers = collect();

        if ($model->quest?->dispute_opened) {
            $triggers->push([
                'rule_key' => 'dispute_related_review',
                'rule_type' => 'review_pattern',
                'category' => 'dispute_related_review',
                'severity' => 'warning',
                'confidence' => 75,
                'matched_text' => 'Dispute-related contract',
                'context' => 'Review is tied to a quest with dispute history.',
            ]);
        }

        if ($model->reviewee_id) {
            $count = Review::query()
                ->where('reviewee_id', $model->reviewee_id)
                ->where('id', '!=', $model->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();
            if ($count >= 2) {
                $triggers->push([
                    'rule_key' => 'review_velocity',
                    'rule_type' => 'review_pattern',
                    'category' => 'velocity_manipulation',
                    'severity' => 'warning',
                    'confidence' => 80,
                    'matched_text' => ($count + 1).' reviews in 24 hours',
                    'context' => 'More than two reviews submitted for the same user within 24 hours.',
                ]);
            }
        }

        if (mb_strlen($text) > 20) {
            $duplicate = Review::query()
                ->where('id', '!=', $model->id)
                ->where('comment', $model->comment)
                ->exists();
            if ($duplicate) {
                $triggers->push([
                    'rule_key' => 'identical_review_text',
                    'rule_type' => 'review_pattern',
                    'category' => 'duplicate_review',
                    'severity' => 'warning',
                    'confidence' => 90,
                    'matched_text' => Str::limit($text, 120),
                    'context' => 'Identical review text exists elsewhere.',
                ]);
            }
        }

        return $triggers;
    }

    private function imageTriggers(Model $model): Collection
    {
        if (! $model instanceof Portfolio) {
            return collect();
        }

        return $model->files
            ->flatMap(fn ($file) => collect($this->cloudinary->scanPortfolioFile($file))
                ->map(fn (array $label) => [
                    'rule_key' => 'cloudinary:'.$label['name'],
                    'rule_type' => 'image',
                    'category' => 'inappropriate_image',
                    'severity' => $label['confidence'] >= 90 ? 'critical' : 'warning',
                    'confidence' => $label['confidence'],
                    'matched_text' => $label['name'],
                    'context' => 'Cloudinary moderation flagged '.$label['name'].' at '.$label['confidence'].'% confidence.',
                    'meta' => ['portfolio_file_id' => $file->id, 'label' => $label],
                ]))
            ->values();
    }

    private function visibilityState(string $contentType, string $severity): string
    {
        if (in_array($contentType, ['quest', 'proposal', 'profile'], true)) {
            return 'live_under_review';
        }

        return $severity === 'critical' ? 'held_pending_review' : 'pending_review';
    }

    private function contextAround(string $text, string $needle): string
    {
        $pos = stripos($text, $needle);
        if ($pos === false) {
            return Str::limit($text, 180);
        }

        return Str::limit(substr($text, max(0, $pos - 60), strlen($needle) + 120), 220);
    }
}
