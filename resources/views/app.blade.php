<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
            @php($broadcastClient = \App\Support\BroadcastClientConfig::forRequest())
            @if($broadcastClient['appKey'])
                <meta name="broadcast-driver" content="{{ $broadcastClient['broadcaster'] }}">
                <meta name="broadcast-app-key" content="{{ $broadcastClient['appKey'] }}">
                <meta name="broadcast-host" content="{{ $broadcastClient['host'] }}">
                <meta name="broadcast-port" content="{{ $broadcastClient['port'] }}">
                <meta name="broadcast-scheme" content="{{ $broadcastClient['scheme'] }}">
                <meta name="broadcast-cluster" content="{{ $broadcastClient['cluster'] }}">
                <meta name="broadcast-use-custom-host" content="{{ $broadcastClient['useCustomHost'] ? '1' : '0' }}">
                {{-- Legacy meta names for older cached JS --}}
                <meta name="reverb-app-key" content="{{ $broadcastClient['appKey'] }}">
                <meta name="reverb-host" content="{{ $broadcastClient['host'] }}">
                <meta name="reverb-port" content="{{ $broadcastClient['port'] }}">
                <meta name="reverb-scheme" content="{{ $broadcastClient['scheme'] }}">
            @endif
        @endauth

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="/images/logo/v7b_icon_512_light.png">
        <link rel="apple-touch-icon" href="/images/logo/v7b_icon_512_light.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
