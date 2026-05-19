<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\QuestOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OperationsProposalsController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $quick = (string) $request->query('quick', '');
        $adminStatusReady = Schema::hasColumn('quest_offers', 'admin_status');

        $proposals = QuestOffer::query()
            ->with(['quest:id,title,reference_code', 'freelancer:id,name,email,avatar_url,verification_tier'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($scope) use ($q): void {
                    $scope->where('id', $q)
                        ->orWhere('pitch', 'like', "%{$q}%")
                        ->orWhereHas('quest', fn ($quest) => $quest->where('title', 'like', "%{$q}%")->orWhere('reference_code', 'like', "%{$q}%"))
                        ->orWhereHas('freelancer', fn ($user) => $user->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
                });
            })
            ->when($adminStatusReady && $quick === 'flagged_today', fn ($query) => $query->where('admin_status', '<>', 'clear')->where('updated_at', '>=', now()->startOfDay()))
            ->when($adminStatusReady && $quick === 'needs_action', fn ($query) => $query->whereIn('admin_status', ['flagged', 'under_review', 'referred', 'action_required']))
            ->latest()
            ->paginate(20)
            ->through(fn (QuestOffer $proposal) => [
                'id' => $proposal->id,
                'status' => $proposal->status,
                'admin_status' => $proposal->admin_status?->value ?? (string) $proposal->admin_status,
                'amount' => (int) ($proposal->quoted_amount_minor ?? 0),
                'created_at' => $proposal->created_at?->toIso8601String(),
                'excerpt' => str($proposal->pitch ?: $proposal->scope_detail ?: 'No proposal content preview.')->limit(160)->toString(),
                'quest' => $proposal->quest ? [
                    'title' => $proposal->quest->title,
                    'reference_code' => $proposal->quest->reference_code,
                ] : null,
                'freelancer' => $proposal->freelancer ? [
                    'name' => $proposal->freelancer->name,
                    'email' => $proposal->freelancer->email,
                    'verification_tier' => $proposal->freelancer->verification_tier,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('Operations/Proposals/Index', [
            'proposals' => $proposals,
            'filters' => ['q' => $q, 'quick' => $quick],
            'adminStatusReady' => $adminStatusReady,
        ]);
    }
}
