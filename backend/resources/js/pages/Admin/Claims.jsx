import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { Detail, ListingSection, RecordHeading } from '../../components/admin-record.jsx';
import { AdminLayout } from '../../components/admin-layout.jsx';
import { Button } from '../../components/ui/button.jsx';

export default function Claims({ adminName }) {
    const [results, setResults] = useState(null);
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState('pending');
    const [revision, setRevision] = useState(0);
    const [selected, setSelected] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        api(`admin/claims?page=${page}&status=${status}`, 'GET', undefined, controller.signal).then(result => {
            if (page > result.last_page) setPage(result.last_page);
            else setResults(result);
        }).catch(error => {
            if (!controller.signal.aborted) setError(error.message);
        });
        return () => controller.abort();
    }, [page, revision, status]);

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
        {error && <p id="feedback" data-error role="alert">{error}</p>}
        {selected ? <>
            <RecordHeading icon="claims" title="Claim details" name={selected.school_name} back={() => setSelected(null)} />
            <ListingSection icon="membership" title="Claimant information" readOnly>
                <Detail label="Claimant" value={selected.claimant_name} />
                <Detail label="Role" value={selected.claimant_role} />
                <Detail label="Phone" value={selected.phone} />
                <Detail label="Email" value={selected.email} />
                <Detail label="Method" value={selected.method} />
                <Detail label="Status" value={selected.status} />
                <Detail label="Reviewed at" value={selected.reviewed_at} />
                <Detail label="Review note" value={selected.review_note} wide />
            </ListingSection>
            {selected.status === 'pending' && <form className="listing-full-form" key={selected.id} onSubmit={review}>
                <ListingSection icon="shield" title="Review decision">
                    <label>Decision<select name="decision" required>
                        <option value="">Select a decision</option>
                        <option value="verified">Approve verified claim</option>
                        <option value="rejected">Decline claim</option>
                    </select></label>
                    <label>Review note<textarea name="review_note" required maxLength={2000} /></label>
                </ListingSection>
                <div className="listing-save-bar"><Button type="submit" disabled={busy}>Record decision</Button></div>
            </form>}
        </> : <>
        <div className="page-heading"><h1>Listing claims</h1></div>
        <section aria-label="Claim requests">
            <label className="mb-4 max-w-xs">Status<select value={status} onChange={event => { setStatus(event.target.value); setPage(1); }}>
                <option value="pending">Pending</option><option value="verified">Approved</option>
                <option value="rejected">Declined</option><option value="all">All claims</option>
            </select></label>
            <div className="table-scroll"><table>
                <thead><tr><th>Institution</th><th>Claimant</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    {results?.data.map(claim => <tr key={claim.id}>
                        <td>{claim.school_name}</td><td>{claim.claimant_name}</td><td>{claim.status}</td>
                        <td><Button variant="outline" disabled={busy} onClick={() => { setError(''); setSelected(claim); }}>{claim.status === 'pending' ? 'Review' : 'Details'}</Button></td>
                    </tr>)}
                    {!results?.data.length && <tr><td colSpan={4}>{results ? 'No matching claims.' : 'Loading claims…'}</td></tr>}
                </tbody>
            </table></div>
            {results && <nav className="pagination" aria-label="Claim pages">
                <Button variant="outline" disabled={page <= 1} onClick={() => setPage(current => current - 1)}>Previous</Button>
                <span>Page {page} of {results.last_page}</span>
                <Button variant="outline" disabled={page >= results.last_page} onClick={() => setPage(current => current + 1)}>Next</Button>
            </nav>}
        </section></>}
    </AdminLayout>;
}
