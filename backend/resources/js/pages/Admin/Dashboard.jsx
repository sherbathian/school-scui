import { Head, Link, router } from '@inertiajs/react';
import { lazy, Suspense, useState } from 'react';
import { api } from '../../admin-api.js';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { NavigationIcon } from '../../components/admin-navigation.jsx';
import { BranchPicker } from '../../components/branch-picker.jsx';

const WidgetGrid = lazy(() => import('../../components/dashboard/WidgetGrid.jsx'));

export default function Dashboard({ adminName, platform, role, branch, branches, cards, widgets = [], operations, schoolAccess, reportDate, studentLinked }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const title = `${role} dashboard`;
    const date = new Intl.DateTimeFormat(undefined, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(reportDate));

    async function signOut() {
        setBusy(true);
        setError('');
        try {
            await api('logout', 'POST');
            window.location.assign('/admin/login');
        } catch (error) {
            setError(error.message);
            setBusy(false);
        }
    }

    return <AdminLayout screen="dashboard" {...{ title, adminName, platform, role, operations, schoolAccess, signOut, busy }}>
        <Head title={`${title} · Schoolhouse`} />
        <div className="page-heading">
            <div><h1>{title}</h1><p>{branch?.name ?? 'All institutions'} · {date}</p></div>
            {operations && <Link className="admin-link-button" href={`/admin/branches/${branch.id}/operations`}>Open branch operations</Link>}
        </div>
        {error && <p id="feedback" role="alert" data-error>{error}</p>}
        {(platform || branches.length > 1) && <BranchPicker branch={branch} branches={branches}
            onSelect={id => router.visit(id ? `/admin/dashboard/${id}` : '/admin/dashboard')} />}
        <div className="metrics dashboard-metrics" aria-label="Summary">
            {Object.entries(cards).map(([label, value]) => <div className="metric-button" key={label}>
                <span className="metric-icon" aria-hidden="true"><NavigationIcon name={role === 'Student' ? 'school' : 'modules'} /></span>
                <div className="metric-copy"><span>{label}</span><strong>{typeof value === 'number' ? value.toLocaleString() : value}</strong></div>
            </div>)}
        </div>
        {widgets.length > 0 && <Suspense fallback={<p role="status">Loading dashboard widgets…</p>}>
            <WidgetGrid widgets={widgets} />
        </Suspense>}
        {role === 'Student' && !studentLinked && <section><h2>My reports</h2><p>Personal attendance and fees will appear when your sign-in is linked to your student record.</p></section>}
        {Object.keys(cards).length === 0 && <p>No summaries are available for your permissions at this branch.</p>}
    </AdminLayout>;
}
