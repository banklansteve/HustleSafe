import axios from 'axios';
import { xsrfToken } from './csrfHeader';

function createSupportClient(timeoutMs) {
    const client = axios.create({
        timeout: timeoutMs,
        withCredentials: true,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    });

    client.interceptors.request.use((config) => {
        const method = (config.method || 'get').toLowerCase();
        if (['post', 'put', 'patch', 'delete'].includes(method)) {
            const token = xsrfToken();
            if (token) {
                config.headers = config.headers ?? {};
                config.headers['X-XSRF-TOKEN'] = token;
            }
        }

        return config;
    });

    return client;
}

/** Send messages — must not share a queue with poll/read. */
export const supportChatSendHttp = createSupportClient(45_000);

/** Poll for new messages. */
export const supportChatPollHttp = createSupportClient(12_000);

/** Mark-read — short timeout, failures are ignored. */
export const supportChatReadHttp = createSupportClient(5_000);

/** Open/bootstrap/history — moderate timeout. */
export const supportChatHttp = createSupportClient(25_000);
