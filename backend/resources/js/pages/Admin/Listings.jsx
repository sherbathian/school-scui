import { csvFields, loadCsvPages } from './csv-records.js';
import DataTools from './branch/DataTools.jsx';
import { api } from '../../admin-api.js';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { NavigationIcon } from '../../components/admin-navigation.jsx';
import { Detail, ListingSection } from '../../components/admin-record.jsx';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';
import { Input } from '../../components/ui/input.jsx';

const listingCsvFields = csvFields([
    'name', 'slug', 'institute_type', 'short_name', 'branch_name', 'tagline', 'description',
    'city_id', 'area_id', 'address_line', 'phone', 'email', 'website_url', 'gender_type', 'medium',
    'established_year', 'latitude', 'longitude', 'whatsapp', 'social_links', 'seo_title', 'seo_description',
], ['name', 'institute_type']);

async function importListing(row) {
    const body = Object.fromEntries(Object.entries(row).filter(([, value]) => value !== ''));
    if (body.social_links) {
        try {
            body.social_links = JSON.parse(body.social_links);
        } catch {
            throw new Error('Social links must be a JSON object keyed by platform.');
        }
    }
    await api('admin/listings', 'POST', body);
}

const types = { school: 'School', college: 'College', university: 'University', training_institute: 'Training institute' };
const typeIcons = { school: 'school', college: 'academics', university: 'city', training_institute: 'staff' };
const statusIcons = { draft: 'edit', pending_review: 'pending', published: 'check', suspended: 'pause', needs_verification: 'pending' };
const statuses = { draft: 'Draft', pending_review: 'Pending review', published: 'Published', suspended: 'Suspended' };
const profile = {
    short_name: ['Short name', 80], branch_name: ['Branch name', 100], tagline: ['Tagline', 160],
    address_line: ['Street address', 255], phone: ['Public phone', 30, 'tel'],
    email: ['Public email', 190, 'email'], website_url: ['Website', 255, 'url'],
    whatsapp: ['Public WhatsApp', 30, 'tel'], seo_title: ['SEO title', 160], seo_description: ['SEO description', 300],
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
    const detailsUrl = listing.id ? `/admin/listings/${listing.id}` : '/admin/listings';
    const profileField = name => {
        const [label, maxLength, type] = profile[name];
        return <Field key={name} {...{ label, name, maxLength, type }} defaultValue={listing[name]} />;
    };

    return <form className="listing-full-form" onSubmit={event => submit(event, listing.id, { city_id: city, area_id: area })}>
        <ListingSection icon="school" title="Institute profile">
            <Field label="Institute name" name="name" maxLength={255} required defaultValue={listing.name} />
            <Field label="Institute type"><select name="institute_type" defaultValue={listing.institute_type ?? 'school'}><Choices values={types} /></select></Field>
            {['short_name', 'branch_name', 'tagline'].map(profileField)}
            {listing.group_id && <Field label="Branch code" name="branch_code" maxLength={20} defaultValue={listing.branch_code} />}
            <Field label="Student intake"><select name="gender_type" defaultValue={listing.gender_type ?? ''}>
                <option value="">Not specified</option><Choices values={intake} />
            </select></Field>
            <Field label="Language of instruction"><select name="medium" defaultValue={listing.medium ?? ''}>
                <option value="">Not specified</option><Choices values={medium} />
            </select></Field>
            <Field label="Year established" name="established_year" type="number" min={1} defaultValue={listing.established_year} />
            <Field label="Description"><textarea name="description" rows={4} maxLength={10000} defaultValue={listing.description} /></Field>
        </ListingSection>
        <ListingSection icon="pin" title="Location">
            <PlacePicker cities={cities} value={city} area={area} names onChange={(nextCity, nextArea) => { setCity(nextCity); setArea(nextArea); }} />
            {profileField('address_line')}
            <Field label="Latitude" name="latitude" type="number" min={-90} max={90} step={0.0000001} defaultValue={listing.latitude} />
            <Field label="Longitude" name="longitude" type="number" min={-180} max={180} step={0.0000001} defaultValue={listing.longitude} />
        </ListingSection>
        <ListingSection icon="membership" title="Public contact">
            {['phone', 'whatsapp', 'email', 'website_url'].map(profileField)}
        </ListingSection>
        <ListingSection icon="search" title="Search visibility">
            {['seo_title', 'seo_description'].map(profileField)}
        </ListingSection>
        <div className="listing-save-bar">
            <Link className="admin-link-button" href={detailsUrl}>Cancel</Link>
            <Button type="submit" disabled={busy}><NavigationIcon name="check" /> {listing.id ? 'Save changes' : 'Create draft'}</Button>
        </div>
    </form>;
}

const intake = { boys: 'Boys', girls: 'Girls', co_ed: 'Co-educational' };
const medium = { english: 'English', urdu: 'Urdu', both: 'English and Urdu' };

