<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ];

        if (Route::has('login')) {
            $urls[] = ['loc' => url('/login'), 'changefreq' => 'monthly', 'priority' => '0.6'];
        }

        if (Route::has('register')) {
            $urls[] = ['loc' => url('/register'), 'changefreq' => 'monthly', 'priority' => '0.6'];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
