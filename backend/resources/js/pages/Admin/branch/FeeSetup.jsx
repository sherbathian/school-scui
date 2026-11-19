import { useEffect, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'That request could not be completed.')
  return result.data
}

const emptyMaster = { school_session_id: '', fee_group_id: '', fee_type_id: '', billing_month: '', due_date: '', amount: '', fine_type: 'none', fine_value: '0.00' }

export default function FeeSetup({ school, activeSection = 'all', canManage = false, academicsEnabled = true }) {
  const [feeTypes, setFeeTypes] = useState([])
  const [discounts, setDiscounts] = useState([])
  const [groups, setGroups] = useState([])
  const [grades, setGrades] = useState([])
  const [sessions, setSessions] = useState([])
  const [masters, setMasters] = useState([])
  const [error, setError] = useState('')
  const [typeDraft, setTypeDraft] = useState({ title: '', description: '' })
  const [discountDraft, setDiscountDraft] = useState({ title: '', discount_type: 'percentage', value: '', description: '' })
  const [groupDraft, setGroupDraft] = useState({ title: '', description: '', allows_discount: true, grade_ids: [] })
  const [editingGroupId, setEditingGroupId] = useState(null)
  const [masterDraft, setMasterDraft] = useState(emptyMaster)
  const [editingMasterId, setEditingMasterId] = useState(null)
  const [assignmentMaster, setAssignmentMaster] = useState(null)
  const [assignmentStudents, setAssignmentStudents] = useState([])
  const [assignmentCharges, setAssignmentCharges] = useState([])
  const [selectedStudentIds, setSelectedStudentIds] = useState([])
  const [assignmentGradeId, setAssignmentGradeId] = useState('')
  const [assignmentSectionId, setAssignmentSectionId] = useState('')
  const [assignmentSearch, setAssignmentSearch] = useState('')
  const [assignmentNote, setAssignmentNote] = useState('')
  const [busy, setBusy] = useState(false)
  const [search, setSearch] = useState('')
  const matches = (record, text) => Object.values(record).some((value) => String(value ?? '').toLowerCase().includes(text.toLowerCase()))

  useEffect(() => {
    let active = true
    async function load() {
      try {
        const endpoints = [
          'fee-types', 'fee-discounts', 'fee-groups', 'grades', 'sessions', 'fee-masters',
        ].map((path) => !academicsEnabled && ['grades', 'sessions'].includes(path)
          ? []
          : apiRequest(`/api/schools/${school.id}/${path}`).then(readData))
        const [types, discountRows, groupRows, gradeRows, sessionRows, masterRows] = await Promise.all(endpoints)
        if (!active) return
        setFeeTypes(types)
        setDiscounts(discountRows)
        setGroups(groupRows)
        setGrades(gradeRows)
        setSessions(sessionRows.filter((session) => session.status === 'active'))
        setMasters(masterRows)
        setMasterDraft((current) => ({ ...current, school_session_id: String(sessionRows.find((session) => session.is_current)?.id || '') }))
      } catch (loadError) {
        if (active) setError(loadError.message)
      }
    }
    load()
    return () => { active = false }
  }, [school.id])

  async function addType(event) {
    event.preventDefault()
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/fee-types`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(typeDraft),
      }))
      setFeeTypes((current) => [...current, created].sort((a, b) => a.title.localeCompare(b.title)))
      setTypeDraft({ title: '', description: '' })
    } catch (requestError) { setError(requestError.message) }
  }

  async function importFeeRecord(resource, row, setRows) {
    const result = await readData(await apiRequest(`/api/schools/${school.id}/${resource}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(row),
    }))
    setRows((current) => [...current, result])
  }

  async function importMaster(row) {
    const activeSessionId = sessions.find((session) => session.is_current)?.id
    if (!activeSessionId) throw new Error('Set the active academic year before importing fee masters.')
    const master = await readData(await apiRequest(`/api/schools/${school.id}/fee-masters`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...row, school_session_id: Number(activeSessionId), fee_group_id: Number(row.fee_group_id), fee_type_id: Number(row.fee_type_id) }),
    }))
    setMasters((current) => [...current, master])
  }

  async function importGroup(row) {
    const gradeIds = row.grade_ids ? row.grade_ids.split(';').map((value) => Number(value.trim())).filter(Boolean) : []
    const group = await readData(await apiRequest(`/api/schools/${school.id}/fee-groups`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: row.title, description: row.description || null, allows_discount: !['false', '0', 'no'].includes(row.allows_discount.toLowerCase()), grade_ids: gradeIds }),
    }))
    setGroups((current) => [...current, group])
  }

  async function importAssignment(master, row) {
    const student = assignmentStudents.find((item) => item.admission_number.toLowerCase() === row.admission_number.toLowerCase())
    if (!student) throw new Error('Admission number does not match an active student in the current academic year.')
    await readData(await apiRequest(`/api/schools/${school.id}/fee-masters/${master.id}/assignments`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ student_ids: [student.id] }),
    }))
    setAssignmentCharges(await readData(await apiRequest(`/api/schools/${school.id}/fee-masters/${master.id}/assignments`)))
  }

  async function editType(type) {
    const title = window.prompt('Fee type title', type.title)
    if (!title || title.trim() === type.title) return
    const description = window.prompt('Description (optional)', type.description || '')
    if (description === null) return
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/fee-types/${type.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title: title.trim(), description }),
      }))
      setFeeTypes((current) => current.map((row) => row.id === updated.id ? updated : row))
    } catch (requestError) { setError(requestError.message) }
  }

  async function deleteType(type) {
    if (!window.confirm(`Delete fee type ${type.title}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-types/${type.id}`, { method: 'DELETE' }))
      setFeeTypes((current) => current.filter((row) => row.id !== type.id))
    } catch (requestError) { setError(requestError.message) }
  }

  async function addDiscount(event) {
    event.preventDefault()
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/fee-discounts`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(discountDraft),
      }))
      setDiscounts((current) => [...current, created].sort((a, b) => a.title.localeCompare(b.title)))
      setDiscountDraft({ title: '', discount_type: 'percentage', value: '', description: '' })
    } catch (requestError) { setError(requestError.message) }
  }

  async function editDiscount(discount) {
    const title = window.prompt('Discount title', discount.title)
    if (!title || title.trim() === discount.title) return
    const discountType = window.prompt('Discount type (percentage or fixed)', discount.discount_type)
    if (discountType === null) return
    const value = window.prompt('Discount value', discount.value)
    if (value === null) return
    const description = window.prompt('Description (optional)', discount.description || '')
    if (description === null) return
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${school.id}/fee-discounts/${discount.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title: title.trim(), discount_type: discountType, value, description }),
      }))
      setDiscounts((current) => current.map((row) => row.id === updated.id ? updated : row))
    } catch (requestError) { setError(requestError.message) }
  }

  async function deleteDiscount(discount) {
    if (!window.confirm(`Delete discount ${discount.title}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-discounts/${discount.id}`, { method: 'DELETE' }))
      setDiscounts((current) => current.filter((row) => row.id !== discount.id))
    } catch (requestError) { setError(requestError.message) }
  }

  function toggleGrade(gradeId) {
    setGroupDraft((current) => ({ ...current, grade_ids: current.grade_ids.includes(gradeId)
      ? current.grade_ids.filter((id) => id !== gradeId) : [...current.grade_ids, gradeId] }))
  }

  async function saveGroup(event) {
    event.preventDefault()
    setError('')
    setBusy(true)
    try {
      const endpoint = `/api/schools/${school.id}/fee-groups${editingGroupId ? `/${editingGroupId}` : ''}`
      const saved = await readData(await apiRequest(endpoint, {
        method: editingGroupId ? 'PATCH' : 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(groupDraft),
      }))
      setGroups((current) => editingGroupId ? current.map((row) => row.id === saved.id ? saved : row) : [...current, saved])
      setGroupDraft({ title: '', description: '', allows_discount: true, grade_ids: [] })
      setEditingGroupId(null)
    } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  function startEditGroup(group) {
    setEditingGroupId(group.id)
    setGroupDraft({ title: group.title, description: group.description || '', allows_discount: group.allows_discount, grade_ids: group.grades.map((grade) => grade.id) })
  }

  async function deleteGroup(group) {
    if (!window.confirm(`Delete fee group ${group.title}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-groups/${group.id}`, { method: 'DELETE' }))
      setGroups((current) => current.filter((row) => row.id !== group.id))
    } catch (requestError) { setError(requestError.message) }
  }

  async function saveMaster(event) {
    event.preventDefault()
    setError('')
    setBusy(true)
    try {
      const endpoint = `/api/schools/${school.id}/fee-masters${editingMasterId ? `/${editingMasterId}` : ''}`
      const saved = await readData(await apiRequest(endpoint, {
        method: editingMasterId ? 'PATCH' : 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...masterDraft, school_session_id: Number(masterDraft.school_session_id), fee_group_id: Number(masterDraft.fee_group_id), fee_type_id: Number(masterDraft.fee_type_id) }),
      }))
      setMasters((current) => editingMasterId ? current.map((row) => row.id === saved.id ? saved : row) : [...current, saved])
      setMasterDraft({ ...emptyMaster, school_session_id: String(sessions.find((session) => session.is_current)?.id || '') })
      setEditingMasterId(null)
    } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  function startEditMaster(master) {
    setEditingMasterId(master.id)
    setMasterDraft({ school_session_id: String(master.school_session.id), fee_group_id: String(master.fee_group.id), fee_type_id: String(master.fee_type.id), billing_month: master.billing_month, due_date: master.due_date, amount: master.amount, fine_type: master.fine_type, fine_value: master.fine_value })
  }

  async function openAssignments(master) {
    setError('')
    setAssignmentMaster(master)
    setSelectedStudentIds([])
    setAssignmentGradeId('')
    setAssignmentSectionId('')
    setAssignmentSearch('')
    setAssignmentNote('')
    try {
      const [students, charges] = await Promise.all([
        readData(await apiRequest(`/api/schools/${school.id}/students?academic_year=${encodeURIComponent(school.active_session?.academic_year || '')}`)),
        readData(await apiRequest(`/api/schools/${school.id}/fee-masters/${master.id}/assignments`)),
      ])
      setAssignmentStudents(students.filter((student) => student.status === 'active' && student.current_enrollment?.status === 'active'))
      setAssignmentCharges(charges)
    } catch (requestError) { setError(requestError.message) }
  }

  const filteredAssignmentStudents = assignmentStudents.filter((student) => {
    const enrollment = student.current_enrollment
    const search = assignmentSearch.trim().toLowerCase()
    return (!assignmentGradeId || String(enrollment?.grade.id) === assignmentGradeId)
      && (!assignmentSectionId || String(enrollment?.section.id) === assignmentSectionId)
      && (!search || `${student.admission_number} ${student.first_name} ${student.last_name}`.toLowerCase().includes(search))
  })

  async function assignFees(event) {
    event.preventDefault()
    if (!assignmentMaster || !selectedStudentIds.length) return
    setError('')
    setBusy(true)
    try {
      const payload = { student_ids: selectedStudentIds.map(Number) }
      if (assignmentGradeId) payload.grade_id = Number(assignmentGradeId)
      if (assignmentSectionId) payload.section_id = Number(assignmentSectionId)
      const result = await readData(await apiRequest(`/api/schools/${school.id}/fee-masters/${assignmentMaster.id}/assignments`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      }))
      setAssignmentCharges(await readData(await apiRequest(`/api/schools/${school.id}/fee-masters/${assignmentMaster.id}/assignments`)))
      setSelectedStudentIds([])
      setAssignmentNote(`Assigned ${result.assigned} student${result.assigned === 1 ? '' : 's'}; ${result.already_assigned} already had this monthly charge.`)
    } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  async function deleteMaster(master) {
    if (!window.confirm(`Delete ${master.fee_type.title} from ${master.fee_group.title}?`)) return
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-masters/${master.id}`, { method: 'DELETE' }))
      setMasters((current) => current.filter((row) => row.id !== master.id))
    } catch (requestError) { setError(requestError.message) }
  }

  return (
    <section className="fee-setup" aria-labelledby={`fee-setup-title-${school.id}`}>
      <h4 id={`fee-setup-title-${school.id}`}>Fee setup</h4>
      {error ? <p className="form-error" role="alert">{error}</p> : null}
      {(activeSection === 'all' || activeSection === 'fee-types') ? <section>
        <h5>Fee types</h5>
        <DataTools title="Fee types" rows={feeTypes} fields={[{ key: 'title', label: 'title', required: true }, { key: 'description', label: 'description' }]} canImport={canManage} onImport={(row) => importFeeRecord('fee-types', row, setFeeTypes)} />
        {canManage ? <form className="fee-setup-form" onSubmit={addType}>
          <label htmlFor={`setup-type-title-${school.id}`}>Fee type title</label><input id={`setup-type-title-${school.id}`} value={typeDraft.title} onChange={(event) => setTypeDraft({ ...typeDraft, title: event.target.value })} required />
          <label htmlFor={`setup-type-description-${school.id}`}>Fee type description</label><textarea id={`setup-type-description-${school.id}`} value={typeDraft.description} onChange={(event) => setTypeDraft({ ...typeDraft, description: event.target.value })} />
          <button className="primary-button small-button" type="submit">Add fee type</button>
        </form> : null}
        <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search fee types" placeholder="Search fee types" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Fee type</th><th>Description</th><th>Actions</th></tr></thead><tbody>{feeTypes.filter((item) => matches(item, search)).map((type) => <tr key={type.id}><td>{type.title}</td><td>{type.description || '—'}</td><td>{canManage ? <div className="expense-row-actions"><TableAction action="edit" label="Edit fee type" onClick={() => editType(type)} /><TableAction action="delete" label="Delete fee type" danger onClick={() => deleteType(type)} /></div> : null}</td></tr>)}</tbody></table></div>
      </section> : null}
      {(activeSection === 'all' || activeSection === 'discounts') ? <section>
        <h5>Discounts</h5>
        <DataTools title="Discounts" rows={discounts} fields={[{ key: 'title', label: 'title', required: true }, { key: 'discount_type', label: 'discount_type', required: true }, { key: 'value', label: 'value', required: true }, { key: 'description', label: 'description' }]} canImport={canManage} onImport={(row) => importFeeRecord('fee-discounts', row, setDiscounts)} />
        {canManage ? <form className="fee-setup-form" onSubmit={addDiscount}>
          <label htmlFor={`discount-title-${school.id}`}>Discount title</label><input id={`discount-title-${school.id}`} value={discountDraft.title} onChange={(event) => setDiscountDraft({ ...discountDraft, title: event.target.value })} required />
          <label htmlFor={`discount-type-${school.id}`}>Discount type</label><select id={`discount-type-${school.id}`} value={discountDraft.discount_type} onChange={(event) => setDiscountDraft({ ...discountDraft, discount_type: event.target.value })}><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select>
          <label htmlFor={`discount-value-${school.id}`}>Value {discountDraft.discount_type === 'percentage' ? '(0–100%)' : '(currency amount)'}</label><input id={`discount-value-${school.id}`} type="number" min="0" max={discountDraft.discount_type === 'percentage' ? '100' : undefined} step="0.01" value={discountDraft.value} onChange={(event) => setDiscountDraft({ ...discountDraft, value: event.target.value })} required />
          <label htmlFor={`discount-description-${school.id}`}>Discount description</label><textarea id={`discount-description-${school.id}`} value={discountDraft.description} onChange={(event) => setDiscountDraft({ ...discountDraft, description: event.target.value })} />
          <button className="primary-button small-button" type="submit">Add discount</button>
        </form> : null}
        <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search discounts" placeholder="Search discounts" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Discount</th><th>Type</th><th>Value</th><th>Description</th><th>Actions</th></tr></thead><tbody>{discounts.filter((item) => matches(item, search)).map((discount) => <tr key={discount.id}><td>{discount.title}</td><td>{discount.discount_type}</td><td>{discount.value}</td><td>{discount.description || '—'}</td><td>{canManage ? <div className="expense-row-actions"><TableAction action="edit" label="Edit discount" onClick={() => editDiscount(discount)} /><TableAction action="delete" label="Delete discount" danger onClick={() => deleteDiscount(discount)} /></div> : null}</td></tr>)}</tbody></table></div>
      </section> : null}
      {(activeSection === 'all' || activeSection === 'groups') ? <section>
        <h5>Fee groups</h5>
        <DataTools title="Fee groups" rows={groups.map((group) => ({ ...group, grade_ids: group.grades.map((grade) => grade.id).join('; ') }))} fields={[{ key: 'title', label: 'title', required: true }, { key: 'description', label: 'description' }, { key: 'allows_discount', label: 'allows_discount' }, { key: 'grade_ids', label: 'grade_ids' }]} canImport={canManage} onImport={importGroup} />
        {canManage ? <form className="fee-setup-form" onSubmit={saveGroup}>
          <label htmlFor={`group-title-${school.id}`}>Group title</label><input id={`group-title-${school.id}`} value={groupDraft.title} onChange={(event) => setGroupDraft({ ...groupDraft, title: event.target.value })} required />
          <label htmlFor={`group-description-${school.id}`}>Description</label><textarea id={`group-description-${school.id}`} value={groupDraft.description} onChange={(event) => setGroupDraft({ ...groupDraft, description: event.target.value })} />
          <label><input type="checkbox" checked={groupDraft.allows_discount} onChange={(event) => setGroupDraft({ ...groupDraft, allows_discount: event.target.checked })} /> Allows discounts</label>
          <fieldset className="fee-grade-options"><legend>Grades in this group</legend>{grades.map((grade) => <label key={grade.id}><input type="checkbox" checked={groupDraft.grade_ids.includes(grade.id)} onChange={() => toggleGrade(grade.id)} />{grade.title}</label>)}</fieldset>
          <button className="primary-button small-button" type="submit" disabled={busy}>{editingGroupId ? 'Save fee group' : 'Add fee group'}</button>
          {editingGroupId ? <button className="quiet-button" type="button" onClick={() => { setEditingGroupId(null); setGroupDraft({ title: '', description: '', allows_discount: true, grade_ids: [] }) }}>Cancel group edit</button> : null}
        </form> : null}
        <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search fee groups" placeholder="Search fee groups" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Group</th><th>Grades</th><th>Discounts</th><th>Actions</th></tr></thead><tbody>{groups.filter((item) => matches({ title: item.title, grades: item.grades.map((grade) => grade.title).join(' ') }, search)).map((group) => <tr key={group.id}><td>{group.title}</td><td>{group.grades.map((grade) => grade.title).join(', ') || '—'}</td><td>{group.allows_discount ? 'Allowed' : 'Not allowed'}</td><td>{canManage ? <div className="expense-row-actions"><TableAction action="edit" label="Edit fee group" onClick={() => startEditGroup(group)} /><TableAction action="delete" label="Delete fee group" danger onClick={() => deleteGroup(group)} /></div> : null}</td></tr>)}</tbody></table></div>
      </section> : null}
      {(activeSection === 'all' || activeSection === 'masters') ? <section>
        <h5>Fee masters</h5>
        <DataTools title="Fee masters" rows={masters.map((master) => ({ ...master, fee_group_id: master.fee_group.id, fee_type_id: master.fee_type.id }))} fields={[{ key: 'fee_group_id', label: 'fee_group_id', required: true }, { key: 'fee_type_id', label: 'fee_type_id', required: true }, { key: 'billing_month', label: 'billing_month', required: true }, { key: 'due_date', label: 'due_date', required: true }, { key: 'amount', label: 'amount', required: true }, { key: 'fine_type', label: 'fine_type' }, { key: 'fine_value', label: 'fine_value' }]} canImport={canManage} onImport={importMaster} />
        {canManage ? <form className="fee-setup-form" onSubmit={saveMaster}>
          <label>Active academic year</label><span className="active-year-label"><strong>{sessions.find((session) => String(session.id) === masterDraft.school_session_id)?.title || school.active_session?.title || 'Set an active year in Sessions'}</strong></span>
          <label htmlFor={`master-group-${school.id}`}>Fee group</label><select id={`master-group-${school.id}`} value={masterDraft.fee_group_id} onChange={(event) => setMasterDraft({ ...masterDraft, fee_group_id: event.target.value })} required><option value="">Choose a group</option>{groups.map((group) => <option key={group.id} value={group.id}>{group.title}</option>)}</select>
          <label htmlFor={`master-type-${school.id}`}>Fee type</label><select id={`master-type-${school.id}`} value={masterDraft.fee_type_id} onChange={(event) => setMasterDraft({ ...masterDraft, fee_type_id: event.target.value })} required><option value="">Choose a fee type</option>{feeTypes.map((type) => <option key={type.id} value={type.id}>{type.title}</option>)}</select>
          <label htmlFor={`master-month-${school.id}`}>Billing month</label><input id={`master-month-${school.id}`} type="month" value={masterDraft.billing_month} onChange={(event) => setMasterDraft({ ...masterDraft, billing_month: event.target.value })} required />
          <label htmlFor={`master-due-${school.id}`}>Due date</label><input id={`master-due-${school.id}`} type="date" value={masterDraft.due_date} onChange={(event) => setMasterDraft({ ...masterDraft, due_date: event.target.value })} required />
          <label htmlFor={`master-amount-${school.id}`}>Master amount</label><input id={`master-amount-${school.id}`} type="number" min="0" step="0.01" value={masterDraft.amount} onChange={(event) => setMasterDraft({ ...masterDraft, amount: event.target.value })} required />
          <label htmlFor={`master-fine-type-${school.id}`}>Fine type</label><select id={`master-fine-type-${school.id}`} value={masterDraft.fine_type} onChange={(event) => setMasterDraft({ ...masterDraft, fine_type: event.target.value, fine_value: event.target.value === 'none' ? '0.00' : masterDraft.fine_value })}><option value="none">None</option><option value="fixed">Fixed amount</option><option value="percentage">Percentage</option></select>
          <label htmlFor={`master-fine-value-${school.id}`}>Fine value</label><input id={`master-fine-value-${school.id}`} type="number" min="0" max={masterDraft.fine_type === 'percentage' ? '100' : undefined} step="0.01" value={masterDraft.fine_value} onChange={(event) => setMasterDraft({ ...masterDraft, fine_value: event.target.value })} disabled={masterDraft.fine_type === 'none'} required />
          <button className="primary-button small-button" type="submit" disabled={busy}>{editingMasterId ? 'Save fee master' : 'Add fee master'}</button>
          {editingMasterId ? <button className="quiet-button" type="button" onClick={() => { setEditingMasterId(null); setMasterDraft({ ...emptyMaster, school_session_id: String(sessions.find((session) => session.is_current)?.id || '') }) }}>Cancel master edit</button> : null}
        </form> : null}
        <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search fee masters" placeholder="Search fee masters" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Month and session</th><th>Fee group and type</th><th>Amount / due date</th><th>Fine</th><th>Actions</th></tr></thead><tbody>{masters.filter((master) => matches({ month: master.billing_month, year: master.school_session.title, group: master.fee_group.title, fee: master.fee_type.title }, search)).map((master) => {
          const isCurrentYear = String(master.school_session.id) === String(sessions.find((session) => session.is_current)?.id || '')
          return <tr key={master.id}><td>{master.billing_month}<small>{master.school_session.title}</small></td><td>{master.fee_group.title}<small>{master.fee_type.title}</small></td><td>{master.amount}<small>Due {master.due_date}</small></td><td>{master.fine_type} · {master.fine_value}</td><td>{canManage ? <div className="expense-row-actions"><TableAction action="edit" label="Edit fee master" onClick={() => startEditMaster(master)} disabled={!isCurrentYear} /><TableAction action="assign" label="Assign students" onClick={() => openAssignments(master)} disabled={!isCurrentYear} /><TableAction action="delete" label="Delete fee master" danger onClick={() => deleteMaster(master)} /></div> : null}</td></tr>
        })}</tbody></table></div>
        {assignmentMaster ? <div className="fee-assignment-panel">
          <div className="academic-section-heading"><h5>Assign {assignmentMaster.fee_type.title} · {assignmentMaster.billing_month}</h5><button className="quiet-button" type="button" onClick={() => setAssignmentMaster(null)}>Close</button></div>
          <DataTools title="Student fee assignments" rows={assignmentCharges.map((charge) => ({ admission_number: charge.student.admission_number, student: `${charge.student.first_name} ${charge.student.last_name}`, billing_month: charge.billing_month, amount: charge.amount, due_date: charge.due_date, status: charge.status }))} fields={[{ key: 'admission_number', label: 'admission_number', required: true }, { key: 'student', label: 'student' }, { key: 'billing_month', label: 'billing_month' }, { key: 'amount', label: 'amount' }, { key: 'due_date', label: 'due_date' }, { key: 'status', label: 'status' }]} canImport={canManage} onImport={(row) => importAssignment(assignmentMaster, row)} />
          {canManage ? <form onSubmit={assignFees}>
            <div className="fee-assignment-filters">
              <label>Grade<select value={assignmentGradeId} onChange={(event) => { setAssignmentGradeId(event.target.value); setAssignmentSectionId(''); setSelectedStudentIds([]) }}><option value="">All grades</option>{grades.filter((grade) => grade.status === 'active').map((grade) => <option key={grade.id} value={grade.id}>{grade.title}</option>)}</select></label>
              <label>Section<select value={assignmentSectionId} onChange={(event) => { setAssignmentSectionId(event.target.value); setSelectedStudentIds([]) }}><option value="">All sections</option>{grades.filter((grade) => !assignmentGradeId || String(grade.id) === assignmentGradeId).flatMap((grade) => grade.sections.filter((section) => section.status === 'active').map((section) => <option key={`${grade.id}-${section.id}`} value={section.id}>{grade.title} · {section.title}</option>))}</select></label>
              <label>Search students<input type="search" value={assignmentSearch} onChange={(event) => setAssignmentSearch(event.target.value)} placeholder="Name or admission number" /></label>
            </div>
            <div className="fee-assignment-list">{filteredAssignmentStudents.length ? filteredAssignmentStudents.map((student) => <label key={student.id} className="fee-assignment-student"><input type="checkbox" checked={selectedStudentIds.includes(String(student.id))} onChange={(event) => setSelectedStudentIds((current) => event.target.checked ? [...current, String(student.id)] : current.filter((id) => id !== String(student.id)))} /><span><strong>{student.last_name}, {student.first_name}</strong><small>{student.admission_number} · {student.current_enrollment.grade.title} · {student.current_enrollment.section.title}</small></span></label>) : <p className="muted-copy">No active students match these filters.</p>}</div>
            <button className="primary-button small-button" type="submit" disabled={busy || !selectedStudentIds.length}>{busy ? 'Assigning…' : `Assign ${selectedStudentIds.length || ''} selected student${selectedStudentIds.length === 1 ? '' : 's'}`}</button>
          </form> : null}
          <div className="fee-assigned-list"><strong>Already assigned for {assignmentMaster.billing_month}</strong>{assignmentCharges.length ? assignmentCharges.map((charge) => <span key={charge.id}>{charge.student.last_name}, {charge.student.first_name} · {charge.status} · {charge.amount}</span>) : <span>No student charges assigned yet.</span>}</div>
          {assignmentNote ? <p className="assignment-success" role="status">{assignmentNote}</p> : null}
        </div> : null}
      </section> : null}
    </section>
  )
}
