import { api } from '../../admin-api.js';

export function csvFields(keys, required = []) {
    return keys.map(key => ({ key, label: key, required: required.includes(key) }));
}

export function csvBoolean(value, fallback = true) {
    if (value === '') return fallback;
    const normalized = value.toLowerCase();
    if (['true', '1', 'yes'].includes(normalized)) return true;
    if (['false', '0', 'no'].includes(normalized)) return false;
    throw new Error('Use true or false for checkbox fields.');
}

export async function loadCsvPages(path, filters = {}) {
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));
    const get = page => {
        query.set('page', page);
        return api(`${path}?${query}`);
    };
    const first = await get(1);
    const rows = [...first.data];
    const last = first.meta?.last_page ?? first.last_page ?? 1;
    for (let page = 2; page <= last; page++) rows.push(...(await get(page)).data);
    return rows;
}
