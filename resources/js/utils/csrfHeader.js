/**
 * Read Laravel XSRF-TOKEN cookie for axios/fetch POST from the SPA.
 */
export function xsrfToken() {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}
