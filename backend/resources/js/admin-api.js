export async function api(path, method = 'GET', data, signal) {
    const isMultipart = data instanceof FormData;
    const response = await fetch('/api/' + path, {
        method, signal,
        headers: { Accept: 'application/json', ...(!isMultipart && { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: data === undefined ? undefined : isMultipart ? data : JSON.stringify(data),
    });
    if (response.status === 204) return;
    const payload = await response.json();
    if (!response.ok) {
        throw new Error(Object.values(payload.errors ?? {}).flat().join(' ') || payload.message || 'Request failed.');
    }
    return payload;
}
