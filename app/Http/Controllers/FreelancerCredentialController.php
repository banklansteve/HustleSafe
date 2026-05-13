<?php

namespace App\Http\Controllers;

use App\Enums\CredentialType;
use App\Http\Requests\FreelancerCredential\StoreFreelancerCredentialRequest;
use App\Http\Requests\FreelancerCredential\UpdateFreelancerCredentialRequest;
use App\Models\FreelancerCredential;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FreelancerCredentialController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = FreelancerCredential::query()
            ->where('user_id', $user->id)
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->get();

        $sections = collect(CredentialType::cases())->map(function (CredentialType $t) use ($items) {
            return [
                'type' => $t->value,
                'label' => $t->label(),
                'items' => $items
                    ->where('credential_type', $t->value)
                    ->values()
                    ->map(fn (FreelancerCredential $c) => $this->toArray($c))
                    ->all(),
            ];
        })->all();

        return Inertia::render('Account/Credentials/Index', [
            'sections' => $sections,
        ]);
    }

    public function create(Request $request, string $type): Response|RedirectResponse
    {
        $enum = CredentialType::tryFrom($type);
        if ($enum === null) {
            return redirect()->route('account.credentials.index');
        }

        return Inertia::render('Account/Credentials/Form', [
            'mode' => 'create',
            'credential' => null,
            'credentialType' => $enum->value,
            'typeLabel' => $enum->label(),
        ]);
    }

    public function store(StoreFreelancerCredentialRequest $request, TrustScoreOrchestrator $trust, string $type): RedirectResponse
    {
        abort_unless(CredentialType::tryFrom($type) !== null, 404);

        $user = $request->user();
        $data = $request->validated();
        $path = null;
        if ($request->hasFile('document')) {
            $path = $request->file('document')->store("credentials/user_{$user->id}", 'public');
        }

        FreelancerCredential::query()->create([
            'user_id' => $user->id,
            'credential_type' => $data['credential_type'],
            'title' => $data['title'],
            'issuing_authority' => $data['issuing_authority'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'coverage_summary' => $data['coverage_summary'] ?? null,
            'document_path' => $path,
            'is_verified' => false,
            'is_public' => true,
            'display_order' => 0,
        ]);

        $trust->recalculate($user->fresh());

        return redirect()->route('account.credentials.index')->with('success', __('Record saved. Our team may request supporting documents to verify entries typical in Nigeria (e.g. CAC, COREN, ITF, council licences, NAICOM-regulated policies).'));
    }

    public function edit(Request $request, FreelancerCredential $freelancerCredential): Response
    {
        abort_unless($freelancerCredential->user_id === $request->user()->id, 403);

        $enum = CredentialType::tryFrom($freelancerCredential->credential_type);

        return Inertia::render('Account/Credentials/Form', [
            'mode' => 'edit',
            'credential' => $this->toArray($freelancerCredential),
            'credentialType' => $freelancerCredential->credential_type,
            'typeLabel' => $enum?->label() ?? $freelancerCredential->credential_type,
        ]);
    }

    public function update(UpdateFreelancerCredentialRequest $request, FreelancerCredential $freelancerCredential, TrustScoreOrchestrator $trust): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('document')) {
            if ($freelancerCredential->document_path) {
                Storage::disk('public')->delete($freelancerCredential->document_path);
            }
            $freelancerCredential->document_path = $request->file('document')->store(
                'credentials/user_'.$request->user()->id,
                'public'
            );
        }

        $freelancerCredential->fill([
            'credential_type' => $data['credential_type'],
            'title' => $data['title'],
            'issuing_authority' => $data['issuing_authority'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'coverage_summary' => $data['coverage_summary'] ?? null,
        ]);
        $freelancerCredential->save();
        $trust->recalculate($request->user()->fresh());

        return redirect()->route('account.credentials.index')->with('success', __('Credential updated.'));
    }

    public function destroy(Request $request, FreelancerCredential $freelancerCredential, TrustScoreOrchestrator $trust): RedirectResponse
    {
        abort_unless($freelancerCredential->user_id === $request->user()->id, 403);
        if ($freelancerCredential->document_path) {
            Storage::disk('public')->delete($freelancerCredential->document_path);
        }
        $freelancerCredential->delete();
        $trust->recalculate($request->user()->fresh());

        return redirect()->route('account.credentials.index')->with('success', __('Credential removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(FreelancerCredential $c): array
    {
        return [
            'id' => $c->id,
            'credential_type' => $c->credential_type,
            'title' => $c->title,
            'issuing_authority' => $c->issuing_authority,
            'reference_number' => $c->reference_number,
            'issued_on' => $c->issued_on?->toDateString(),
            'expires_on' => $c->expires_on?->toDateString(),
            'coverage_summary' => $c->coverage_summary,
            'is_verified' => (bool) $c->is_verified,
            'is_public' => (bool) $c->is_public,
            'document_url' => $c->document_path ? Storage::disk('public')->url($c->document_path) : null,
        ];
    }
}
