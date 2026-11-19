import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { Detail, ListingSection, RecordHeading } from '../../components/admin-record.jsx';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';
import { Input } from '../../components/ui/input.jsx';

const titles = { users: 'User accounts', members: 'Branch memberships', roles: 'Branch roles', permissions: 'Permissions catalogue' };
const labels = { users: 'user', members: 'membership', roles: 'role' };

function PermissionPicker({ permissions, selected = [], name }) {
    return <fieldset className="access-permissions">
        <legend>{name === 'direct_permissions' ? 'Direct permissions' : 'Role permissions'}</legend>
        <div>{permissions.map(permission => <label key={permission.name}>
            <input type="checkbox" name={name} value={permission.name} defaultChecked={selected.includes(permission.name)} />
            <span>{permission.name}</span>
        </label>)}</div>
    </fieldset>;
}

function UserFields({ record }) {
    return <>
        <label>Name<Input name="name" required maxLength={255} defaultValue={record.name} /></label>
        <label>Email address<Input type="email" name="email" required maxLength={255} defaultValue={record.email} /></label>
        <label>Private phone<Input type="tel" name="phone" maxLength={30} defaultValue={record.phone} /></label>
        <label>Preferred language<select name="locale" defaultValue={record.locale ?? 'en'}>
            <option value="en">English</option><option value="ur">Urdu</option>
        </select></label>
        <label>{record.id ? 'New password (optional)' : 'Password'}<Input type="password" name="password"
            minLength={8} maxLength={72} required={!record.id} autoComplete="new-password" /></label>
        <label className="review-toggle"><input type="checkbox" name="is_active" defaultChecked={record.is_active ?? true} /> Account active</label>
    </>;
}

function MemberFields({ record, roles, permissions }) {
    const validRole = roles.some(role => role.name === record.role);
    return <>
        {record.id ? <p>{record.name} · {record.email}</p> :
            <label>Account email<Input name="email" type="email" required placeholder="person@example.com" /></label>}
        <label>Branch role<select name="role" defaultValue={validRole ? record.role : ''} required>
            <option value="">Select a role</option>
            {roles.map(role => <option key={role.id} value={role.name}>{role.name}</option>)}
        </select></label>
        <label className="review-toggle"><input type="checkbox" name="is_active" defaultChecked={record.is_active ?? true} /> Membership active</label>
        <label className="review-toggle"><input type="checkbox" name="is_primary_contact" defaultChecked={record.is_primary_contact ?? false} /> Primary contact</label>
        {record.id && <PermissionPicker name="direct_permissions" {...{ permissions }} selected={record.direct_permissions} />}
    </>;
}

function RoleFields({ record, permissions }) {
    return <>
        <label>Role name<Input name="name" required maxLength={64} defaultValue={record.name} readOnly={record.is_built_in}
            placeholder="e.g. admissions-coordinator" /></label>
        <PermissionPicker name="permissions" {...{ permissions }} selected={record.permissions} />
    </>;
}

export default function Access({ screen, branch, branches, adminName, adminId }) {
    return <AccessPage key={screen + ':' + (branch?.id ?? '')} {...{ screen, branch, branches, adminName, adminId }} />;
}

