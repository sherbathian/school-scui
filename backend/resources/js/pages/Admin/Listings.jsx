import { api } from '../../admin-api.js';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';
import { Input } from '../../components/ui/input.jsx';

const types = { school: 'School', college: 'College', university: 'University', training_institute: 'Training institute' };
const statuses = { draft: 'Draft', pending_review: 'Pending review', published: 'Published', suspended: 'Suspended' };
const profile = {
    short_name: ['Short name', 80], branch_name: ['Branch name', 100], tagline: ['Tagline', 160],
    address_line: ['Street address', 255], phone: ['Public phone', 30, 'tel'],
    email: ['Public email', 190, 'email'], website_url: ['Website', 255, 'url'],
};

function Field({ label, children, ...props }) {
    return <label>{label}{children ?? <Input {...props} />}</label>;
}

function Choices({ values }) {
    return Object.entries(values).map(([value, label]) => <option key={value} value={value}>{label}</option>);
}

function PlacePicker({ cities, value = '', area = '', onChange, names = false }) {
    const [areas, setAreas] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!value) return;
        const controller = new AbortController();
        api(`admin/cities/${value}/areas`, 'GET', undefined, controller.signal)
            .then(result => setAreas(result.data))
            .catch(error => { if (!controller.signal.aborted) setError(error.message); });
        return () => controller.abort();
    }, [value, cities]);
    return <>
        <Field label="City"><select name={names ? 'city_id' : undefined} value={value} onChange={event => {
            setAreas([]); setError(''); onChange(event.target.value, '');
        }}><option value="">{names ? 'No city selected' : 'All cities'}</option>
            {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
        </select></Field>
        <Field label="Area"><select name={names ? 'area_id' : undefined} value={area} disabled={!value}
            onChange={event => onChange(value, event.target.value)}>
            <option value="">{names ? 'No area selected' : 'All areas'}</option>
            {areas.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select></Field>
        {error && <p role="alert">{error}</p>}
    </>;
}

function ListingEditor({ listing, submit, busy, onError }) {
    const [cities, setCities] = useState([]);
    useEffect(() => {
        const controller = new AbortController();
        api('admin/cities', 'GET', undefined, controller.signal).then(result => setCities(result.data))
            .catch(error => { if (!controller.signal.aborted) onError(error); });
        return () => controller.abort();
    }, [onError]);
    const [city, setCity] = useState(listing.city_id ?? '');
    const [area, setArea] = useState(listing.area_id ?? '');
    return <section className="listing-form" aria-labelledby="editor-title">
        <h2 id="editor-title">Listing information</h2>
        <form onSubmit={event => submit(event, listing.id, { city_id: city, area_id: area })}>
            <div className="fields">
                <Field label="Institute name" name="name" maxLength={255} required autoFocus defaultValue={listing.name} />
                <Field label="Institute type"><select name="institute_type" defaultValue={listing.institute_type ?? 'school'}><Choices values={types} /></select></Field>
                <PlacePicker cities={cities} value={city} area={area} names onChange={(nextCity, nextArea) => { setCity(nextCity); setArea(nextArea); }} />
                {Object.entries(profile).map(([name, [label, maxLength, type]]) => <Field key={name} {...{ label, name, maxLength, type }} defaultValue={listing[name]} />)}
                <Field label="Description"><textarea name="description" maxLength={10000} defaultValue={listing.description} /></Field>
            </div>
            <div className="drawer-footer">
                <Link className="admin-link-button" href="/admin/listings">Cancel</Link>
                <Button type="submit" disabled={busy}>{listing.id ? 'Save changes' : 'Create draft'}</Button>
            </div>
        </form>
    </section>;
}

function Workspace({ run, busy, onError }) {
    const [filters, setFilters] = useState({});
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [revision, refresh] = useState(0);
    const [results, setResults] = useState(null);
    const [cities, setCities] = useState([]);

    useEffect(() => {
        const controller = new AbortController();
        api('admin/cities', 'GET', undefined, controller.signal).then(result => setCities(result.data))
            .catch(error => { if (!controller.signal.aborted) onError(error); });
        return () => controller.abort();
    }, [revision, onError]);

    useEffect(() => {
        const controller = new AbortController();
        const query = new URLSearchParams({ page });
        for (const [key, value] of Object.entries(filters)) if (value) query.set(key, value);
        api('admin/listings?' + query, 'GET', undefined, controller.signal).then(result => {
            if (page > result.meta.last_page) setPage(result.meta.last_page);
            else setResults(result);
        }).catch(error => { if (!controller.signal.aborted) onError(error); });
        return () => controller.abort();
    }, [filters, page, revision, onError]);

    function filter(changes) {
        setPage(1);
        setFilters(current => ({ ...current, ...changes }));
    }

    function moderate(id, operation, message) {
        run(async () => {
            await api(`admin/listings/${id}/${operation}`, 'POST');
            refresh(value => value + 1);
            return message;
        });
    }

    return <>
        <div className="page-heading"><div><h1>Directory listings</h1><p>Review and manage institute profiles on Taleemfinder.</p></div>
            <Link className="admin-link-button primary" href="/admin/listings/create">+ New listing</Link></div>
        <div className="metrics" aria-label="Listing totals">
            {Object.entries({ published: 'Published', needs_verification: 'Awaiting verification', suspended: 'Suspended' }).map(([key, label]) =>
                <button key={key} type="button" className="metric-button" data-kind={key}
                    aria-pressed={key === 'needs_verification' ? Boolean(filters.needs_verification) : filters.listing_status === key}
                    onClick={event => {
                        const active = event.currentTarget.getAttribute('aria-pressed') === 'true';
                        filter({
                            listing_status: !active && key !== 'needs_verification' ? key : '',
                            needs_verification: !active && key === 'needs_verification' ? '1' : '',
                        });
                    }}>
                    <span>{label}</span><strong>{results?.summary[key].toLocaleString() ?? '…'}</strong>
                </button>)}
        </div>
        <section id="listings" aria-labelledby="table-heading">
            <div className="section-heading"><div><h2 id="table-heading">All listings</h2>
                {results && <p className="table-count">{results.meta.total.toLocaleString()} matching listings</p>}</div>
                <label className="review-toggle"><input type="checkbox" checked={Boolean(filters.needs_verification)} onChange={event =>
                    filter({ needs_verification: event.target.checked ? '1' : '', listing_status: '' })} /> Awaiting verification only</label></div>
            <form className="listing-search" onSubmit={event => {
                event.preventDefault();
                filter({ search: search.trim() });
            }}>
                <Field label="Find a listing" type="search" value={search} maxLength={100}
                    placeholder="Search by institute name or slug" onChange={event => setSearch(event.target.value)} />
                <Button type="submit" variant="outline">Search</Button>
                <Button variant="outline" onClick={() => { setSearch(''); setPage(1); setFilters({}); }}>Clear filters</Button>
            </form>
            <div className="fields">
                <Field label="Listing status"><select value={filters.listing_status ?? ''} disabled={Boolean(filters.needs_verification)}
                    onChange={event => filter({ listing_status: event.target.value })}><option value="">All listings</option><Choices values={statuses} /></select></Field>
                <Field label="Institute type"><select value={filters.institute_type ?? ''} onChange={event => filter({ institute_type: event.target.value })}>
                    <option value="">All types</option><Choices values={types} /></select></Field>
                <PlacePicker cities={cities} value={filters.city_id} area={filters.area_id}
                    onChange={(city_id, area_id) => filter({ city_id, area_id })} />
            </div>
            <div className="table-scroll"><table><thead><tr><th>Institute</th><th>Status</th><th>Verification</th><th>Actions</th></tr></thead><tbody>
                {results?.data.map(item => <tr key={item.id}><td><span>{item.name}</span><small className="listing-context">
                    {[types[item.institute_type], cities.find(city => String(city.id) === String(item.city_id))?.name].filter(Boolean).join(' · ')}
                </small></td>
                    <td><span className="status-pill" data-status={item.listing_status}>{statuses[item.listing_status]}</span></td>
                    <td>{item.verified_at ? 'Verified' : 'Unverified'}</td>
                    <td><div className="row-actions">
                        <Link className="admin-link-button" href={`/admin/listings/${item.id}/edit`}>Edit</Link>
                        <Button variant="outline" disabled={busy || Boolean(item.verified_at)} onClick={() => moderate(item.id, 'verify', 'Listing verified.')}>Verify</Button>
                        <Button variant="outline" disabled={busy || !item.verified_at || item.status !== 'active' || item.listing_status === 'published'}
                            onClick={() => moderate(item.id, 'publish', 'Listing published.')}>Publish</Button>
                        <Button variant="outline" disabled={busy || item.listing_status === 'suspended'} onClick={() => {
                            if (window.confirm(`Suspend ${item.name}? Its listing will be hidden from the public directory.`)) {
                                moderate(item.id, 'suspend', 'Listing suspended.');
                            }
                        }}>Suspend</Button>
                    </div></td></tr>)}
                {!results?.data.length && <tr><td colSpan={4}>{results ? 'No listings match this filter.' : 'Loading listings…'}</td></tr>}
            </tbody></table></div>
            <nav className="pagination" aria-label="Listing pages">
                <Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
                {results && <span>Page {page} of {results.meta.last_page}</span>}
                <Button variant="outline" disabled={!results || page >= results.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
            </nav>
        </section>
    </>;
}

export default function Listings({ administrator, adminName, screen = 'index', listing }) {
    const [busy, setBusy] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const [onError] = useState(() => error => setFeedback({ message: error.message, error: true }));

    async function run(task) {
        setBusy(true);
        setFeedback(null);
        try {
            const message = await task();
            if (message) setFeedback({ message, error: false });
        } catch (error) {
            onError(error);
        } finally {
            setBusy(false);
        }
    }

    function signIn(event) {
        event.preventDefault();
        const { email, password, remember } = event.currentTarget.elements;
        run(async () => {
            await api('admin/login', 'POST', { email: email.value, password: password.value, remember: remember.checked });
            window.location.assign('/admin/listings');
        });
    }

    function saveListing(event, id, location) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        run(async () => {
            await api(id ? `admin/listings/${id}` : 'admin/listings', id ? 'PATCH' : 'POST', { ...data, ...location });
            router.visit('/admin/listings');
        });
    }

    if (!administrator) {
        return <main className="login-page">
            <Head title="Platform admin sign in · Schoolhouse" />
            <div className="login-content">
                <a className="brand" href="/"><span aria-hidden="true">S</span> Schoolhouse</a>
                <h1>Sign in to Schoolhouse</h1>
                <p>Use your platform administrator account.</p>
                <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.message}</p>
                <form id="sign-in" method="post" onSubmit={signIn}>
                    <Field label="Email address" name="email" type="email" autoComplete="username" required placeholder="you@example.com" />
                    <Field label="Password" name="password" type="password" autoComplete="current-password" required />
                    <label className="review-toggle"><input type="checkbox" name="remember" /> Remember me</label>
                    <Button type="submit" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</Button>
                </form>
                <a className="login-return" href="/">← Back to TaleemFinder</a>
            </div>
        </main>;
    }

    const title = screen === 'create' ? 'Create listing' : screen === 'edit' ? 'Edit listing' : 'Directory listings';
    return <AdminLayout {...{ screen, title, adminName, busy }}
        signOut={() => run(async () => { await api('logout', 'POST'); window.location.assign('/admin/login'); })}>
        <Head title={title + ' · Schoolhouse'} />
        <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.message}</p>
        {screen === 'index' ? <Workspace {...{ run, busy, onError }} /> : <>
            <div className="page-heading"><h1>{title}</h1></div>
            <ListingEditor key={listing?.id ?? 'new'} listing={listing ?? {}} submit={saveListing} {...{ busy, onError }} />
        </>}
    </AdminLayout>;
}
