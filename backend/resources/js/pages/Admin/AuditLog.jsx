import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { api } from '../../admin-api.js';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';

const labels = {
    'membership.added': 'Membership added',
    'membership.updated': 'Membership updated',
    'membership.removed': 'Membership removed',
    'role.created': 'Role created',
    'role.updated': 'Role updated',
    'role.deleted': 'Role deleted',
    'module.enabled': 'Module enabled',
    'module.disabled': 'Module disabled',
    'module.reset': 'Module override reset',
};

function State({ values }) {
    if (!values) return '—';

    return <dl className="space-y-1">{Object.entries(values).map(([key, value]) => (
        <div key={key}>
            <dt className="inline font-medium">{key.replaceAll('_', ' ')} </dt>
            <dd className="inline">{Array.isArray(value) ? value.join(', ') || 'None' : String(value)}</dd>
        </div>
    ))}</dl>;
}

export default function AuditLog({ entries, branches, filters, actions, adminName, platform }) {
    const [form, setForm] = useState(filters);
    const [error, setError] = useState('');
    const field = name => ({
        name,
        value: form[name] ?? '',
        onChange: event => setForm(current => ({ ...current, [name]: event.target.value })),
    });
    const names = new Map(branches.map(branch => [branch.id, branch.name]));

    function submit(event) {
        event.preventDefault();
        router.get('/admin/audit-log', Object.fromEntries(Object.entries(form).filter(([, value]) => value)), {
            preserveState: true,
            onSuccess: () => setError(''),
            onError: errors => setError(Object.values(errors)[0]),
        });
    }

    async function signOut() {
        try {
            await api('logout', 'POST');
            router.visit('/admin/login');
        } catch (failure) {
            setError(failure.message);
        }
    }

    return <AdminLayout screen="audit-log" title="Audit Log" {...{ adminName, platform, signOut }}>
        <Head title="Audit Log · Schoolhouse" />
        <h1 className="text-2xl font-semibold">Audit Log</h1>
        <form className="my-4 flex flex-wrap items-end gap-3" onSubmit={submit}>
            {branches.length > 1 && <label>Branch <select {...field('branch')}>
                <option value="">All accessible branches</option>
                {branches.map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
            </select></label>}
            <label>Action <select {...field('action')}>
                <option value="">All actions</option>
                {actions.map(action => <option key={action} value={action}>{labels[action]}</option>)}
            </select></label>
            <label>From <input type="date" {...field('from')} /></label>
            <label>To <input type="date" min={form.from || undefined} {...field('to')} /></label>
            <Button type="submit">Apply filters</Button>
        </form>
        {error && <p role="alert">{error}</p>}
        {entries.data.length === 0 ? <p>No matching audit entries.</p> : (
            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead><tr>{['Time', 'Branch', 'Actor ID', 'Action', 'Target', 'Before', 'After'].map(label => <th className="p-2" key={label} scope="col">{label}</th>)}</tr></thead>
                    <tbody>{entries.data.map(entry => <tr key={entry.id} className="border-t align-top">
                        <td className="p-2"><time dateTime={entry.recorded_at}>{new Date(entry.recorded_at).toLocaleString()}</time></td>
                        <td className="p-2">{names.get(entry.school_id) ?? `#${entry.school_id}`}</td>
                        <td className="p-2">#{entry.actor_id}</td>
                        <td className="p-2">{labels[entry.action]}</td>
                        <td className="p-2">{entry.target_id}</td>
                        <td className="p-2"><State values={entry.before} /></td>
                        <td className="p-2"><State values={entry.after} /></td>
                    </tr>)}</tbody>
                </table>
            </div>
        )}
        <nav className="mt-4 flex items-center gap-4" aria-label="Audit log pagination">
            {entries.prev_page_url && <Link href={entries.prev_page_url}>Previous</Link>}
            <span>Page {entries.current_page} of {entries.last_page}</span>
            {entries.next_page_url && <Link href={entries.next_page_url}>Next</Link>}
        </nav>
    </AdminLayout>;
}
