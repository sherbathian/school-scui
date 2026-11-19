import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'That request could not be completed.')
  return result.data
}

const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10)
const emptyInquiry = { first_name: '', last_name: '', guardian_name: '', guardian_phone: '', guardian_email: '', requested_grade_id: '', inquiry_date: today(), source: '', status: 'new', notes: '' }
const inquiryFields = Object.keys(emptyInquiry).map((key) => ({ key, label: key, required: ['first_name', 'last_name', 'inquiry_date'].includes(key) }))

export default function StudentInquiries({ school, canManage, academicsEnabled = true, onStartAdmission }) {
  const [rows, setRows] = useState([])
  const [grades, setGrades] = useState([])
  const [draft, setDraft] = useState(emptyInquiry)
  const [editingId, setEditingId] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [sort, setSort] = useState({ key: 'inquiry_date', direction: 'desc' })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    Promise.all([apiRequest(`/api/schools/${school.id}/student-inquiries`).then(readData), academicsEnabled ? apiRequest(`/api/schools/${school.id}/grades`).then(readData) : []])
      .then(([data, gradeRows]) => { if (active) { setRows(data); setGrades(gradeRows) } })
      .catch((loadError) => { if (active) setError(loadError.message) })
    return () => { active = false }
  }, [school.id, academicsEnabled])

  const filtered = useMemo(() => {
    const result = rows.filter((row) => `${row.first_name} ${row.last_name} ${row.guardian_name || ''} ${row.guardian_phone || ''} ${row.guardian_email || ''} ${row.requested_grade || ''} ${row.status} ${row.source || ''}`.toLowerCase().includes(search.toLowerCase()))
    return result.sort((a, b) => {
      const value = (row) => sort.key === 'student' ? `${row.last_name} ${row.first_name}` : row[sort.key]
      const comparison = String(value(a) ?? '').localeCompare(String(value(b) ?? ''), undefined, { numeric: true, sensitivity: 'base' })
      return sort.direction === 'asc' ? comparison : -comparison
    })
  }, [rows, search, sort])
  const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize))
  const currentPage = Math.min(page, pageCount)
  const visible = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize)
  useEffect(() => setPage(1), [search, pageSize])
  useEffect(() => { if (page > pageCount) setPage(pageCount) }, [page, pageCount])

  function setSortBy(key) { setSort((current) => ({ key, direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc' })) }
  async function importRow(row) {
    const grade = grades.find((item) => String(item.id) === String(row.requested_grade_id) || item.title.toLowerCase() === String(row.requested_grade_id).toLowerCase())
    const created = await readData(await apiRequest(`/api/schools/${school.id}/student-inquiries`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...row, requested_grade_id: grade?.id || null }) }))
    setRows((current) => [created, ...current])
  }

  async function save(event) {
    event.preventDefault(); setBusy(true); setError('')
    try {
      const response = await apiRequest(`/api/schools/${school.id}/student-inquiries${editingId ? `/${editingId}` : ''}`, { method: editingId ? 'PATCH' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...draft, requested_grade_id: draft.requested_grade_id ? Number(draft.requested_grade_id) : null }) })
      const saved = await readData(response)
      setRows((current) => editingId ? current.map((row) => row.id === saved.id ? saved : row) : [saved, ...current])
      setDraft(emptyInquiry); setEditingId(null); setShowForm(false)
    } catch (saveError) { setError(saveError.message) } finally { setBusy(false) }
  }

  async function remove(row) {
    if (!window.confirm(`Delete inquiry for ${row.first_name} ${row.last_name}?`)) return
    try { await readData(await apiRequest(`/api/schools/${school.id}/student-inquiries/${row.id}`, { method: 'DELETE' })); setRows((current) => current.filter((item) => item.id !== row.id)) } catch (deleteError) { setError(deleteError.message) }
  }

  const exportFields = [...inquiryFields, { key: 'requested_grade', label: 'requested_grade' }, { key: 'admission_number', label: 'admission_number' }]
  const pageWindow = Array.from({ length: pageCount }, (_, i) => i + 1).filter((value) => value === 1 || value === pageCount || Math.abs(value - currentPage) <= 1)

  return <section className="academic-panel inquiry-module">
    <header className="inquiry-heading"><div><p className="eyebrow">Student management</p><h2>Inquiries &amp; admissions</h2><p>Track prospective students from first contact through registration.</p></div>{canManage && !showForm ? <button className="primary-button small-button" type="button" onClick={() => { setDraft(emptyInquiry); setEditingId(null); setShowForm(true) }}>＋ New inquiry</button> : null}</header>
    {error ? <p className="form-error" role="alert">{error}</p> : null}
    {canManage ? <DataTools title="Student Inquiries" rows={rows} fields={inquiryFields} exportFields={exportFields} canImport onImport={importRow} /> : null}
    {showForm ? <form className="inquiry-form" onSubmit={save}><header><div><h3>{editingId ? 'Edit inquiry' : 'New student inquiry'}</h3><p>Capture contact details, grade interest, and next step.</p></div></header><div className="inquiry-form-grid">
      <label>Student first name *<input required value={draft.first_name} onChange={(e) => setDraft({ ...draft, first_name: e.target.value })} /></label><label>Student last name *<input required value={draft.last_name} onChange={(e) => setDraft({ ...draft, last_name: e.target.value })} /></label><label>Parent / guardian name<input value={draft.guardian_name} onChange={(e) => setDraft({ ...draft, guardian_name: e.target.value })} /></label><label>Phone<input value={draft.guardian_phone} onChange={(e) => setDraft({ ...draft, guardian_phone: e.target.value })} /></label><label>Email<input type="email" value={draft.guardian_email} onChange={(e) => setDraft({ ...draft, guardian_email: e.target.value })} /></label><label>Interested grade<select value={draft.requested_grade_id} onChange={(e) => setDraft({ ...draft, requested_grade_id: e.target.value })}><option value="">Not selected</option>{grades.map((grade) => <option key={grade.id} value={grade.id}>{grade.title}</option>)}</select></label><label>Inquiry date *<input type="date" required value={draft.inquiry_date} onChange={(e) => setDraft({ ...draft, inquiry_date: e.target.value })} /></label><label>Source<input placeholder="Walk-in, website, referral…" value={draft.source} onChange={(e) => setDraft({ ...draft, source: e.target.value })} /></label><label>Status<select value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}><option value="new">New</option><option value="contacted">Contacted</option><option value="visit_scheduled">Visit scheduled</option><option value="registered">Registered</option><option value="rejected">Rejected</option></select></label><label className="inquiry-wide">Notes<textarea rows="3" value={draft.notes} onChange={(e) => setDraft({ ...draft, notes: e.target.value })} /></label></div><div className="student-form-actions"><button className="primary-button small-button" disabled={busy}>{busy ? 'Saving…' : editingId ? 'Save inquiry' : 'Create inquiry'}</button><button className="quiet-button" type="button" onClick={() => { setShowForm(false); setEditingId(null) }}>Cancel</button></div></form> : null}
    {!showForm ? <><div className="inquiry-toolbar"><label className="module-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Quick search by student, parent, phone, grade or status" aria-label="Search student inquiries" value={search} onChange={(e) => setSearch(e.target.value)} /></label><span>{filtered.length} inquiries</span></div><div className="crud-table-wrap"><table className="crud-table inquiry-table"><thead><tr>{[['student', 'Student'], ['guardian_name', 'Parent / guardian'], ['guardian_phone', 'Phone'], ['requested_grade', 'Interested grade'], ['inquiry_date', 'Inquiry date'], ['status', 'Status']].map(([key, label]) => <th key={key}><button className="student-sort-button" type="button" onClick={() => setSortBy(key)}>{label}<span>{sort.key === key ? sort.direction === 'asc' ? '↑' : '↓' : '↕'}</span></button></th>)}<th>Actions</th></tr></thead><tbody>{visible.map((row) => <tr key={row.id}><td><strong>{row.first_name} {row.last_name}</strong><small>{row.guardian_email || row.source || 'Prospective student'}</small></td><td>{row.guardian_name || '—'}</td><td>{row.guardian_phone || '—'}</td><td>{row.requested_grade || '—'}</td><td>{row.inquiry_date}</td><td><span className={`inquiry-status inquiry-status-${row.status}`}>{row.status.replaceAll('_', ' ')}</span></td><td>{canManage ? <div className="expense-row-actions">{row.status !== 'registered' ? onStartAdmission && <button className="inquiry-admit-action" type="button" onClick={() => onStartAdmission?.(row)}>Start admission</button> : <span className="muted-copy">{row.admission_number || 'Registered'}</span>}<TableAction action="edit" label="Edit inquiry" onClick={() => { setDraft({ ...emptyInquiry, ...row, requested_grade_id: row.requested_grade_id ? String(row.requested_grade_id) : '' }); setEditingId(row.id); setShowForm(true) }} /><TableAction action="delete" label="Delete inquiry" danger onClick={() => remove(row)} /></div> : null}</td></tr>)}{!visible.length ? <tr><td colSpan="7" className="expense-empty-cell">No inquiries found.</td></tr> : null}</tbody></table></div><footer className="expense-table-footer"><span>Showing {filtered.length ? (currentPage - 1) * pageSize + 1 : 0}–{Math.min(currentPage * pageSize, filtered.length)} of {filtered.length} inquiries</span><label>Rows <select value={pageSize} onChange={(e) => setPageSize(Number(e.target.value))}><option>10</option><option>25</option><option>50</option></select></label><nav aria-label="Inquiry table pagination"><button aria-label="Previous page" type="button" disabled={currentPage <= 1} onClick={() => setPage(currentPage - 1)}>‹</button>{pageWindow.map((number, index) => <span key={number} className="inquiry-page-item">{index && number - pageWindow[index - 1] > 1 ? <i>…</i> : null}<button className={currentPage === number ? 'active' : ''} aria-current={currentPage === number ? 'page' : undefined} type="button" onClick={() => setPage(number)}>{number}</button></span>)}<button aria-label="Next page" type="button" disabled={currentPage >= pageCount} onClick={() => setPage(currentPage + 1)}>›</button></nav></footer></> : null}
  </section>
}
