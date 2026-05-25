<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\SendCustomerSupportMessageRequest;
use App\Http\Requests\Support\StartCustomerSupportChatRequest;
use App\Http\Requests\Support\SubmitSupportFeedbackRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Chat\GifSearchService;
use App\Services\Support\CustomerSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class CustomerSupportChatController extends Controller
{
    public function __construct(
        private readonly CustomerSupportService $service,
        private readonly GifSearchService $gifSearch,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        if (! $this->service->tablesReady()) {
            return response()->json(['enabled' => false]);
        }

        $user = $request->user();
        if ($user === null || in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return response()->json(['enabled' => false]);
        }

        return response()->json($this->service->widgetBootstrap($user));
    }

    public function startJson(StartCustomerSupportChatRequest $request): JsonResponse
    {
        $ticket = $this->service->startChat($request->user(), $request->validated());
        $opened = $this->service->openTicketForUser($ticket, $request->user());

        return response()->json($opened);
    }

    public function openJson(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $opened = $this->service->openTicketForUser($ticket, $request->user());
        $lastId = $this->latestMessageIdFromList($opened['messages'] ?? []);
        $this->service->markRead($ticket, $request->user(), $lastId);

        return response()->json($opened);
    }

    public function rateJson(SubmitSupportRatingRequest $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);
        abort_unless($ticket->chat_status === 'closed', 422, 'Conversation is still open.');

        if ($ticket->rated_at !== null) {
            return response()->json([
                'ticket' => $this->service->ticketListPayload($ticket, $request->user()),
                'message' => 'Already rated',
            ]);
        }

        $updated = $this->service->recordRating(
            $ticket,
            (int) $request->validated('stars'),
            $request->validated('comment'),
        );

        return response()->json([
            'ticket' => $this->service->ticketListPayload($updated, $request->user()),
            'message' => 'Thank you for your feedback!',
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $active = SupportTicket::query()
            ->where('user_id', $user->id)
            ->whereNull('opened_by_admin_id')
            ->whereIn('chat_status', ['queued', 'active'])
            ->latest('updated_at')
            ->first();

        return Inertia::render('Support/Chat', [
            'categories' => $this->service->categories(),
            'chats' => $this->service->userChats($user),
            'activeTicket' => $active ? $this->service->ticketListPayload($active, $user) : null,
        ]);
    }

    public function start(StartCustomerSupportChatRequest $request): RedirectResponse
    {
        $ticket = $this->service->startChat($request->user(), $request->validated());

        return redirect()->route('support.chat.show', $ticket);
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $messages = $this->service->messages($ticket, $request->user());
        $lastId = $this->latestMessageIdFromList($messages['items'] ?? []);
        $this->service->markRead($ticket, $request->user(), $lastId);

        return Inertia::render('Support/ChatShow', [
            'ticket' => $this->service->ticketListPayload($ticket, $request->user()),
            'messages' => $messages['items'],
            'hasMore' => $messages['has_more'],
            'categories' => $this->service->categories(),
        ]);
    }

    public function messages(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate([
            'before_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer'],
        ]);

        if ($request->has('after_id')) {
            return response()->json([
                'items' => $this->service->messagesSince($ticket, $request->user(), $request->integer('after_id')),
            ]);
        }

        return response()->json($this->service->messages($ticket, $request->user(), $data['before_id'] ?? null));
    }

    public function send(SendCustomerSupportMessageRequest $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $payload = $this->service->sendMessage(
            $ticket,
            $request->user(),
            $request->validated(),
            $request->file('attachments'),
        );

        return response()->json(['message' => $payload]);
    }

    public function typing(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate(['typing' => ['required', 'boolean']]);
        $this->service->broadcastTyping($ticket, $request->user(), (bool) $data['typing']);

        return response()->json(['ok' => true]);
    }

    public function typingState(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        return response()->json([
            'typing' => $this->service->typingStateForTicket($ticket, $request->user()),
        ]);
    }

    public function read(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate([
            'last_message_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->markRead($ticket, $request->user(), (int) $data['last_message_id']);

        return response()->json(['ok' => true]);
    }

    public function gifSearch(Request $request): JsonResponse
    {
        return response()->json($this->gifSearch->search($request->query('q')));
    }

    public function react(Request $request, SupportTicket $ticket, SupportTicketMessage $message): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate(['emoji' => ['required', 'string', 'max:8']]);

        return response()->json([
            'message' => $this->service->reactToMessage($ticket, $message, $request->user(), $data['emoji']),
        ]);
    }

    public function feedbackJson(SubmitSupportFeedbackRequest $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);
        abort_unless($ticket->chat_status === 'closed', 422, 'Conversation is still open.');

        if ($ticket->rated_at !== null) {
            return response()->json([
                'ticket' => $this->service->ticketListPayload($ticket, $request->user()),
                'already_rated' => true,
            ]);
        }

        $updated = $this->service->recordFeedback($ticket, $request->validatedFeedback());

        return response()->json([
            'ticket' => $this->service->ticketListPayload($updated, $request->user()),
            'already_rated' => false,
            'message' => 'Thank you for your feedback!',
        ]);
    }

    public function rateShow(Request $request, SupportTicket $ticket): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $reaction = $request->query('reaction');
        if (is_string($reaction) && $reaction !== '' && ! collect($this->service->closureReactions())->contains(fn (array $r) => ($r['key'] ?? '') === $reaction)) {
            $reaction = null;
        }

        return Inertia::render('Support/Feedback', [
            'ticket' => [
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'already_rated' => $ticket->rated_at !== null,
                'score' => $ticket->rating_score,
                'reaction' => $ticket->rating_reaction,
            ],
            'submitUrl' => URL::signedRoute('support.rate.submit', ['ticket' => $ticket->uuid]),
            'surveySteps' => $this->service->feedbackSurveySteps(),
            'reactions' => $this->service->closureReactions(),
            'preselectedReaction' => $reaction,
        ]);
    }

    public function rateSubmit(SubmitSupportFeedbackRequest $request, SupportTicket $ticket): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        if ($ticket->rated_at !== null) {
            return back()->with('info', 'You have already submitted feedback for this session.');
        }

        $this->service->recordFeedback($ticket, $request->validatedFeedback());

        return back()->with('success', 'Thank you for your feedback!');
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function latestMessageIdFromList(array $messages): ?int
    {
        if ($messages === []) {
            return null;
        }

        $last = $messages[array_key_last($messages)];

        return isset($last['id']) ? (int) $last['id'] : null;
    }
}