function AccessPage({ screen, branch, branches, adminName, adminId }) {
    const branchScreen = screen === 'members' || screen === 'roles';
    const title = titles[screen];
    const noun = labels[screen];
    const [rows, setRows] = useState(null);
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]);
    const [editor, setEditor] = useState(null);
    const [details, setDetails] = useState(null);
    const [query, setQuery] = useState('');
    const [revision, setRevision] = useState(0);
    const [busy, setBusy] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const rolesPath = `schools/${branch?.id}/roles`;
    const path = screen === 'users' ? 'users' : screen === 'permissions' ? 'admin/permissions'
        : screen === 'members' ? `schools/${branch?.id}/users` : rolesPath;

    useEffect(() => {
        if (branchScreen && !branch) return;
        const controller = new AbortController();
        const read = endpoint => api(endpoint, 'GET', undefined, controller.signal);
        Promise.all([read(path), screen === 'members' ? read(rolesPath) : null]).then(([result, roleResult]) => {
            setRows(screen === 'roles' ? result.data.roles : result.data);
            const roleData = roleResult?.data ?? (screen === 'roles' ? result.data : null);
            if (roleData) {
                setRoles(roleData.roles.filter(role => role.team_id !== null));
                setPermissions(roleData.permissions);
            }
        }).catch(error => {
            if (!controller.signal.aborted) setFeedback({ error: true, text: error.message });
        });
        return () => controller.abort();
    }, [branch, branchScreen, path, rolesPath, revision, screen]);

    async function run(action) {
        setBusy(true);
        setFeedback(null);
        try {
            const text = await action();
            if (text) setFeedback({ text });
        } catch (error) {
            setFeedback({ error: true, text: error.message });
        } finally {
            setBusy(false);
        }
    }

    function save(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const data = Object.fromEntries(form);
        if (screen === 'users' || screen === 'members') data.is_active = form.has('is_active');
        if (screen === 'members') {
            data.is_primary_contact = form.has('is_primary_contact');
            if (editor.id) {
                data.direct_permissions = form.getAll('direct_permissions');
                if (data.role === editor.role) delete data.role;
            }
        }
        if (screen === 'roles') data.permissions = form.getAll('permissions');
        run(async () => {
            await api(editor.id ? `${path}/${editor.id}` : path, editor.id ? 'PATCH' : 'POST', data);
            setEditor(null);
            setDetails(null);
            setRevision(value => value + 1);
            return editor.id ? 'Changes saved.' : 'Record created.';
        });
    }

    function remove(record) {
        const action = screen === 'members' ? 'Remove' : 'Delete';
        if (!window.confirm(`${action} ${record.name}?`)) return;
        run(async () => {
            await api(`${path}/${record.id}`, 'DELETE');
            setEditor(null);
            setRevision(value => value + 1);
            return screen === 'members' ? 'Membership removed.' : 'Record deleted.';
        });
    }

    const columns = screen === 'users' ? ['Account', 'Status', 'Branches'] : screen === 'members'
        ? ['Account', 'Role', 'Status', 'Direct permissions'] : screen === 'roles'
            ? ['Role', 'Permissions', 'Assigned users'] : ['Permission', 'Assigned to roles'];
    const visible = rows?.filter(record => [record.name, record.email, record.role].some(value =>
        value?.toLowerCase().includes(query.trim().toLowerCase())));

    function cells(record) {
        const status = record.is_active ? 'Active' : 'Inactive';
        if (screen === 'users') return [record.name, status + (record.is_super_admin ? ' · Super Admin' : ''), record.schools.length];
        if (screen === 'members') return [record.name, record.role, status + (record.is_primary_contact ? ' · Primary contact' : ''),
            record.direct_permissions?.join(', ') || '—'];
        if (screen === 'roles') return [record.name, record.permissions.length, record.assigned_users];
        return [record.name, record.role_count];
    }

    function canEdit(record) {
        return screen !== 'permissions' && !record.is_super_admin && !record.is_immutable && record.team_id !== null;
    }

    return <AdminLayout {...{ screen, title, adminName, busy }} signOut={() => run(async () => {
        await api('logout', 'POST');
        window.location.assign('/admin/login');
    })}>
        <Head title={title + ' · Schoolhouse'} />
        {!editor && !details && <><div className="page-heading"><div><h1>{title}</h1>{branch && <p>{branch.name}</p>}</div>
            {noun && (!branchScreen || branch) && <Button disabled={busy} onClick={() => { setFeedback(null); setEditor({}); }}>+ New {noun}</Button>}
        </div>
        {branchScreen && <label className="mb-4 max-w-sm">Select branch
            <select value={branch?.id ?? ''} disabled={busy} onChange={event =>
                router.visit(event.target.value ? `/admin/branches/${event.target.value}/${screen}` : `/admin/${screen}`)}>
                <option value="">Choose a branch</option>
                {branches.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
        </label>}</>}
        <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.text}</p>
        {editor && <>
            <RecordHeading icon={editor.id ? 'edit' : 'create'} title={`${editor.id ? 'Edit' : 'New'} ${noun}`} name={editor.name ?? branch?.name}
                back={() => { setEditor(null); setDetails(null); }} />
            <form className="listing-full-form" key={editor.id ?? 'new'} onSubmit={save}>
                <ListingSection icon="shield" title="Access information">
                    {screen === 'users' && <UserFields record={editor} />}
                    {screen === 'members' && <MemberFields record={editor} {...{ roles, permissions }} />}
                    {screen === 'roles' && <RoleFields record={editor} {...{ permissions }} />}
                </ListingSection>
                <div className="listing-save-bar"><Button type="submit" disabled={busy}>Save {noun}</Button>
                    <Button variant="outline" disabled={busy} onClick={() => setEditor(null)}>Cancel</Button></div>
            </form>
        </>}
        {!editor && details && <>
            <RecordHeading icon="shield" title={noun ? `${noun[0].toUpperCase() + noun.slice(1)} details` : 'Permission details'}
                name={details.name} back={() => setDetails(null)}>
                {canEdit(details) && <Button disabled={busy} onClick={() => setEditor(details)}>Edit {noun}</Button>}
            </RecordHeading>
            <ListingSection icon="key" title="Access information" readOnly>
                {cells(details).map((value, index) => <Detail key={columns[index]} label={columns[index]} value={value} />)}
                {details.email && <Detail label="Email address" value={details.email} />}
                {screen === 'users' && <Detail label="Private phone" value={details.phone} />}
                {screen === 'roles' && <Detail label="Granted permissions" value={details.permissions.join(', ') || '—'} wide />}
            </ListingSection>
        </>}
        {!editor && !details && (!branchScreen || branch) && <section aria-label={title}>
            {screen === 'permissions' && <p className="mb-4">Assign application permissions in Branch roles or Branch memberships.</p>}
            <label className="mb-4 max-w-sm">Search <Input type="search" value={query} onChange={event => setQuery(event.target.value)} /></label>
            <div className="table-scroll"><table>
                <thead><tr>{columns.map(column => <th key={column}>{column}</th>)}{noun && <th>Actions</th>}</tr></thead>
                <tbody>
                    {visible?.map(record => <tr key={record.id}>
                        {cells(record).map((value, index) => <td key={columns[index]}>{index === 0
                            ? <button type="button" className="record-name-link" onClick={() => setDetails(record)}>{value}</button> : value}
                            {index === 0 && record.email && <small className="listing-context">{record.email}</small>}</td>)}
                        {noun && <td><div className="row-actions">
                            {canEdit(record) ? <>
                                <Button variant="outline" disabled={busy} onClick={() => { setFeedback(null); setEditor(record); }}>Edit</Button>
                                {!record.is_built_in && record.id !== adminId && <Button variant="outline" disabled={busy}
                                    onClick={() => remove(record)}>{screen === 'members' ? 'Remove' : 'Delete'}</Button>}
                            </> : 'Protected'}
                        </div></td>}
                    </tr>)}
                    {!visible?.length && <tr><td colSpan={columns.length + Number(Boolean(noun))}>{rows ? 'No matching records.' : 'Loading…'}</td></tr>}
                </tbody>
            </table></div>
        </section>}
    </AdminLayout>;
}
