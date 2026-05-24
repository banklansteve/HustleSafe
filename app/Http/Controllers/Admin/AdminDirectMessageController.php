<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDirectConversation;
use App\Models\AdminDirectMessage;
use App\Models\User;
use App\Services\Admin\AdminDirectMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Chat\GifSearchService;

class AdminDirectMessageController extends Controller
{
    public function __construct(
        private readonly AdminDirectMessageService $service,
        private readonly GifSearchService $gifSearch,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'staff' => $this->service->staffDirectory($user),
            'conversations' => $this->service->conversations($user),
            'unread_count' => $this->service->unreadCount($user),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->service->unreadCount($request->user())]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return response()->json([
            'conversations' => $this->service->conversations($request->user(), $data['q'] ?? null),
        ]);
    }

    public function open(Request $request, User $recipient): JsonResponse
    {
        $conversation = $this->service->findOrCreateConversation($request->user(), (int) $recipient->id);
        $messages = $this->service->messages($conversation, $request->user());

        return response()->json([
            'conversation' => $this->service->conversationPayload($conversation->load(['userOne.role', 'userTwo.role', 'lastMessage']), $request->user()),
            'messages' => $messages['items'],
            'has_more' => $messages['has_more'],
        ]);
    }

    public function messages(Request $request, AdminDirectConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'before_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('after_id', $data) && $data['after_id'] !== null) {
            return response()->json([
                'items' => $this->service->messagesSince($conversation, $request->user(), (int) $data['after_id']),
            ]);
        }

        return response()->json($this->service->messages(
            $conversation,
            $request->user(),
            $data['before_id'] ?? null,
        ));
    }

    public function send(Request $request, AdminDirectConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:8000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'gif_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $payload = $this->service->send(
            $conversation,
            $request->user(),
            $data,
            $request->file('attachments'),
        );

        return response()->json(['message' => $payload]);
    }

    public function delivered(Request $request, AdminDirectMessage $message): JsonResponse
    {
        $this->service->markDelivered($message, $request->user());

        return response()->json(['ok' => true]);
    }

    public function read(Request $request, AdminDirectConversation $conversation): JsonResponse
    {
        $data = $request->validate(['up_to_message_id' => ['nullable', 'integer']]);
        $this->service->markRead(
            $conversation,
            $request->user(),
            isset($data['up_to_message_id']) ? (int) $data['up_to_message_id'] : null,
        );

        return response()->json(['ok' => true]);
    }

    public function typing(Request $request, AdminDirectConversation $conversation): JsonResponse
    {
        $data = $request->validate(['typing' => ['required', 'boolean']]);
        $this->service->typing($conversation, $request->user(), $data['typing']);

        return response()->json(['ok' => true]);
    }

    public function gifSearch(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        return response()->json([
            'items' => $this->gifSearch->search($data['q'] ?? null),
            'configured' => $this->gifSearch->isConfigured(),
        ]);
    }
}
