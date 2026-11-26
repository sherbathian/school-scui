import { adminPath } from '../../admin-path.js';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';

export default function BranchModules({ branch, adminName }) {
    return <ModulePage key={branch.id} {...{ branch, adminName }} />;
}

function ModulePage({ branch, adminName }) {
    const [modules, setModules] = useState(null);
    const [feedback, setFeedback] = useState(null);
    const [busy, setBusy] = useState(false);
    const path = `admin/branches/${branch.id}/modules`;

    useEffect(() => {
        const controller = new AbortController();
        api(path, 'GET', undefined, controller.signal)
            .then(result => setModules(result.data))
            .catch(error => {
                if (!controller.signal.aborted) setFeedback({ error: true, message: error.message });
            });
        return () => controller.abort();
    }, [path]);

    async function run(action) {
        setBusy(true);
        setFeedback(null);
        try {
            await action();
        } catch (error) {
            setFeedback({ error: true, message: error.message });
        } finally {
            setBusy(false);
        }
    }

    function toggle(module) {
        run(async () => {
            const result = await api(`${path}/${module.slug}`, 'PATCH', { enabled: !module.switched_on });
            setModules(result.data);
            setFeedback({ message: `${module.name} switch updated.` });
        });
    }

    return <AdminLayout screen="modules" title="Branch modules" {...{ adminName, busy }}
        signOut={() => run(async () => {
            await api('logout', 'POST');
            window.location.assign(adminPath('/login'));
        })}>
        <Head title={`Branch modules · ${branch.name} · Schoolhouse`} />
        <div className="page-heading">
            <div><h1>Branch modules</h1><p>{branch.name}</p></div>
            <Link className="admin-link-button" href={adminPath('/listings')}>Back to listings</Link>
        </div>
        <p id="feedback" role="status" data-error={feedback?.error || undefined}>{feedback?.message}</p>
        <section aria-label="Modules">
            <div className="table-scroll"><table>
                <thead><tr><th>Module</th><th>Access</th><th>Switch</th></tr></thead>
                <tbody>
                    {modules?.map(module => {
                        const missing = module.requires.find(slug => !modules.find(item => item.slug === slug)?.enabled);
                        const requirement = modules.find(item => item.slug === missing)?.name;
                        return <tr key={module.slug}>
                            <td>{module.name}</td>
                            <td>{module.core ? 'Always available when published' : module.enabled ? (module.read_only ? 'Read-only' : 'Enabled') : 'Disabled'}
                                {requirement && <p className="module-summary">Requires {requirement}</p>}
                            </td>
                            <td>{module.core ? 'Core' : <div className="row-actions"><Button variant="outline" aria-label={`${module.switched_on ? 'Disable' : 'Enable'} ${module.name}`}
                                disabled={busy || !module.available || (!module.switched_on && (Boolean(requirement) || !module.entitled))}
                                onClick={() => toggle(module)}>{module.switched_on ? 'Disable' : 'Enable'}</Button>
                                {module.has_override && <Button variant="outline" disabled={busy} onClick={() => run(async () => {
                                    setModules((await api(`${path}/${module.slug}`, 'DELETE')).data);
                                    setFeedback({ message: `${module.name} switch reset.` });
                                })}>Reset</Button>}
                            </div>}</td>
                        </tr>;
                    })}
                    {!modules && <tr><td colSpan={3}>{feedback?.error ? 'Module settings could not be loaded.' : 'Loading modules…'}</td></tr>}
                </tbody>
            </table></div>
        </section>
    </AdminLayout>;
}
