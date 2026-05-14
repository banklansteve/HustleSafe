<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegisteredUserRequest;
use App\Models\FreelancerBusinessProfile;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use App\Services\TrustScoreOrchestrator;
use App\Support\TextCasing;
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

        $first = TextCasing::titleWords($data['first_name']) ?? '';
        $last = TextCasing::titleWords($data['last_name']) ?? '';
        $city = TextCasing::titleWords($data['city']) ?? '';
        $addressLine = TextCasing::capitalizeFirstAlphabetic($data['address_line']) ?? '';
        $company = isset($data['company_name']) && $data['company_name'] !== null && $data['company_name'] !== ''
            ? TextCasing::titleWords($data['company_name'])
            : null;

        $user = User::create([
            'name' => trim($first.' '.$last),
            'first_name' => $first,
            'last_name' => $last,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'company_name' => $company,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address_line' => $addressLine,
            'city' => $city,
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
