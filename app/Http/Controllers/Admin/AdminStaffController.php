<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignAdminStaffRoleRequest;
use App\Http\Requests\Admin\ImportAdminStaffCsvRequest;
use App\Http\Requests\Admin\InviteOperationsStaffRequest;
use App\Mail\OperationsStaffInvitationMail;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Services\Support\CustomerSupportService;
use App\Services\ProvisionOperationsStaffAccount;
use App\Support\AdminCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStaffController extends Controller
{
    public function index(CustomerSupportService $support): Response
    {
        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        $superRoleId = Role::query()->where('slug', 'super_admin')->value('id');

        $staff = User::query()
            ->whereIn('role_id', array_filter([$adminRoleId, $superRoleId]))
            ->with('role:id,name,slug')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        if ($support->tablesReady()) {
            $staff->getCollection()->transform(function (User $user) use ($support) {
                $user->setAttribute('support_ratings', $support->adminSupportRatingStats((int) $user->id));

                return $user;
            });
        }

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
        ]);
    }

    public function export(): StreamedResponse
    {
        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        $superRoleId = Role::query()->where('slug', 'super_admin')->value('id');

        $header = ['id', 'name', 'email', 'role_slug', 'created_at'];

        return AdminCsv::download('staff-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($adminRoleId, $superRoleId): void {
            User::query()
                ->whereIn('role_id', array_filter([$adminRoleId, $superRoleId]))
                ->with('role:id,slug')
                ->orderByDesc('id')
                ->chunk(200, function ($users) use ($out): void {
                    foreach ($users as $user) {
                        fputcsv($out, [
                            $user->id,
                            $user->name,
                            $user->email,
                            $user->role?->slug,
                            $user->created_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }

    public function store(AssignAdminStaffRoleRequest $request, AdminActivityLogger $logger): RedirectResponse
    {
        $actor = $request->user();
        $email = $request->validated('email');

        $target = User::query()->where('email', $email)->first();
        if ($target === null) {
            return back()->withErrors(['email' => __('No user exists with that email address.')]);
        }

        if ($target->id === $actor->id) {
            return back()->withErrors(['email' => __('You cannot change your own role from this form.')]);
        }

        if ($target->role?->slug === 'super_admin') {
            return back()->withErrors(['email' => __('Super admin accounts cannot be reassigned here.')]);
        }

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $target->forceFill([
            'role_id' => $adminRole->id,
            'operations_staff_invited_at' => now(),
            'operations_staff_invited_by' => $actor->id,
            'operations_staff_password_set_at' => null,
        ])->save();

        $setupUrl = URL::temporarySignedRoute(
            'operations.invitation.show',
            now()->addDays(7),
            ['user' => $target->id]
        );

        Mail::to($target)->send(new OperationsStaffInvitationMail($target, $setupUrl));

        $logger->log(
            actor: $actor,
            action: 'admin.assign_admin_role',
            subjectType: User::class,
            subjectId: $target->id,
            properties: [
                'email' => $target->email,
                'new_role' => 'admin',
            ],
            request: $request,
        );

        return redirect()->route('admin.staff.index')->with('success', __('Operational admin access granted and a password setup invite was sent.'));
    }

    public function invite(InviteOperationsStaffRequest $request, ProvisionOperationsStaffAccount $provision): RedirectResponse
    {
        $data = $request->validated();
        $provision->invite(
            $request->user(),
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $request,
        );

        return redirect()->route('admin.staff.index')->with('success', __('Invitation sent. They can set a password from the email link.'));
    }

    public function import(ImportAdminStaffCsvRequest $request, AdminActivityLogger $logger): RedirectResponse
    {
        $actor = $request->user();
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return back()->withErrors(['file' => __('CSV is empty.')]);
        }

        $norm = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $emailIdx = array_search('email', $norm, true);
        if ($emailIdx === false) {
            fclose($handle);

            return back()->withErrors(['file' => __('CSV must include an "email" column.')]);
        }

        $promoted = 0;
        $skipped = 0;
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $email = isset($row[$emailIdx]) ? strtolower(trim((string) $row[$emailIdx])) : '';
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $target = User::query()->where('email', $email)->first();
            if ($target === null) {
                $skipped++;

                continue;
            }

            if ($target->id === $actor->id || $target->role?->slug === 'super_admin') {
                $skipped++;

                continue;
            }

            $target->update(['role_id' => $adminRole->id]);

            $logger->log(
                actor: $actor,
                action: 'admin.assign_admin_role_csv',
                subjectType: User::class,
                subjectId: $target->id,
                properties: ['email' => $target->email, 'csv_row' => $rowNum],
                request: $request,
            );
            $promoted++;
            if ($promoted >= 200) {
                break;
            }
        }

        fclose($handle);

        return back()->with('success', __('Granted admin to :n account(s); skipped :s row(s).', ['n' => $promoted, 's' => $skipped]));
    }
}
