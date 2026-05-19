<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSettingsRegistry;
use App\Support\AdminCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSettingsController extends Controller
{
    public function show(AdminSettingsRegistry $settings): Response
    {
        return Inertia::render('Admin/Settings/Index', $settings->payload());
    }

    public function update(Request $request, string $section, AdminSettingsRegistry $settings): RedirectResponse
    {
        $settings->updateSection($section, $request->input('settings', []), $request);

        return back()->with('success', 'Settings saved.');
    }

    public function export(): StreamedResponse
    {
        $rows = [
            ['app.env', (string) config('app.env')],
            ['app.debug', config('app.debug') ? 'true' : 'false'],
            ['app.timezone', (string) config('app.timezone')],
            ['app.force_https', config('app.force_https') ? 'true' : 'false'],
            ['app.url_scheme', config('app.url_scheme') ?? ''],
            ['app.url', (string) config('app.url')],
            ['mail.default', (string) config('mail.default')],
        ];

        return AdminCsv::download('settings-snapshot-'.now()->format('Y-m-d-His').'.csv', ['key', 'value'], function ($out) use ($rows): void {
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
        });
    }
}
