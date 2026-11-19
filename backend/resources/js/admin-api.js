export async function api(path, method = 'GET', data, signal) {
    const response = await fetch('/api/' + path, {
        method, signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    if (response.status === 204) return;
    const payload = await response.json();
    if (!response.ok) {
        throw new Error(Object.values(payload.errors ?? {}).flat().join(' ') || payload.message || 'Request failed.');
    }
    return payload;
}
