import { useEffect, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'
import TablePagination from './TablePagination.jsx'

const textFields = [
  ['father_name', 'Father name', 'text'], ['father_phone', 'Father phone', 'tel'], ['father_occupation', 'Father occupation', 'text'], ['father_cnic', 'Father CNIC', 'text'],
  ['mother_name', 'Mother name', 'text'], ['mother_phone', 'Mother phone', 'tel'], ['mother_occupation', 'Mother occupation', 'text'], ['mother_cnic', 'Mother CNIC', 'text'],
  ['guardian_name', 'Guardian name', 'text'], ['guardian_relation', 'Guardian relation', 'text'], ['guardian_email', 'Guardian email', 'email'], ['guardian_phone', 'Guardian phone', 'tel'], ['guardian_occupation', 'Guardian occupation', 'text'], ['guardian_cnic', 'Guardian CNIC', 'text'], ['guardian_address', 'Guardian address', 'textarea'],
]
const guardianKeys = textFields.map(([key]) => key)
const importFields = [{ key: 'source_parent_id', label: 'source_parent_id' }, { key: 'source_data', label: 'source_data' }, ...textFields.map(([key]) => ({ key, label: key })), { key: 'is_guardian_other', label: 'is_guardian_other' }]
const exportFields = ['id', 'source_parent_id', ...guardianKeys, 'is_guardian_other', 'students_count'].map((key) => ({ key, label: key }))

function emptyGuardian() {
  return Object.fromEntries([...guardianKeys, 'is_guardian_other'].map((key) => [key, key === 'is_guardian_other' ? false : '']))
}

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'That request could not be completed.')
  return result.data
}

async function readPage(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'That request could not be completed.')
  return result
}

