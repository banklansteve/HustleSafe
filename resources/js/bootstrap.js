import axios from 'axios';
import { xsrfToken } from './utils/csrfHeader';
import './echo';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.timeout = 180000;

window.axios.interceptors.request.use((config) => {
    const method = (config.method || 'get').toLowerCase();
    if (['post', 'put', 'patch', 'delete'].includes(method)) {
        const t = xsrfToken();
        if (t) {
            config.headers = config.headers ?? {};
            config.headers['X-XSRF-TOKEN'] = t;
        }
    }

    return config;
});
