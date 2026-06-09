<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserActivityPatrol\UserActivityPatrolActionRequest;
use App\Models\User;
use App\Models\UserActivityPatrolFlag;
use App\Services\Admin\UserActivityPatrol\UserActivityPatrolActionService;
use App\Services\Admin\UserActivityPatrol\UserActivityPatrolDetailService;
use App\Services\Admin\UserActivityPatrol\UserActivityPatrolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsUserActivityPatrolController extends Controller
{
    protected bool $isSuperAdmin = false;

    protected string $routePrefix = 'operations';

    protected bool $useAdminShell = false;

    public function __construct(
        protected readonly UserActivityPatrolService $patrol,
        protected readonly UserActivityPatrolDetailService $detail,
        protected readonly UserActivityPatrolActionService $actions,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('UserActivityPatrol/Index', [
            ...$this->patrol->indexPayload($request, $this->isSuperAdmin),
            'route_prefix' => $this->routePrefix,
            'use_admin_shell' => $this->useAdminShell,
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->patrol->listing($request));
    }

    public function detail(Request $request, User $user): JsonResponse
    {
        abort_if(in_array($user->role?->slug, ['admin', 'super_admin'], true), 404);

        $flag = null;
        if ($request->filled('flag_id')) {
            $flag = UserActivityPatrolFlag::query()
                ->where('user_id', $user->id)
                ->find($request->integer('flag_id'));
        }

        return response()->json($this->detail->build($user, $flag, $this->isSuperAdmin));
    }

    public function assign(UserActivityPatrolFlag $flag): JsonResponse
    {
        $flag = $this->actions->assign($flag, request()->user());

        return response()->json(['message' => __('Case assigned to you.'), 'flag' => $flag]);
    }

    public function release(UserActivityPatrolFlag $flag): JsonResponse
    {
        $flag = $this->actions->release($flag, request()->user());

        return response()->json(['message' => __('Case released back to queue.'), 'flag' => $flag]);
    }

    public function warn(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->warn($user, $request->user(), $request->validated(), $request, $this->isSuperAdmin);

        return back()->with('success', __('Warning sent to user.'));
    }

    public function watchlist(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->watchlist($user, $request->user(), $request->validated(), $this->isSuperAdmin);

        return back()->with('success', __('User added to watchlist.'));
    }

    public function investigate(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->investigate($user, $request->user(), $request->validated());

        return back()->with('success', __('Investigation opened.'));
    }

    public function message(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->message($user, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Message sent.'));
    }

    public function suspend(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->actions->suspend($user, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Account suspended.'));
    }

    public function terminate(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->actions->terminate($user, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Account terminated.'));
    }

    public function imposeSanction(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->actions->imposeSanction($user, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Sanction imposed.'));
    }

    public function reverseTransaction(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->actions->reverseTransaction($user, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Transaction reversed.'));
    }

    public function mergeAccounts(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $request->validate(['secondary_user_id' => ['required', 'integer', 'exists:users,id']]);
        $this->actions->mergeAccounts($user, $request->user(), $request->validated());

        return back()->with('success', __('Accounts merged.'));
    }

    public function note(UserActivityPatrolActionRequest $request, User $user): RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'min:10', 'max:2000']]);
        $this->actions->addNote($user, $request->user(), $request->validated());

        return back()->with('success', __('Note added.'));
    }

    public function resolve(UserActivityPatrolFlag $flag, Request $request): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $this->actions->resolve($flag, $request->user(), (string) $request->input('notes', ''));

        return back()->with('success', __('Flag resolved.'));
    }

    public function dismiss(Request $request, UserActivityPatrolFlag $flag): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->actions->dismiss($flag, $request->user(), (string) $request->input('reason'));

        return back()->with('success', __('Anomaly dismissed.'));
    }
}
