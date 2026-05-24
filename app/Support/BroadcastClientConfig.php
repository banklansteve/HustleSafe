<?php

namespace App\Support;

class BroadcastClientConfig
{
    /**
     * Laravel Echo client settings for the active broadcast driver (reverb or pusher).
     *
     * @return array{
     *     appKey: string|null,
     *     host: string,
     *     port: int,
     *     scheme: string,
     *     enabled: bool,
     *     driver: string,
     *     broadcaster: string,
     *     cluster: string,
     *     useCustomHost: bool,
     *     pollVisibleMs: int,
     *     pollHiddenMs: int
     * }
     */
    public static function forRequest(): array
    {
        $driver = (string) config('broadcasting.default', 'null');
        $connection = config("broadcasting.connections.{$driver}", []);
        $key = $connection['key'] ?? null;

        $pollVisibleMs = 500;
        $pollHiddenMs = 2500;

        $enabled = ! in_array($driver, ['null', '', 'log'], true)
            && is_string($key)
            && $key !== '';

        if ($driver === 'pusher') {
            return self::pusherClient($connection, $key, $enabled, $pollVisibleMs, $pollHiddenMs);
        }

        return self::reverbClient($key, $enabled, $pollVisibleMs, $pollHiddenMs);
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, mixed>
     */
    private static function pusherClient(
        array $connection,
        ?string $key,
        bool $enabled,
        int $pollVisibleMs,
        int $pollHiddenMs,
    ): array {
        $cluster = is_string($connection['options']['cluster'] ?? null)
            ? $connection['options']['cluster']
            : (is_string(env('PUSHER_APP_CLUSTER')) ? env('PUSHER_APP_CLUSTER') : 'mt1');

        $customHost = trim((string) env('PUSHER_HOST', ''));
        $useCustomHost = $customHost !== '';
        $scheme = strtolower((string) env('PUSHER_SCHEME', 'https'));
        $port = (int) env('PUSHER_PORT', $scheme === 'https' ? 443 : 6001);

        $host = $useCustomHost
            ? $customHost
            : (request()->getHost() ?: 'localhost');

        return [
            'appKey' => is_string($key) && $key !== '' ? $key : null,
            'host' => $host,
            'port' => $port > 0 ? $port : 443,
            'scheme' => in_array($scheme, ['http', 'https'], true) ? $scheme : 'https',
            'enabled' => $enabled,
            'driver' => 'pusher',
            'broadcaster' => 'pusher',
            'cluster' => $cluster,
            'useCustomHost' => $useCustomHost,
            'pollVisibleMs' => $pollVisibleMs,
            'pollHiddenMs' => $pollHiddenMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reverbClient(
        ?string $key,
        bool $enabled,
        int $pollVisibleMs,
        int $pollHiddenMs,
    ): array {
        $request = request();
        $envHost = trim((string) env('REVERB_HOST', ''), " \t\n\r\0\x0B\"'");

        // Always match the browser address bar (127.0.0.1 vs localhost must not mix).
        $host = $request->getHost() !== ''
            ? $request->getHost()
            : ($envHost !== '' ? $envHost : '127.0.0.1');

        $port = (int) env('REVERB_PORT', 8080);
        $scheme = $request->getScheme() !== ''
            ? strtolower($request->getScheme())
            : strtolower((string) env('REVERB_SCHEME', 'http'));

        return [
            'appKey' => is_string($key) && $key !== '' ? $key : null,
            'host' => $host,
            'port' => $port > 0 ? $port : 8080,
            'scheme' => in_array($scheme, ['http', 'https'], true) ? $scheme : 'http',
            'enabled' => $enabled,
            'driver' => 'reverb',
            'broadcaster' => 'reverb',
            'cluster' => '',
            'useCustomHost' => true,
            'pollVisibleMs' => $pollVisibleMs,
            'pollHiddenMs' => $pollHiddenMs,
        ];
    }
}
