<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegisteredUserRequest;
use App\Models\FreelancerBusinessProfile;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'locations' => State::query()
                ->with(['localGovernments:id,state_id,name'])
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'questCategories' => QuestCategory::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(StoreRegisteredUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['first_name'].' '.$data['last_name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'state_id' => $data['state_id'],
            'local_government_id' => $data['local_government_id'],
            'account_type' => $data['account_type'],
            'password' => Hash::make($data['password']),
        ]);

        if ($data['account_type'] === 'hustler') {
            FreelancerBusinessProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['cac_verification_status' => 'not_submitted'],
            );
            $ids = $data['quest_category_ids'] ?? [];
            if ($ids !== []) {
                $user->questCategoryPreferences()->sync($ids);
            }
        }

        app(TrustScoreOrchestrator::class)->recalculate($user->fresh());

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
