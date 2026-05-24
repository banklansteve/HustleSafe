<?php

namespace App\Http\Controllers;

use App\Events\QuestConversationMessageSent;
use App\Events\QuestConversationTyping;
use App\Http\Requests\Quests\StoreQuestConversationMessageRequest;
use App\Jobs\ScanContentForModerationJob;
use App\Models\Quest;
use App\Models\QuestConversationMessage;
use App\Models\QuestConversationThread;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\QuestThreadMessageNotification;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\UserNotificationInboxService;
use App\Support\MessagingViewPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestConversationController extends Controller
{
    public function show(Request $request, Quest $quest, ?User $contact = null): Response|RedirectResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $readiness = app(FreelancerWorkspaceReadinessService::class);
        $freelancerParty = null;
        if ($this->userActsAsFreelancerAccount($user) && (int) $quest->client_id !== (int) $user->id) {
            if ($contact !== null && (int) $contact->id !== (int) $user->id) {
                abort(403);
            }
            if (! $readiness->freelancerMayUseQuestMessaging($user, $quest)) {
                return redirect()
                    ->route('quests.show', $quest)
                    ->with('status', __('Complete your address and work categories before messaging clients.'));
            }
            $freelancerParty = $user;
        } elseif ((int) $quest->client_id === (int) $user->id) {
            if ($contact === null) {
                abort(404);
            }
            if (! $this->userActsAsFreelancerAccount($contact)) {
                abort(404);
            }
            $freelancerParty = $contact;
        } else {
            abort(403);
        }

        $thread = QuestConversationThread::query()->firstOrCreate(
            [
                'quest_id' => $quest->id,
                'freelancer_id' => $freelancerParty->id,
            ],
            [
                'client_id' => $quest->client_id,
            ],
        );

        if ($thread->isBlockedByAdmin()) {
            return redirect()
                ->route('quests.show', $quest)
                ->with('status', __('This conversation thread is no longer available.'));
        }

        $this->markThreadReadFor($thread, $user);

        $thread->load(['messages.user:id,first_name,name,slug,avatar_url,role_id', 'messages.user.role:id,slug']);

        return Inertia::render('Quests/Messages/Show', [
            'quest' => [
                'title' => $quest->title,
                'slug' => $quest->slug,
                'uuid' => $quest->uuid,
                'route_key' => $quest->getRouteKey(),
            ],
            'counterparty' => $this->counterpartyPayload($quest, $user, $freelancerParty),
            'thread' => [
                'id' => $thread->id,
                'messages' => $thread->messages->map(fn (QuestConversationMessage $m) => $this->messagePayload($m, $user))->values()->all(),
            ],
            'post_url' => $this->userActsAsFreelancerAccount($user) && (int) $quest->client_id !== (int) $user->id
                ? route('quests.messages.store', [$quest->getRouteKey()])
                : route('quests.messages.store', [$quest->getRouteKey(), $freelancerParty->slug]),
            'rules' => [
                'no_contact' => __('Phone numbers, email, and social handles are blocked. Attempting to move deals off-platform can result in a permanent ban.'),
            ],
            'messaging_limits' => $this->messagingLimits($quest, $freelancerParty),
        ]);
    }

    public function store(
        StoreQuestConversationMessageRequest $request,
        Quest $quest,
        ?User $contact = null,
    ): RedirectResponse|JsonResponse {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $readiness = app(FreelancerWorkspaceReadinessService::class);
        $freelancerParty = null;
        if ($this->userActsAsFreelancerAccount($user) && (int) $quest->client_id !== (int) $user->id) {
            if ($contact !== null && (int) $contact->id !== (int) $user->id) {
                abort(403);
            }
            if (! $readiness->freelancerMayUseQuestMessaging($user, $quest)) {
                abort(403);
            }
            $freelancerParty = $user;
        } elseif ((int) $quest->client_id === (int) $user->id) {
            if ($contact === null || ! $this->userActsAsFreelancerAccount($contact)) {
                abort(404);
            }
            $freelancerParty = $contact;
        } else {
            abort(403);
        }

        $maxPerThread = (int) config('quests.thread_max_messages', 120);
        $thread = QuestConversationThread::query()->firstOrCreate(
            [
                'quest_id' => $quest->id,
                'freelancer_id' => $freelancerParty->id,
            ],
            [
                'client_id' => $quest->client_id,
            ],
        );

        if ($thread->isBlockedByAdmin()) {
            abort(403, __('This conversation thread is no longer available.'));
        }

        if ($thread->messages_count >= $maxPerThread) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('This thread has reached the maximum number of messages. Continue on-platform after a proposal is accepted.'),
                ], 422);
            }

            return back()->with('status', __('This thread has reached the maximum number of messages. Continue on-platform after a proposal is accepted.'));
        }

        $message = $thread->messages()->create([
            'user_id' => $user->id,
            'body' => $request->validated()['body'],
        ]);
        ScanContentForModerationJob::dispatch(QuestConversationMessage::class, (int) $message->id)->afterResponse();

        $thread->increment('messages_count');
        $thread->forceFill(['last_message_at' => now()])->save();

        $recipient = (int) $user->id === (int) $freelancerParty->id
            ? User::query()->findOrFail($quest->client_id)
            : $freelancerParty;

        $recipient->unreadNotifications()
            ->where('type', QuestThreadMessageNotification::class)
            ->get()
            ->each(function ($n) use ($quest, $user): void {
                $d = is_array($n->data) ? $n->data : [];
                if (($d['kind'] ?? '') === 'quest_thread_message'
                    && (int) ($d['quest_id'] ?? 0) === (int) $quest->id
                    && (int) ($d['sender_id'] ?? 0) === (int) $user->id) {
                    $n->delete();
                }
            });

        if (! MessagingViewPresence::isViewing(
            MessagingViewPresence::SCOPE_QUEST_THREAD,
            (int) $thread->id,
            (int) $recipient->id,
        )) {
            $recipient->notify(new QuestThreadMessageNotification($quest, $user, $message));
        }

        $message->load(['user:id,first_name,name,slug,avatar_url,role_id', 'user.role:id,slug']);

        broadcast(new QuestConversationMessageSent(
            $thread->id,
            $this->messageBroadcastPayload($message),
        ));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messagePayload($message, $user),
            ]);
        }

        return back()->with('success', __('Message sent.'));
    }

    public function read(Request $request, Quest $quest, ?User $contact = null): JsonResponse
    {
        $this->authorize('view', $quest);

        [$thread, $user] = $this->resolveThreadContext($request, $quest, $contact);

        $this->markThreadReadFor($thread, $user);

        return response()->json(['ok' => true]);
    }

    public function typing(Request $request, Quest $quest, ?User $contact = null): JsonResponse
    {
        $this->authorize('view', $quest);

        [$thread, $user] = $this->resolveThreadContext($request, $quest, $contact);

        $data = $request->validate(['typing' => ['required', 'boolean']]);
        $typing = (bool) $data['typing'];

        if ($typing) {
            MessagingViewPresence::touch(
                MessagingViewPresence::SCOPE_QUEST_THREAD,
                (int) $thread->id,
                (int) $user->id,
            );
        }

        broadcast(new QuestConversationTyping(
            (int) $thread->id,
            (int) $user->id,
            (string) $user->name,
            $typing,
        ));

        return response()->json(['ok' => true]);
    }

    protected function markThreadReadFor(QuestConversationThread $thread, User $viewer): void
    {
        if ((int) $viewer->id === (int) $thread->freelancer_id) {
            $thread->forceFill(['freelancer_last_read_at' => now()])->save();
        } elseif ((int) $viewer->id === (int) $thread->client_id) {
            $thread->forceFill(['client_last_read_at' => now()])->save();
        }

        MessagingViewPresence::touch(
            MessagingViewPresence::SCOPE_QUEST_THREAD,
            (int) $thread->id,
            (int) $viewer->id,
        );

        $questId = (int) $thread->quest_id;
        if ($questId > 0) {
            app(UserNotificationInboxService::class)->markQuestThreadForQuest($viewer, $questId);
        }
    }

    /**
     * @return array{0: QuestConversationThread, 1: User}
     */
    protected function resolveThreadContext(Request $request, Quest $quest, ?User $contact): array
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $readiness = app(FreelancerWorkspaceReadinessService::class);
        $freelancerParty = null;

        if ($this->userActsAsFreelancerAccount($user) && (int) $quest->client_id !== (int) $user->id) {
            if ($contact !== null && (int) $contact->id !== (int) $user->id) {
                abort(403);
            }
            if (! $readiness->freelancerMayUseQuestMessaging($user, $quest)) {
                abort(403);
            }
            $freelancerParty = $user;
        } elseif ((int) $quest->client_id === (int) $user->id) {
            if ($contact === null || ! $this->userActsAsFreelancerAccount($contact)) {
                abort(404);
            }
            $freelancerParty = $contact;
        } else {
            abort(403);
        }

        $thread = QuestConversationThread::query()->firstOrCreate(
            [
                'quest_id' => $quest->id,
                'freelancer_id' => $freelancerParty->id,
            ],
            [
                'client_id' => $quest->client_id,
            ],
        );

        if ($thread->isBlockedByAdmin()) {
            abort(403, __('This conversation thread is no longer available.'));
        }

        return [$thread, $user];
    }

    /**
     * @return array<string, mixed>
     */
    protected function counterpartyPayload(Quest $quest, User $viewer, User $freelancerParty): array
    {
        if ((int) $viewer->id === (int) $freelancerParty->id) {
            $c = $quest->client;

            return [
                'name' => $c?->name,
                'first_name' => $c?->first_name,
                'slug' => $c?->slug,
                'avatar_url' => $c?->avatar_url,
                'role' => 'client',
                'profile_url' => null,
            ];
        }

        return [
            'name' => $freelancerParty->name,
            'first_name' => $freelancerParty->first_name,
            'slug' => $freelancerParty->slug,
            'avatar_url' => $freelancerParty->avatar_url,
            'role' => 'freelancer',
            'profile_url' => $this->freelancerPublicProfileUrl($freelancerParty),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messagePayload(QuestConversationMessage $m, User $viewer): array
    {
        $u = $m->user;

        return [
            'id' => $m->id,
            'body' => $m->body,
            'created_at' => $m->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'sender' => $this->messageSenderPayload($u, $viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageBroadcastPayload(QuestConversationMessage $m): array
    {
        $u = $m->user;

        return [
            'id' => $m->id,
            'body' => $m->body,
            'created_at' => $m->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'sender' => $this->messageSenderPayload($u, null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageSenderPayload(?User $u, ?User $viewer): array
    {
        return [
            'id' => $u?->id,
            'name' => $u?->name,
            'first_name' => $u?->first_name,
            'slug' => $u?->slug,
            'avatar_url' => $u?->avatar_url,
            'profile_url' => $this->freelancerPublicProfileUrl($u),
            'is_me' => $viewer !== null && (int) $viewer->id === (int) ($u?->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messagingLimits(Quest $quest, User $freelancerParty): array
    {
        $postAward = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->where('freelancer_id', $freelancerParty->id)
            ->where('status', 'accepted')
            ->exists();

        $max = $postAward
            ? (int) config('quests.thread_message_body_max_after_accepted', 720)
            : (int) config('quests.thread_message_body_max_default', 2000);

        return [
            'body_max' => $max,
            'post_award' => $postAward,
            'post_award_hint' => $postAward
                ? __('This quest has an accepted proposal — keep messages brief and delivery-focused (max :n characters each).', ['n' => $max])
                : null,
        ];
    }

    protected function freelancerPublicProfileUrl(?User $user): ?string
    {
        if ($user === null || ! $this->userActsAsFreelancerAccount($user) || ! is_string($user->slug) || $user->slug === '') {
            return null;
        }

        return route('freelancers.public', $user->slug, absolute: false);
    }

    /**
     * Treat hustlers as freelancers even when role_id is not yet hydrated (e.g. first request after signup).
     */
    protected function userActsAsFreelancerAccount(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role?->slug === 'freelancer'
            || ($user->role?->slug === null && $user->account_type === 'hustler');
    }
}