export default function GuardianManagement({ school, canManage, onNavigate, onOpenStudent }) {
  const [guardians, setGuardians] = useState([])
  const [search, setSearch] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 25, total: 0, from: null, to: null })
  const [sort, setSort] = useState({ key: 'father_name', direction: 'asc' })
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [parentId, setParentId] = useState('')
  const [form, setForm] = useState(emptyGuardian)
  const [files, setFiles] = useState({})
  const [removeFiles, setRemoveFiles] = useState({})
  const [mediaUrls, setMediaUrls] = useState({})
  const [selectedGuardian, setSelectedGuardian] = useState(null)
  const [detailsLoading, setDetailsLoading] = useState(false)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setSearchQuery(search.trim()), 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { setPage(1) }, [searchQuery, perPage])

  async function loadGuardians(signal) {
    const params = new URLSearchParams({ page: String(page), per_page: String(perPage), search: searchQuery, sort: sort.key, direction: sort.direction })
    const result = await readPage(await apiRequest(`/api/schools/${school.id}/guardians?${params}`, signal ? { signal } : undefined))
    setGuardians(result.data)
    setMeta(result.meta)
  }

  async function loadAllGuardians() {
    const getPage = async (requestedPage) => {
      const params = new URLSearchParams({ page: String(requestedPage), per_page: '100', search: searchQuery, sort: sort.key, direction: sort.direction })
      return readPage(await apiRequest(`/api/schools/${school.id}/guardians?${params}`))
    }
    const first = await getPage(1)
    const all = [...first.data]
    for (let start = 2; start <= first.meta.last_page; start += 5) {
      const pages = await Promise.all(Array.from({ length: Math.min(5, first.meta.last_page - start + 1) }, (_, index) => getPage(start + index)))
      pages.forEach((result) => all.push(...result.data))
    }
    return all
  }

  useEffect(() => {
    let cancelled = false
    const controller = new AbortController()
    setLoading(true)
    setError('')
    loadGuardians(controller.signal)
      .catch((loadError) => { if (!cancelled && loadError.name !== 'AbortError') setError(loadError.message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true; controller.abort() }
  }, [school.id, page, perPage, searchQuery, sort.key, sort.direction])

  function sortBy(key) {
    setPage(1)
    setSort((current) => ({ key, direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc' }))
  }

  function sortHeader(key, label) {
    const active = sort.key === key
    return <button className="student-sort-button" type="button" onClick={() => sortBy(key)}>{label}<span aria-hidden="true">{active ? (sort.direction === 'asc' ? '↑' : '↓') : '↕'}</span></button>
  }

  function refresh() {
    setLoading(true)
    return loadGuardians().catch((loadError) => setError(loadError.message)).finally(() => setLoading(false))
  }

  function startCreate() {
    setEditingId(null)
    setParentId('')
    setForm(emptyGuardian())
    setFiles({})
    setRemoveFiles({})
    setMediaUrls({})
    setShowForm(true)
    setError('')
  }

  async function startEdit(guardian) {
    setBusy(true)
    setError('')
    try {
      const detail = await readData(await apiRequest(`/api/schools/${school.id}/guardians/${guardian.id}`))
      setEditingId(guardian.id)
      setParentId(String(detail.id))
      setForm(Object.fromEntries([...guardianKeys, 'is_guardian_other'].map((key) => [key, detail[key] ?? (key === 'is_guardian_other' ? false : '')])))
      setMediaUrls(Object.fromEntries(['father', 'mother', 'guardian'].map((kind) => [kind, detail[`${kind}_doc_url`] || ''])))
      setFiles({})
      setRemoveFiles({})
      setShowForm(true)
    } catch (loadError) { setError(loadError.message) } finally { setBusy(false) }
  }

  async function openDetails(guardian) {
    setDetailsLoading(true)
    setError('')
    try {
      setSelectedGuardian(await readData(await apiRequest(`/api/schools/${school.id}/guardians/${guardian.id}`)))
    } catch (loadError) { setError(loadError.message) } finally { setDetailsLoading(false) }
  }

  async function saveGuardian(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const payload = new FormData()
      Object.entries(form).forEach(([key, value]) => payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value ?? ''))
      Object.entries(files).forEach(([key, file]) => { if (file) payload.append(`${key}_doc`, file) })
      Object.entries(removeFiles).forEach(([key, value]) => payload.append(`remove_${key}_doc`, value ? '1' : '0'))
      if (editingId) payload.append('_method', 'PATCH')
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/guardians${editingId ? `/${editingId}` : ''}`, { method: 'POST', body: payload }))
      if (editingId) setGuardians((current) => current.map((item) => item.id === editingId ? { ...item, ...updated } : item))
      else setPage(1)
      setShowForm(false)
      if (!editingId) void refresh()
    } catch (saveError) { setError(saveError.message) } finally { setBusy(false) }
  }

  async function importGuardian(row) {
    const payload = Object.fromEntries(['source_parent_id', ...guardianKeys, 'is_guardian_other'].filter((key) => row[key] !== undefined && row[key] !== '').map((key) => [key, key === 'is_guardian_other' ? ['1', 'true', 'yes'].includes(String(row[key]).toLowerCase()) : row[key]]))
    if (row.source_data) {
      try { payload.source_data = JSON.parse(row.source_data) } catch { throw new Error('source_data must contain valid JSON.') }
    }
    await readData(await apiRequest(`/api/schools/${school.id}/guardians`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }))
  }

  async function deleteGuardian(guardian) {
    if (!window.confirm(`Delete guardian ${guardian.father_name}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/guardians/${guardian.id}`, { method: 'DELETE' }))
      setGuardians((current) => current.filter((item) => item.id !== guardian.id))
      setMeta((current) => ({ ...current, total: Math.max(0, current.total - 1) }))
      if (guardians.length === 1 && page > 1) setPage((current) => current - 1)
    } catch (deleteError) { setError(deleteError.message) }
  }

  function documentControl(kind, title) {
    const hasExisting = Boolean(mediaUrls[kind] || (editingId && guardians.find((guardian) => guardian.id === editingId)?.[`${kind}_doc_path`]))
    return <label className="guardian-file-field">{title}<span className="guardian-file-drop">{files[kind] ? files[kind].name : 'Drag and drop a file or browse'}<input type="file" accept=".pdf,image/png,image/jpeg" onChange={(event) => setFiles((current) => ({ ...current, [kind]: event.target.files?.[0] || null }))} /></span>{mediaUrls[kind] ? <a href={mediaUrls[kind]} target="_blank" rel="noreferrer">View current document</a> : null}{hasExisting && editingId ? <span className="student-file-remove"><input type="checkbox" checked={Boolean(removeFiles[kind])} onChange={(event) => setRemoveFiles((current) => ({ ...current, [kind]: event.target.checked }))} /> Remove current document</span> : <small>PDF or image · max 5 MB</small>}</label>
  }

  return <section className="academic-panel guardian-module" aria-labelledby={`guardians-heading-${school.id}`}>
    {!showForm && selectedGuardian ? <section className="guardian-details-page" aria-label="Guardian details">
      <button className="quiet-button guardian-back-button" type="button" onClick={() => setSelectedGuardian(null)}>← Back to guardians</button>
      <header className="guardian-details-hero"><span className="guardian-details-avatar">{selectedGuardian.father_name?.[0] || 'G'}</span><div><p className="eyebrow">Guardian profile · Parent ID {selectedGuardian.id}</p><h3>{selectedGuardian.father_name}</h3><p>{selectedGuardian.father_phone || 'No father phone'}{selectedGuardian.mother_name ? ` · ${selectedGuardian.mother_name}` : ''}</p></div><span className="guardian-child-count">{selectedGuardian.children?.length || 0} linked {selectedGuardian.children?.length === 1 ? 'student' : 'students'}</span>{canManage ? <button className="primary-button small-button" type="button" onClick={() => { setSelectedGuardian(null); startEdit({ id: selectedGuardian.id, father_name: selectedGuardian.father_name }) }}>Edit guardian</button> : null}</header>
      {error ? <p className="form-error" role="alert">{error}</p> : null}
      {detailsLoading ? <p className="muted-copy" role="status">Loading guardian details…</p> : <>
        <div className="guardian-details-grid">
          <section><h5>Father details</h5><dl>{[['Father name', 'father_name'], ['Phone', 'father_phone'], ['Occupation', 'father_occupation'], ['CNIC', 'father_cnic']].map(([label, key]) => <div key={key}><dt>{label}</dt><dd>{selectedGuardian[key] || '—'}</dd></div>)}</dl></section>
          <section><h5>Mother details</h5><dl>{[['Mother name', 'mother_name'], ['Phone', 'mother_phone'], ['Occupation', 'mother_occupation'], ['CNIC', 'mother_cnic']].map(([label, key]) => <div key={key}><dt>{label}</dt><dd>{selectedGuardian[key] || '—'}</dd></div>)}</dl></section>
          {selectedGuardian.is_guardian_other ? <section><h5>Other guardian</h5><dl>{[['Name', 'guardian_name'], ['Relation', 'guardian_relation'], ['Email', 'guardian_email'], ['Phone', 'guardian_phone'], ['Occupation', 'guardian_occupation'], ['CNIC', 'guardian_cnic'], ['Address', 'guardian_address']].map(([label, key]) => <div className={['guardian_email', 'guardian_address'].includes(key) ? 'guardian-detail-wide' : ''} key={key}><dt>{label}</dt><dd>{selectedGuardian[key] || '—'}</dd></div>)}</dl></section> : null}
        </div>
        <section className="guardian-documents-card"><h5>Documents</h5><div>{[['Father document', 'father'], ['Mother document', 'mother'], ...(selectedGuardian.is_guardian_other ? [['Guardian document', 'guardian']] : [])].map(([label, key]) => <article key={key}><span>{label}</span>{selectedGuardian[`${key}_doc_url`] ? <span className="student-file-links"><a href={selectedGuardian[`${key}_doc_url`]} target="_blank" rel="noreferrer">Open</a><a href={selectedGuardian[`${key}_doc_url`]} download>Download</a></span> : <small>No document uploaded</small>}</article>)}</div></section>
        <section className="guardian-children-card"><div className="academic-section-heading"><h5>Children</h5><span>{selectedGuardian.children?.length || 0}</span></div>{selectedGuardian.children?.length ? <div className="crud-table-wrap"><table className="crud-table guardian-children-table"><thead><tr><th>Student</th><th>Admission number</th><th>Current class</th><th>Status</th><th>Actions</th></tr></thead><tbody>{selectedGuardian.children.map((child) => <tr key={child.id}><td>{child.last_name}, {child.first_name}</td><td>{child.admission_number}</td><td>{child.current_enrollment ? `${child.current_enrollment.grade || '—'} · ${child.current_enrollment.section || '—'}` : 'Not enrolled'}<small>{child.current_enrollment?.academic_year || ''}</small></td><td>{child.status}</td><td><TableAction action="view" label={`View ${child.first_name} ${child.last_name}`} onClick={() => { onNavigate?.('students'); onOpenStudent?.(child) }} /></td></tr>)}</tbody></table></div> : <p className="muted-copy">No students are linked to this guardian yet.</p>}</section>
      </>}
    </section> : !showForm ? <>
      <div className="directory-heading"><div><h4 id={`guardians-heading-${school.id}`}>Guardians</h4><p>Manage parent and guardian details for {school.name}.</p></div><span>{meta.total} {meta.total === 1 ? 'guardian' : 'guardians'}</span></div>
      {error ? <p className="form-error" role="alert">{error}</p> : null}
      {canManage ? <DataTools title="Guardians" rows={guardians} fields={importFields} exportFields={exportFields} canImport onImport={importGuardian} onImportComplete={refresh} loadExportRows={loadAllGuardians} /> : null}
      <div className="guardian-list-toolbar"><label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search guardians" placeholder="Search by parent ID, parent name, phone or CNIC" value={search} onChange={(event) => setSearch(event.target.value)} /></label>{canManage ? <button className="primary-button small-button" type="button" onClick={startCreate}>＋ New guardian</button> : null}</div>
      {loading ? <p className="muted-copy" role="status">Loading guardians…</p> : null}
      {!loading && guardians.length ? <div className="crud-table-wrap"><table className="crud-table guardian-table"><thead><tr><th>{sortHeader('source_parent_id', 'Parent ID')}</th><th>{sortHeader('father_name', 'Father name')}</th><th>{sortHeader('father_phone', 'Father phone')}</th><th>{sortHeader('father_occupation', 'Father occupation')}</th><th>{sortHeader('father_cnic', 'Father CNIC')}</th><th>{sortHeader('mother_name', 'Mother name')}</th><th>{sortHeader('students_count', 'Students')}</th><th>Actions</th></tr></thead><tbody>{guardians.map((guardian) => <tr key={guardian.id}><td>{guardian.source_parent_id || guardian.id}</td><td>{guardian.father_name || '—'}</td><td>{guardian.father_phone || '—'}</td><td>{guardian.father_occupation || '—'}</td><td>{guardian.father_cnic || '—'}</td><td>{guardian.mother_name || '—'}</td><td>{guardian.students_count || 0}</td><td><TableAction action="view" label="View guardian and children" onClick={() => openDetails(guardian)} />{canManage ? <><TableAction action="edit" label="Edit guardian" onClick={() => startEdit(guardian)} disabled={busy} /><TableAction action="delete" label="Delete guardian" danger onClick={() => deleteGuardian(guardian)} /></> : null}</td></tr>)}</tbody></table></div> : null}
      {!loading && guardians.length === 0 ? <p className="muted-copy">No guardians match this search. Add a guardian or import a CSV file to get started.</p> : null}
      {!loading ? <TablePagination meta={meta} page={page} perPage={perPage} label="guardians" onPageChange={setPage} onPerPageChange={setPerPage} /> : null}
    </> : <form className="guardian-profile-form" onSubmit={saveGuardian}>
      <div className="student-form-heading"><div><p className="eyebrow">Guardians</p><h4>{editingId ? 'Edit guardian' : 'Create guardian'}</h4><p>Enter parent details and attach supporting documents.</p></div><button className="quiet-button" type="button" onClick={() => setShowForm(false)}>Cancel</button></div>
      {error ? <p className="form-error" role="alert">{error}</p> : null}
      {editingId ? <div className="guardian-parent-id"><span>Parent ID</span><strong>{parentId}</strong><small>Generated automatically</small></div> : null}
      <fieldset className="guardian-form-section"><legend>Father details</legend><div className="guardian-form-grid">{textFields.slice(0, 4).map(([key, label, type]) => <label key={key}>{label}{type === 'textarea' ? <textarea value={form[key]} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} /> : <input type={type} value={form[key]} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} required={['father_name', 'father_cnic'].includes(key)} />}</label>)}{documentControl('father', 'Father document')}</div></fieldset>
      <fieldset className="guardian-form-section"><legend>Mother details</legend><div className="guardian-form-grid">{textFields.slice(4, 8).map(([key, label, type]) => <label key={key}>{label}<input type={type} value={form[key]} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} required={['mother_name', 'mother_cnic'].includes(key)} /></label>)}{documentControl('mother', 'Mother document')}</div></fieldset>
      <fieldset className="guardian-form-section"><legend>Other guardian</legend><label className="student-form-check guardian-toggle"><input type="checkbox" checked={form.is_guardian_other} onChange={(event) => setForm((current) => ({ ...current, is_guardian_other: event.target.checked }))} /> Use another guardian</label>{form.is_guardian_other ? <div className="guardian-form-grid">{textFields.slice(8).map(([key, label, type]) => <label key={key}>{label}{type === 'textarea' ? <textarea rows="2" value={form[key]} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} /> : <input type={type} value={form[key]} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} required={['guardian_name', 'guardian_relation', 'guardian_phone'].includes(key)} />}</label>)}{documentControl('guardian', 'Guardian document')}</div> : null}</fieldset>
      <div className="student-form-actions"><button className="primary-button small-button" type="submit" disabled={busy}>{busy ? 'Saving…' : editingId ? 'Save changes' : 'Create guardian'}</button><button className="quiet-button" type="button" onClick={() => setShowForm(false)}>Cancel</button></div>
    </form>}
  </section>
}
