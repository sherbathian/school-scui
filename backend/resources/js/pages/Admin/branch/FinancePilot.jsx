import { useEffect, useRef, useState } from 'react';
import { apiRequest } from './api.js';

const money = minor => {
    const amount = BigInt(minor);
    const digits = (amount < 0n ? -amount : amount).toString().padStart(3, '0');
    return `PKR ${amount < 0n ? '-' : ''}${digits.slice(0, -2)}.${digits.slice(-2)}`;
};

async function request(path, options) {
    const response = await apiRequest(path, options);
    if (response.status === 204) return null;
    const result = await response.json().catch(() => null);
    if (!response.ok || !result) {
        throw new Error(Object.values(result?.errors ?? {}).flat()[0] ?? result?.message ?? 'The request failed.');
    }
    return result;
}

function usePage(path, revision, refresh) {
    const [url, setUrl] = useState(path);
    const [page, setPage] = useState(null);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!url) return;
        const controller = new AbortController();
        setError('');
        request(url, { signal: controller.signal }).then(setPage).catch(error => {
            if (!controller.signal.aborted) setError(error.message);
        });
        return () => controller.abort();
    }, [url, revision]);
    return { page, error, setUrl, refresh };
}

function Pagination({ list }) {
    const previous = list.page?.prev_page_url ?? list.page?.links?.previous;
    const next = list.page?.next_page_url ?? list.page?.links?.next;
    return (previous || next) && <div className="flex gap-3">
        {previous && <button type="button" onClick={() => list.setUrl(previous)}>Previous page</button>}
        {next && <button type="button" onClick={() => list.setUrl(next)}>Next page</button>}
    </div>;
}

function useMutation() {
    const [busy, setBusy] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const pending = useRef(null);
    const inFlight = useRef(false);

    async function submit(path, body, onSuccess, { method = 'POST', replay = false } = {}) {
        if (inFlight.current) return;
        inFlight.current = true;
        setBusy(true);
        setFeedback(null);
        const payload = JSON.stringify(body);
        const fingerprint = `${path}:${payload}`;
        const headers = { 'Content-Type': 'application/json' };

        try {
            if (replay) {
                if (pending.current?.fingerprint !== fingerprint) {
                    const key = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
                    pending.current = { fingerprint, key };
                }
                headers['Idempotency-Key'] = pending.current.key;
            }
            const result = await request(path, { method, headers, body: payload });
            const text = onSuccess(result);
            pending.current = null;
            setFeedback({ text });
        } catch (error) {
            setFeedback({ error: true, text: error.message });
        } finally {
            inFlight.current = false;
            setBusy(false);
        }
    }

    return { busy, submit, feedback };
}

function Feedback({ state }) {
    return state && <p role={state.error ? 'alert' : 'status'}>{state.text}</p>;
}

