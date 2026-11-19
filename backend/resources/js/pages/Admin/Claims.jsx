import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';

export default function Claims({ adminName }) {
    const [results, setResults] = useState(null);
    const [page, setPage] = useState(1);
    const [revision, setRevision] = useState(0);
    const [selected, setSelected] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        api(`admin/claims?page=${page}`, 'GET', undefined, controller.signal).then(result => {
            if (page > result.last_page) setPage(result.last_page);
            else setResults(result);
        }).catch(error => {
            if (!controller.signal.aborted) setError(error.message);
        });
        return () => controller.abort();
    }, [page, revision]);

    async function review(event) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        setBusy(true);
        setError('');
        try {
            await api(`admin/claims/${selected.id}`, 'PATCH', data);
            setSelected(null);
            setRevision(current => current + 1);
        } catch (error) {
            setError(error.message);
        } finally {
            setBusy(false);
        }
    }

    async function signOut() {
        try {
            await api('logout', 'POST');
            window.location.assign('/admin/login');
        } catch (error) {
            setError(error.message);
        }
    }

    return <AdminLayout screen="claims" title="Listing claims" {...{ adminName, busy, signOut }}>
        <Head title="Listing claims · Schoolhouse" />
        <div className="page-heading"><h1>Listing claims</h1></div>
        {error && <p id="feedback" data-error role="alert">{error}</p>}
        {selected && <section className="listing-form">
            <h2>Review {selected.school_name}</h2>
            <p>{selected.claimant_name} · {selected.claimant_role || 'Role unspecified'}</p>
            <p>{selected.phone} · {selected.email}</p>
            <form key={selected.id} onSubmit={review}>
                <div className="fields">
                    <label>Decision<select name="decision" required>
                        <option value="">Select a decision</option>
                        <option value="verified">Approve verified claim</option>
                        <option value="rejected">Decline claim</option>
                    </select></label>
                    <label>Review note<textarea name="review_note" required maxLength={2000} /></label>
                </div>
                <div className="flex gap-2">
                    <Button type="submit" disabled={busy}>Record decision</Button>
                    <Button variant="outline" disabled={busy} onClick={() => setSelected(null)}>Cancel</Button>
                </div>
            </form>
        </section>}
        <section aria-label="Pending claim requests">
            <div className="table-scroll"><table>
                <thead><tr><th>Institution</th><th>Claimant</th><th>Role</th><th>Actions</th></tr></thead>
                <tbody>
                    {results?.data.map(claim => <tr key={claim.id}>
                        <td>{claim.school_name}</td><td>{claim.claimant_name}</td><td>{claim.claimant_role || '—'}</td>
                        <td><Button variant="outline" disabled={busy} onClick={() => { setError(''); setSelected(claim); }}>Review</Button></td>
                    </tr>)}
                    {!results?.data.length && <tr><td colSpan={4}>{results ? 'No pending claims.' : 'Loading claims…'}</td></tr>}
                </tbody>
            </table></div>
            {results && <nav className="pagination" aria-label="Claim pages">
                <Button variant="outline" disabled={page <= 1} onClick={() => setPage(current => current - 1)}>Previous</Button>
                <span>Page {page} of {results.last_page}</span>
                <Button variant="outline" disabled={page >= results.last_page} onClick={() => setPage(current => current + 1)}>Next</Button>
            </nav>}
        </section>
    </AdminLayout>;
}
