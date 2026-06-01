<?php

namespace App\Services\Finance;

use App\Enums\ReconciliationExceptionStatus;
use App\Models\FinancialReconciliationException;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class FinancialAuditExceptionService
{
    /**
     * @return array<string, mixed>
     */
    public function listing(): array
    {
        $exceptions = FinancialReconciliationException::query()
            ->with(['assignee:id,name', 'paymentEscrow:id,reference'])
            ->orderByDesc('first_detected_at')
            ->paginate(30);

        return [
            'data' => collect($exceptions->items())->map(fn (FinancialReconciliationException $e) => [
                'id' => $e->id,
                'uuid' => $e->uuid,
                'type' => $e->type,
                'type_label' => $e->typeEnum()?->label() ?? $e->type,
                'status' => $e->status,
                'title' => $e->title,
                'description' => $e->description,
                'paystack_reference' => $e->paystack_reference,
                'escrow_reference' => $e->paymentEscrow?->reference,
                'variance_minor' => $e->variance_minor,
                'variance_display' => $e->variance_minor !== null ? \App\Support\NgnMoney::format((int) abs($e->variance_minor)) : null,
                'first_detected_at' => $e->first_detected_at?->toIso8601String(),
                'assigned_to' => $e->assignee?->name,
                'assigned_to_user_id' => $e->assigned_to_user_id,
                'investigation_notes' => $e->investigation_notes,
                'escalated_at' => $e->escalated_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $exceptions->currentPage(),
                'last_page' => $exceptions->lastPage(),
                'total' => $exceptions->total(),
            ],
        ];
    }

    public function assign(FinancialReconciliationException $exception, User $assignee): FinancialReconciliationException
    {
        abort_unless($assignee->role?->slug === 'super_admin', 403);

        $exception->update([
            'assigned_to_user_id' => $assignee->id,
            'status' => ReconciliationExceptionStatus::UnderInvestigation->value,
        ]);

        return $exception->fresh(['assignee']);
    }

    public function addNotes(FinancialReconciliationException $exception, string $notes): FinancialReconciliationException
    {
        $exception->update([
            'investigation_notes' => trim($exception->investigation_notes."\n\n".now()->format('Y-m-d H:i').': '.$notes),
            'status' => ReconciliationExceptionStatus::UnderInvestigation->value,
        ]);

        return $exception->fresh();
    }

    public function resolve(FinancialReconciliationException $exception, User $resolver, string $description): FinancialReconciliationException
    {
        if (trim($description) === '') {
            throw ValidationException::withMessages(['resolution' => [__('Resolution description is required.')]]);
        }

        $exception->update([
            'status' => ReconciliationExceptionStatus::Resolved->value,
            'resolution_description' => $description,
            'resolved_at' => now(),
            'resolved_by_user_id' => $resolver->id,
        ]);

        return $exception->fresh();
    }
}
