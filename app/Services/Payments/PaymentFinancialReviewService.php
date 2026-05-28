<?php

namespace App\Services\Payments;

use App\Enums\PaymentAnomalyType;
use App\Models\PaymentReviewFlag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentFinancialReviewService
{
    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>, filters: array<string, mixed>}
     */
    public function listing(Request $request): array
    {
        $status = (string) $request->query('status', 'pending');
        $severity = (string) $request->query('severity', '');
        $type = (string) $request->query('anomaly_type', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $sort = (string) $request->query('sort', 'date_desc');
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));

        $query = PaymentReviewFlag::query()
            ->with([
                'staffAdmin:id,name,email',
                'resolvedBy:id,name,email',
                'escrow:id,reference,amount_minor,quest_id',
                'quest:id,title,reference_code',
            ]);

        if ($status !== '' && $status !== 'all') {
            $query->where('resolution_status', $status);
        }

        if ($severity !== '') {
            $query->where('severity', $severity);
        }

        if ($type !== '') {
            $query->where('anomaly_type', $type);
        }

        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        match ($sort) {
            'severity' => $query->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")->orderByDesc('id'),
            'date_asc' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'items' => collect($paginator->items())->map(fn (PaymentReviewFlag $flag) => $this->flagRow($flag))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pending_count' => PaymentReviewFlag::query()->where('resolution_status', 'pending')->count(),
            ],
            'filters' => [
                'status' => $status,
                'severity' => $severity,
                'anomaly_type' => $type,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
            'anomaly_types' => collect(PaymentAnomalyType::cases())
                ->map(fn (PaymentAnomalyType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->all(),
            'status_options' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'reviewed', 'label' => 'Reviewed'],
                ['value' => 'escalated', 'label' => 'Escalated'],
                ['value' => 'dismissed', 'label' => 'Dismissed'],
                ['value' => 'all', 'label' => 'All'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(User $admin, PaymentReviewFlag $flag, array $data): PaymentReviewFlag
    {
        $action = (string) ($data['action'] ?? '');

        $status = match ($action) {
            'reviewed' => 'reviewed',
            'escalate' => 'escalated',
            'dismiss' => 'dismissed',
            default => throw ValidationException::withMessages(['action' => __('Unknown resolution action.')]),
        };

        if ($action === 'dismiss' && trim((string) ($data['resolution_note'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'resolution_note' => __('A dismissal reason is required.'),
            ]);
        }

        $flag->forceFill([
            'resolution_status' => $status,
            'resolution_note' => $data['resolution_note'] ?? null,
            'resolved_by_admin_id' => $admin->id,
            'resolved_at' => now(),
        ])->save();

        return $flag->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function flagRow(PaymentReviewFlag $flag): array
    {
        $type = PaymentAnomalyType::tryFrom($flag->anomaly_type);

        return [
            'id' => $flag->id,
            'anomaly_type' => $flag->anomaly_type,
            'anomaly_label' => $type?->label() ?? $flag->anomaly_type,
            'severity' => $flag->severity,
            'resolution_status' => $flag->resolution_status,
            'concern_note' => $flag->concern_note,
            'resolution_note' => $flag->resolution_note,
            'transaction_reference' => $flag->transaction_reference,
            'payment_escrow_id' => $flag->payment_escrow_id,
            'quest_id' => $flag->quest_id,
            'quest_title' => $flag->quest?->title,
            'quest_reference' => $flag->quest?->reference_code,
            'amount_minor' => (int) ($flag->escrow?->amount_minor ?? ($flag->signal_payload['funded_amount_minor'] ?? $flag->signal_payload['contract_amount_minor'] ?? $flag->signal_payload['total_amount_minor'] ?? 0)),
            'signal_payload' => $flag->signal_payload ?? [],
            'staff_admin' => $flag->staffAdmin ? [
                'id' => $flag->staffAdmin->id,
                'name' => $flag->staffAdmin->name,
                'email' => $flag->staffAdmin->email,
            ] : null,
            'resolved_by' => $flag->resolvedBy ? [
                'id' => $flag->resolvedBy->id,
                'name' => $flag->resolvedBy->name,
            ] : null,
            'created_at' => $flag->created_at?->toIso8601String(),
            'resolved_at' => $flag->resolved_at?->toIso8601String(),
            'is_unreviewed' => $flag->resolution_status === 'pending',
        ];
    }
}
