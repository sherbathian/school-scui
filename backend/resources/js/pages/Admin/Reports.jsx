import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { api } from '../../admin-api.js';
import { AdminLayout } from '../../components/admin-layout.jsx';

export default function Reports({
    adminName, platform, branch, branches, canViewAttendance, filters, columns, rows, print,
    report, title, reportOptions, sessions, grades, sections, errors = {},
}) {
    const [busy, setBusy] = useState(false);
    const [signOutError, setSignOutError] = useState('');
    const path = `/admin/reports/${branch.id}`;
    const filterQuery = new URLSearchParams(Object.entries(filters).filter(([, value]) => value != null)).toString();

    function reportLink(key) {
        const query = new URLSearchParams(filterQuery);
        query.set('report', key);
        if (key !== 'low-attendance') query.delete('threshold');
        return `${path}?${query}`;
    }

    async function signOut() {
        setBusy(true);
        setSignOutError('');
        try {
            await api('logout', 'POST');
            window.location.assign('/admin/login');
        } catch (error) {
            setSignOutError(error.message);
            setBusy(false);
        }
    }

    const table = rows.length ? <div className="reports-table">
        <table>
            <caption>{title}</caption>
            <thead><tr>{columns.map(column => <th key={column} scope="col">{column}</th>)}</tr></thead>
            <tbody>{rows.map((row, index) => <tr key={index}>
                {row.map((cell, cellIndex) => <td key={columns[cellIndex]}>{cell}</td>)}
            </tr>)}</tbody>
        </table>
    </div> : <p>No attendance records match these filters.</p>;

    if (print) {
        return <main className="reports-print">
            <Head title={`${title} · Schoolhouse`} />
            <h1>{title}</h1>
            <p>{branch.name} · {filters.from} to {filters.to}</p>
            <button type="button" className="reports-print-button" onClick={() => window.print()}>Print</button>
            {table}
        </main>;
    }

    return <AdminLayout screen="reports" title="Reports" {...{ adminName, platform, signOut, busy }}>
        <Head title="Reports · Schoolhouse" />
        <div className="page-heading">
            <div><h1>Reports</h1><p>{branch.name}</p></div>
            <Link className="admin-link-button" href={`/admin/dashboard/${branch.id}`}>Dashboard</Link>
        </div>
        {signOutError && <p role="alert">{signOutError}</p>}
        {branches.length > 1 && <label>Branch <select value={branch.id} onChange={event => router.visit(`/admin/reports/${event.target.value}`)}>
            {branches.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select></label>}
        {canViewAttendance ? <>
            <nav className="reports-options" aria-label="Available reports">
                {Object.entries(reportOptions).map(([key, label]) => <Link key={key} href={reportLink(key)}
                    aria-current={key === report ? 'page' : undefined}>{label}</Link>)}
            </nav>
            <form className="reports-filters" action={path} key={`${branch.id}:${filterQuery}`}>
                <input type="hidden" name="report" value={report} />
                <label>From <input name="from" type="date" required defaultValue={filters.from} /></label>
                <label>To <input name="to" type="date" required defaultValue={filters.to} /></label>
                <label>Session <select name="session" defaultValue={filters.session ?? ''}>
                    <option value="">All sessions</option>
                    {sessions.map(session => <option key={session}>{session}</option>)}
                </select></label>
                <label>Grade <select name="grade" defaultValue={filters.grade ?? ''}>
                    <option value="">All grades</option>
                    {grades.map(grade => <option key={grade.id} value={grade.id}>{grade.title}</option>)}
                </select></label>
                <label>Section <select name="section" defaultValue={filters.section ?? ''}>
                    <option value="">All sections</option>
                    {sections.map(section => <option key={section.id} value={section.id}>{section.title}</option>)}
                </select></label>
                {report === 'low-attendance' && <label>Below (%) <input name="threshold" type="number"
                    min="0" max="100" step="1" required defaultValue={filters.threshold} /></label>}
                <button type="submit" className="admin-link-button">Apply filters</button>
            </form>
            {report === 'low-attendance' && <p>Includes students below the threshold with recorded attendance. Present and late count as attended. Excused-only dates are excluded.</p>}
            {Object.values(errors).map(message => <p role="alert" key={message}>{message}</p>)}
            <div className="reports-actions">
                <a href={`${path}/csv?${filterQuery}`}>Download CSV</a>
                <a href={`${path}/print?${filterQuery}`}>Print view</a>
            </div>
            {table}
        </> : <p>No reports are available for your permissions and modules at this branch.</p>}
    </AdminLayout>;
}
