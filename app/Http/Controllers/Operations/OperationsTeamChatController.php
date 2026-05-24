<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffTeamChatMessage;
use App\Models\StaffTeamChatRoom;
use App\Services\Operations\StaffTeamChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OperationsTeamChatController extends Controller
{
    public function __construct(private readonly StaffTeamChatService $service) {}

    public function index(Request $request): Response
    {
        $this->service->touchPresence($request->user());
        $room = $this->service->globalRoom();
        $isSuperAdmin = $request->user()->role?->slug === 'super_admin';
        $routeNamespace = $request->route()?->getName() && str_starts_with((string) $request->route()->getName(), 'admin.') ? 'admin' : 'operations';

        $chatBootstrap = null;
        try {
            $chatBootstrap = $this->service->bootstrapPayload($room, $request->user());
        } catch (Throwable $e) {
            Log::error('staff_team_chat.bootstrap_failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);
        }

        return Inertia::render($isSuperAdmin ? 'Admin/TeamChat/Index' : 'Operations/TeamChat/Index', [
            'room' => ['id' => $room->id, 'name' => $room->name, 'slug' => $room->slug],
            'routeNamespace' => $routeNamespace,
            'isSuperAdmin' => $isSuperAdmin,
            'chatBootstrap' => $chatBootstrap,
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $this->service->touchPresence($request->user());
        $room = $this->service->globalRoom();

        return response()->json($this->service->bootstrapPayload($room, $request->user()));
    }

    public function messages(Request $request, StaffTeamChatRoom $room): JsonResponse
    {
        $data = $request->validate([
            'before_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('after_id', $data) && $data['after_id'] !== null) {
            return response()->json([
                'items' => $this->service->messagesSince($room, $request->user(), (int) $data['after_id']),
            ]);
        }

        return response()->json($this->service->messages($room, $request->user(), $data['before_id'] ?? null));
    }

    public function send(Request $request, StaffTeamChatRoom $room): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:8000'],
            'is_official_guidance' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'gif_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $data['is_official_guidance'] = $request->boolean('is_official_guidance');

        $payload = $this->service->send(
            $room,
            $request->user(),
            $data,
            $request->file('attachments'),
        );

        return response()->json(['message' => $payload]);
    }

    public function react(Request $request, StaffTeamChatMessage $message): JsonResponse
    {
        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);
        $payload = $this->service->react($message, $request->user(), $data['emoji']);

        return response()->json(['message' => $payload]);
    }

    public function pin(Request $request, StaffTeamChatRoom $room, StaffTeamChatMessage $message): JsonResponse
    {
        $this->service->pin($room, $message, $request->user());

        return response()->json(['message' => 'Message pinned.']);
    }

    public function read(Request $request, StaffTeamChatRoom $room): JsonResponse
    {
        $data = $request->validate(['up_to_message_id' => ['nullable', 'integer']]);
        $this->service->markRead(
            $room,
            $request->user(),
            isset($data['up_to_message_id']) ? (int) $data['up_to_message_id'] : null,
        );

        return response()->json(['ok' => true]);
    }

    public function typing(Request $request, StaffTeamChatRoom $room): JsonResponse
    {
        $data = $request->validate(['typing' => ['required', 'boolean']]);
        $this->service->typing($room, $request->user(), $data['typing']);

        return response()->json(['ok' => true]);
    }

    public function search(Request $request, StaffTeamChatRoom $room): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);

        return response()->json(['results' => $this->service->search($room, $data['q'])]);
    }

    public function presence(Request $request): JsonResponse
    {
        $this->service->touchPresence($request->user());

        return response()->json(['presence' => $this->service->presence()]);
    }

    public function remove(Request $request, StaffTeamChatMessage $message): JsonResponse
    {
        $this->service->removeMessage($message, $request->user());

        return response()->json(['message' => 'Message removed.']);
    }
}
