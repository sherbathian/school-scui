import { Detail, ListingSection, RecordHeading } from '../../../components/admin-record.jsx'
import { Button } from '../../../components/ui/button.jsx'
import { useEffect, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'That request could not be completed.')
  return result.data
}

export default function SchoolSessions({ school, canManage, onCurrentChange }) {
  const [sessions, setSessions] = useState([])
  const [title, setTitle] = useState('')
  const [startsOn, setStartsOn] = useState('')
  const [endsOn, setEndsOn] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [creating, setCreating] = useState(false)
  const [details, setDetails] = useState(null)
  const [editForm, setEditForm] = useState({ title: '', starts_on: '', ends_on: '' })
  const [search, setSearch] = useState('')
  const visibleSessions = sessions.filter((session) => `${session.title} ${session.starts_on} ${session.ends_on} ${session.status}`.toLowerCase().includes(search.toLowerCase()))

  useEffect(() => {
    let active = true
    apiRequest(`/api/schools/${school.id}/sessions`)
      .then(readData)
      .then((data) => { if (active) setSessions(data) })
      .catch((requestError) => { if (active) setError(requestError.message) })
    return () => { active = false }
  }, [school.id])

  async function createSession(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const session = await readData(await apiRequest(`/api/schools/${school.id}/sessions`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, starts_on: startsOn, ends_on: endsOn }),
      }))
      setSessions((current) => [...current.map((item) => session.is_current
        ? { ...item, is_current: false, current_marker: null }
        : item), session].sort((left, right) => right.starts_on.localeCompare(left.starts_on)))
      if (session.is_current) onCurrentChange?.(session)
      setTitle('')
      setStartsOn('')
      setEndsOn('')
      setCreating(false)
      setDetails(session)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusy(false)
    }
  }

  async function importSession(row) {
    const session = await readData(await apiRequest(`/api/schools/${school.id}/sessions`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: row.title, starts_on: row.starts_on, ends_on: row.ends_on }),
    }))
    setSessions((current) => [...current, session].sort((left, right) => right.starts_on.localeCompare(left.starts_on)))
  }

  async function makeCurrent(session) {
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/sessions/${session.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ is_current: true }),
      }))
      setSessions((current) => current.map((item) => item.id === updated.id ? updated : { ...item, is_current: false, current_marker: null }))
      onCurrentChange?.(updated)
    } catch (requestError) {
      setError(requestError.message)
    }
  }

  function startEditing(session) {
    setEditingId(session.id)
    setEditForm({ title: session.title, starts_on: session.starts_on, ends_on: session.ends_on })
  }

  async function saveSession(event, session) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/sessions/${session.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(editForm),
      }))
      setSessions((current) => current.map((item) => item.id === updated.id ? updated : item).sort((left, right) => right.starts_on.localeCompare(left.starts_on)))
      if (updated.is_current) onCurrentChange?.(updated)
      setEditingId(null)
      setDetails(updated)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusy(false)
    }
  }

  const editing = sessions.find(session => session.id === editingId)
  if (creating || editing) {
    const values = editing ? editForm : { title, starts_on: startsOn, ends_on: endsOn }
    const change = (field, value) => {
      if (editing) setEditForm(current => ({ ...current, [field]: value }))
      else if (field === 'title') setTitle(value)
      else if (field === 'starts_on') setStartsOn(value)
      else setEndsOn(value)
    }
    const close = () => { setEditingId(null); setCreating(false); setDetails(null); setError('') }
    return <>
      <RecordHeading icon={editing ? 'edit' : 'create'} title={editing ? 'Edit academic year' : 'New academic year'} name={editing?.title} back={close} />
      {error && <p className="form-error" role="alert">{error}</p>}
      <form className="listing-full-form" onSubmit={editing ? event => saveSession(event, editing) : createSession}>
        <ListingSection icon="attendance" title="Academic year">
          <label>Session title<input value={values.title} onChange={event => change('title', event.target.value)} maxLength={100} required /></label>
          <label>Start date<input type="date" value={values.starts_on} onChange={event => change('starts_on', event.target.value)} required /></label>
          <label>End date<input type="date" min={values.starts_on || undefined} value={values.ends_on} onChange={event => change('ends_on', event.target.value)} required /></label>
        </ListingSection>
        <div className="listing-save-bar"><Button type="button" variant="outline" onClick={close}>Cancel</Button>
          <Button type="submit" disabled={busy}>Save academic year</Button></div>
      </form>
    </>
  }

  if (details) return <>
    <RecordHeading icon="attendance" title="Academic year details" name={details.title} back={() => setDetails(null)}>
      {canManage && <Button onClick={() => startEditing(details)}>Edit academic year</Button>}
    </RecordHeading>
    <ListingSection icon="attendance" title="Academic year" readOnly>
      <Detail label="Session title" value={details.title} /><Detail label="Start date" value={details.starts_on} />
      <Detail label="End date" value={details.ends_on} /><Detail label="Status" value={details.status} />
      <Detail label="Current academic year" value={details.is_current ? 'Yes' : 'No'} />
    </ListingSection>
  </>

  return <section className="academic-panel" aria-label="Academic years">
    <div className="directory-heading">
      <h4>Academic years</h4>
      {canManage && <Button onClick={() => setCreating(true)}>New academic year</Button>}
    </div>
    {error && <p className="form-error" role="alert">{error}</p>}
    <DataTools title="School sessions" rows={sessions} fields={[{ key: 'title', label: 'title', required: true }, { key: 'starts_on', label: 'starts_on', required: true }, { key: 'ends_on', label: 'ends_on', required: true }, { key: 'status', label: 'status' }, { key: 'is_current', label: 'is_current' }]} canImport={canManage} onImport={importSession} />
    <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search academic years" placeholder="Search academic years" value={search} onChange={event => setSearch(event.target.value)} /></label>
    {visibleSessions.length ? <div className="crud-table-wrap"><table className="crud-table">
      <thead><tr><th>Session</th><th>Date range</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>{visibleSessions.map(session => <tr key={session.id}>
        <td><button type="button" className="record-name-link" onClick={() => setDetails(session)}>{session.title}</button>
          {session.is_current && <small>Active academic year</small>}</td>
        <td>{session.starts_on} – {session.ends_on}</td><td>{session.status}</td>
        <td>{canManage && !session.is_current && <TableAction action="current" label="Make current session" onClick={() => makeCurrent(session)} />}
          {canManage && <TableAction action="edit" label="Edit session" onClick={() => startEditing(session)} />}</td>
      </tr>)}</tbody>
    </table></div> : <p className="muted-copy">No academic years found.</p>}
  </section>
}
