<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;

class PowerHoursService
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        $timezone = $this->timezone((string) ($data['timezone'] ?? config('app.timezone', 'Africa/Lagos')));
        $weekly = [];

        foreach (self::DAYS as $day) {
            $row = $data['weekly'][$day] ?? [];
            $enabled = (bool) ($row['enabled'] ?? false);
            $start = $this->timeOrDefault((string) ($row['start'] ?? '09:00'), '09:00');
            $end = $this->timeOrDefault((string) ($row['end'] ?? '17:00'), '17:00');

            $weekly[$day] = [
                'enabled' => $enabled && $start !== $end,
                'start' => $start,
                'end' => $end,
            ];
        }

        return [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'timezone' => $timezone,
            'response_mode' => in_array($data['response_mode'] ?? 'same_day', ['same_day', 'next_business_day', 'flexible'], true)
                ? $data['response_mode']
                : 'same_day',
            'weekly' => $weekly,
            'note' => trim((string) ($data['note'] ?? '')),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formPayload(?array $powerHours): array
    {
        return $this->normalize($powerHours ?: [
            'enabled' => false,
            'timezone' => 'Africa/Lagos',
            'response_mode' => 'same_day',
            'weekly' => [
                'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'tue' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'wed' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'thu' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'fri' => ['enabled' => true, 'start' => '09:00', 'end' => '16:00'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function signalFor(User $user): array
    {
        $payload = $this->formPayload($user->power_hours ?? null);
        if (! ($payload['enabled'] ?? false)) {
            return [
                ...$payload,
                'status' => 'not_set',
                'label' => 'Power Hours not set',
                'summary' => 'This freelancer has not published availability yet.',
                'today' => null,
            ];
        }

        $now = CarbonImmutable::now($payload['timezone']);
        $day = strtolower($now->format('D'));
        $today = $payload['weekly'][$day] ?? ['enabled' => false];
        $isOpen = false;

        if ($today['enabled'] ?? false) {
            $start = CarbonImmutable::parse($now->toDateString().' '.$today['start'], $payload['timezone']);
            $end = CarbonImmutable::parse($now->toDateString().' '.$today['end'], $payload['timezone']);
            $isOpen = $now->betweenIncluded($start, $end);
        }

        return [
            ...$payload,
            'status' => $isOpen ? 'available_now' : 'outside_hours',
            'label' => $isOpen ? 'Inside Power Hours' : 'Outside Power Hours',
            'summary' => $this->summary($payload),
            'today' => ($today['enabled'] ?? false) ? $today : null,
            'local_time' => $now->format('g:i A'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summary(array $payload): string
    {
        $enabled = collect($payload['weekly'] ?? [])
            ->filter(fn (array $row) => (bool) ($row['enabled'] ?? false));

        if ($enabled->isEmpty()) {
            return 'Availability is enabled, but no working windows have been selected.';
        }

        $first = $enabled->first();
        $days = $enabled->keys()->map(fn (string $day) => ucfirst($day))->implode(', ');

        return "{$days}, {$first['start']} - {$first['end']} {$payload['timezone']}";
    }

    private function timeOrDefault(string $value, string $fallback): string
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $fallback;
    }

    private function timezone(string $value): string
    {
        return in_array($value, DateTimeZone::listIdentifiers(), true) ? $value : 'Africa/Lagos';
    }
}
