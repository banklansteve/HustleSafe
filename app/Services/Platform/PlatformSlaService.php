<?php

namespace App\Services\Platform;

use App\Models\AdminNotification;
use App\Models\PlatformSlaClock;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Support\Support\SupportWorkingDays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlatformSlaService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function start(
        string $slaKey,
        Model $subject,
        ?User $assignee = null,
        ?User $triggeredBy = null,
        array $metadata = [],
        ?CarbonInterface $triggeredAt = null,
    ): ?PlatformSlaClock {
        if (! Schema::hasTable('platform_sla_clocks') || ! $this->definition($slaKey)) {
            return null;
        }

        $existing = PlatformSlaClock::query()
            ->where('sla_key', $slaKey)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('status', ['active', 'breached'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $triggered = Carbon::parse($triggeredAt ?? now());
        $dueAt = $this->computeDueAt($slaKey, $triggered);

        return PlatformSlaClock::query()->create([
            'sla_key' => $slaKey,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'assigned_admin_id' => $assignee?->id,
            'triggered_by_user_id' => $triggeredBy?->id,
            'triggered_at' => $triggered,
            'due_at' => $dueAt,
            'status' => 'active',
            'metadata' => $metadata,
        ]);
    }

    public function resolveForSubject(string $slaKey, Model $subject): void
    {
        if (! Schema::hasTable('platform_sla_clocks')) {
            return;
        }

        PlatformSlaClock::query()
            ->where('sla_key', $slaKey)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('status', ['active', 'breached'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    public function reassign(PlatformSlaClock $clock, ?User $assignee): void
    {
        $clock->forceFill(['assigned_admin_id' => $assignee?->id])->save();
    }

    public function computeDueAt(string $slaKey, CarbonInterface $from): CarbonInterface
    {
        $definition = $this->definition($slaKey);
        $value = max(1, $this->configuredValue($slaKey));
        $unit = (string) ($definition['unit'] ?? 'hours');

        return match ($unit) {
            'working_days' => SupportWorkingDays::addWorkingDays($from, $value),
            'calendar_days' => $from->copy()->addDays($value),
            default => $from->copy()->addHours($value),
        };
    }

    public function configuredValue(string $slaKey): int
    {
        $definition = $this->definition($slaKey);
        if (! $definition) {
            return 1;
        }

        return PlatformSettings::int((string) $definition['setting_key'], (int) $definition['default']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $slaKey): ?array
    {
        $definition = config("sla.definitions.{$slaKey}");

        return is_array($definition) ? $definition : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitionsForSettings(): array
    {
        return collect(config('sla.definitions', []))
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                ...$definition,
                'value' => $this->configuredValue($key),
            ])
            ->values()
            ->all();
    }

    public function userExpectationMessage(string $slaKey): string
    {
        $definition = $this->definition($slaKey);
        if (! $definition) {
            return '';
        }

        $template = (string) ($definition['user_message'] ?? 'We aim to resolve this within :duration.');

        return str_replace(':duration', $this->durationLabel($slaKey), $template);
    }

    public function userExpectationForSubject(string $slaKey, Model $subject): ?string
    {
        if (! Schema::hasTable('platform_sla_clocks')) {
            return $this->userExpectationMessage($slaKey);
        }

        $clock = PlatformSlaClock::query()
            ->where('sla_key', $slaKey)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('status', ['active', 'breached'])
            ->latest('id')
            ->first();

        if (! $clock) {
            return null;
        }

        return $this->userExpectationMessage($slaKey);
    }

    public function durationLabel(string $slaKey): string
    {
        $value = $this->configuredValue($slaKey);
        $unit = (string) ($this->definition($slaKey)['unit'] ?? 'hours');

        return match ($unit) {
            'working_days' => $value === 1 ? '1 working day' : "{$value} working days",
            'calendar_days' => $value === 1 ? '1 day' : "{$value} days",
            default => $value === 1 ? '1 hour' : "{$value} hours",
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function countdownPayload(?PlatformSlaClock $clock): ?array
    {
        if ($clock === null) {
            return null;
        }

        $dueAt = $clock->due_at;
        $now = now();
        $remainingSeconds = $dueAt && $dueAt->isFuture() ? $now->diffInSeconds($dueAt) : 0;
        $isOverdue = ($dueAt !== null && $now->greaterThanOrEqualTo($dueAt)) || $clock->status === 'breached';

        return [
            'uuid' => $clock->uuid,
            'sla_key' => $clock->sla_key,
            'label' => $this->definition($clock->sla_key)['label'] ?? Str::headline($clock->sla_key),
            'status' => $clock->status,
            'due_at' => $dueAt?->toIso8601String(),
            'due_at_label' => $dueAt?->diffForHumans(),
            'remaining_seconds' => $remainingSeconds,
            'is_overdue' => $isOverdue || $clock->status === 'breached',
            'breached_at' => $clock->breached_at?->toIso8601String(),
            'assigned_admin' => $clock->assignedAdmin?->only(['id', 'name', 'email']),
        ];
    }

    public function activeClockForSubject(string $slaKey, Model $subject): ?PlatformSlaClock
    {
        if (! Schema::hasTable('platform_sla_clocks')) {
            return null;
        }

        return PlatformSlaClock::query()
            ->with('assignedAdmin:id,name,email')
            ->where('sla_key', $slaKey)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('status', ['active', 'breached'])
            ->latest('id')
            ->first();
    }

    public function processBreaches(): int
    {
        if (! Schema::hasTable('platform_sla_clocks')) {
            return 0;
        }

        $count = 0;

        PlatformSlaClock::query()
            ->with('assignedAdmin:id,name,email')
            ->where('status', 'active')
            ->where('due_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($clocks) use (&$count): void {
                foreach ($clocks as $clock) {
                    $this->markBreached($clock);
                    $count++;
                }
            });

        return $count;
    }

    public function markBreached(PlatformSlaClock $clock): void
    {
        if ($clock->status !== 'active') {
            return;
        }

        $clock->forceFill([
            'status' => 'breached',
            'breached_at' => now(),
            'escalated_at' => $clock->escalated_at ?? now(),
        ])->save();

        $this->notifyBreach($clock);
    }

    public function notifyBreach(PlatformSlaClock $clock): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $definition = $this->definition($clock->sla_key);
        $label = (string) ($definition['label'] ?? Str::headline($clock->sla_key));
        $actionUrl = (string) ($definition['admin_action_url'] ?? route('admin.alerts.index', [], false));
        $subjectLabel = (string) data_get($clock->metadata, 'subject_label', '#'.$clock->subject_id);
        $dedupeKey = "sla_breach:{$clock->uuid}";

        if ($clock->assigned_admin_id) {
            AdminNotification::query()->updateOrCreate(
                [
                    'admin_user_id' => $clock->assigned_admin_id,
                    'data->dedupe_key' => $dedupeKey,
                ],
                [
                    'category' => 'sla',
                    'priority' => 'critical',
                    'title' => 'SLA deadline breached',
                    'body' => "{$label} · {$subjectLabel}",
                    'action_label' => 'Review now',
                    'action_url' => $actionUrl,
                    'data' => [
                        'dedupe_key' => $dedupeKey,
                        'sla_clock_uuid' => $clock->uuid,
                        'sla_key' => $clock->sla_key,
                    ],
                ],
            );
        }

        AdminNotification::query()->updateOrCreate(
            [
                'admin_user_id' => null,
                'data->dedupe_key' => $dedupeKey.':platform',
            ],
            [
                'category' => 'sla',
                'priority' => 'critical',
                'title' => 'SLA breach escalated to Super Admin',
                'body' => "{$label} · {$subjectLabel} · overdue since ".$clock->due_at?->diffForHumans(),
                'action_label' => 'Open alert centre',
                'action_url' => route('admin.alerts.index', [], false),
                'data' => [
                    'dedupe_key' => $dedupeKey.':platform',
                    'sla_clock_uuid' => $clock->uuid,
                    'sla_key' => $clock->sla_key,
                    'escalated' => true,
                ],
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeBreachesForAdmin(?User $admin): array
    {
        if (! Schema::hasTable('platform_sla_clocks')) {
            return [];
        }

        return PlatformSlaClock::query()
            ->with('assignedAdmin:id,name,email')
            ->where('status', 'breached')
            ->when(
                $admin && $admin->role?->slug !== 'super_admin',
                fn ($query) => $query->where('assigned_admin_id', $admin->id),
            )
            ->latest('breached_at')
            ->limit(50)
            ->get()
            ->map(fn (PlatformSlaClock $clock) => $this->countdownPayload($clock))
            ->filter()
            ->values()
            ->all();
    }
}