function Posting({ path, list }) {
    const [received, setReceived] = useState('');
    const [selected, setSelected] = useState('');
    const [rows, setRows] = useState([]);
    const [validation, setValidation] = useState('');
    const mutation = useMutation();
    const charges = list.page?.data ?? [];
    const chosen = charges.find(charge => String(charge.id) === selected);

    function post(event) {
        event.preventDefault();
        setValidation('');
        const allocations = rows.map(row => {
            const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(row.amount);
            const minor = match ? BigInt(match[1]) * 100n + BigInt((match[2] ?? '').padEnd(2, '0')) : 0n;
            return { charge_id: row.id, amount_minor: Number(minor), available: row.remaining_minor };
        });
        const total = allocations.reduce((sum, allocation) => sum + allocation.amount_minor, 0);
        if (!Number.isSafeInteger(total) || total < 1 || allocations.some(item => item.amount_minor < 1 || item.amount_minor > item.available)) {
            setValidation('Enter a positive amount within each charge balance, using up to two decimal places.');
            return;
        }
        mutation.submit(path, {
            received_on: received, amount_minor: total,
            allocations: allocations.map(({ charge_id, amount_minor }) => ({ charge_id, amount_minor })),
        }, result => {
            setRows([]);
            list.refresh();
            return `Posted receipt #${result.data.receipt_id}.`;
        }, { replay: true });
    }

    return <section className="space-y-3">
        <h2>Post receipt</h2>
        <Feedback state={list.error && { error: true, text: list.error }} />
        <form onSubmit={post} className="space-y-3">
            <fieldset disabled={mutation.busy} className="space-y-3">
                <label className="block">Funds received on <input type="date" value={received} required onChange={event => setReceived(event.target.value)} /></label>
                <div className="flex flex-wrap items-end gap-2">
                    <label>Monthly charge <select value={selected} onChange={event => setSelected(event.target.value)}>
                        <option value="">Choose a charge</option>
                        {charges.filter(charge => !rows.some(row => row.id === charge.id)).map(charge =>
                            <option key={charge.id} value={charge.id}>{charge.admission_number} · {charge.billing_month} · #{charge.id} · {money(charge.remaining_minor)}</option>)}
                    </select></label>
                    <button type="button" disabled={!chosen} onClick={() => {
                        setRows([...rows, { ...chosen, amount: '' }]);
                        setSelected('');
                    }}>Add allocation</button>
                </div>
                <Pagination list={list} />
                {list.page && !charges.length && <p>No open charges with a remaining balance.</p>}
                {rows.map(row => <div key={row.id} className="flex flex-wrap items-end gap-2">
                    <label>Amount for {row.admission_number} · #{row.id}
                        <input inputMode="decimal" required value={row.amount} onChange={event => {
                            setRows(rows.map(item => item.id === row.id ? { ...item, amount: event.target.value } : item));
                        }} />
                    </label>
                    <button type="button" onClick={() => setRows(rows.filter(item => item.id !== row.id))}>Remove allocation</button>
                </div>)}
                <button className="primary-button" type="submit" disabled={!rows.length}>Post receipt</button>
            </fieldset>
        </form>
        <Feedback state={validation && { error: true, text: validation }} />
        <Feedback state={mutation.feedback} />
    </section>;
}

function Reversals({ path, list }) {
    const [receipt, setReceipt] = useState('');
    const [reason, setReason] = useState('duplicate');
    const mutation = useMutation();

    return <section className="space-y-3">
        <h2>Reverse receipt in full</h2>
        <Feedback state={list.error && { error: true, text: list.error }} />
        <form onSubmit={event => {
            event.preventDefault();
            mutation.submit(`${path}/${receipt}/reversal`, { reason }, result => {
                setReceipt('');
                list.refresh();
                return `Recorded reversal #${result.data.reversal_id}.`;
            }, { replay: true });
        }}>
            <fieldset disabled={mutation.busy} className="flex flex-wrap items-end gap-2">
                <label>Posted receipt <select required value={receipt} onChange={event => setReceipt(event.target.value)}>
                    <option value="">Choose a receipt</option>
                    {list.page?.data.map(item => <option key={item.id} value={item.id} disabled={!item.reversible}>
                        #{item.id} · {item.received_on} · {money(item.amount_minor)}{item.reversed ? ' · Reversed' : item.reversible ? '' : ' · No accounting period'}
                    </option>)}
                </select></label>
                <label>Reason <select value={reason} onChange={event => setReason(event.target.value)}>
                    <option value="duplicate">Duplicate receipt</option>
                    <option value="entry_error">Entry error</option>
                </select></label>
                <button className="primary-button" type="submit" disabled={!receipt}>Reverse in full</button>
            </fieldset>
        </form>
        <Pagination list={list} />
        <Feedback state={mutation.feedback} />
    </section>;
}

function Periods({ path, list, readOnly }) {
    const [zone, setZone] = useState(null);
    const [month, setMonth] = useState('');
    const mutation = useMutation();
    const calendar = list.page?.data;
    const open = calendar?.periods.find(period => period.status === 'open');
    const success = text => () => { list.refresh(); return text; };

    return <section className="space-y-3">
        <h2>Accounting periods</h2>
        <Feedback state={list.error && { error: true, text: list.error }} />
        {calendar && <>
            {calendar.periods.length || readOnly ? <p>Timezone {calendar.timezone ?? 'Not set'}</p> :
                <form onSubmit={event => {
                    event.preventDefault();
                    mutation.submit(`${path}/timezone`, { timezone: zone ?? calendar.timezone }, success('Timezone saved.'), { method: 'PATCH' });
                }} className="flex flex-wrap gap-2">
                    <label>Branch timezone <input placeholder="Asia/Karachi" value={zone ?? calendar.timezone ?? ''} onChange={event => setZone(event.target.value)} required /></label>
                    <button className="primary-button" disabled={mutation.busy}>Save timezone</button>
                </form>}
            {!readOnly && <form onSubmit={event => {
                event.preventDefault();
                mutation.submit(path, { month }, success('Accounting period opened.'));
            }} className="flex flex-wrap gap-2">
                <label>Accounting month <input type="month" required value={month} onChange={event => setMonth(event.target.value)} /></label>
                <button className="primary-button" disabled={mutation.busy || !calendar.timezone || Boolean(open)}>Open period</button>
            </form>}
            {calendar.periods.map(period => <p key={period.id}>{period.month} · {period.status} · {period.starts_on} to {period.ends_on}</p>)}
            {!readOnly && open && <button type="button" disabled={mutation.busy} onClick={() => {
                if (window.confirm(`Close ${open.month}? A closed period cannot be reopened.`)) {
                    mutation.submit(`${path}/${open.id}/close`, {}, success('Accounting period closed.'));
                }
            }}>Close {open.month}</button>}
        </>}
        <Feedback state={mutation.feedback} />
    </section>;
}

function Audit({ list }) {
    return <section className="space-y-3">
        <h2>Finance audit</h2>
        <Feedback state={list.error && { error: true, text: list.error }} />
        {list.page && !list.page.data.length && <p>No audit events.</p>}
        <ul>{list.page?.data.map(event => <li key={`${event.source}-${event.id}`}>
            {event.action} · {event.recorded_at} {event.timestamp_basis === 'utc' ? 'UTC' : '(legacy timestamp)'}
            {event.accounting_date && ` · Accounting date ${event.accounting_date}`}
            {event.receipt_id && ` · Receipt #${event.receipt_id} · ${money(event.amount_minor)}`}
            {event.reversal_id && ` · Reversal #${event.reversal_id}`}
            {event.month && ` · ${event.month}`}
        </li>)}</ul>
        <Pagination list={list} />
    </section>;
}

function Reconciliation({ base, list, selected = false }) {
    const [month, setMonth] = useState('');
    const [cutoff, setCutoff] = useState('');
    const report = list.page?.data;

    return <section className="space-y-3">
        <h2>{selected ? 'Selected finance reader' : 'Finance reconciliation'}</h2>
        <form className="flex flex-wrap items-end gap-2" onSubmit={event => {
            event.preventDefault();
            const filters = new URLSearchParams({ month, cutoff });
            const endpoint = selected ? 'finance-reader' : 'finance-reconciliation';
            list.setUrl(`${base}/${endpoint}?${filters}`);
        }}>
            <label>Report month <input type="month" value={month} required onChange={event => setMonth(event.target.value)} /></label>
            <label>Inclusive cutoff <input type="date" value={cutoff} required onChange={event => setCutoff(event.target.value)} /></label>
            <button className="primary-button" type="submit">{selected ? 'Run selected reader' : 'Run reconciliation'}</button>
        </form>
        <Feedback state={list.error && { error: true, text: list.error }} />
        {report && !list.error && <>
            <p>{report.month} through {report.cutoff} · {report.currency}</p>
            <div className={selected ? 'grid gap-4' : 'grid gap-4 md:grid-cols-2'}>
                {report.legacy && <div>
                    <h3>Legacy invoices and payments</h3>
                    <p>{report.legacy.collection_basis} {report.legacy.balance_basis}</p>
                    <dl>
                        <dt>Collections through cutoff</dt><dd>{money(report.legacy.collected_minor)}</dd>
                        <dt>Current unpaid invoices</dt><dd>{report.legacy.open_invoice_count_current}</dd>
                        <dt>Collectible at cutoff</dt><dd>{money(report.legacy.collectible_minor_at_cutoff)}</dd>
                    </dl>
                </div>}
                {report.v4 && <div>
                    <h3>v4 receipts and monthly charges</h3>
                    <p>{report.v4.activity_basis} {report.v4.balance_basis}</p>
                    {!report.v4.accounting_context_complete && <p role="status">
                        Provisional totals. Unassigned receipts: {report.v4.unassigned_receipt_count}.
                        Unassigned reversals: {report.v4.unassigned_reversal_count}.
                    </p>}
                    <dl>
                        <dt>Gross receipts</dt><dd>{money(report.v4.gross_minor)}</dd>
                        <dt>Reversal offsets</dt><dd>{money(report.v4.reversal_minor)}</dd>
                        <dt>Net movement</dt><dd>{money(report.v4.net_minor)}</dd>
                        <dt>Charge balance at cutoff</dt><dd>{money(report.v4.remaining_minor_at_cutoff)}</dd>
                    </dl>
                </div>}
            </div>
        </>}
    </section>;
}

function FinanceSection({ branchId, grant, revision, refresh }) {
    const base = `/api/schools/${branchId}`;
    const receipts = `${base}/posted-family-receipts`;
    const periods = `${base}/accounting-periods`;
    const path = { post: `${receipts}/charges`, reverse: receipts, periods, 'periods-read-only': periods, audit: `${base}/finance-audit` }[grant];
    const list = usePage(path ?? null, revision, refresh);

    switch (grant) {
        case 'post': return <Posting path={receipts} list={list} />;
        case 'reverse': return <Reversals path={receipts} list={list} />;
        case 'periods':
        case 'periods-read-only': return <Periods path={periods} list={list} readOnly={grant === 'periods-read-only'} />;
        case 'audit': return <Audit list={list} />;
        case 'reader': return <Reconciliation base={base} list={list} selected />;
        case 'reconciliation': return <Reconciliation base={base} list={list} />;
        default: return null;
    }
}

export default function FinancePilot({ branchId, grants }) {
    const [revision, setRevision] = useState(0);
    const refresh = () => setRevision(value => value + 1);

    return <div className="space-y-6">
        {grants.flatMap(grant => grant === 'reconciliation' ? ['reader', grant] : [grant]).map(grant => <FinanceSection key={grant} {...{ branchId, grant, revision, refresh }} />)}
    </div>;
}
