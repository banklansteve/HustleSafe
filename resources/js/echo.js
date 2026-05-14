import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { xsrfToken } from './utils/csrfHeader';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;

function reverbHost() {
    const fromEnv = import.meta.env.VITE_REVERB_HOST;

    if (fromEnv != null && String(fromEnv).trim() !== '') {
        return String(fromEnv).trim();
    }

    return window.location.hostname;
}

function reverbPort() {
    const raw = import.meta.env.VITE_REVERB_PORT;
    const n = Number(raw);

    return Number.isFinite(n) && n > 0 ? n : 8080;
}

if (!key) {
    window.Echo = null;
} else {
    const scheme = (import.meta.env.VITE_REVERB_SCHEME ?? 'http').toString().toLowerCase();
    const port = reverbPort();
    const xsrf = xsrfToken();

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        cluster: '',
        wsHost: reverbHost(),
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: scheme === 'https' ? ['ws', 'wss'] : ['ws'],
        disableStats: true,
        auth: {
            headers: {
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            },
        },
    });
}
