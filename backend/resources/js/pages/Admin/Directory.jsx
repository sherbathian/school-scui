import { serviceResources } from './service-fields.js';
import { communityResources } from './community-fields.js';
import { platformResources } from './platform-fields.js';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { Detail, ListingSection, RecordHeading } from '../../components/admin-record.jsx';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';
import { Input } from '../../components/ui/input.jsx';

const school = ['school_id', 'Branch', 'schools'];
const sortOrder = ['sort_order', 'Sort order', 'integer', 0];
const active = ['is_active', 'Active', 'checkbox', true];
const lookup = [['name', 'Name', 'required'], ['slug', 'Slug'], sortOrder];
const titled = [['title', 'Title', 'required'], ['slug', 'Slug']];

const resources = {
    ...platformResources,
    ...communityResources,
    ...serviceResources,
    boards: ['Boards and curricula', [...lookup, ['kind', 'Kind', ['board', 'curriculum', 'university_charter'], 'board'], active]],
    'education-levels': ['Education levels', lookup],
    facilities: ['Facilities', [...lookup, ['kind', 'Kind', ['facility', 'focus'], 'facility'], ['icon', 'Icon'], active]],
    'school-groups': ['School groups', [
        ['name', 'Name', 'required'], ['slug', 'Slug'], ['description', 'Description', 'textarea'],
        ['website_url', 'Website', 'url'], ['logo_path', 'Logo storage path'], ['status', 'Status', ['active', 'suspended'], 'active'],
    ]],
    'listing-fees': ['Listing fees', [
        [...school, undefined, true], ['education_level_id', 'Education level', 'education_levels'], ['label', 'Label'],
        ['monthly_fee', 'Monthly fee', 'money'], ['admission_fee', 'Admission fee', 'money'], ['annual_charges', 'Annual charges', 'money'],
        ['currency', 'Currency', 'text', 'PKR'], ['effective_from', 'Effective from', 'date'],
        ['last_confirmed_at', 'Last confirmed', 'datetime-local'], ['notes', 'Notes'],
    ]],
    'school-media': ['School media', [
        [...school, undefined, true], ['kind', 'Kind', ['photo', 'brochure', 'video'], 'photo'], ['path', 'Storage path'], ['url', 'Media URL', 'url'],
        ['caption', 'Caption'], ['alt_text', 'Alt text'], sortOrder, ['is_approved', 'Approved', 'checkbox', true],
    ]],
    'course-categories': ['Course categories', lookup],
    courses: ['Courses', [
        ...titled, school, ['provider_name', 'Provider name'], ['city_id', 'City', 'cities', undefined, true],
        ['area_id', 'Area', 'areas'], ['course_category_id', 'Course category', 'course_categories'],
        ['summary', 'Summary'], ['description', 'Description', 'textarea'], ['duration_value', 'Duration', 'integer'],
        ['duration_unit', 'Duration unit', ['days', 'weeks', 'months']], ['mode', 'Delivery mode', ['onsite', 'online', 'hybrid'], 'onsite'], ['fee', 'Fee', 'money'],
        ['next_start_date', 'Next start date', 'date'], ['status', 'Status', ['draft', 'published', 'archived'], 'draft'],
    ]],
    programs: ['Programs', [
        ...titled, [...school, undefined, true], ['level', 'Level', ['intermediate', 'bachelor', 'master', 'phd', 'diploma', 'certificate'], 'bachelor'], ['field', 'Field'],
        ['duration_months', 'Duration in months', 'integer'], ['fee_amount', 'Fee', 'money'],
        ['fee_period', 'Fee period', ['semester', 'year', 'month', 'total']], ['eligibility', 'Eligibility', 'textarea'], ['admission_open', 'Admission open', 'checkbox', false],
        ['status', 'Status', ['active', 'archived'], 'active'],
    ]],
};

const assignmentTypes = [
    ['boards', 'Boards and curricula'],
    ['education-levels', 'Education levels'],
    ['facilities', 'Facilities'],
];

export default function Directory({ resource, schoolId, branches, adminName }) {
    return <DirectoryPage key={`${resource}-${schoolId ?? ''}`} {...{ resource, schoolId, branches, adminName }} />;
}

