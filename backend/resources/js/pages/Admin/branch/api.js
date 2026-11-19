export function apiRequest(path, options = {}) {
    const method = options.method ?? 'GET';
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())) {
        headers.set('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
    }

    return fetch(path, { ...options, credentials: 'same-origin', headers });
}
