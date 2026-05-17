<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminEngagementPolicyController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/EngagementPolicy', [
            'policy' => config('quest_engagement'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $policy = config('quest_engagement');

        return response()->streamDownload(function () use ($policy): void {
            echo "# Engagement & auto-complete (support readout)\n\n";
            echo 'Generated: '.now()->toIso8601String()."\n\n";
            echo "```json\n";
            echo json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            echo "\n```\n";
        }, 'engagement-policy-'.now()->format('Y-m-d-His').'.md', [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
