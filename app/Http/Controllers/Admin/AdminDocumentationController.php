<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDocumentationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDocumentationController extends Controller
{
    public function __invoke(Request $request, AdminDocumentationService $documentation, ?string $topic = null): Response
    {
        $topics = $documentation->topics();
        $activeTopic = collect($topics)->firstWhere('slug', $topic ?: (string) $request->query('topic'));

        return Inertia::render('Admin/Documentation/Guide', [
            'topics' => $topics,
            'activeTopic' => $activeTopic['slug'] ?? 'overview',
            'searchIndex' => $documentation->searchIndex(),
        ]);
    }
}
