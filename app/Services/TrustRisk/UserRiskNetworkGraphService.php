<?php

namespace App\Services\TrustRisk;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserReferral;
use App\Models\UserRiskNetworkNote;
use App\Models\UserRiskProfile;
use App\Models\UserVerification;
use App\Models\WalletBankAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class UserRiskNetworkGraphService
{
    public function __construct(private readonly TrustRiskSettingsService $settings) {}

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, clusters: list<array<string, mixed>>, center_user_id: int}
     */
    public function graphForUser(User $center): array
    {
        $edges = collect();
        $connectedIds = collect([$center->id]);

        $edges = $edges->merge($this->sharedIpEdges($center->id));
        $edges = $edges->merge($this->sharedDeviceEdges($center->id));
        $edges = $edges->merge($this->sharedBankEdges($center->id));
        $edges = $edges->merge($this->sharedKycEdges($center->id));

        $connectedIds = $connectedIds
            ->merge($edges->pluck('source'))
            ->merge($edges->pluck('target'))
            ->unique()
            ->values();

        $profiles = UserRiskProfile::query()
            ->whereIn('user_id', $connectedIds)
            ->get()
            ->keyBy('user_id');

        $users = User::query()->whereIn('id', $connectedIds)->get(['id', 'name', 'email'])->keyBy('id');

        $nodes = $connectedIds->map(function (int $id) use ($users, $profiles, $center): array {
            $profile = $profiles->get($id);
            $score = (int) ($profile?->composite_score ?? 0);
            $tier = $profile?->tier ?? $this->settings->tierForScore($score);

            return [
                'id' => (string) $id,
                'user_id' => $id,
                'label' => $users->get($id)?->name ?? "User #{$id}",
                'score' => $score,
                'tier' => $tier,
                'size' => max(20, min(60, 20 + $score * 0.4)),
                'is_center' => $id === $center->id,
            ];
        })->values()->all();

        $edgeList = $edges->unique(fn ($e) => "{$e['source']}-{$e['target']}-{$e['type']}")->values()->all();
        $clusters = $this->detectClusters($nodes, $edgeList);

        return [
            'center_user_id' => $center->id,
            'nodes' => $nodes,
            'edges' => $edgeList,
            'clusters' => $clusters,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notesForUser(User $subject): array
    {
        return UserRiskNetworkNote::query()
            ->with('author:id,name')
            ->where('subject_user_id', $subject->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (UserRiskNetworkNote $n) => [
                'id' => $n->id,
                'note' => $n->note,
                'author' => $n->author?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function storeNote(User $subject, User $author, string $note): UserRiskNetworkNote
    {
        return UserRiskNetworkNote::query()->create([
            'subject_user_id' => $subject->id,
            'author_user_id' => $author->id,
            'note' => $note,
        ]);
    }

    /**
     * @return Collection<int, array{source: int, target: int, type: string, label: string, first_seen: ?string, last_seen: ?string}>
     */
    private function sharedIpEdges(int $userId): Collection
    {
        if (! Schema::hasTable('login_events')) {
            return collect();
        }

        $ips = LoginEvent::query()->where('user_id', $userId)->pluck('ip_address')->filter()->unique();
        $edges = collect();

        foreach ($ips as $ip) {
            $others = LoginEvent::query()
                ->where('ip_address', $ip)
                ->where('user_id', '!=', $userId)
                ->selectRaw('user_id, MIN(logged_in_at) as first_seen, MAX(logged_in_at) as last_seen')
                ->groupBy('user_id')
                ->get();

            foreach ($others as $row) {
                $edges->push([
                    'source' => $userId,
                    'target' => (int) $row->user_id,
                    'type' => 'shared_ip',
                    'label' => 'Shared IP',
                    'first_seen' => $row->first_seen,
                    'last_seen' => $row->last_seen,
                ]);
            }
        }

        return $edges;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sharedDeviceEdges(int $userId): Collection
    {
        if (! Schema::hasTable('user_referrals')) {
            return collect();
        }

        $fps = UserReferral::query()
            ->where(fn ($q) => $q->where('referrer_user_id', $userId)->orWhere('referred_user_id', $userId))
            ->whereNotNull('device_fingerprint')
            ->pluck('device_fingerprint')
            ->unique();

        $edges = collect();
        foreach ($fps as $fp) {
            $others = UserReferral::query()
                ->where('device_fingerprint', $fp)
                ->where(fn ($q) => $q->where('referrer_user_id', '!=', $userId)->orWhere('referred_user_id', '!=', $userId))
                ->get();

            foreach ($others as $ref) {
                $otherId = (int) $ref->referrer_user_id === $userId ? (int) $ref->referred_user_id : (int) $ref->referrer_user_id;
                if ($otherId > 0 && $otherId !== $userId) {
                    $edges->push([
                        'source' => $userId,
                        'target' => $otherId,
                        'type' => 'shared_device',
                        'label' => 'Shared device',
                        'first_seen' => $ref->created_at?->toIso8601String(),
                        'last_seen' => $ref->updated_at?->toIso8601String(),
                    ]);
                }
            }
        }

        return $edges;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sharedBankEdges(int $userId): Collection
    {
        if (! Schema::hasTable('wallet_bank_accounts')) {
            return collect();
        }

        $accounts = WalletBankAccount::query()->where('user_id', $userId)->pluck('account_number')->filter();
        $edges = collect();

        foreach ($accounts as $num) {
            $others = WalletBankAccount::query()
                ->where('account_number', $num)
                ->where('user_id', '!=', $userId)
                ->pluck('user_id');

            foreach ($others as $otherId) {
                $edges->push([
                    'source' => $userId,
                    'target' => (int) $otherId,
                    'type' => 'shared_bank',
                    'label' => 'Shared payout account',
                    'first_seen' => null,
                    'last_seen' => null,
                ]);
            }
        }

        return $edges;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sharedKycEdges(int $userId): Collection
    {
        if (! Schema::hasTable('user_verifications')) {
            return collect();
        }

        $mine = UserVerification::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->get();

        $edges = collect();
        foreach ($mine as $v) {
            $meta = is_array($v->metadata) ? $v->metadata : [];
            $docNum = $meta['document_number'] ?? $meta['id_number'] ?? null;
            $dob = $meta['date_of_birth'] ?? $meta['dob'] ?? null;
            $name = $meta['legal_name'] ?? $meta['full_name'] ?? null;

            if ($docNum) {
                $others = UserVerification::query()
                    ->where('user_id', '!=', $userId)
                    ->where('status', 'approved')
                    ->whereJsonContains('metadata->document_number', $docNum)
                    ->orWhereJsonContains('metadata->id_number', $docNum)
                    ->pluck('user_id');

                foreach ($others as $otherId) {
                    $edges->push([
                        'source' => $userId,
                        'target' => (int) $otherId,
                        'type' => 'shared_kyc_document',
                        'label' => 'Shared document ID',
                        'first_seen' => $v->reviewed_at?->toIso8601String(),
                        'last_seen' => null,
                    ]);
                }
            }

            if ($name && $dob) {
                $others = UserVerification::query()
                    ->where('user_id', '!=', $userId)
                    ->where('status', 'approved')
                    ->get()
                    ->filter(function (UserVerification $o) use ($name, $dob): bool {
                        $m = is_array($o->metadata) ? $o->metadata : [];

                        return ($m['legal_name'] ?? $m['full_name'] ?? null) === $name
                            && ($m['date_of_birth'] ?? $m['dob'] ?? null) === $dob;
                    })
                    ->pluck('user_id');

                foreach ($others as $otherId) {
                    $edges->push([
                        'source' => $userId,
                        'target' => (int) $otherId,
                        'type' => 'shared_kyc_identity',
                        'label' => 'Name + DOB match',
                        'first_seen' => $v->reviewed_at?->toIso8601String(),
                        'last_seen' => null,
                    ]);
                }
            }
        }

        return $edges;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function detectClusters(array $nodes, array $edges): array
    {
        $adj = [];
        foreach ($edges as $e) {
            $a = (int) $e['source'];
            $b = (int) $e['target'];
            $adj[$a][] = $b;
            $adj[$b][] = $a;
        }

        $visited = [];
        $clusters = [];
        $clusterIdx = 0;

        foreach ($nodes as $node) {
            $id = (int) $node['user_id'];
            if (isset($visited[$id])) {
                continue;
            }

            $component = [];
            $stack = [$id];
            while ($stack) {
                $cur = array_pop($stack);
                if (isset($visited[$cur])) {
                    continue;
                }
                $visited[$cur] = true;
                $component[] = $cur;
                foreach ($adj[$cur] ?? [] as $nb) {
                    if (! isset($visited[$nb])) {
                        $stack[] = $nb;
                    }
                }
            }

            if (count($component) >= 3) {
                $clusterIdx++;
                $clusters[] = [
                    'id' => "cluster_{$clusterIdx}",
                    'label' => 'Suspected network '.$clusterIdx,
                    'member_ids' => $component,
                    'size' => count($component),
                ];
            }
        }

        return $clusters;
    }
}
