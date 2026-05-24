import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { xsrfToken } from './csrfHeader';

const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '::1']);

function readMeta(name) {
    const el = document.querySelector(`meta[name="${name}"]`);

    return el?.getAttribute('content')?.trim() || '';
}

/**
 * WebSocket host must match how you open the site in the browser.
 * REVERB_HOST=localhost breaks when you use hustlesafe.test (or similar).
 */
function resolveWsHost(configuredHost) {
    const pageHost = window.location.hostname;

    if (!configuredHost) {
        return pageHost;
    }

    if (LOCAL_HOSTS.has(configuredHost) && !LOCAL_HOSTS.has(pageHost)) {
        return pageHost;
    }

    return configuredHost;
}

function buildConfig(inertiaConfig = null) {
    if (inertiaConfig?.appKey) {
        return {
            appKey: inertiaConfig.appKey,
            host: inertiaConfig.host || window.location.hostname,
            port: Number(inertiaConfig.port) || 8080,
            scheme: (inertiaConfig.scheme || 'http').toLowerCase(),
            broadcaster: inertiaConfig.broadcaster || inertiaConfig.driver || 'reverb',
            cluster: inertiaConfig.cluster || 'mt1',
            useCustomHost: inertiaConfig.useCustomHost === true,
        };
    }

    const driver = readMeta('broadcast-driver') || 'reverb';
    const metaKey = readMeta('broadcast-app-key') || readMeta('reverb-app-key');
    if (metaKey) {
        return {
            appKey: metaKey,
            host: readMeta('broadcast-host') || readMeta('reverb-host') || window.location.hostname,
            port: Number(readMeta('broadcast-port') || readMeta('reverb-port')) || 8080,
            scheme: (readMeta('broadcast-scheme') || readMeta('reverb-scheme') || 'http').toLowerCase(),
            broadcaster: driver,
            cluster: readMeta('broadcast-cluster') || 'mt1',
            useCustomHost: readMeta('broadcast-use-custom-host') === '1',
        };
    }

    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    if (import.meta.env.VITE_BROADCAST_DRIVER === 'pusher' && pusherKey) {
        return {
            appKey: pusherKey,
            host: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
            port: Number(import.meta.env.VITE_PUSHER_PORT) || 443,
            scheme: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https').toString().toLowerCase(),
            broadcaster: 'pusher',
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            useCustomHost: !!import.meta.env.VITE_PUSHER_HOST,
        };
    }

    const envKey = import.meta.env.VITE_REVERB_APP_KEY;
    if (!envKey) {
        return null;
    }

    const port = Number(import.meta.env.VITE_REVERB_PORT);

    return {
        appKey: envKey,
        host: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        port: Number.isFinite(port) && port > 0 ? port : 8080,
        scheme: (import.meta.env.VITE_REVERB_SCHEME ?? 'http').toString().toLowerCase(),
        broadcaster: 'reverb',
        cluster: 'mt1',
        useCustomHost: true,
    };
}

function createEcho(config) {
    const xsrf = xsrfToken();
    const auth = {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
        },
    };

    window.Pusher = Pusher;

    if (config.broadcaster === 'pusher') {
        const cluster = config.cluster || 'mt1';

        if (config.useCustomHost) {
            const host = resolveWsHost(config.host);
            const scheme = config.scheme === 'https' ? 'https' : 'http';
            const port = config.port;

            return new Echo({
                broadcaster: 'pusher',
                key: config.appKey,
                cluster,
                wsHost: host,
                wsPort: port,
                wssPort: port,
                forceTLS: scheme === 'https',
                enabledTransports: scheme === 'https' ? ['ws', 'wss'] : ['ws', 'wss'],
                disableStats: true,
                authEndpoint: '/broadcasting/auth',
                auth,
            });
        }

        return new Echo({
            broadcaster: 'pusher',
            key: config.appKey,
            cluster,
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            authEndpoint: '/broadcasting/auth',
            auth,
        });
    }

    const host = resolveWsHost(config.host);
    const scheme = config.scheme === 'https' ? 'https' : 'http';
    const port = config.port;

    return new Echo({
        broadcaster: 'reverb',
        key: config.appKey,
        cluster: '',
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: scheme === 'https' ? ['ws', 'wss'] : ['ws'],
        disableStats: true,
        authEndpoint: '/broadcasting/auth',
        auth,
    });
}

function echoMatchesConfig(existingEcho, config) {
    const pusher = existingEcho?.connector?.pusher;
    if (!pusher) {
        return false;
    }

    const cfg = pusher.config ?? {};
    if (cfg.key !== config.appKey) {
        return false;
    }

    if (config.broadcaster === 'pusher' && !config.useCustomHost) {
        return (cfg.cluster || 'mt1') === (config.cluster || 'mt1');
    }

    const host = resolveWsHost(config.host);

    return cfg.wsHost === host && Number(cfg.wsPort) === Number(config.port);
}

/**
 * @param {{ appKey?: string|null, host?: string, port?: number, scheme?: string, broadcaster?: string, driver?: string, cluster?: string, useCustomHost?: boolean }|null|undefined} inertiaConfig
 */
export function ensureEcho(inertiaConfig = null) {
    const config = buildConfig(inertiaConfig);
    if (!config?.appKey) {
        return null;
    }

    // Browser hostname wins for Reverb (avoids localhost vs 127.0.0.1 mismatch).
    const browserHost = typeof window !== 'undefined' ? window.location.hostname : '';
    const host = config.broadcaster === 'reverb' && browserHost
        ? browserHost
        : resolveWsHost(config.host);
    const normalized = { ...config, host };

    if (window.Echo && echoMatchesConfig(window.Echo, normalized)) {
        return window.Echo;
    }

    if (window.Echo?.disconnect) {
        window.Echo.disconnect();
    }

    window.Echo = createEcho(normalized);

    return window.Echo;
}
