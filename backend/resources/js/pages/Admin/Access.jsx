import { adminPath } from '../../admin-path.js';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { Detail, ListingSection, RecordHeading } from '../../components/admin-record.jsx';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';
import { Input } from '../../components/ui/input.jsx';

const titles = { users: 'User accounts', members: 'Branch memberships', roles: 'Branch roles', permissions: 'Permissions catalogue' };
const labels = { users: 'user', members: 'membership', roles: 'role' };

function AssignedUsers({ role, branch, platform }) {
    const [users, setUsers] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        if (role.team_id === null && !platform) return;
        const controller = new AbortController();
        api(`schools/${branch.id}/roles/${role.id}/assigned-users`, 'GET', undefined, controller.signal)
            .then(result => { if (!controller.signal.aborted) setUsers(result.data); })
            .catch(error => { if (!controller.signal.aborted) setError(error.message); });
        return () => controller.abort();
    }, [branch.id, role.id, role.team_id, platform]);

    return <section className="mt-4" aria-label="Assigned users">
        <h2 className="mb-3">Assigned users</h2>
        {role.team_id === null && !platform ? <p>Only Super Admins can view platform role assignments.</p>
            : error ? <p role="alert">{error}</p>
            : !users ? <p role="status">Loading assigned users…</p>
            : users.length === 0 ? <p>No users are assigned to this role.</p>
            : <div className="table-scroll"><table>
                <thead><tr><th>Name</th><th>Sign-in email</th><th>Access status</th></tr></thead>
                <tbody>{users.map(user => <tr key={user.id}>
                    <td>{user.name}</td><td>{user.email}</td><td>{user.is_active ? 'Active' : 'Inactive'}</td>
                </tr>)}</tbody>
            </table></div>}
    </section>;
}

