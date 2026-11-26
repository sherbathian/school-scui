export function adminPath(path) {
    const prefix = typeof window !== 'undefined' && window.location.hostname === 'admin.taleemfinder.pk'
        ? ''
        : '/admin';

    return prefix + path;
}
