<?php

namespace App\Services;

use App\Models\AdminUserSanction;
use App\Models\ConversationPolicyWarning;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserPolicyNoticesService
{
    public function __construct(private readonly UserNotificationInboxService $inbox) {}

    /**
     * @return array{pending: list<array<string, mixed>>, history: list<array<string, mixed>>, pending_count: int}
     */
    public function indexPayload(User $user): array
    {
        $this->inbox->markConversationPolicyWarnings($user);

        $pending = $this->conversationWarnings($user, acknowledged: false)
            ->merge($this->sanctionWarnings($user, acknowledged: false))
            ->sortByDesc('issued_at')
            ->values()
            ->all();

        $history = $this->conversationWarnings($user, acknowledged: true)
            ->merge($this->sanctionWarnings($user, acknowledged: true))
            ->sortByDesc('issued_at')
            ->take(30)
            ->values()
            ->all();

        return [
            'pending' => $pending,
            'history' => $history,
            'pending_count' => count($pending),
        ];
    }

    public function acknowledge(User $user, string $source, int $id): void
    {
        if ($source === 'conversation') {
            $warning = ConversationPolicyWarning::query()
                ->where('user_id', $user->id)
                ->whereKey($id)
                ->firstOrFail();

            if ($warning->acknowledged_at === null) {
                $warning->update(['acknowledged_at' => now()]);
            }

            $this->inbox->markConversationPolicyWarning($user, (int) $warning->id);

            return;
        }

        if ($source === 'sanction') {
            $sanction = AdminUserSanction::query()
                ->where('user_id', $user->id)
                ->where('type', 'warning')
                ->whereKey($id)
                ->firstOrFail();

            if ($sanction->user_acknowledged_at === null) {
                $sanction->update(['user_acknowledged_at' => now()]);
            }

            return;
        }

        throw ValidationException::withMessages(['source' => __('Invalid notice type.')]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function conversationWarnings(User $user, bool $acknowledged): \Illuminate\Support\Collection
    {
        return ConversationPolicyWarning::query()
            ->with([
                'issuedBy:id,name',
                'review.quest:id,title,reference_code',
            ])
            ->where('user_id', $user->id)
            ->when($acknowledged, fn ($q) => $q->whereNotNull('acknowledged_at'), fn ($q) => $q->whereNull('acknowledged_at'))
            ->latest('created_at')
            ->get()
            ->map(fn (ConversationPolicyWarning $warning) => [
                'id' => $warning->id,
                'source' => 'conversation',
                'title' => __('Messaging policy notice'),
                'body' => $warning->note,
                'reason_label' => __('Conversation monitoring'),
                'quest_title' => $warning->review?->quest?->title,
                'quest_reference' => $warning->review?->quest?->reference_code,
                'issued_by' => $warning->issuedBy?->name,
                'issued_at' => $warning->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'acknowledged_at' => $warning->acknowledged_at?->timezone('Africa/Lagos')->toIso8601String(),
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function sanctionWarnings(User $user, bool $acknowledged): \Illuminate\Support\Collection
    {
        return AdminUserSanction::query()
            ->with('admin:id,name')
            ->where('user_id', $user->id)
            ->where('type', 'warning')
            ->whereNull('reversed_at')
            ->when($acknowledged, fn ($q) => $q->whereNotNull('user_acknowledged_at'), fn ($q) => $q->whereNull('user_acknowledged_at'))
            ->latest('starts_at')
            ->get()
            ->map(fn (AdminUserSanction $sanction) => [
                'id' => $sanction->id,
                'source' => 'sanction',
                'title' => __('Platform warning'),
                'body' => $sanction->notes ?: __('A member of our trust & safety team issued a warning on your account.'),
                'reason_label' => Str::headline(str_replace('_', ' ', (string) $sanction->reason_code)),
                'quest_title' => null,
                'quest_reference' => null,
                'issued_by' => $sanction->admin?->name,
                'issued_at' => $sanction->starts_at?->timezone('Africa/Lagos')->toIso8601String(),
                'acknowledged_at' => $sanction->user_acknowledged_at?->timezone('Africa/Lagos')->toIso8601String(),
            ]);
    }
}