function DirectoryPage({ resource, schoolId, branches, adminName }) {
    const isAssignments = resource === 'listing-assignments';
    const title = isAssignments ? 'Listing assignments' : resources[resource][0];
    const [busy, setBusy] = useState(false);
    const [feedback, setFeedback] = useState(null);

    async function run(action) {
        setFeedback(null);
        setBusy(true);
        try {
            const message = await action();
            if (message) setFeedback({ message });
        } catch (error) {
            setFeedback({ error: true, message: error.message });
        } finally {
            setBusy(false);
        }
    }

    return <AdminLayout screen={resource} {...{ title, adminName, busy }} signOut={() => run(async () => {
        await api('logout', 'POST');
        window.location.assign('/admin/login');
    })}>
        <Head title={`${title} · Schoolhouse`} />
        {isAssignments
            ? <Assignments {...{ title, branches, schoolId, busy, run, setFeedback }} />
            : <Catalogue {...{ title, resource, schoolId, busy, run, setFeedback }} />}
        <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.message}</p>
    </AdminLayout>;
}

export function Catalogue({ resource, title, schoolId, branchScoped = false, busy, run, setFeedback }) {
    const fields = resources[resource][1];
    const isPlatform = Object.hasOwn(platformResources, resource);
    const isCommunity = Object.hasOwn(communityResources, resource);
    const isService = Object.hasOwn(serviceResources, resource);
    const personalToken = resource === 'personal-access-tokens';
    const deviceToken = resource === 'device-tokens';
    const otp = resource === 'otp-codes';
    const notifications = resource === 'notifications';
    const path = `admin/${isService ? 'services' : isCommunity ? 'community' : isPlatform ? 'platform' : 'directory'}/${resource}`;
    const [page, setPage] = useState(1);
    const [filter, setFilter] = useState(schoolId ?? '');
    const [records, setRecords] = useState(null);
    const [options, setOptions] = useState(null);
    const [editor, setEditor] = useState(null);
    const [details, setDetails] = useState(null);
    const [issuedToken, setIssuedToken] = useState(null);
    const [reload, setReload] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        api(`${path}/options`, 'GET', undefined, controller.signal)
            .then(result => setOptions(result.data))
            .catch(error => { if (!controller.signal.aborted) setFeedback({ error: true, message: error.message }); });
        return () => controller.abort();
    }, [path, setFeedback]);

    useEffect(() => {
        const controller = new AbortController();
        const params = new URLSearchParams({ page });
        if (filter) params.set(resource === 'student-transfers' ? 'branch_id' : 'school_id', filter);
        setRecords(null);
        api(`${path}?${params}`, 'GET', undefined, controller.signal)
            .then(setRecords)
            .catch(error => { if (!controller.signal.aborted) setFeedback({ error: true, message: error.message }); });
        return () => controller.abort();
    }, [path, page, filter, reload, resource, setFeedback]);

    function save(data, file) {
        run(async () => {
            let body = data;
            let method = editor.id ? 'PATCH' : 'POST';
            if (file) {
                body = new FormData();
                for (const [key, value] of Object.entries(data)) {
                    body.set(key, typeof value === 'boolean' ? Number(value) : value ?? '');
                }
                body.set('file', file);
                if (editor.id) body.set('_method', method);
                method = 'POST';
            }

            const result = await api(editor.id ? `${path}/${editor.id}` : path, method, body);
            setIssuedToken(result.issued_token ?? null);
            setDetails(notifications ? null : result.data);
            setEditor(null);
            setReload(current => current + 1);
            return 'Record saved.';
        });
    }

    function remove(row, label) {
        if (!window.confirm(`${personalToken || deviceToken ? 'Revoke' : 'Delete'} ${label}?`)) return;
        run(async () => {
            await api(`${path}/${row.id}`, 'DELETE');
            if (editor?.id === row.id) setEditor(null);
            if (records.data.length === 1 && page > 1) setPage(page - 1);
            setReload(current => current + 1);
            return 'Record deleted.';
        });
    }

    function toggleRead(row) {
        run(async () => {
            const result = await api(`${path}/${row.id}`, 'PATCH', { read: !row.read });
            if (details?.id === row.id) setDetails(result.data);
            setReload(current => current + 1);
            return 'Notification updated.';
        });
    }

    function invalidate(row) {
        if (!window.confirm('Invalidate this verification code?')) return;
        run(async () => {
            await api(`${path}/${row.id}`, 'PATCH', { invalidate: true });
            setReload(current => current + 1);
            return 'Verification code invalidated.';
        });
    }

    function attachBranch(event) {
        event.preventDefault();
        const branchId = new FormData(event.currentTarget).get('branch');
        run(async () => {
            await api(`${path}/${editor.id}/branches/${branchId}`, 'PUT');
            setOptions((await api(`${path}/options`)).data);
            return 'Branch added to the organisation.';
        });
    }

    const isTransfer = resource === 'student-transfers';
    const branchName = id => options?.schools?.find(item => String(item.id) === String(id))?.name ?? '—';
    const hasSchool = fields.some(([name]) => name === 'school_id');
    const editingGroup = resource === 'school-groups' && editor?.id;

    const upload = {
        'school-media': ['Upload photo or brochure', 'image/jpeg,image/png,image/webp,application/pdf', 'path', 'listing-media/'],
        books: ['Upload book photo', 'image/jpeg,image/png,image/webp', 'photo_path', 'book-photos/'],
        'study-resources': ['Upload study PDF', 'application/pdf', 'file_path', 'study-files/'],
    }[resource];
    const hasAttachment = row => upload && row[upload[2]]?.startsWith(upload[3]);

    const fixedBranchField = !branchScoped ? undefined : resource !== 'student-transfers' ? 'school_id'
        : editor?.id && String(editor.to_school_id) === String(schoolId) ? 'to_school_id' : 'from_school_id';

    const editableFields = fields.filter(([, , type]) => type !== 'read-only');
    const formFields = personalToken && !editor?.id ? [['name', 'Name', 'required'], ['days', 'Valid for days (1–30)', 'integer', 7]]
        : deviceToken && !editor?.id ? [...editableFields, ['token', 'Push registration token', 'password', undefined, true]]
            : editableFields;

    if (editor) return <>
        <RecordHeading icon={editor.id ? 'edit' : 'create'} title={editor.id ? 'Edit record' : 'New record'} name={title}
            back={() => setEditor(null)} />
        <RecordForm key={editor.id ?? 'new'} {...{ editor, options, busy, save }} fields={formFields} upload={upload} fixedBranchField={fixedBranchField} close={() => { setEditor(null); if (editor.id) setDetails(editor); }} />
        {editingGroup && <form className="listing-page-card" onSubmit={attachBranch}>
            <h2>Organisation branches</h2>
            <ul className="mb-4">
                {options.schools.filter(branch => Number(branch.group_id) === editor.id).map(branch =>
                    <li key={branch.id}><Link href={`/admin/listings/${branch.id}/edit`}>{branch.name}</Link></li>)}
            </ul>
            <label>Add an unassigned branch<select name="branch" required defaultValue="">
                <option value="">Select a branch</option>
                {options.schools.filter(branch => branch.group_id == null).map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
            </select></label>
            <Button type="submit" disabled={busy}>Add branch</Button>
        </form>}
    </>;

    if (details) return <>
        <RecordHeading icon="directory" title="Record details" name={title} back={() => { setDetails(null); setIssuedToken(null); }}>
            {hasAttachment(details) && <a className="admin-link-button" href={`/api/${path}/${details.id}/download`}>Download file</a>}
            {!otp && !notifications && <Button onClick={() => { setEditor(details); setDetails(null); setIssuedToken(null); }}>Edit record</Button>}
            {notifications && <Button onClick={() => toggleRead(details)}>Mark {details.read ? 'unread' : 'read'}</Button>}
        </RecordHeading>
        {issuedToken && <section className="listing-page-card">
            <h2>Copy this token now</h2>
            <p className="mb-2">This token is shown once. It can read only the account profile.</p>
            <Input readOnly value={issuedToken} onFocus={event => event.target.select()} aria-label="New personal access token" />
            <Button className="mt-2" variant="outline" onClick={() => setIssuedToken(null)}>Hide token</Button>
        </section>}
        <ListingSection icon="listings" title="Record information" readOnly>
            {fields.map(([name, label, type]) => <Detail key={name} label={label} wide={type === 'textarea'}
                value={type === 'multi' ? (details[name]?.join(', ') || 'All types') : type === 'checkbox' ? (Number(details[name]) ? 'Yes' : 'No')
                    : typeof type === 'string' && Array.isArray(options?.[type])
                        ? options[type].find(item => String(item.id) === String(details[name]))?.name ?? details[name]
                        : details[name]} />)}
        </ListingSection>
    </>;

    return <>
        <div className="page-heading"><h1>{title}</h1>
            {!otp && <Button disabled={busy || !options} onClick={() => setEditor(resource === 'student-transfers' ? { from_school_id: filter || null } : { school_id: filter || null })}>
                {notifications ? '+ Send notification' : '+ New record'}
            </Button>}
        </div>
        {hasSchool && !branchScoped && <label className="mb-4 max-w-xs">Filter by branch
            <select value={filter} onChange={event => { setFilter(event.target.value); setPage(1); }}>
                <option value="">All branches</option>
                {options?.schools?.map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
            </select>
        </label>}
        <div className="table-scroll"><table>
            <thead><tr><th>Record</th><th>{isTransfer ? 'From / To' : hasSchool ? 'Branch' : (isPlatform || isCommunity || isService) ? 'Summary' : 'Slug'}</th><th>Actions</th></tr></thead>
            <tbody>
                {records?.data.map(row => {
                    const label = row.name || row.title || row.label || row.caption || row.url || row.path || row.invoice_no || row.description || row.parent_name || row.stat_date || row.transfer_date || row.limit_key || row.purpose || `Record #${row.id}`;
                    return <tr key={row.id}>
                        <td><button className="record-name-link" type="button" disabled={!options} onClick={() => setDetails(row)}>{label}</button></td>
                        <td>{isTransfer ? `${branchName(row.from_school_id)} → ${branchName(row.to_school_id)}` : hasSchool ? branchName(row.school_id) : row.slug ?? row.status ?? row.role ?? row.channel ?? (notifications ? (row.read ? 'Read' : 'Unread') : '—')}</td>
                        <td><div className="row-actions">
                            {hasAttachment(row) &&
                                <a className="admin-link-button" href={`/api/${path}/${row.id}/download`}>Download</a>}
                            {resource === 'school-media' && /^https?:\/\//i.test(row.url ?? '') &&
                                <a className="admin-link-button" href={row.url} target="_blank" rel="noreferrer">Open link</a>}
                            {otp && <Button variant="outline" disabled={busy || Boolean(row.consumed_at)} onClick={() => invalidate(row)}>Invalidate</Button>}
                            {notifications && <Button variant="outline" disabled={busy} onClick={() => toggleRead(row)}>Mark {row.read ? 'unread' : 'read'}</Button>}
                            {!otp && !notifications && <Button variant="outline" disabled={busy || !options} onClick={() => setEditor(row)}>Edit</Button>}
                            {!otp && !row.protected && <Button variant="outline" disabled={busy} onClick={() => remove(row, label)}>{personalToken || deviceToken ? 'Revoke' : 'Delete'}</Button>}
                        </div></td>
                    </tr>;
                })}
                {!records?.data.length && <tr><td colSpan={3}>{records ? 'No records found.' : 'Loading records…'}</td></tr>}
            </tbody>
        </table></div>
        {records?.last_page > 1 && <nav aria-label="Directory pagination" className="mt-4 flex items-center gap-3">
            <Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
            <span>Page {page} of {records.last_page}</span>
            <Button variant="outline" disabled={page >= records.last_page} onClick={() => setPage(page + 1)}>Next</Button>
        </nav>}
    </>;
}

function RecordForm({ fields, editor, options, busy, save, close, upload, fixedBranchField }) {
    const [city, setCity] = useState(editor.city_id ?? '');
    const [area, setArea] = useState(editor.area_id ?? '');
    const [branches, setBranches] = useState({
        school_id: editor.school_id ?? '', from_school_id: editor.from_school_id ?? '', to_school_id: editor.to_school_id ?? '',
    });
    const branchScoped = {
        student_id: 'school_id', student_inquiry_id: 'school_id', fee_discount_id: 'school_id', school_session_id: 'school_id',
        from_student_id: 'from_school_id', to_student_id: 'to_school_id',
    };

    function submit(event) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        save(Object.fromEntries(fields.map(([name, , type]) => [
            name, type === 'checkbox' ? data.has(name) : type === 'multi' ? data.getAll(name) : data.get(name) || null,
        ])), data.get('file')?.size ? data.get('file') : null);
    }

    return <form className="listing-full-form" onSubmit={submit}>
        <ListingSection icon="listings" title="Record information">
                {fields.map(([name, label, type = 'text', fallback, required]) => {
                    const value = editor[name] ?? fallback ?? '';
                    if (type === 'multi') {
                        return <fieldset key={name}><legend>{label}</legend>{fallback.map(choice =>
                            <label className="review-toggle" key={choice}><input type="checkbox" name={name} value={choice}
                                defaultChecked={editor[name]?.includes(choice) ?? false} /> {choice.replaceAll('_', ' ')}</label>)}</fieldset>;
                    }
                    if (type === 'checkbox') {
                        return <label key={name} className="review-toggle"><input name={name} type="checkbox"
                            defaultChecked={Boolean(Number(value))} /> {label}</label>;
                    }
                    if (Array.isArray(type)) {
                        return <label key={name}>{label}<select name={name} required={fallback !== undefined} defaultValue={value}>
                            {fallback === undefined && <option value="">None</option>}
                            {type.map(choice => <option key={choice} value={choice}>{choice.replaceAll('_', ' ')}</option>)}
                        </select></label>;
                    }
                    if (type in options) {
                        const branchKey = branchScoped[name];
                        const items = type === 'areas'
                            ? options.areas.filter(item => String(item.city_id) === String(city))
                            : branchKey ? options[type].filter(item => String(item.school_id) === String(branches[branchKey])) : options[type];
                        const controls = name === 'city_id' ? { value: city, onChange: event => { setCity(event.target.value); setArea(''); } }
                            : name === 'area_id' ? { value: area, onChange: event => setArea(event.target.value) } : { defaultValue: value };
                        if (name === fixedBranchField) return <label key={name}>{label}
                            <input type="hidden" name={name} value={value} />
                            <span>{items.find(item => String(item.id) === String(value))?.name ?? value}</span>
                        </label>;
                        if (Object.hasOwn(branches, name)) controls.onChange = event =>
                            setBranches(current => ({ ...current, [name]: event.target.value }));
                        return <label key={name}>{label}<select key={branchKey ? branches[branchKey] : name} name={name} required={required} {...controls}>
                            <option value="">{required ? `Select ${label.toLowerCase()}` : 'None'}</option>
                            {items.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
                        </select></label>;
                    }
                    if (type === 'textarea') return <label key={name}>{label}<textarea name={name} required={required} defaultValue={value} /></label>;
                    const numeric = ['integer', 'money'].includes(type);
                    return <label key={name}>{label}<Input name={name} required={required || type === 'required' || fallback !== undefined}
                        type={numeric ? 'number' : type === 'required' ? 'text' : type}
                        step={type === 'money' ? '0.01' : undefined} min={type === 'money' ? 0 : undefined}
                        defaultValue={type === 'datetime-local' && value ? String(value).replace(' ', 'T').slice(0, 16) : value} /></label>;
                })}
            {upload && <label className="mb-4">{upload[0]}
                <Input type="file" name="file" accept={upload[1]} />
            </label>}
        </ListingSection>
            <div className="listing-save-bar">
                <Button type="submit" disabled={busy}>Save record</Button>
                <Button type="button" variant="outline" disabled={busy} onClick={close}>Cancel</Button>
            </div>
    </form>;
}

function Assignments({ title, branches, schoolId, busy, run, setFeedback }) {
    const [branch, setBranch] = useState(schoolId ?? '');
    const [selection, setSelection] = useState(null);
    useEffect(() => {
        setSelection(null);
        if (!branch) return;
        const controller = new AbortController();
        Promise.all(assignmentTypes.map(([type]) =>
            api(`admin/listings/${branch}/assignments/${type}`, 'GET', undefined, controller.signal)))
            .then(results => setSelection(Object.fromEntries(results.map((result, index) => [assignmentTypes[index][0], result]))))
            .catch(error => { if (!controller.signal.aborted) setFeedback({ error: true, message: error.message }); });
        return () => controller.abort();
    }, [branch, setFeedback]);

    return <>
        <div className="page-heading"><h1>{title}</h1>
            {branch && <Link className="admin-link-button" href={`/admin/listings/${branch}/edit`}>Edit listing</Link>}
        </div>
        <label className="mb-4 max-w-sm">Branch<select value={branch} disabled={busy} onChange={event => setBranch(event.target.value)}>
            <option value="">Choose a branch</option>
            {branches.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select></label>
        {branch && !selection && <p>Loading assignments…</p>}
        {selection && assignmentTypes.map(([type, label]) => {
            const { data, options } = selection[type];
            return <form className="listing-form mb-4" key={`${branch}-${type}`} onSubmit={event => {
                event.preventDefault();
                const ids = new FormData(event.currentTarget).getAll('ids').map(Number);
                run(async () => {
                    await api(`admin/listings/${branch}/assignments/${type}`, 'PUT', { ids });
                    return `${label} saved.`;
                });
            }}>
                <fieldset><legend>{label}</legend>
                    {options.map(item => <label className="review-toggle" key={item.id}>
                        <input type="checkbox" name="ids" value={item.id} defaultChecked={data.map(Number).includes(Number(item.id))} />
                        {item.name}
                    </label>)}
                </fieldset>
                {options.length ? <Button type="submit" disabled={busy}>Save {label.toLowerCase()}</Button>
                    : <Link href={`/admin/directory/${type}`}>Add {label.toLowerCase()} first</Link>}
            </form>;
        })}
    </>;
}
