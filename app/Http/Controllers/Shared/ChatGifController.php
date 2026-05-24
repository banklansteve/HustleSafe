<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\Chat\GifSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatGifController extends Controller
{
    public function __construct(private readonly GifSearchService $gifSearch) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $items = $this->gifSearch->search($data['q'] ?? null);

        return response()->json([
            'items' => $items,
            'configured' => $this->gifSearch->isConfigured(),
        ]);
    }
}
