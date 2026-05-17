<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminCsv;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSettingsController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'app' => [
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'timezone' => config('app.timezone'),
                'force_https' => (bool) config('app.force_https'),
                'url_scheme' => config('app.url_scheme'),
                'url' => config('app.url'),
            ],
            'mail' => [
                'default' => config('mail.default'),
            ],
        ]);
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
