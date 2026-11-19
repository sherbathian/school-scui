import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';
import { Input } from '../../components/ui/input.jsx';

const areaTypes = { locality: 'Locality', sector: 'Sector', housing_scheme: 'Housing scheme' };

export default function Locations({ kind, adminName }) {
    return <LocationPage key={kind} {...{ kind, adminName }} />;
}

function LocationPage({ kind, adminName }) {
    const isCity = kind === 'cities';
    const title = isCity ? 'Cities' : 'Areas';
    const noun = isCity ? 'city' : 'area';
    const [rows, setRows] = useState(null);
    const [cities, setCities] = useState([]);
    const [editor, setEditor] = useState(null);
    const [cityFilter, setCityFilter] = useState('');
    const [busy, setBusy] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const listPath = isCity ? 'admin/cities?all=1' : 'admin/areas';

    useEffect(() => {
        const controller = new AbortController();
        const get = path => api(path, 'GET', undefined, controller.signal);
        Promise.all([get(listPath), isCity ? null : get('admin/cities')])
            .then(([result, cityResult]) => {
                setRows(result.data);
                setCities(cityResult?.data ?? []);
            })
            .catch(error => {
                if (!controller.signal.aborted) setFeedback({ error: true, message: error.message });
            });
        return () => controller.abort();
    }, [isCity, listPath]);

    async function run(action) {
        setBusy(true);
        setFeedback(null);
        try {
            const message = await action();
            if (message) setFeedback({ message });
        } catch (error) {
            setFeedback({ error: true, message: error.message });
        } finally {
            setBusy(false);
        }
    }

    async function refresh() {
        setRows((await api(listPath)).data);
    }

    function open(record) {
        setFeedback(null);
        setEditor(record);
    }

    function save(event) {
        event.preventDefault();
        const { city_id, ...details } = Object.fromEntries(new FormData(event.currentTarget));
        const creating = !editor.id;
        const path = !creating ? `admin/${kind}/${editor.id}` : isCity ? 'admin/cities' : `admin/cities/${city_id}/areas`;
        run(async () => {
            await api(path, creating ? 'POST' : 'PATCH', details);
            setEditor(null);
            await refresh();
            return creating ? 'Location added.' : 'Location updated.';
        });
    }

    function remove(record) {
        if (!window.confirm(`Delete ${record.name}?`)) return;
        run(async () => {
            await api(`admin/${kind}/${record.id}`, 'DELETE');
            if (editor?.id === record.id) setEditor(null);
            await refresh();
            return 'Location deleted.';
        });
    }

    const filtered = rows?.filter(row => !cityFilter || String(row.city_id) === cityFilter);

    return <AdminLayout screen={kind} {...{ title, adminName, busy }} signOut={() => run(async () => {
        await api('logout', 'POST');
        window.location.assign('/admin/login');
    })}>
        <Head title={title + ' · Schoolhouse'} />
        <div className="page-heading"><h1>{title}</h1>
            <Button onClick={() => open({})} disabled={busy || (!isCity && !cities.length)}>+ New {noun}</Button>
        </div>
        <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.message}</p>
        {!isCity && rows && !cities.length && <p className="mb-4">Add a city before creating an area.</p>}
        {editor && <section className="listing-form" aria-labelledby="location-form-title">
            <h2 id="location-form-title" className="mb-4">{editor.id ? 'Edit' : 'Create'} {noun}</h2>
            <form key={editor.id ?? 'new'} onSubmit={save}>
                <div className="fields">
                    <label>Name<Input name="name" required maxLength={isCity ? 100 : 120} defaultValue={editor.name} autoFocus /></label>
                    {isCity ? <label>Province or territory<Input name="province" maxLength={100} defaultValue={editor.province} /></label> : <>
                        {editor.id ? <div><strong>City</strong><p>{editor.city_name}</p></div> :
                            <label>City<select name="city_id" required defaultValue={cityFilter}>
                                <option value="">Select a city</option>
                                {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
                            </select></label>}
                        <label>Area type<select name="area_type" defaultValue={editor.area_type ?? 'locality'}>
                            {Object.entries(areaTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select></label>
                    </>}
                </div>
                <div className="flex gap-2"><Button type="submit" disabled={busy}>Save {noun}</Button>
                    <Button variant="outline" disabled={busy} onClick={() => setEditor(null)}>Cancel</Button></div>
            </form>
        </section>}
        <section aria-label={title}>
            {!isCity && <label className="mb-4 max-w-xs">Filter by city<select value={cityFilter} onChange={event => setCityFilter(event.target.value)}>
                <option value="">All cities</option>
                {Array.from(new Map(rows?.map(row => [row.city_id, row.city_name])).entries()).map(([id, name]) =>
                    <option key={id} value={id}>{name}</option>)}
            </select></label>}
            <div className="table-scroll"><table>
                <thead><tr><th>Name</th><th>{isCity ? 'Province or territory' : 'City'}</th>{!isCity && <th>Type</th>}<th>Actions</th></tr></thead>
                <tbody>
                    {filtered?.map(record => <tr key={record.id}>
                        <td>{record.name}</td><td>{isCity ? record.province || '—' : record.city_name}</td>
                        {!isCity && <td>{areaTypes[record.area_type]}</td>}
                        <td><div className="row-actions">
                            <Button variant="outline" onClick={() => open(record)} disabled={busy}>Edit</Button>
                            <Button variant="outline" onClick={() => remove(record)} disabled={busy}>Delete</Button>
                        </div></td>
                    </tr>)}
                    {!filtered?.length && <tr><td colSpan={isCity ? 3 : 4}>{rows ? 'No locations found.' : 'Loading locations…'}</td></tr>}
                </tbody>
            </table></div>
        </section>
    </AdminLayout>;
}