function ListingDetails({ listing, locationNames }) {
    const live = listing.status === 'active' && listing.listing_status === 'published' && listing.verified_at;
    const displayDate = value => value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
        : '—';

    return <div className="listing-details">
        <ListingSection icon="school" title="Institute profile" readOnly>
            <Detail label="Institute name" value={listing.name} />
            <Detail label="Institute type" value={types[listing.institute_type]} />
            <Detail label="Short name" value={listing.short_name} />
            <Detail label="Branch name" value={listing.branch_name} />
            <Detail label="Branch code" value={listing.branch_code} />
            <Detail label="Tagline" value={listing.tagline} />
            <Detail label="Student intake" value={intake[listing.gender_type]} />
            <Detail label="Language of instruction" value={medium[listing.medium]} />
            <Detail label="Year established" value={listing.established_year} />
            <Detail label="Description" value={listing.description} wide />
        </ListingSection>
        <ListingSection icon="directory" title="Publication" readOnly>
            <Detail label="Listing status" value={<span className="status-pill listing-status" data-status={listing.listing_status}>
                <NavigationIcon name={statusIcons[listing.listing_status]} /> {statuses[listing.listing_status]}
            </span>} />
            <Detail label="Verification" value={listing.verified_at ? 'Verified' : 'Unverified'} />
            <Detail label="Verified on" value={displayDate(listing.verified_at)} />
            <Detail label="Published on" value={displayDate(listing.published_at)} />
            {live && <Detail label="Public profile" value={<a className="listing-inline-link" href={`/institutions/${listing.slug}`}>View public profile</a>} />}
        </ListingSection>
        <ListingSection icon="pin" title="Location" readOnly>
            <Detail label="City" value={locationNames?.city} />
            <Detail label="Area" value={locationNames?.area} />
            <Detail label="Street address" value={listing.address_line} />
            <Detail label="Latitude" value={listing.latitude} />
            <Detail label="Longitude" value={listing.longitude} />
        </ListingSection>
        <ListingSection icon="membership" title="Public contact" readOnly>
            {['phone', 'whatsapp', 'email', 'website_url'].map(key => <Detail key={key} label={profile[key][0]} value={listing[key]} />)}
        </ListingSection>
        <ListingSection icon="search" title="Search visibility" readOnly>
            <Detail label="SEO title" value={listing.seo_title} />
            <Detail label="SEO description" value={listing.seo_description} wide />
        </ListingSection>
    </div>;
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
        <div className="page-heading listing-page-heading"><div><h1><NavigationIcon name="directory" /> Directory listings</h1><p>Review and manage institute profiles on Taleemfinder.</p></div>
            <Link className="admin-link-button primary" href="/admin/listings/create"><NavigationIcon name="create" /> New listing</Link></div>
        <DataTools title="Directory listings" rows={[]} fields={listingCsvFields}
            exportFields={[...listingCsvFields, ...csvFields(['id', 'group_id', 'status', 'listing_status', 'verified_at', 'published_at'])]}
            loadExportRows={() => loadCsvPages('admin/listings', filters)} canImport
            onImport={importListing} onImportComplete={() => refresh(value => value + 1)} />
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
                    <span className="metric-icon"><NavigationIcon name={statusIcons[key]} /></span>
                    <span className="metric-copy">{label}<strong>{results?.summary[key].toLocaleString() ?? '…'}</strong></span>
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
                <Button type="submit" variant="outline"><NavigationIcon name="search" /> Search</Button>
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
                {results?.data.map(item => <tr key={item.id}><td><div className="listing-identity">
                    <span className="listing-type-icon" data-type={item.institute_type}><NavigationIcon name={typeIcons[item.institute_type] ?? 'school'} /></span>
                    <div><Link className="listing-inline-link" href={`/admin/listings/${item.id}`}>{item.name}</Link><small className="listing-context">
                    {[types[item.institute_type], cities.find(city => String(city.id) === String(item.city_id))?.name].filter(Boolean).join(' · ')}
                </small></div></div></td>
                    <td><span className="status-pill listing-status" data-status={item.listing_status}><NavigationIcon name={statusIcons[item.listing_status]} />{statuses[item.listing_status]}</span></td>
                    <td><span className="listing-verification" data-verified={Boolean(item.verified_at)}>
                        <NavigationIcon name={item.verified_at ? 'shield' : 'pending'} />{item.verified_at ? 'Verified' : 'Unverified'}
                    </span></td>
                    <td><div className="listing-row-actions">
                        <Link className="admin-link-button" href={`/admin/listings/${item.id}/edit`}><NavigationIcon name="edit" /> Edit</Link>
                        <Link className="admin-link-button" href={`/admin/branches/${item.id}/operations`}><NavigationIcon name="school" /> Operations</Link>
                        <details className="listing-more">
                            <summary className="admin-link-button"><NavigationIcon name="more" /> More</summary>
                            <div className="listing-secondary-actions">
                        <Link className="admin-link-button" href={`/admin/branches/${item.id}/modules`} aria-label={`Modules for ${item.name}`}><NavigationIcon name="modules" /> Modules</Link>
                        <Link className="admin-link-button" href={`/admin/directory/listing-assignments?school_id=${item.id}`}><NavigationIcon name="listings" /> Assignments</Link>
                        <Link className="admin-link-button" href={`/admin/branches/${item.id}/members`}><NavigationIcon name="users" /> Members</Link>
                        <Link className="admin-link-button" href={`/admin/branches/${item.id}/roles`}><NavigationIcon name="key" /> Roles</Link>
                        <Button variant="outline" disabled={busy} onClick={() => {
                            const active = item.status === 'active';
                            if (active && !window.confirm(`Pause branch access for ${item.name}?`)) return;
                            run(async () => {
                                await api(`schools/${item.id}`, 'PATCH', { status: active ? 'suspended' : 'active' });
                                refresh(value => value + 1);
                                return active ? 'Branch paused.' : 'Branch reactivated.';
                            });
                        }}><NavigationIcon name={item.status === 'active' ? 'pause' : 'play'} />{item.status === 'active' ? 'Pause branch' : 'Reactivate branch'}</Button>
                        <Button variant="outline" disabled={busy} onClick={() => {
                            if (!window.confirm(`Archive ${item.name}? This removes its public listing and branch access.`)) return;
                            run(async () => {
                                await api(`schools/${item.id}`, 'DELETE');
                                refresh(value => value + 1);
                                return 'School archived.';
                            });
                        }}><NavigationIcon name="archive" /> Archive</Button>
                        <Button variant="outline" disabled={busy || Boolean(item.verified_at)} onClick={() => moderate(item.id, 'verify', 'Listing verified.')}><NavigationIcon name="shield" /> Verify</Button>
                        <Button variant="outline" disabled={busy || !item.verified_at || item.status !== 'active' || item.listing_status === 'published'}
                            onClick={() => moderate(item.id, 'publish', 'Listing published.')}><NavigationIcon name="publish" /> Publish</Button>
                        <Button variant="outline" disabled={busy || item.listing_status === 'suspended'} onClick={() => {
                            if (window.confirm(`Suspend ${item.name}? Its listing will be hidden from the public directory.`)) {
                                moderate(item.id, 'suspend', 'Listing suspended.');
                            }
                        }}><NavigationIcon name="pause" /> Suspend</Button>
                            </div>
                        </details>
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

export default function Listings({ administrator, adminName, screen = 'index', listing, locationNames }) {
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
            window.location.assign('/admin/dashboard');
        });
    }

    function saveListing(event, id, location) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        run(async () => {
            const result = await api(id ? `admin/listings/${id}` : 'admin/listings', id ? 'PATCH' : 'POST', { ...data, ...location });
            router.visit(`/admin/listings/${result.data.id}`);
        });
    }

    if (!administrator) {
        return <main className="login-page">
            <Head title="Schoolhouse sign in" />
            <div className="login-content">
                <a className="brand" href="/"><span aria-hidden="true">S</span> Schoolhouse</a>
                <h1>Sign in to Schoolhouse</h1>
                <p>Use your Schoolhouse account.</p>
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

    const title = screen === 'create' ? 'Create listing' : screen === 'edit' ? 'Edit listing' : screen === 'details' ? 'Listing details' : 'Directory listings';
    return <AdminLayout {...{ screen, title, adminName, busy }}
        signOut={() => run(async () => { await api('logout', 'POST'); window.location.assign('/admin/login'); })}>
        <Head title={title + ' · Schoolhouse'} />
        <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.message}</p>
        {screen === 'index' ? <Workspace {...{ run, busy, onError }} /> : <>
            <div className="page-heading listing-page-heading">
                <div><h1><NavigationIcon name={screen === 'details' ? 'directory' : screen === 'create' ? 'create' : 'edit'} /> {title}</h1>
                    {listing && <p>{listing.name}</p>}
                </div>
                <div className="listing-page-actions">
                    <Link className="admin-link-button" href="/admin/listings">All listings</Link>
                    {screen === 'details' && <Link className="admin-link-button primary" href={`/admin/listings/${listing.id}/edit`}>
                        <NavigationIcon name="edit" /> Edit listing
                    </Link>}
                </div>
            </div>
            {screen === 'details' ? <ListingDetails {...{ listing, locationNames }} />
                : <ListingEditor key={listing?.id ?? 'new'} listing={listing ?? {}} submit={saveListing} {...{ busy, onError }} />}
        </>}
    </AdminLayout>;
}
