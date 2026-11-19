import { ListingSection, RecordHeading } from '../../../components/admin-record.jsx'
import { Button } from '../../../components/ui/button.jsx'
import { Fragment, useEffect, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'
import TablePagination from './TablePagination.jsx'

const LOOKUPS = [
  { key: 'designations', label: 'Designations' },
  { key: 'departments', label: 'Departments' },
  { key: 'leave-types', label: 'Leave types' },
]

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'That request could not be completed.')
  return result.data
}

async function readPage(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'That request could not be completed.')
  return result
}

export default function StaffManagement({ school, canManage, academicsEnabled = true, activeView = 'staff-directory', onNavigate }) {
  const [lookups, setLookups] = useState({ designations: [], departments: [], 'leave-types': [] })
  const [staff, setStaff] = useState([])
  const [staffMeta, setStaffMeta] = useState({ current_page: 1, last_page: 1, per_page: 25, total: 0, from: null, to: null })
  const [staffLoading, setStaffLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [sessions, setSessions] = useState([])
  const [users, setUsers] = useState([])
  const [error, setError] = useState('')
  const [lookupTitles, setLookupTitles] = useState({})
  const [form, setForm] = useState({ staff_no: '', first_name: '', last_name: '', designation_id: '', department_id: '', gender: 'female', marital_status: '', birth_date: '', joining_date: '', basic_salary: '0.00', user_id: '' })
  const [expandedId, setExpandedId] = useState(null)
  const [balances, setBalances] = useState([])
  const [balanceForm, setBalanceForm] = useState({ leave_type_id: '', school_session_id: '', allocated: '', used: '0' })
  const [editingId, setEditingId] = useState(null)
  const [editName, setEditName] = useState({ first_name: '', last_name: '' })
  const [search, setSearch] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [sort, setSort] = useState({ key: 'last_name', direction: 'asc' })
  const [lookupSearch, setLookupSearch] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setSearchQuery(search.trim()), 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { setPage(1) }, [searchQuery, perPage])

  async function loadStaff(signal) {
    const params = new URLSearchParams({ page: String(page), per_page: String(perPage), search: searchQuery, sort: sort.key, direction: sort.direction })
    const result = await readPage(await apiRequest(`/api/schools/${school.id}/staff?${params}`, signal ? { signal } : undefined))
    setStaff(result.data)
    setStaffMeta(result.meta)
  }

  async function loadAllStaff() {
    const getPage = async (requestedPage) => {
      const params = new URLSearchParams({ page: String(requestedPage), per_page: '100', search: searchQuery, sort: sort.key, direction: sort.direction })
      return readPage(await apiRequest(`/api/schools/${school.id}/staff?${params}`))
    }
    const first = await getPage(1)
    const all = [...first.data]
    for (let start = 2; start <= first.meta.last_page; start += 5) {
      const pages = await Promise.all(Array.from({ length: Math.min(5, first.meta.last_page - start + 1) }, (_, index) => getPage(start + index)))
      pages.forEach((result) => all.push(...result.data))
    }
    return all
  }

  function refresh() {
    if (activeView !== 'staff-directory') return Promise.resolve()
    setStaffLoading(true)
    return loadStaff().catch((loadError) => setError(loadError.message)).finally(() => setStaffLoading(false))
  }

  function sortBy(key) {
    setPage(1)
    setSort((current) => ({ key, direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc' }))
  }

  function sortHeader(key, label) {
    const active = sort.key === key
    return <button className="student-sort-button" type="button" onClick={() => sortBy(key)}>{label}<span aria-hidden="true">{active ? (sort.direction === 'asc' ? '↑' : '↓') : '↕'}</span></button>
  }

  useEffect(() => {
    if (activeView !== 'staff-directory') return undefined
    let active = true
    const controller = new AbortController()
    setStaffLoading(true)
    loadStaff(controller.signal)
      .catch((loadError) => { if (active && loadError.name !== 'AbortError') setError(loadError.message) })
      .finally(() => { if (active) setStaffLoading(false) })
    return () => { active = false; controller.abort() }
  }, [school.id, page, perPage, searchQuery, sort.key, sort.direction, activeView])

  useEffect(() => {
    let active = true
    async function load() {
      try {
        const results = await Promise.all([
          ...LOOKUPS.map(({ key }) => apiRequest(`/api/schools/${school.id}/staff-lookups/${key}`).then(readData)),
          academicsEnabled ? apiRequest(`/api/schools/${school.id}/sessions`).then(readData) : [],
          canManage ? apiRequest(`/api/schools/${school.id}/users`).then(readData) : Promise.resolve([]),
        ])
        if (!active) return
        setLookups({ designations: results[0], departments: results[1], 'leave-types': results[2] })
        setSessions(results[3])
        setUsers(results[4])
      } catch (loadError) {
        if (active) setError(loadError.message)
      }
    }
    load()
    return () => { active = false }
  }, [school.id, canManage])

  async function addLookup(event, type) {
    event.preventDefault()
    const title = lookupTitles[type]?.trim()
    if (!title) return
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/staff-lookups/${type}`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title }),
      }))
      setLookups((current) => ({ ...current, [type]: [...current[type], created].sort((a, b) => a.title.localeCompare(b.title)) }))
      setLookupTitles((current) => ({ ...current, [type]: '' }))
    } catch (requestError) { setError(requestError.message) }
  }

  async function importLookup(type, row) {
    const created = await readData(await apiRequest(`/api/schools/${school.id}/staff-lookups/${type}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title: row.title }),
    }))
    setLookups((current) => ({ ...current, [type]: [...current[type], created].sort((left, right) => left.title.localeCompare(right.title)) }))
  }

  async function editLookup(item, type) {
    const title = window.prompt('Lookup title', item.title)
    if (!title || title.trim() === item.title) return
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/staff-lookups/${type}/${item.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title: title.trim() }),
      }))
      setLookups((current) => ({ ...current, [type]: current[type].map((row) => row.id === item.id ? updated : row) }))
    } catch (requestError) { setError(requestError.message) }
  }

  async function deleteLookup(item, type) {
    if (!window.confirm(`Delete ${item.title}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/staff-lookups/${type}/${item.id}`, { method: 'DELETE' }))
      setLookups((current) => ({ ...current, [type]: current[type].filter((row) => row.id !== item.id) }))
    } catch (requestError) { setError(requestError.message) }
  }

  async function addStaff(event) {
    event.preventDefault()
    setError('')
    const payload = {
      ...form,
      designation_id: Number(form.designation_id),
      department_id: Number(form.department_id),
      user_id: form.user_id ? Number(form.user_id) : null,
      marital_status: form.marital_status || null,
    }
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/staff`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      }))
      setForm({ staff_no: '', first_name: '', last_name: '', designation_id: '', department_id: '', gender: 'female', marital_status: '', birth_date: '', joining_date: '', basic_salary: '0.00', user_id: '' })
      setPage(1)
      onNavigate?.('staff-directory')
    } catch (requestError) { setError(requestError.message) }
  }

  async function importStaff(row) {
    const designation = lookups.designations.find((item) => item.title.toLowerCase() === row.designation.toLowerCase())
    const department = lookups.departments.find((item) => item.title.toLowerCase() === row.department.toLowerCase())
    if (!designation || !department) throw new Error('Designation or department does not match this school’s lookup list.')
    await readData(await apiRequest(`/api/schools/${school.id}/staff`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ staff_no: row.staff_no, first_name: row.first_name, last_name: row.last_name, designation_id: designation.id, department_id: department.id, gender: row.gender, birth_date: row.birth_date, joining_date: row.joining_date, basic_salary: row.basic_salary }),
    }))
  }

  async function saveName(person) {
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(editName),
      }))
      setStaff((current) => current.map((row) => row.id === updated.id ? updated : row))
      setEditingId(null)
      void refresh()
    } catch (requestError) { setError(requestError.message) }
  }

  async function archiveStaff(person) {
    if (!window.confirm(`Archive ${person.first_name} ${person.last_name}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}`, { method: 'DELETE' }))
      setStaff((current) => current.filter((row) => row.id !== person.id))
      setStaffMeta((current) => ({ ...current, total: Math.max(0, current.total - 1) }))
      if (staff.length === 1 && page > 1) setPage((current) => current - 1)
    } catch (requestError) { setError(requestError.message) }
  }

  const visibleStaff = staff

  async function toggleBalances(person) {
    setExpandedId((current) => current === person.id ? null : person.id)
    if (expandedId === person.id) return
    setError('')
    try {
      setBalances(await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}/leave-balances`)))
    } catch (requestError) { setError(requestError.message) }
  }

  async function addBalance(event, person) {
    event.preventDefault()
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}/leave-balances`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...balanceForm, leave_type_id: Number(balanceForm.leave_type_id), school_session_id: Number(balanceForm.school_session_id) }),
      }))
      setBalances((current) => [...current, created])
      setBalanceForm({ leave_type_id: '', school_session_id: '', allocated: '', used: '0' })
    } catch (requestError) { setError(requestError.message) }
  }

  async function importBalance(person, row) {
    const created = await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}/leave-balances`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ leave_type_id: Number(row.leave_type_id), school_session_id: Number(row.school_session_id), allocated: row.allocated, used: row.used }),
    }))
    setBalances((current) => [...current, created])
  }

  async function editBalance(balance, person) {
    const allocated = window.prompt('Allocated leave days', balance.allocated)
    if (allocated === null) return
    const used = window.prompt('Used leave days', balance.used)
    if (used === null) return
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}/leave-balances/${balance.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ allocated, used }),
      }))
      setBalances((current) => current.map((item) => item.id === updated.id ? updated : item))
    } catch (requestError) { setError(requestError.message) }
  }

  async function deleteBalance(balance, person) {
    if (!window.confirm('Delete this leave balance?')) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/staff/${person.id}/leave-balances/${balance.id}`, { method: 'DELETE' }))
      setBalances((current) => current.filter((item) => item.id !== balance.id))
    } catch (requestError) { setError(requestError.message) }
  }

  const editing = activeView === 'staff-directory' ? staff.find(person => person.id === editingId) : null
  if (editing) return <>
    <RecordHeading icon="edit" title="Edit staff member" name={editing.staff_no} back={() => setEditingId(null)} />
    {error && <p className="form-error" role="alert">{error}</p>}
    <form className="listing-full-form" onSubmit={event => { event.preventDefault(); saveName(editing) }}>
      <ListingSection icon="staff" title="Staff information">
        <label>First name<input value={editName.first_name} onChange={event => setEditName({ ...editName, first_name: event.target.value })} required /></label>
        <label>Last name<input value={editName.last_name} onChange={event => setEditName({ ...editName, last_name: event.target.value })} required /></label>
      </ListingSection>
      <div className="listing-save-bar"><Button type="button" variant="outline" onClick={() => setEditingId(null)}>Cancel</Button>
        <Button type="submit">Save profile</Button></div>
    </form>
  </>

  return (
    <section className="academic-panel" aria-labelledby={`staff-title-${school.id}`}>
      <div className="directory-heading"><h4 id={`staff-title-${school.id}`}>{activeView === 'staff-create' ? 'Add staff member' : activeView === 'staff-directory' ? 'Staff' : LOOKUPS.find(({ key }) => `staff-${key}` === activeView)?.label}</h4>{activeView === 'staff-directory' ? <span>{staffMeta.total} {staffMeta.total === 1 ? 'person' : 'people'}</span> : null}</div>
      {error ? <p className="form-error" role="alert">{error}</p> : null}
      {canManage ? (
        <>
          {LOOKUPS.filter(({ key }) => `staff-${key}` === activeView).map(({ key, label }) => (
            <section key={key}>
              <h5>{label}</h5>
              <DataTools title={label} rows={lookups[key]} fields={[{ key: 'title', label: 'title', required: true }]} canImport={canManage} onImport={(row) => importLookup(key, row)} />
              <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label={`Search ${label.toLowerCase()}`} placeholder={`Search ${label.toLowerCase()}`} value={lookupSearch} onChange={(event) => setLookupSearch(event.target.value)} /></label>
              <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>{label.slice(0, -1) || label}</th><th>Actions</th></tr></thead><tbody>{lookups[key].filter((item) => item.title.toLowerCase().includes(lookupSearch.toLowerCase())).map((item) => (
                <tr key={item.id}><td>{item.title}</td><td><TableAction action="edit" label={`Edit ${label.toLowerCase().replace(/s$/, '')}`} onClick={() => editLookup(item, key)} /><TableAction action="delete" label={`Delete ${label.toLowerCase().replace(/s$/, '')}`} danger onClick={() => deleteLookup(item, key)} /></td></tr>
              ))}</tbody></table></div>
              <form className="member-form-row" onSubmit={(event) => addLookup(event, key)}>
                <label className="visually-hidden" htmlFor={`lookup-${key}-${school.id}`}>New {label.toLowerCase().replace(/s$/, '')}</label>
                <input id={`lookup-${key}-${school.id}`} value={lookupTitles[key] || ''} onChange={(event) => setLookupTitles((current) => ({ ...current, [key]: event.target.value }))} placeholder={`Add ${label.toLowerCase().replace(/s$/, '')}`} required />
                <button className="primary-button small-button" type="submit">Add {label.replace(/s$/, '').toLowerCase()}</button>
              </form>
            </section>
          ))}
          {activeView === 'staff-create' ? <form className="add-staff-form" onSubmit={addStaff}>
            <h5>Add staff member</h5>
            <label htmlFor={`staff-no-${school.id}`}>Staff number</label><input id={`staff-no-${school.id}`} value={form.staff_no} onChange={(event) => setForm({ ...form, staff_no: event.target.value })} required />
            <label htmlFor={`staff-first-${school.id}`}>Staff first name</label><input id={`staff-first-${school.id}`} value={form.first_name} onChange={(event) => setForm({ ...form, first_name: event.target.value })} required />
            <label htmlFor={`staff-last-${school.id}`}>Staff last name</label><input id={`staff-last-${school.id}`} value={form.last_name} onChange={(event) => setForm({ ...form, last_name: event.target.value })} required />
            <label htmlFor={`staff-designation-${school.id}`}>Designation</label><select id={`staff-designation-${school.id}`} value={form.designation_id} onChange={(event) => setForm({ ...form, designation_id: event.target.value })} required><option value="">Choose a designation</option>{lookups.designations.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select>
            <label htmlFor={`staff-department-${school.id}`}>Department</label><select id={`staff-department-${school.id}`} value={form.department_id} onChange={(event) => setForm({ ...form, department_id: event.target.value })} required><option value="">Choose a department</option>{lookups.departments.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select>
            <label htmlFor={`staff-gender-${school.id}`}>Gender</label><select id={`staff-gender-${school.id}`} value={form.gender} onChange={(event) => setForm({ ...form, gender: event.target.value })}><option value="female">Female</option><option value="male">Male</option></select>
            <label htmlFor={`staff-marital-${school.id}`}>Marital status</label><select id={`staff-marital-${school.id}`} value={form.marital_status} onChange={(event) => setForm({ ...form, marital_status: event.target.value })}><option value="">Not provided</option>{['single', 'married', 'divorced', 'widowed'].map((value) => <option key={value} value={value}>{value}</option>)}</select>
            <label htmlFor={`staff-birth-${school.id}`}>Birth date</label><input id={`staff-birth-${school.id}`} type="date" value={form.birth_date} onChange={(event) => setForm({ ...form, birth_date: event.target.value })} required />
            <label htmlFor={`staff-joining-${school.id}`}>Joining date</label><input id={`staff-joining-${school.id}`} type="date" value={form.joining_date} onChange={(event) => setForm({ ...form, joining_date: event.target.value })} required />
            <label htmlFor={`staff-salary-${school.id}`}>Basic salary</label><input id={`staff-salary-${school.id}`} type="number" min="0" step="0.01" value={form.basic_salary} onChange={(event) => setForm({ ...form, basic_salary: event.target.value })} required />
            <label htmlFor={`staff-user-${school.id}`}>Linked account <span>(optional)</span></label><select id={`staff-user-${school.id}`} value={form.user_id} onChange={(event) => setForm({ ...form, user_id: event.target.value })}><option value="">No account linked</option>{users.map((person) => <option key={person.id} value={person.id}>{person.name} · {person.email}</option>)}</select>
            <button className="primary-button small-button" type="submit">Add staff member</button>
          </form> : null}
        </>
      ) : null}
      {activeView === 'staff-directory' ? <>{canManage ? <div className="roster-toolbar roster-add-toolbar"><span /> <button className="primary-button small-button" type="button" onClick={() => onNavigate?.('staff-create')}>＋ Add staff</button></div> : null}<DataTools title="Staff" rows={staff} fields={[{ key: 'staff_no', label: 'staff_no', required: true }, { key: 'first_name', label: 'first_name', required: true }, { key: 'last_name', label: 'last_name', required: true }, { key: 'designation', label: 'designation', required: true }, { key: 'department', label: 'department', required: true }, { key: 'gender', label: 'gender', required: true }, { key: 'birth_date', label: 'birth_date', required: true }, { key: 'joining_date', label: 'joining_date', required: true }, { key: 'basic_salary', label: 'basic_salary', required: true }, { key: 'status', label: 'status' }]} exportFields={[{ key: 'staff_no', label: 'staff_no' }, { key: 'first_name', label: 'first_name' }, { key: 'last_name', label: 'last_name' }, { key: 'designation', label: 'designation' }, { key: 'department', label: 'department' }, { key: 'gender', label: 'gender' }, { key: 'joining_date', label: 'joining_date' }, { key: 'status', label: 'status' }]} canImport={canManage} onImport={importStaff} onImportComplete={refresh} loadExportRows={loadAllStaff} /><label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search staff" placeholder="Search staff by name, staff no., designation or department" value={search} onChange={(event) => setSearch(event.target.value)} /></label></> : null}
      {activeView === 'staff-directory' && staffLoading ? <p className="muted-copy" role="status">Loading staff records…</p> : null}
      {activeView === 'staff-directory' && !staffLoading && visibleStaff.length ? <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>{sortHeader('last_name', 'Staff member')}</th><th>{sortHeader('staff_no', 'Staff number')}</th><th>{sortHeader('designation', 'Designation')}</th><th>{sortHeader('department', 'Department')}</th><th>{sortHeader('status', 'Status')}</th><th>Actions</th></tr></thead><tbody>{visibleStaff.map((person) => (
        <Fragment key={person.id}>
          <tr><td>{person.last_name}, {person.first_name}</td><td>{person.staff_no}</td><td>{person.designation}</td><td>{person.department}</td><td>{person.status}</td><td>{canManage ? <><TableAction action="edit" label="Edit staff member" onClick={() => { setEditingId(person.id); setEditName({ first_name: person.first_name, last_name: person.last_name }) }} /><TableAction action="archive" label="Archive staff member" danger onClick={() => archiveStaff(person)} /></> : null}<TableAction action="balances" label="Leave balances" expanded={expandedId === person.id} onClick={() => toggleBalances(person)} /></td></tr>
          {expandedId === person.id ? <tr><td colSpan="6"><div className="staff-balance-panel">
            <DataTools title={`Leave balances ${person.staff_no}`} rows={balances} fields={[{ key: 'leave_type_id', label: 'leave_type_id', required: true }, { key: 'school_session_id', label: 'school_session_id', required: true }, { key: 'allocated', label: 'allocated', required: true }, { key: 'used', label: 'used', required: true }]} canImport={canManage} onImport={(row) => importBalance(person, row)} />
            <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Leave type</th><th>Session</th><th>Used / allocated</th><th>Actions</th></tr></thead><tbody>{balances.map((balance) => <tr key={balance.id}><td>{balance.leave_type?.title}</td><td>{balance.school_session?.title}</td><td>{balance.used} / {balance.allocated} days</td><td>{canManage ? <><TableAction action="edit" label="Edit leave balance" onClick={() => editBalance(balance, person)} /><TableAction action="delete" label="Delete leave balance" danger onClick={() => deleteBalance(balance, person)} /></> : null}</td></tr>)}</tbody></table></div>
            {canManage ? <form className="add-staff-form" onSubmit={(event) => addBalance(event, person)}>
              <label htmlFor={`balance-type-${person.id}`}>Leave type</label><select id={`balance-type-${person.id}`} value={balanceForm.leave_type_id} onChange={(event) => setBalanceForm({ ...balanceForm, leave_type_id: event.target.value })} required><option value="">Choose a leave type</option>{lookups['leave-types'].map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select>
              <label htmlFor={`balance-session-${person.id}`}>School session</label><select id={`balance-session-${person.id}`} value={balanceForm.school_session_id} onChange={(event) => setBalanceForm({ ...balanceForm, school_session_id: event.target.value })} required><option value="">Choose a session</option>{sessions.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select>
              <label htmlFor={`balance-allocated-${person.id}`}>Allocated days</label><input id={`balance-allocated-${person.id}`} type="number" min="0" step="0.1" value={balanceForm.allocated} onChange={(event) => setBalanceForm({ ...balanceForm, allocated: event.target.value })} required />
              <label htmlFor={`balance-used-${person.id}`}>Used days</label><input id={`balance-used-${person.id}`} type="number" min="0" step="0.1" value={balanceForm.used} onChange={(event) => setBalanceForm({ ...balanceForm, used: event.target.value })} required />
              <button className="primary-button small-button" type="submit">Save leave balance</button>
            </form> : null}
          </div></td></tr> : null}
        </Fragment>
      ))}</tbody></table></div> : activeView === 'staff-directory' && !staffLoading ? <p className="muted-copy">No staff records match this search.</p> : null}
      {activeView === 'staff-directory' && !staffLoading ? <TablePagination meta={staffMeta} page={page} perPage={perPage} label="staff members" onPageChange={setPage} onPerPageChange={setPerPage} /> : null}
    </section>
  )
}
