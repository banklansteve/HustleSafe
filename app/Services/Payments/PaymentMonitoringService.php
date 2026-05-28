<?php

namespace App\Services\Payments;

use App\Models\PaymentReviewFlag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentMonitoringService
{
    public function __construct(
        private readonly PaymentMonitoringAnomalyEngine $engine,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function queue(Request $request): array
    {
        return $this->engine->listing($request);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function raiseFlag(User $staff, array $data): PaymentReviewFlag
    {
        $fingerprint = (string) ($data['anomaly_fingerprint'] ?? '');
        if ($fingerprint === '') {
            throw ValidationException::withMessages(['anomaly_fingerprint' => __('Anomaly reference is required.')]);
        }

        $pending = PaymentReviewFlag::query()
            ->where('anomaly_fingerprint', $fingerprint)
            ->where('resolution_status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'anomaly_fingerprint' => __('This anomaly already has a pending financial review flag.'),
            ]);
        }

        $payload = is_array($data['signal_payload'] ?? null) ? $data['signal_payload'] : [];

        return PaymentReviewFlag::query()->create([
            'anomaly_type' => (string) $data['anomaly_type'],
            'severity' => (string) $data['severity'],
            'anomaly_fingerprint' => $fingerprint,
            'payment_escrow_id' => $data['payment_escrow_id'] ?? null,
            'quest_id' => $data['quest_id'] ?? null,
            'wallet_transaction_id' => $data['wallet_transaction_id'] ?? null,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'signal_payload' => $payload,
            'staff_admin_id' => $staff->id,
            'concern_note' => (string) $data['concern_note'],
            'resolution_status' => 'pending',
        ]);
    }
}