function UserBranches({ record, branches, busy, run, onChange }) {
    const [branchId, setBranchId] = useState('');
    const [roleOptions, setRoleOptions] = useState(null);
    const available = branches.filter(branch => !record.schools.some(school => school.id === branch.id));

    useEffect(() => {
        if (!branchId) return;
        const controller = new AbortController();
        api(`schools/${branchId}/roles`, 'GET', undefined, controller.signal)
            .then(result => {
                if (!controller.signal.aborted) setRoleOptions({ roles: result.data.roles.filter(role => String(role.team_id) === branchId) });
            })
            .catch(error => {
                if (!controller.signal.aborted) setRoleOptions({ error: error.message });
            });
        return () => controller.abort();
    }, [branchId]);

    function assign(event) {
        event.preventDefault();
        const role = new FormData(event.currentTarget).get('role');
        const branch = available.find(item => String(item.id) === branchId);
        if (!branch || !role) return;
        run(async () => {
            const { data } = await api(`schools/${branch.id}/users`, 'POST', { email: record.email, role });
            onChange([...record.schools, { ...branch, role: data.role, is_active: data.is_active }]);
            setBranchId('');
            setRoleOptions(null);
            return `Assigned to ${branch.name}.`;
        });
    }

    function unassign(branch) {
        if (!window.confirm(`Remove ${record.name}’s membership in ${branch.name}?`)) return;
        run(async () => {
            await api(`schools/${branch.id}/users/${record.id}`, 'DELETE');
            onChange(record.schools.filter(item => item.id !== branch.id));
            return `Removed membership in ${branch.name}.`;
        });
    }

    return <section className="mt-4" aria-label="Branch memberships">
        <h2 className="mb-3">Branch memberships</h2>
        {record.schools.length ? <div className="table-scroll"><table>
            <thead><tr><th>Branch</th><th>Role</th><th>Status</th>{!record.is_super_admin && <th>Actions</th>}</tr></thead>
            <tbody>{record.schools.map(branch => <tr key={branch.id}>
                <td>{branch.name}</td><td>{branch.role}</td><td>{branch.is_active ? 'Active' : 'Inactive'}</td>
                {!record.is_super_admin && <td><Button variant="outline" disabled={busy} onClick={() => unassign(branch)}>Remove</Button></td>}
            </tr>)}</tbody>
        </table></div> : <p>No branch memberships.</p>}
        {!record.is_super_admin && available.length > 0 && <form className="fields mt-4" onSubmit={assign}>
            <label>Branch<select value={branchId} required disabled={busy} onChange={event => {
                setRoleOptions(null);
                setBranchId(event.target.value);
            }}>
                <option value="">Choose a branch</option>
                {available.map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
            </select></label>
            <label>Role<select key={branchId} name="role" required disabled={busy || !roleOptions?.roles?.length} defaultValue="">
                <option value="">{branchId && !roleOptions ? 'Loading roles…' : 'Choose a role'}</option>
                {roleOptions?.roles?.map(role => <option key={role.id} value={role.name}>{role.name}</option>)}
            </select></label>
            {roleOptions?.error && <p role="alert">{roleOptions.error}</p>}
            <div><Button type="submit" disabled={busy || !roleOptions?.roles?.length}>Assign branch</Button></div>
        </form>}
    </section>;
}

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

function MemberFields({ record, roles, permissions, platform }) {
    const choices = platform ? roles : roles.filter(role => role.name !== 'admin' || record.role === 'admin');
    const validRole = choices.some(role => role.name === record.role);
    const [role, setRole] = useState(validRole ? record.role : '');
    return <>
        {record.id ? <p>{record.name} · {record.email}</p> :
            <label>Account email<Input name="email" type="email" required placeholder="person@example.com" /></label>}
        <label>Branch role<select name="role" value={role} onChange={event => setRole(event.target.value)} required>
            <option value="">Select a role</option>
            {choices.map(role => <option key={role.id} value={role.name}>{role.name}</option>)}
        </select></label>
        <label className="review-toggle"><input type="checkbox" name="is_active" defaultChecked={record.is_active ?? true} /> Membership active</label>
        <label className="review-toggle"><input type="checkbox" name="is_primary_contact" defaultChecked={record.is_primary_contact ?? false} /> Primary contact</label>
        {record.id && role === 'student' && <label>Linked student admission number
            <Input name="student_admission_number" defaultValue={record.student_admission_number ?? ''} />
        </label>}
        {platform && record.id && <PermissionPicker name="direct_permissions" {...{ permissions }} selected={record.direct_permissions} />}
    </>;
}

function RoleFields({ record, permissions }) {
    return <>
        <label>Role name<Input name="name" required maxLength={64} defaultValue={record.name} readOnly={record.is_built_in}
            placeholder="e.g. admissions-coordinator" /></label>
        <PermissionPicker name="permissions" {...{ permissions }} selected={record.permissions} />
    </>;
}

export default function Access({ screen, branch, branches, adminName, adminId, platform = true, schoolAccess, operations }) {
    return <AccessPage key={screen + ':' + (branch?.id ?? '')} {...{ screen, branch, branches, adminName, adminId, platform, schoolAccess, operations }} />;
}

function AccessPage({ screen, branch, branches, adminName, adminId, platform = true, schoolAccess, operations }) {
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
                if (platform) data.direct_permissions = form.getAll('direct_permissions');
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
        if (screen === 'users') return [record.name, status + (record.is_super_admin ? ' · Super Admin' : ''), record.schools.map(branch => branch.name + (branch.is_active ? '' : ' (inactive)')).join(', ') || '—'];
        if (screen === 'members') return [record.name, record.role, status + (record.is_primary_contact ? ' · Primary contact' : ''),
            record.direct_permissions?.join(', ') || '—'];
        if (screen === 'roles') return [record.name, record.permissions.length, record.assigned_users];
        return [record.name, record.role_count];
    }

    function canEdit(record) {
        return screen !== 'permissions' && (platform || screen !== 'members' || !['admin', 'school_admin'].includes(record.role)) && !record.is_super_admin && !record.is_immutable && record.team_id !== null;
    }

    return <AdminLayout {...{ screen, title, adminName, busy, platform, schoolAccess, operations }} signOut={() => run(async () => {
        await api('logout', 'POST');
        window.location.assign(adminPath('/login'));
    })}>
        <Head title={title + ' · Schoolhouse'} />
        {!editor && !details && <><div className="page-heading"><div><h1>{title}</h1>{branch && <p>{branch.name}</p>}</div>
            {noun && (!branchScreen || branch) && <Button disabled={busy} onClick={() => { setFeedback(null); setEditor({}); }}>+ New {noun}</Button>}
        </div>
        {branchScreen && (platform || branches.length > 1) && <label className="mb-4 max-w-sm">Select branch
            <select value={branch?.id ?? ''} disabled={busy} onChange={event =>
                router.visit(event.target.value ? adminPath(`/branches/${event.target.value}/${screen}`) : adminPath(`/${screen}`))}>
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
                    {screen === 'members' && <MemberFields record={editor} {...{ roles, permissions, platform }} />}
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
            {screen === 'users' && <UserBranches key={details.id} record={details} {...{ branches, busy, run }}
                onChange={schools => {
                    setDetails(current => current?.id === details.id ? { ...current, schools } : current);
                    setRows(current => current.map(record => record.id === details.id ? { ...record, schools } : record));
                }} />}
            {screen === 'roles' && <AssignedUsers key={details.id} role={details} {...{ branch, platform }} />}
        </>}
        {!editor && !details && (!branchScreen || branch) && <section aria-label={title}>
            {screen === 'permissions' && <p className="mb-4">Assign application permissions in Branch roles or Branch memberships.</p>}
            <label className="mb-4 max-w-sm">Search <Input type="search" value={query} onChange={event => setQuery(event.target.value)} /></label>
            <div className="table-scroll"><table>
                <thead><tr>{columns.map(column => <th key={column}>{column}</th>)}{noun && <th>Actions</th>}</tr></thead>
                <tbody>
                    {visible?.map(record => <tr key={record.id}>
                        {cells(record).map((value, index) => <td key={columns[index]}>{index === 0
                            ? <button type="button" className="record-name-link" onClick={() => setDetails(record)}>{value}</button>
                            : screen === 'users' && index === 2
                                ? <button type="button" className="record-name-link" aria-label={`Manage branches for ${record.name}`}
                                    onClick={() => setDetails(record)}>{value === '—' && !record.is_super_admin ? 'Assign branch' : value}</button>
                            : screen === 'roles' && index === 2 && (platform || record.team_id !== null)
                                ? <button type="button" className="record-name-link" aria-label={`View assigned users for ${record.name}`}
                                    onClick={() => setDetails(record)}>{value}</button>
                                : value}
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
