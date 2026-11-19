import { Fragment, useEffect, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(result.errors?.title?.[0] || result.errors?.section_ids?.[0] || result.message || 'That request could not be completed.')
  }
  return result.data
}

function GradeCard({ schoolId, grade, sections, canManage, onUpdated }) {
  const [editing, setEditing] = useState(false)
  const [title, setTitle] = useState(grade.title)
  const [status, setStatus] = useState(grade.status)
  const [selectedSections, setSelectedSections] = useState(grade.sections.map((section) => section.id))
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  function toggleSection(sectionId) {
    setSelectedSections((current) => current.includes(sectionId)
      ? current.filter((id) => id !== sectionId)
      : [...current, sectionId])
  }

  async function saveGrade(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${schoolId}/grades/${grade.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, status, section_ids: selectedSections }),
      }))
      onUpdated(updated)
      setEditing(false)
    } catch (saveError) {
      setError(saveError.message)
    } finally {
      setBusy(false)
    }
  }

  async function archiveGrade() {
    setBusy(true)
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${schoolId}/grades/${grade.id}`, { method: 'DELETE' }))
      onUpdated({ ...grade, status: 'inactive' })
    } catch (archiveError) {
      setError(archiveError.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Fragment>
      <tr><td>{grade.title}<small>{grade.status}</small></td><td>{grade.sections.map((section) => section.title).join(', ') || '—'}</td><td>{canManage ? <><TableAction action="edit" label={editing ? 'Cancel edit' : 'Edit grade'} onClick={() => { setTitle(grade.title); setStatus(grade.status); setSelectedSections(grade.sections.map((section) => section.id)); setEditing((current) => !current) }} />{grade.status === 'active' ? <TableAction action="archive" label="Archive grade" danger onClick={archiveGrade} disabled={busy} /> : null}</> : null}</td></tr>
      {editing ? <tr><td colSpan="3"><form className="academic-edit-form" onSubmit={saveGrade}>
        <label htmlFor={`grade-title-${grade.id}`}>Grade title</label><input id={`grade-title-${grade.id}`} value={title} onChange={(event) => setTitle(event.target.value)} required />
        <label htmlFor={`grade-status-${grade.id}`}>Status</label><select id={`grade-status-${grade.id}`} value={status} onChange={(event) => setStatus(event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select>
        <fieldset className="section-checklist"><legend>Assigned sections</legend>{sections.filter((section) => section.status === 'active').map((section) => <label key={section.id}><input type="checkbox" checked={selectedSections.includes(section.id)} onChange={() => toggleSection(section.id)} />{section.title}</label>)}{sections.filter((section) => section.status === 'active').length === 0 ? <p className="muted-copy">Add an active section first.</p> : null}</fieldset>
        <button className="primary-button small-button" type="submit" disabled={busy}>Save grade</button>
      </form>{error ? <p className="form-error" role="alert">{error}</p> : null}</td></tr> : error ? <tr><td colSpan="3"><p className="form-error" role="alert">{error}</p></td></tr> : null}
    </Fragment>
  )
}

function SectionCard({ schoolId, section, canManage, onUpdated }) {
  const [editing, setEditing] = useState(false)
  const [title, setTitle] = useState(section.title)
  const [status, setStatus] = useState(section.status)
  const [error, setError] = useState('')

  async function saveSection(event) {
    event.preventDefault()
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${schoolId}/sections/${section.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, status }),
      }))
      onUpdated(updated)
      setEditing(false)
    } catch (saveError) {
      setError(saveError.message)
    }
  }

  async function archiveSection() {
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${schoolId}/sections/${section.id}`, { method: 'DELETE' }))
      onUpdated({ ...section, status: 'inactive' })
    } catch (archiveError) {
      setError(archiveError.message)
    }
  }

  return (
    <Fragment>
      <tr>
        <td>{section.title}</td><td>{section.status}</td>
        <td>{canManage ? <><TableAction action="edit" label="Edit section" onClick={() => { setTitle(section.title); setStatus(section.status); setEditing(true) }} />{section.status === 'active' ? <TableAction action="archive" label="Archive section" danger onClick={archiveSection} /> : null}</> : null}</td>
      </tr>
      {editing ? <tr><td colSpan="3"><form className="section-edit-form" onSubmit={saveSection}>
        <label htmlFor={`section-title-${section.id}`}>Section title</label><input id={`section-title-${section.id}`} value={title} onChange={(event) => setTitle(event.target.value)} required />
        <label htmlFor={`section-status-${section.id}`}>Status</label><select id={`section-status-${section.id}`} value={status} onChange={(event) => setStatus(event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select>
        <button className="quiet-button" type="submit">Save</button><button className="quiet-button" type="button" onClick={() => setEditing(false)}>Cancel</button>
      </form>{error ? <p className="form-error" role="alert">{error}</p> : null}</td></tr> : error ? <tr><td colSpan="3"><p className="form-error" role="alert">{error}</p></td></tr> : null}
    </Fragment>
  )
}

function SubjectCard({ schoolId, subject, grades, canManage, onUpdated }) {
  const [editing, setEditing] = useState(false)
  const [title, setTitle] = useState(subject.title)
  const [status, setStatus] = useState(subject.status)
  const [selectedGrades, setSelectedGrades] = useState(subject.grades.map((grade) => grade.id))
  const [error, setError] = useState('')

  function toggleGrade(gradeId) {
    setSelectedGrades((current) => current.includes(gradeId)
      ? current.filter((id) => id !== gradeId)
      : [...current, gradeId])
  }

  async function saveSubject(event) {
    event.preventDefault()
    setError('')
    try {
      const updated = await readData(await apiRequest(`/api/schools/${schoolId}/subjects/${subject.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, status, grade_ids: selectedGrades }),
      }))
      onUpdated(updated)
      setEditing(false)
    } catch (saveError) {
      setError(saveError.message)
    }
  }

  async function archiveSubject() {
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${schoolId}/subjects/${subject.id}`, { method: 'DELETE' }))
      onUpdated({ ...subject, status: 'inactive' })
    } catch (archiveError) {
      setError(archiveError.message)
    }
  }

  return (
    <Fragment>
      <tr><td>{subject.title}<small>{subject.status}</small></td><td>{subject.grades.map((grade) => grade.title).join(', ') || '—'}</td><td>{canManage ? <><TableAction action="edit" label={editing ? 'Cancel edit' : 'Edit subject'} onClick={() => { setTitle(subject.title); setStatus(subject.status); setSelectedGrades(subject.grades.map((grade) => grade.id)); setEditing((current) => !current) }} />{subject.status === 'active' ? <TableAction action="archive" label="Archive subject" danger onClick={archiveSubject} /> : null}</> : null}</td></tr>
      {editing ? <tr><td colSpan="3"><form className="academic-edit-form" onSubmit={saveSubject}>
        <label htmlFor={`subject-title-${subject.id}`}>Subject title</label><input id={`subject-title-${subject.id}`} value={title} onChange={(event) => setTitle(event.target.value)} required />
        <label htmlFor={`subject-status-${subject.id}`}>Status</label><select id={`subject-status-${subject.id}`} value={status} onChange={(event) => setStatus(event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select>
        <fieldset className="section-checklist"><legend>Assigned grades</legend>{grades.filter((grade) => grade.status === 'active').map((grade) => <label key={grade.id}><input type="checkbox" checked={selectedGrades.includes(grade.id)} onChange={() => toggleGrade(grade.id)} />{grade.title}</label>)}{grades.filter((grade) => grade.status === 'active').length === 0 ? <p className="muted-copy">Add an active grade first.</p> : null}</fieldset>
        <button className="primary-button small-button" type="submit">Save subject</button>
      </form>{error ? <p className="form-error" role="alert">{error}</p> : null}</td></tr> : error ? <tr><td colSpan="3"><p className="form-error" role="alert">{error}</p></td></tr> : null}
    </Fragment>
  )
}

export default function AcademicStructure({ school, canManage, expanded = false, standalone = false, activeSection = 'all' }) {
  const [open, setOpen] = useState(expanded)
  const [loading, setLoading] = useState(false)
  const [loaded, setLoaded] = useState(false)
  const [grades, setGrades] = useState([])
  const [sections, setSections] = useState([])
  const [subjects, setSubjects] = useState([])
  const [gradeTitle, setGradeTitle] = useState('')
  const [gradeSectionIds, setGradeSectionIds] = useState([])
  const [sectionTitle, setSectionTitle] = useState('')
  const [subjectTitle, setSubjectTitle] = useState('')
  const [subjectGradeIds, setSubjectGradeIds] = useState([])
  const [search, setSearch] = useState('')
  const [error, setError] = useState('')
  const matchingSections = sections.filter((item) => `${item.title} ${item.status}`.toLowerCase().includes(search.toLowerCase()))
  const matchingGrades = grades.filter((item) => `${item.title} ${item.status} ${item.sections.map((section) => section.title).join(' ')}`.toLowerCase().includes(search.toLowerCase()))
  const matchingSubjects = subjects.filter((item) => `${item.title} ${item.status} ${item.grades.map((grade) => grade.title).join(' ')}`.toLowerCase().includes(search.toLowerCase()))

  useEffect(() => {
    if (!open || loaded) return undefined
    const controller = new AbortController()

    async function loadStructure() {
      setLoading(true)
      setError('')
      try {
        const [gradeResponse, sectionResponse, subjectResponse] = await Promise.all([
          apiRequest(`/api/schools/${school.id}/grades`, { signal: controller.signal }),
          apiRequest(`/api/schools/${school.id}/sections`, { signal: controller.signal }),
          apiRequest(`/api/schools/${school.id}/subjects`, { signal: controller.signal }),
        ])
        const [gradeData, sectionData, subjectData] = await Promise.all([readData(gradeResponse), readData(sectionResponse), readData(subjectResponse)])
        setGrades(gradeData)
        setSections(sectionData)
        setSubjects(subjectData)
        setLoaded(true)
      } catch (loadError) {
        if (!controller.signal.aborted) setError(loadError.message)
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }

    loadStructure()
    return () => controller.abort()
  }, [open, loaded, school.id])

  function updateGrade(updated) {
    setGrades((current) => current.map((grade) => grade.id === updated.id ? updated : grade))
  }

  function updateSection(updated) {
    setSections((current) => current.map((section) => section.id === updated.id ? updated : section))
  }

  function updateSubject(updated) {
    setSubjects((current) => current.map((subject) => subject.id === updated.id ? updated : subject))
  }

  async function createSection(event) {
    event.preventDefault()
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/sections`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: sectionTitle }),
      }))
      setSections((current) => [...current, created].sort((left, right) => left.title.localeCompare(right.title)))
      setSectionTitle('')
    } catch (createError) {
      setError(createError.message)
    }
  }

  function toggleGradeSection(sectionId) {
    setGradeSectionIds((current) => current.includes(sectionId)
      ? current.filter((id) => id !== sectionId)
      : [...current, sectionId])
  }

  async function createGrade(event) {
    event.preventDefault()
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/grades`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: gradeTitle, section_ids: gradeSectionIds }),
      }))
      setGrades((current) => [...current, created].sort((left, right) => left.title.localeCompare(right.title)))
      setGradeTitle('')
      setGradeSectionIds([])
    } catch (createError) {
      setError(createError.message)
    }
  }

  function toggleSubjectGrade(gradeId) {
    setSubjectGradeIds((current) => current.includes(gradeId)
      ? current.filter((id) => id !== gradeId)
      : [...current, gradeId])
  }

  async function createSubject(event) {
    event.preventDefault()
    setError('')
    try {
      const created = await readData(await apiRequest(`/api/schools/${school.id}/subjects`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: subjectTitle, grade_ids: subjectGradeIds }),
      }))
      setSubjects((current) => [...current, created].sort((left, right) => left.title.localeCompare(right.title)))
      setSubjectTitle('')
      setSubjectGradeIds([])
    } catch (createError) {
      setError(createError.message)
    }
  }

  async function importRecord(resource, row) {
    const payload = { title: row.title, ...(row.status ? { status: row.status } : {}) }
    if (resource === 'grades' && row.sections) {
      const selectedSections = row.sections.split(';').map((title) => title.trim().toLowerCase()).filter(Boolean)
      payload.section_ids = selectedSections.map((title) => {
        const section = sections.find((item) => item.title.toLowerCase() === title)
        if (!section) throw new Error(`Section “${title}” does not exist in this school.`)
        return section.id
      })
    }
    if (resource === 'subjects' && row.grades) {
      const selectedGrades = row.grades.split(';').map((title) => title.trim().toLowerCase()).filter(Boolean)
      payload.grade_ids = selectedGrades.map((title) => {
        const grade = grades.find((item) => item.title.toLowerCase() === title)
        if (!grade) throw new Error(`Grade “${title}” does not exist in this school.`)
        return grade.id
      })
    }
    const created = await readData(await apiRequest(`/api/schools/${school.id}/${resource}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }))
    const setRows = resource === 'sections' ? setSections : resource === 'grades' ? setGrades : setSubjects
    setRows((current) => [...current, created].sort((left, right) => left.title.localeCompare(right.title)))
  }

  return (
    <div className="academic-structure">
      {!standalone ? <button className="team-toggle" type="button" aria-expanded={open} onClick={() => setOpen((current) => !current)}>
        {open ? 'Hide academic structure' : 'View academic structure'}
      </button> : null}
      {open ? (
        <div className="academic-panel">
          {error ? <p className="form-error" role="alert">{error}</p> : null}
          {loading ? <p className="muted-copy" role="status">Loading academic structure…</p> : null}
          {!loading ? (
            <>
              {(activeSection === 'all' || activeSection === 'sections') ? <section aria-labelledby={`sections-heading-${school.id}`}>
                <div className="academic-section-heading">
                  <h4 id={`sections-heading-${school.id}`}>Sections</h4>
                  <span>{sections.length}</span>
                </div>
                <DataTools title="Sections" rows={sections} fields={[{ key: 'title', label: 'title', required: true }, { key: 'status', label: 'status' }]} canImport={canManage} onImport={(row) => importRecord('sections', row)} />
                {canManage ? (
                  <form className="add-academic-form" onSubmit={createSection}>
                    <label htmlFor={`new-section-${school.id}`}>New section name</label>
                    <input id={`new-section-${school.id}`} value={sectionTitle} onChange={(event) => setSectionTitle(event.target.value)} required />
                    <button className="primary-button small-button" type="submit">Add section</button>
                  </form>
                ) : null}
                <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search sections" placeholder="Search sections" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
                {sections.length ? (
                  <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Section</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                    {matchingSections.map((section) => <SectionCard key={section.id} schoolId={school.id} section={section} canManage={canManage} onUpdated={updateSection} />)}
                  </tbody></table></div>
                ) : <p className="muted-copy">No sections yet.</p>}
              </section> : null}

              {(activeSection === 'all' || activeSection === 'grades') ? <section className="grades-section" aria-labelledby={`grades-heading-${school.id}`}>
                <div className="academic-section-heading">
                  <h4 id={`grades-heading-${school.id}`}>Grades</h4>
                  <span>{grades.length}</span>
                </div>
                <DataTools title="Grades" rows={grades.map((grade) => ({ ...grade, sections: grade.sections.map((section) => section.title).join('; ') }))} fields={[{ key: 'title', label: 'title', required: true }, { key: 'status', label: 'status' }, { key: 'sections', label: 'sections' }]} canImport={canManage} onImport={(row) => importRecord('grades', row)} />
                {canManage ? (
                  <form className="add-grade-form" onSubmit={createGrade}>
                    <label htmlFor={`new-grade-${school.id}`}>New grade name</label>
                    <input id={`new-grade-${school.id}`} value={gradeTitle} onChange={(event) => setGradeTitle(event.target.value)} required />
                    <fieldset className="section-checklist">
                      <legend>Assign sections</legend>
                      {sections.filter((section) => section.status === 'active').map((section) => (
                        <label key={section.id}>
                          <input type="checkbox" checked={gradeSectionIds.includes(section.id)} onChange={() => toggleGradeSection(section.id)} />
                          {section.title}
                        </label>
                      ))}
                      {sections.filter((section) => section.status === 'active').length === 0 ? <p className="muted-copy">Add an active section first.</p> : null}
                    </fieldset>
                    <button className="primary-button small-button" type="submit">Add grade</button>
                  </form>
                ) : null}
                <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search grades" placeholder="Search grades" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
                {grades.length ? (
                  <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Grade</th><th>Assigned sections</th><th>Actions</th></tr></thead><tbody>
                    {matchingGrades.map((grade) => <GradeCard key={grade.id} schoolId={school.id} grade={grade} sections={sections} canManage={canManage} onUpdated={updateGrade} />)}
                  </tbody></table></div>
                ) : <p className="muted-copy">No grades yet.</p>}
              </section> : null}

              {(activeSection === 'all' || activeSection === 'subjects') ? <section aria-labelledby={`subjects-heading-${school.id}`}>
                <div className="academic-section-heading">
                  <h4 id={`subjects-heading-${school.id}`}>Subjects</h4>
                  <span>{subjects.length}</span>
                </div>
                <DataTools title="Subjects" rows={subjects.map((subject) => ({ ...subject, grades: subject.grades.map((grade) => grade.title).join('; ') }))} fields={[{ key: 'title', label: 'title', required: true }, { key: 'status', label: 'status' }, { key: 'grades', label: 'grades' }]} canImport={canManage} onImport={(row) => importRecord('subjects', row)} />
                {canManage ? (
                  <form className="add-grade-form" onSubmit={createSubject}>
                    <label htmlFor={`new-subject-${school.id}`}>New subject name</label>
                    <input id={`new-subject-${school.id}`} value={subjectTitle} onChange={(event) => setSubjectTitle(event.target.value)} required />
                    <fieldset className="section-checklist">
                      <legend>Assign grades</legend>
                      {grades.filter((grade) => grade.status === 'active').map((grade) => (
                        <label key={grade.id}>
                          <input type="checkbox" checked={subjectGradeIds.includes(grade.id)} onChange={() => toggleSubjectGrade(grade.id)} />
                          {grade.title}
                        </label>
                      ))}
                      {grades.filter((grade) => grade.status === 'active').length === 0 ? <p className="muted-copy">Add an active grade first.</p> : null}
                    </fieldset>
                    <button className="primary-button small-button" type="submit">Add subject</button>
                  </form>
                ) : null}
                <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search subjects" placeholder="Search subjects" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
                {subjects.length ? (
                  <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Subject</th><th>Assigned grades</th><th>Actions</th></tr></thead><tbody>
                    {matchingSubjects.map((subject) => <SubjectCard key={subject.id} schoolId={school.id} subject={subject} grades={grades} canManage={canManage} onUpdated={updateSubject} />)}
                  </tbody></table></div>
                ) : <p className="muted-copy">No subjects yet.</p>}
              </section> : null}
            </>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
