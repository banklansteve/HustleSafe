<?php

namespace App\Services\Quest;

use App\Enums\QuestStatus;
use App\Models\LoginEvent;
use App\Models\Quest;
use App\Models\QuestListingExtensionLog;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\QuestListingDeadlineExtendedNotification;
use App\Notifications\QuestProposalDeadlineWarningNotification;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\QuestSlugService;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class QuestListingExpiryService
{
    public function __construct(
        private readonly QuestSlugService $slugs,
        private readonly AdminActivityFeedService $activity,
    ) {}

    /**
     * @return array{min: int, max: int, default: int, extension_max: int, warning_hours: int}
     */
    public function bounds(): array
    {
        return PlatformSettings::proposalDeadlineBounds();
    }

    public function resolveDaysForCreate(?int $clientDays): int
    {
        $bounds = $this->bounds();
        if ($clientDays === null || $clientDays <= 0) {
            return $bounds['default'];
        }

        return PlatformSettings::clampProposalDeadlineDays($clientDays);
    }

    public function applyListingDeadline(Quest $quest, int $days, ?\DateTimeInterface $from = null): void
    {
        $days = PlatformSettings::clampProposalDeadlineDays($days);
        $anchor = $from ? \Carbon\CarbonImmutable::parse($from) : now();

        $quest->forceFill([
            'auto_listing_expiry_days' => $days,
            'listing_expires_at' => $anchor->addDays($days),
        ])->saveQuietly();
    }

    public function listingStillRelevant(Quest $quest): bool
    {
        if ($quest->status !== QuestStatus::Open) {
            return false;
        }

        if ($quest->freelancer_id !== null || $quest->accepted_quest_offer_id !== null) {
            return false;
        }

        return true;
    }

    public function canExtend(Quest $quest, User $client): bool
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            return false;
        }

        if (! $this->listingStillRelevant($quest)) {
            return false;
        }

        if (! $quest->listing_expires_at) {
            return false;
        }

        return (int) ($quest->listing_extension_count ?? 0) < 1;
    }

    public function extend(Quest $quest, User $client, int $additionalDays, string $reason): Quest
    {
        if (! $this->canExtend($quest, $client)) {
            throw ValidationException::withMessages([
                'reason' => __('This quest cannot be extended again.'),
            ]);
        }

        $bounds = $this->bounds();
        $additionalDays = max(1, min($bounds['extension_max'], $additionalDays));
        $reason = trim($reason);

        if (strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => __('Please share a brief reason for extending (at least 10 characters).'),
            ]);
        }

        return DB::transaction(function () use ($quest, $client, $additionalDays, $reason, $bounds): Quest {
            $previous = $quest->listing_expires_at ?? now();
            $newExpiry = $previous->copy()->addDays($additionalDays);
            $maxAllowed = now()->addDays($bounds['max'] + $bounds['extension_max']);
            if ($newExpiry->greaterThan($maxAllowed)) {
                throw ValidationException::withMessages([
                    'additional_days' => __('Extension would exceed the platform maximum listing window.'),
                ]);
            }

            $quest->update([
                'listing_expires_at' => $newExpiry,
                'auto_listing_expiry_days' => (int) ($quest->auto_listing_expiry_days ?? 0) + $additionalDays,
                'listing_extension_count' => (int) ($quest->listing_extension_count ?? 0) + 1,
                'listing_extended_at' => now(),
                'listing_extension_reason' => $reason,
                'listing_expiry_warning_sent_at' => null,
            ]);

            if (Schema::hasTable('quest_listing_extension_logs')) {
                QuestListingExtensionLog::query()->create([
                    'quest_id' => $quest->id,
                    'client_user_id' => $client->id,
                    'days_added' => $additionalDays,
                    'previous_expires_at' => $previous,
                    'new_expires_at' => $newExpiry,
                    'reason' => $reason,
                ]);
            }

            $this->activity->record(
                'jobs',
                'quest.listing_extended',
                'Quest proposal deadline extended',
                "{$client->name} extended {$quest->title} by {$additionalDays} day(s)",
                $this->activity->entities([
                    ['type' => 'user', 'id' => $client->id, 'label' => $client->name],
                    ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                ]),
                $reason,
                $quest,
            );

            $this->notifyFreelancersOfExtension($quest->fresh(), $additionalDays, $newExpiry);

            return $quest->fresh();
        });
    }

    public function notifyFreelancersOfExtension(Quest $quest, int $daysAdded, \DateTimeInterface $newExpiry): void
    {
        $freelancerIds = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->visibleInClientInbox()
            ->pluck('freelancer_id')
            ->unique()
            ->filter();

        User::query()->whereIn('id', $freelancerIds)->get()->each(function (User $freelancer) use ($quest, $daysAdded, $newExpiry): void {
            $freelancer->notify(new QuestListingDeadlineExtendedNotification($quest, $daysAdded, $newExpiry));
        });
    }

    public function sendDeadlineWarnings(): int
    {
        $bounds = $this->bounds();
        $warningBefore = now()->addHours($bounds['warning_hours']);
        $sent = 0;

        Quest::query()
            ->where('status', QuestStatus::Open)
            ->whereNull('freelancer_id')
            ->whereNull('accepted_quest_offer_id')
            ->whereNotNull('listing_expires_at')
            ->whereNull('listing_expiry_warning_sent_at')
            ->where('listing_expires_at', '>', now())
            ->where('listing_expires_at', '<=', $warningBefore)
            ->with('client')
            ->chunkById(50, function ($quests) use (&$sent): void {
                foreach ($quests as $quest) {
                    if (! $quest->client) {
                        continue;
                    }

                    $proposals = (int) ($quest->offers_count ?? 0);
                    $quest->client->notify(new QuestProposalDeadlineWarningNotification($quest, $proposals));
                    $quest->update(['listing_expiry_warning_sent_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }

    public function expireDueListings(): int
    {
        $count = 0;

        Quest::query()
            ->where('status', QuestStatus::Open)
            ->whereNull('freelancer_id')
            ->whereNull('accepted_quest_offer_id')
            ->whereNotNull('listing_expires_at')
            ->where('listing_expires_at', '<=', now())
            ->chunkById(50, function ($quests) use (&$count): void {
                foreach ($quests as $quest) {
                    $quest->update([
                        'status' => QuestStatus::ClosedUnawarded,
                        'closure_type' => 'unawarded',
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    public function canRepost(Quest $quest, User $client): bool
    {
        return (int) $quest->client_id === (int) $client->id
            && $quest->status === QuestStatus::ClosedUnawarded;
    }

    public function repost(Quest $source, User $client): Quest
    {
        if (! $this->canRepost($source, $client)) {
            throw ValidationException::withMessages([
                'quest' => __('Only closed unawarded quests can be reposted.'),
            ]);
        }

        $bounds = $this->bounds();
        $days = $bounds['default'];
        $slug = $this->slugs->uniqueSlugFromTitle($source->title);

        return DB::transaction(function () use ($source, $client, $days, $slug): Quest {
            $dueAt = now()->addDays((int) $source->estimated_completion_days);
            $hours = max(1, (int) config('quests.client_edit_window_hours', 48));

            $quest = Quest::query()->create([
                'client_id' => $client->id,
                'slug' => $slug,
                'title' => $source->title,
                'description' => $source->description,
                'quest_category_id' => $source->quest_category_id,
                'state_id' => $source->state_id,
                'local_government_id' => $source->local_government_id,
                'city' => $source->city,
                'status' => QuestStatus::Open,
                'visibility' => $source->visibility,
                'freelancer_location_pref' => $source->freelancer_location_pref,
                'availability_need' => $source->availability_need,
                'project_type' => $source->project_type,
                'estimated_hours' => $source->estimated_hours,
                'team_size' => $source->team_size,
                'auto_listing_expiry_days' => $days,
                'listing_expires_at' => now()->addDays($days),
                'listing_extension_count' => 0,
                'client_edit_until' => now()->addHours($hours),
                'max_offers' => $source->max_offers,
                'budget_amount_minor' => $source->budget_amount_minor,
                'start_timing' => $source->start_timing,
                'estimated_completion_days' => $source->estimated_completion_days,
                'estimated_delivery_date' => $source->estimated_delivery_date,
                'site_visits_allowed' => $source->site_visits_allowed,
                'site_access_level' => $source->site_access_level,
                'pets_on_site' => $source->pets_on_site,
                'pets_detail' => $source->pets_detail,
                'scheduled_start_date' => $source->scheduled_start_date,
                'due_at' => $dueAt,
                'reposted_from_quest_id' => $source->id,
                'terms_accepted_at' => now(),
            ]);

            $this->activity->record(
                'jobs',
                'quest.reposted',
                'Quest reposted',
                "{$client->name} reposted {$source->title} as a fresh listing",
                $this->activity->entities([
                    ['type' => 'user', 'id' => $client->id, 'label' => $client->name],
                    ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                ]),
                null,
                $quest,
            );

            return $quest;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function clientPayload(Quest $quest, ?User $viewer = null): array
    {
        $bounds = $this->bounds();
        $viewer = $viewer ?? auth()->user();

        return [
            'listing_expires_at' => $quest->listing_expires_at?->toIso8601String(),
            'auto_listing_expiry_days' => $quest->auto_listing_expiry_days,
            'listing_extension_count' => (int) ($quest->listing_extension_count ?? 0),
            'listing_extended_at' => $quest->listing_extended_at?->toIso8601String(),
            'can_extend_listing' => $viewer && (int) $quest->client_id === (int) $viewer->id
                ? $this->canExtend($quest, $viewer)
                : false,
            'can_repost' => $viewer && (int) $quest->client_id === (int) $viewer->id
                ? $this->canRepost($quest, $viewer)
                : false,
            'is_listing_clock_active' => $this->listingStillRelevant($quest),
            'proposal_deadline_bounds' => $bounds,
            'reposted_from_quest_id' => $quest->reposted_from_quest_id,
        ];
    }

    public function clientLastActiveAt(User $client): ?\Carbon\Carbon
    {
        $lastLogin = LoginEvent::query()
            ->where('user_id', $client->id)
            ->latest('logged_in_at')
            ->value('logged_in_at');

        if ($lastLogin) {
            return \Carbon\Carbon::parse($lastLogin);
        }

        return $client->updated_at;
    }
}
