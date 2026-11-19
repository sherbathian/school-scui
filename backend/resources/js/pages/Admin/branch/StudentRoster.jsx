import { Fragment, useEffect, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'
import TablePagination from './TablePagination.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) {
    const validationMessage = Object.values(result.errors || {}).flat()[0]
    throw new Error(validationMessage || result.message || 'That request could not be completed.')
  }
  return result.data
}

async function readPage(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'That request could not be completed.')
  return result
}

function currentAcademicYear() {
  const year = new Date().getFullYear()
  return `${year}-${year + 1}`
}

function todayDate() {
  const now = new Date()
  return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10)
}

const studentProfileFields = [
  ['admission_number', 'Admission number', 'text'], ['full_name', 'Full name', 'text'], ['first_name', 'First name', 'text'], ['last_name', 'Last name', 'text'],
  ['admission_type', 'Admission type', 'text'], ['previous_admission_number', 'Previous admission number', 'text'],
  ['admitted_class', 'Admitted class', 'text'], ['current_class_label', 'Source current class', 'text'],
  ['gender', 'Gender', 'select'], ['status', 'Status', 'status'], ['religion', 'Religion', 'text'], ['email', 'Email', 'email'],
  ['phone', 'Phone', 'tel'], ['birth_date', 'Birth date', 'date'], ['admission_date', 'Admission date', 'date'],
  ['leave_date', 'Leave date', 'date'], ['b_form_number', 'B-Form number', 'text'], ['address', 'Address', 'textarea'],
  ['caste_or_tribe', 'Caste or tribe', 'text'],
  ['fee_rate', 'Fee rate', 'number'], ['fee_amount', 'Recorded fee amount', 'number'], ['fee_waiver', 'Fee waiver / free status', 'text'],
  ['arrears_due', 'Recorded arrears due', 'number'], ['withdrawn_from_class', 'Withdrawn from class', 'text'],
  ['source_marks_obtained', 'Source marks obtained', 'number'], ['source_marks_percentage', 'Source marks percentage', 'number'],
  ['previous_school_details', 'Previous school details', 'textarea'], ['note', 'Notes', 'textarea'],
]

function studentName(student) {
  return student.full_name || [student.last_name, student.first_name].filter(Boolean).join(', ')
}

const guardianFields = [
  'father_name', 'father_phone', 'father_occupation', 'father_cnic', 'mother_name', 'mother_phone', 'mother_occupation',
  'mother_cnic', 'guardian_name', 'guardian_relation', 'guardian_email', 'guardian_phone', 'guardian_occupation',
  'guardian_cnic', 'guardian_address',
]

const studentImportFields = [
  ...studentProfileFields.map(([key, label]) => ({ key, label: key, required: ['admission_number', 'first_name', 'last_name'].includes(key) })),
  { key: 'guardian_id', label: 'guardian_id' }, { key: 'guardian_source_parent_id', label: 'guardian_source_parent_id' },
  { key: 'source_data', label: 'source_data' }, { key: 'source_guardian_data', label: 'source_guardian_data' },
  ...[...guardianFields, 'is_guardian_other'].map((key) => ({ key: `guardian_${key}`, label: `guardian_${key}` })),
]

const studentExportFields = [
  ...studentProfileFields.map(([key]) => key), 'guardian_id', 'guardian_source_parent_id', ...guardianFields.map((key) => `guardian_${key}`),
  'guardian_is_guardian_other', 'grade', 'section', 'academic_year', 'enrollment_history', 'source_data', 'source_guardian_data',
].map((key) => ({ key, label: key }))

function studentEditValues(student) {
  return {
    ...Object.fromEntries(studentProfileFields.map(([key]) => [key, student[key] ?? ''])),
    guardian_id: String(student.guardian_id || student.guardian?.id || ''),
  }
}

function fieldLabel(key) {
  return key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

export default function StudentRoster({ school, canManage, canTakeAttendance, expanded = false, attendanceOnly = false, standalone = false, academicsEnabled = true, guardiansEnabled = true, selectedStudentId = null, onOpenStudent, onCloseStudent, onNavigate, admissionSeed = null, onAdmissionComplete, onAdmissionCancel }) {
  const [open, setOpen] = useState(expanded)
  const [loaded, setLoaded] = useState(false)
  const [loading, setLoading] = useState(false)
  const [students, setStudents] = useState([])
  const [grades, setGrades] = useState([])
  const [guardians, setGuardians] = useState([])
  const [guardianLookupSearch, setGuardianLookupSearch] = useState('')
  const [admissionNumber, setAdmissionNumber] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [studentForm, setStudentForm] = useState({ admission_number: '', full_name: '', first_name: '', last_name: '', admission_type: '', previous_admission_number: '', admitted_class: '', current_class_label: '', gender: 'male', religion: '', email: '', phone: '', address: '', birth_date: '', b_form_number: '', admission_date: todayDate(), leave_date: '', caste_or_tribe: '', fee_rate: '', fee_amount: '', fee_waiver: '', arrears_due: '', withdrawn_from_class: '', source_marks_obtained: '', source_marks_percentage: '', previous_school_details: '', note: '', guardian_id: '' })
  const [showStudentForm, setShowStudentForm] = useState(false)
  const [photoFile, setPhotoFile] = useState(null)
  const [bFormFile, setBFormFile] = useState(null)
  const [search, setSearch] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [studentPage, setStudentPage] = useState(1)
  const [studentPageSize, setStudentPageSize] = useState(10)
  const [studentMeta, setStudentMeta] = useState({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null })
  const [studentSort, setStudentSort] = useState({ key: 'student_name', direction: 'asc' })
  const [activeStudentId, setActiveStudentId] = useState(null)
  const [lifecycleStudentId, setLifecycleStudentId] = useState(null)
  const [lifecycleMode, setLifecycleMode] = useState('withdraw')
  const [lifecycleDate, setLifecycleDate] = useState(todayDate())
  const [lifecycleReason, setLifecycleReason] = useState('')
  const [editingStudentId, setEditingStudentId] = useState(null)
  const [editStudentForm, setEditStudentForm] = useState(null)
  const [editPhotoFile, setEditPhotoFile] = useState(null)
  const [editBFormFile, setEditBFormFile] = useState(null)
  const [removeStudentPhoto, setRemoveStudentPhoto] = useState(false)
  const [removeStudentBForm, setRemoveStudentBForm] = useState(false)
  const [detailStudentId, setDetailStudentId] = useState(null)
  const [detailStudent, setDetailStudent] = useState(null)
  const [detailFees, setDetailFees] = useState([])
  const [legacyFeeHistory, setLegacyFeeHistory] = useState([])
  const [detailLoading, setDetailLoading] = useState(false)
  const [gradeId, setGradeId] = useState('')
  const [sectionId, setSectionId] = useState('')
  const academicYear = school.active_session?.academic_year || currentAcademicYear()
  const [attendanceOpen, setAttendanceOpen] = useState(attendanceOnly)
  const [attendanceDate, setAttendanceDate] = useState(todayDate)
  const attendanceYear = school.active_session?.academic_year || currentAcademicYear()
  const [attendanceGradeId, setAttendanceGradeId] = useState('')
  const [attendanceSectionId, setAttendanceSectionId] = useState('')
  const [attendanceRows, setAttendanceRows] = useState([])
  const [attendanceLoaded, setAttendanceLoaded] = useState(false)
  const [attendanceLoading, setAttendanceLoading] = useState(false)
  const [attendanceSaving, setAttendanceSaving] = useState(false)
  const [error, setError] = useState('')

  async function loadStudentPage(signal) {
    const params = new URLSearchParams({ page: String(studentPage), per_page: String(studentPageSize), search: searchQuery, sort: studentSort.key, direction: studentSort.direction })
    const result = await readPage(await apiRequest(`/api/schools/${school.id}/students?${params}`, signal ? { signal } : undefined))
    setStudents(result.data)
    setStudentMeta(result.meta)
  }

  async function loadAllStudents() {
    const getPage = async (requestedPage) => {
      const params = new URLSearchParams({ page: String(requestedPage), per_page: '100', search: searchQuery, sort: studentSort.key, direction: studentSort.direction })
      return readPage(await apiRequest(`/api/schools/${school.id}/students?${params}`))
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
    const timer = setTimeout(() => setSearchQuery(search.trim()), 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    if (!open || !loaded || !guardiansEnabled || attendanceOnly) return undefined
    const term = guardianLookupSearch.trim()
    if (!term) return undefined
    const controller = new AbortController()
    const timer = setTimeout(async () => {
      try {
        const params = new URLSearchParams({ page: '1', per_page: '100', search: term, sort: 'father_name', direction: 'asc' })
        const result = await readPage(await apiRequest(`/api/schools/${school.id}/guardians?${params}`, { signal: controller.signal }))
        if (!controller.signal.aborted) setGuardians((current) => {
          const selectedId = String(editStudentForm?.guardian_id || studentForm.guardian_id || '')
          const selected = current.find((guardian) => String(guardian.id) === selectedId)
          return selected && !result.data.some((guardian) => String(guardian.id) === selectedId) ? [selected, ...result.data] : result.data
        })
      } catch (lookupError) {
        if (!controller.signal.aborted) setError(lookupError.message)
      }
    }, 250)
    return () => { clearTimeout(timer); controller.abort() }
  }, [open, loaded, school.id, guardianLookupSearch, guardiansEnabled, attendanceOnly])

  useEffect(() => { setStudentPage(1) }, [searchQuery, studentPageSize])

  useEffect(() => {
    if (!admissionSeed) return
    setStudentForm((current) => ({ ...current, first_name: admissionSeed.first_name || '', last_name: admissionSeed.last_name || '' }))
    setShowStudentForm(true)
  }, [admissionSeed?.id])
  useEffect(() => {
    if (!open || loaded) return undefined
    const controller = new AbortController()

    async function loadRoster() {
      setLoading(true)
      setError('')
      try {
        const options = { signal: controller.signal }
        const [gradeData, guardianData] = await Promise.all([
          academicsEnabled ? apiRequest(`/api/schools/${school.id}/grades`, options).then(readData) : [],
          guardiansEnabled && !attendanceOnly
            ? apiRequest(`/api/schools/${school.id}/guardians?page=1&per_page=100`, options).then(readData)
            : [],
        ])
        setGrades(gradeData)
        setGuardians(guardianData)
        setGradeId(String(gradeData.find((grade) => grade.status === 'active')?.id || ''))
        setAttendanceGradeId(String(gradeData.find((grade) => grade.status === 'active')?.id || ''))
        setLoaded(true)
      } catch (loadError) {
        if (!controller.signal.aborted) setError(loadError.message)
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }

    loadRoster()
    return () => controller.abort()
  }, [open, loaded, school.id, academicYear, academicsEnabled, guardiansEnabled, attendanceOnly])

  useEffect(() => {
    if (!open || !loaded || attendanceOnly) return undefined
    const controller = new AbortController()
    setLoading(true)
    setError('')
    loadStudentPage(controller.signal)
      .catch((loadError) => { if (!controller.signal.aborted && loadError.name !== 'AbortError') setError(loadError.message) })
      .finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [open, loaded, attendanceOnly, school.id, studentPage, studentPageSize, searchQuery, studentSort.key, studentSort.direction])

  useEffect(() => {
    if (!selectedStudentId) {
      setDetailStudent(null)
      setDetailFees([])
      setLegacyFeeHistory([])
      return undefined
    }
    const controller = new AbortController()
    setDetailLoading(true)
    setError('')
    apiRequest(`/api/schools/${school.id}/students/${selectedStudentId}`, { signal: controller.signal })
      .then(readData)
      .then((detail) => {
        setDetailStudent(detail)
        setDetailFees(detail.fee_history || [])
        setLegacyFeeHistory(detail.legacy_fee_history || [])
      })
      .catch((detailError) => { if (!controller.signal.aborted) setError(detailError.message) })
      .finally(() => { if (!controller.signal.aborted) setDetailLoading(false) })
    return () => controller.abort()
  }, [selectedStudentId, school.id])

  const selectedGrade = grades.find((grade) => String(grade.id) === gradeId)
  const availableSections = selectedGrade?.sections.filter((section) => section.status === 'active') || []
  const attendanceGrade = grades.find((grade) => String(grade.id) === attendanceGradeId)
  const attendanceSections = attendanceGrade?.sections.filter((section) => section.status === 'active') || []
  const student = students.find((record) => record.id === editingStudentId)
  const rosterVisible = !showStudentForm && !student
  const filteredStudents = students
  const studentPageCount = Math.max(1, Number(studentMeta.last_page || 1))
  const safeStudentPage = Math.min(studentPage, studentPageCount)
  useEffect(() => { if (studentPage > studentPageCount) setStudentPage(studentPageCount) }, [studentPage, studentPageCount])

  function studentSortHeader(label, key) {
    const active = studentSort.key === key
    return <button className="student-sort-button" type="button" onClick={() => { setStudentPage(1); setStudentSort({ key, direction: active && studentSort.direction === 'asc' ? 'desc' : 'asc' }) }}>{label}<span aria-hidden="true">{active ? (studentSort.direction === 'asc' ? '↑' : '↓') : '↕'}</span></button>
  }

  function exportStudentRow(student) {
    const enrollment = enrollmentForYear(student)
    return { ...student, guardian_source_parent_id: student.guardian?.source_parent_id || '', ...Object.fromEntries(Object.entries(student.guardian || {}).map(([key, value]) => [`guardian_${key}`, value])), grade: enrollment?.grade?.title || '', section: enrollment?.section?.title || '', academic_year: enrollment?.academic_year || '', enrollment_history: student.enrollments.map((item) => `${item.academic_year}: ${item.grade?.title || ''} / ${item.section?.title || ''} (${item.status})`).join(' | ') }
  }

  async function refreshStudentPage() {
    setLoading(true)
    try { await loadStudentPage() } catch (loadError) { setError(loadError.message) } finally { setLoading(false) }
  }

  function enrollmentForYear(student) {
    return student.enrollments.find((enrollment) => enrollment.academic_year === academicYear) || null
  }

  async function createStudent(event) {
    event.preventDefault()
    setError('')
    try {
      const payload = new FormData()
      Object.entries(studentForm).forEach(([key, value]) => { if (value !== '' && value != null) payload.append(key, value) })
      if (photoFile) payload.append('photo', photoFile)
      if (bFormFile) payload.append('b_form_document', bFormFile)
      const student = await readData(await apiRequest(`/api/schools/${school.id}/students`, {
        method: 'POST',
        body: payload,
      }))
      setStudentPage(1)
      void refreshStudentPage()
      if (admissionSeed && onAdmissionComplete) {
        try { await onAdmissionComplete(admissionSeed, student) } catch (linkError) { setError(`Student profile saved, but the inquiry could not be marked registered: ${linkError.message}`) }
      }
      setAdmissionNumber('')
      setFirstName('')
      setLastName('')
      setStudentForm((current) => ({ ...current, admission_number: '', first_name: '', last_name: '' }))
      setShowStudentForm(false)
      setPhotoFile(null)
      setBFormFile(null)
    } catch (createError) {
      setError(createError.message)
    }
  }

  async function importStudent(row) {
    const guardianParentId = String(row.guardian_source_parent_id || '').trim()
    let linkedGuardian = guardianParentId ? guardians.find((item) => String(item.source_parent_id) === guardianParentId) : null
    if (guardianParentId && !linkedGuardian) {
      const params = new URLSearchParams({ page: '1', per_page: '100', search: guardianParentId })
      const matches = await readPage(await apiRequest(`/api/schools/${school.id}/guardians?${params}`))
      linkedGuardian = matches.data.find((item) => String(item.source_parent_id) === guardianParentId) || null
    }
    const guardian = Object.fromEntries(Object.entries(row).filter(([key]) => key.startsWith('guardian_') && !['guardian_id', 'guardian_source_parent_id'].includes(key)).map(([key, value]) => [key.slice(9), value]))
    const parseSource = (value) => {
      if (!value) return undefined
      try { return typeof value === 'string' ? JSON.parse(value) : value } catch { throw new Error('Source data fields must contain valid JSON.') }
    }
    const payload = { ...row, guardian_id: linkedGuardian?.id || row.guardian_id || '', source_data: parseSource(row.source_data), source_guardian_data: parseSource(row.source_guardian_data), guardian: !linkedGuardian && Object.values(guardian).some(Boolean) ? guardian : undefined }
    delete payload.guardian_source_parent_id
    const student = await readData(await apiRequest(`/api/schools/${school.id}/students`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }))
  }

  async function importEnrollment(row) {
    const matchParams = new URLSearchParams({ page: '1', per_page: '100', search: row.admission_number, sort: 'admission_number', direction: 'asc' })
    const matchingRows = await readPage(await apiRequest(`/api/schools/${school.id}/students?${matchParams}`))
    const student = matchingRows.data.find((item) => item.admission_number.toLowerCase() === row.admission_number.toLowerCase())
    if (!student || student.status !== 'active') throw new Error('Admission number does not match an active student in this school.')
    if (student.current_enrollment?.academic_year === academicYear && student.current_enrollment.status === 'active') {
      throw new Error('Student already has an active enrollment for this year. Use the normal move-class action to change it.')
    }
    const grade = grades.find((item) => item.title.toLowerCase() === row.grade.toLowerCase() && item.status === 'active')
    const section = grade?.sections.find((item) => item.title.toLowerCase() === row.section.toLowerCase() && item.status === 'active')
    if (!grade || !section) throw new Error('Grade and section must match an active assigned class in this school.')
    await readData(await apiRequest(`/api/schools/${school.id}/students/${student.id}/enrollments`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ grade_id: grade.id, section_id: section.id, academic_year: academicYear }),
    }))
    await refreshStudentPage()
  }

  async function enrollStudent(event, student) {
    event.preventDefault()
    setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/students/${student.id}/enrollments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ grade_id: Number(gradeId), section_id: Number(sectionId), academic_year: academicYear }),
      }))
      await refreshStudentPage()
      setActiveStudentId(null)
    } catch (enrollError) {
      setError(enrollError.message)
    }
  }

  async function saveWithdrawal(event, student) {
    event.preventDefault(); setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/students/${student.id}/withdraw`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event_date: lifecycleDate, reason: lifecycleReason }) }))
      await refreshStudentPage()
      setLifecycleStudentId(null); setLifecycleReason('')
    } catch (withdrawError) { setError(withdrawError.message) }
  }

  async function saveRejoining(event, student) {
    event.preventDefault(); setError('')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/students/${student.id}/rejoin`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ grade_id: Number(gradeId), section_id: Number(sectionId), event_date: lifecycleDate, reason: lifecycleReason }) }))
      await refreshStudentPage()
      setLifecycleStudentId(null); setLifecycleReason(''); setSectionId('')
    } catch (rejoinError) { setError(rejoinError.message) }
  }

  async function saveStudent(event, student) {
    event.preventDefault()
    setError('')
    try {
      const payload = new FormData()
      studentProfileFields.forEach(([key]) => payload.append(key, editStudentForm[key] ?? ''))
      payload.append('guardian_id', editStudentForm.guardian_id || '')
      if (editPhotoFile) payload.append('photo', editPhotoFile)
      if (editBFormFile) payload.append('b_form_document', editBFormFile)
      payload.append('remove_photo', removeStudentPhoto ? '1' : '0')
      payload.append('remove_b_form_document', removeStudentBForm ? '1' : '0')
      payload.append('_method', 'PATCH')
      await readData(await apiRequest(`/api/schools/${school.id}/students/${student.id}`, {
        method: 'POST',
        body: payload,
      }))
      await refreshStudentPage()
      setEditingStudentId(null)
      setEditStudentForm(null)
      setEditPhotoFile(null)
      setEditBFormFile(null)
      setRemoveStudentPhoto(false)
      setRemoveStudentBForm(false)
    } catch (saveError) {
      setError(saveError.message)
    }
  }

  async function toggleStudentDetails(student) {
    if (detailStudentId === student.id) {
      setDetailStudentId(null)
      setDetailStudent(null)
      setDetailFees([])
      return
    }
    setDetailStudentId(student.id)
    setDetailStudent(null)
    setDetailFees([])
    setLegacyFeeHistory([])
    setDetailLoading(true)
    setError('')
    try {
      const detailResponse = await apiRequest(`/api/schools/${school.id}/students/${student.id}`)
      const detail = await readData(detailResponse)
      setDetailStudent(detail)
      setDetailFees(detail.fee_history || [])
      setLegacyFeeHistory(detail.legacy_fee_history || [])
    } catch (detailError) {
      setError(detailError.message)
    } finally {
      setDetailLoading(false)
    }
  }

  async function loadAttendance(event) {
    event.preventDefault()
    setError('')
    setAttendanceLoaded(false)
    setAttendanceLoading(true)
    try {
      const query = new URLSearchParams({
        grade_id: attendanceGradeId,
        section_id: attendanceSectionId,
        academic_year: attendanceYear,
        attendance_date: attendanceDate,
      })
      const rows = await readData(await apiRequest(`/api/schools/${school.id}/attendance?${query}`))
      setAttendanceRows(rows)
      setAttendanceLoaded(true)
    } catch (loadError) {
      setError(loadError.message)
    } finally {
      setAttendanceLoading(false)
    }
  }

  function updateAttendance(enrollmentId, status) {
    setAttendanceRows((current) => current.map((row) => row.enrollment_id === enrollmentId ? { ...row, status } : row))
  }

  async function saveAttendance() {
    setError('')
    setAttendanceSaving(true)
    try {
      const savedRows = await readData(await apiRequest(`/api/schools/${school.id}/attendance`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          grade_id: Number(attendanceGradeId),
          section_id: Number(attendanceSectionId),
          academic_year: attendanceYear,
          attendance_date: attendanceDate,
          records: attendanceRows.map((row) => ({ enrollment_id: row.enrollment_id, status: row.status })),
        }),
      }))
      setAttendanceRows(savedRows)
    } catch (saveError) {
      setError(saveError.message)
    } finally {
      setAttendanceSaving(false)
    }
  }

  async function importAttendance(records) {
    const byAdmission = new Map(records.map((record) => [record.admission_number.toLowerCase(), record.status.toLowerCase()]))
    if (records.length !== attendanceRows.length || attendanceRows.some((row) => !byAdmission.has(row.student.admission_number.toLowerCase()))) {
      throw new Error('Include exactly one row for every student in the loaded class register.')
    }
    const invalidStatus = [...byAdmission.values()].find((status) => !['present', 'absent', 'late', 'excused'].includes(status))
    if (invalidStatus) throw new Error('Attendance status must be present, absent, late, or excused.')
    const saved = await readData(await apiRequest(`/api/schools/${school.id}/attendance`, {
      method: 'PUT', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ grade_id: Number(attendanceGradeId), section_id: Number(attendanceSectionId), academic_year: attendanceYear, attendance_date: attendanceDate, records: attendanceRows.map((row) => ({ enrollment_id: row.enrollment_id, status: byAdmission.get(row.student.admission_number.toLowerCase()) })) }),
    }))
    setAttendanceRows(saved)
    return { count: saved.length }
  }

  return (
    <div className={`academic-structure${attendanceOnly ? ' attendance-only' : ''}`}>
      {!standalone ? <button className="team-toggle" type="button" aria-expanded={open} onClick={() => setOpen((current) => !current)}>
        {open ? 'Hide student roster' : 'View student roster'}
      </button> : null}
      {open ? (
        <div className="academic-panel">
          <div className="academic-section-heading">
            <h4>Students</h4>
            <span>{studentMeta.total}</span>
          </div>
          {selectedStudentId ? <section className="student-profile-page" aria-label="Student profile">
            <button className="quiet-button student-back-button" type="button" onClick={onCloseStudent}>← Back to students</button>
            {detailLoading ? <p className="muted-copy" role="status">Loading student profile…</p> : null}
            {error ? <p className="form-error" role="alert">{error}</p> : null}
            {!detailLoading && detailStudent ? <>
              <header className="student-profile-hero">
                {detailStudent.photo_url ? <img className="student-profile-photo" src={detailStudent.photo_url} alt={`${detailStudent.first_name} ${detailStudent.last_name}`} /> : <span className="student-profile-photo student-profile-initials">{detailStudent.first_name?.[0]}{detailStudent.last_name?.[0]}</span>}
                <div><p className="eyebrow">Student profile</p><h3>{studentName(detailStudent)}</h3><p>{detailStudent.admission_number} <span>·</span> {fieldLabel(detailStudent.status)}</p></div>
                <div className="student-profile-actions"><span className="active-year-label">Current session <strong>{academicYear}</strong></span></div>
              </header>
              <div className="student-detail-grid"><section className="student-info-card"><div className="student-card-heading"><span className="student-card-icon" aria-hidden="true">▤</span><div><h5>Student information</h5><p>Admission, identity and contact details</p></div></div><dl>{studentProfileFields.map(([key, label]) => <div className={['email', 'address', 'previous_school_details', 'note'].includes(key) ? 'student-detail-wide' : ''} key={key}><dt>{label}</dt><dd>{key === 'email' && detailStudent[key] ? <a href={`mailto:${detailStudent[key]}`}>{detailStudent[key]}</a> : detailStudent[key] || '—'}</dd></div>)}</dl></section>
                <section className="student-info-card"><div className="student-card-heading"><span className="student-card-icon" aria-hidden="true">♧</span><div><h5>Parent and guardian</h5><p>Family contacts linked to this student</p></div></div><dl>{guardianFields.map((key) => <div className={['guardian_email', 'guardian_address'].includes(key) ? 'student-detail-wide' : ''} key={key}><dt>{fieldLabel(key)}</dt><dd>{detailStudent.guardian?.[key] || '—'}</dd></div>)}<div><dt>Other guardian</dt><dd>{detailStudent.guardian?.is_guardian_other ? 'Yes' : 'No'}</dd></div></dl></section>
                <section className="student-info-card student-documents-card"><div className="student-card-heading"><span className="student-card-icon" aria-hidden="true">⇧</span><div><h5>Documents</h5><p>Student photo and identification files</p></div></div><div className="student-document-items"><article><span><strong>Student photo</strong><small>{detailStudent.photo_url ? 'Photo available' : 'No photo uploaded'}</small></span>{detailStudent.photo_url ? <a className="student-file-button" href={detailStudent.photo_url} target="_blank" rel="noreferrer">View photo</a> : <span className="student-file-empty">—</span>}</article><article><span><strong>B-Form document</strong><small>{detailStudent.b_form_document_url ? 'Identification document available' : 'No document uploaded'}</small></span>{detailStudent.b_form_document_url ? <span className="student-file-links"><a className="student-file-button" href={detailStudent.b_form_document_url} target="_blank" rel="noreferrer">Open</a><a className="student-file-button student-file-button-muted" href={detailStudent.b_form_document_url} download>Download</a></span> : <span className="student-file-empty">—</span>}</article></div></section>
                <section className="student-enrollment-history"><h5>Enrollment history</h5>{detailStudent.enrollments.length ? <div className="student-history-list">{detailStudent.enrollments.map((enrollment) => <div key={enrollment.id}><strong>{enrollment.grade.title} · {enrollment.section.title}</strong><span>{enrollment.academic_year} · {fieldLabel(enrollment.status)}</span></div>)}</div> : <p className="muted-copy">No enrollment history.</p>}</section>
                <section className="student-lifecycle-history"><h5>Withdrawals &amp; rejoinings</h5>{detailStudent.lifecycle_events?.length ? <div className="student-history-list">{detailStudent.lifecycle_events.map((item) => <div key={item.id}><strong>{fieldLabel(item.event_type)} · {item.event_date}</strong><span>{item.reason || 'No notes recorded'}</span></div>)}</div> : <p className="muted-copy">No withdrawal or rejoining history.</p>}</section></div>
              {canManage ? <section className="student-fee-history"><h5>Fee history</h5>{detailFees.length ? <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Fee item</th><th>Academic year / month</th><th>Due date</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>{detailFees.map((fee) => <tr key={`${fee.kind}-${fee.id}`}><td>{fee.fee_type}<small>{fee.kind === 'monthly' ? 'Monthly charge' : 'Invoice'}</small></td><td>{fee.billing_month || fee.academic_year}</td><td>{fee.due_date || '—'}</td><td>{fee.amount}</td><td>{fee.amount_paid || '0.00'}</td><td>{fee.balance}</td><td><span className={`fee-status fee-status-${fee.status}`}>{fieldLabel(fee.status)}</span></td></tr>)}</tbody></table></div> : <p className="muted-copy">No fee records are linked to this student yet.</p>}</section> : null}
              {canManage && legacyFeeHistory.length ? <section className="student-fee-history"><h5>Imported legacy fee balances</h5><p className="muted-copy">Historical spreadsheet month totals, kept separate from invoices and receipts.</p><div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Month</th><th>Received</th><th>Balance</th></tr></thead><tbody>{legacyFeeHistory.map((item) => <tr key={item.billing_month}><td>{item.billing_month}</td><td>{item.received_amount ?? '—'}</td><td>{item.balance_amount ?? '—'}</td></tr>)}</tbody></table></div></section> : null}
            </> : null}
          </section> : <>
          {rosterVisible && <DataTools title="Students" rows={students.map(exportStudentRow)} loadExportRows={async () => (await loadAllStudents()).map(exportStudentRow)} onImportComplete={refreshStudentPage} fields={studentImportFields} exportFields={studentExportFields} canImport={canManage} onImport={importStudent} />}
          <span className="active-year-label">Active academic year <strong>{academicYear}</strong></span>
          {error ? <p className="form-error" role="alert">{error}</p> : null}
          {loading ? <p className="muted-copy" role="status">Loading student roster…</p> : null}
          {!loading && !attendanceOnly && rosterVisible ? <div className="roster-toolbar"><label className="module-search"><span aria-hidden="true">⌕</span><input aria-label="Search students" placeholder="Quick find by student, admission no., phone, grade or section" value={search} onChange={(event) => setSearch(event.target.value)} /></label><span className="student-navigator-count">{studentMeta.total} matches</span>{canManage ? <button className="primary-button small-button" type="button" onClick={() => { setGuardianLookupSearch(''); setShowStudentForm(true) }}>＋ Add student</button> : null}</div> : null}
                  {student && editStudentForm ? <form className="student-edit-panel" onSubmit={(event) => saveStudent(event, student)}>
                    <div className="student-form-heading"><div><h4>Edit student profile</h4><p>Update student, guardian, and document details.</p></div></div>
                    <fieldset><legend>Student profile</legend><div className="student-form-grid">
                      {studentProfileFields.map(([key, label, type]) => <label key={key}>{label}{type === 'select' ? <select value={editStudentForm[key]} onChange={(event) => setEditStudentForm((current) => ({ ...current, [key]: event.target.value }))}><option value="">Not specified</option><option value="male">Male</option><option value="female">Female</option></select> : type === 'status' ? <select value={editStudentForm[key]} onChange={(event) => setEditStudentForm((current) => ({ ...current, [key]: event.target.value }))}><option value="pending">Pending</option><option value="active">Active</option><option value="leave">Leave</option><option value="alumni">Alumni</option><option value="inactive">Inactive</option></select> : type === 'textarea' ? <textarea rows="2" value={editStudentForm[key]} onChange={(event) => setEditStudentForm((current) => ({ ...current, [key]: event.target.value }))} /> : <input type={type} value={editStudentForm[key]} onChange={(event) => setEditStudentForm((current) => ({ ...current, [key]: event.target.value }))} required={['admission_number', 'first_name', 'last_name'].includes(key)} />}</label>)}
                      <label>Student photo<input type="file" accept="image/*" onChange={(event) => setEditPhotoFile(event.target.files?.[0] || null)} />{student.photo_path ? <span className="student-file-remove"><input type="checkbox" checked={removeStudentPhoto} onChange={(event) => setRemoveStudentPhoto(event.target.checked)} /> Remove current photo</span> : null}</label>
                      <label>B-Form document<input type="file" accept=".pdf,image/*" onChange={(event) => setEditBFormFile(event.target.files?.[0] || null)} />{student.b_form_document_path ? <span className="student-file-remove"><input type="checkbox" checked={removeStudentBForm} onChange={(event) => setRemoveStudentBForm(event.target.checked)} /> Remove current document</span> : null}</label>
                    </div></fieldset>
                    <fieldset><legend>Parent / guardian</legend><div className="student-guardian-select-row"><label>Find a guardian<input aria-label="Search guardians for student" placeholder="Search parent name, ID, phone or CNIC" value={guardianLookupSearch} onChange={(event) => setGuardianLookupSearch(event.target.value)} /></label><label>Select a guardian<select value={editStudentForm.guardian_id} onChange={(event) => setEditStudentForm((current) => ({ ...current, guardian_id: event.target.value }))}><option value="">No guardian selected</option>{guardians.map((guardian) => <option key={guardian.id} value={guardian.id}>#{guardian.source_parent_id || guardian.id} · {guardian.father_name} / {guardian.mother_name}{guardian.father_phone ? ` · ${guardian.father_phone}` : ''}</option>)}</select></label></div><p className="muted-copy">Update guardian contact information in the Guardians module.</p></fieldset>
                    <div className="student-form-actions"><button className="primary-button small-button" type="submit">Save changes</button><button className="quiet-button" type="button" onClick={() => { setEditingStudentId(null); setEditStudentForm(null) }}>Cancel</button></div>
                  </form> : null}
          {showStudentForm && canManage ? <form className="student-profile-form" onSubmit={createStudent}>
            <div className="student-form-heading"><div><p className="eyebrow">Registration &amp; admission</p><h4>Student admission</h4><p>{admissionSeed ? `Complete the admission started from inquiry for ${admissionSeed.first_name} ${admissionSeed.last_name}.` : 'Enter the student profile and family contact details.'}</p></div><button className="quiet-button" type="button" onClick={() => { setShowStudentForm(false); if (admissionSeed) onAdmissionCancel?.() }}>Cancel</button></div>
            <fieldset><legend>Student profile</legend><div className="student-form-grid">
              {[
                ['admission_number', 'Admission number', 'text', true], ['full_name', 'Full name', 'text', false], ['first_name', 'First name', 'text', true], ['last_name', 'Last name', 'text', true], ['admission_type', 'Admission type', 'text', false], ['previous_admission_number', 'Previous admission number', 'text', false], ['admitted_class', 'Admitted class', 'text', false], ['current_class_label', 'Source current class', 'text', false], ['gender', 'Gender', 'select', true], ['birth_date', 'Birth date', 'date', false], ['admission_date', 'Admission date', 'date', false], ['leave_date', 'Leave date', 'date', false], ['status', 'Admission status', 'status', false], ['religion', 'Religion', 'text', false], ['email', 'Email', 'email', false], ['phone', 'Phone', 'tel', false], ['b_form_number', 'B-Form number', 'text', false], ['caste_or_tribe', 'Caste or tribe', 'text', false], ['fee_rate', 'Fee rate', 'number', false], ['fee_amount', 'Recorded fee amount', 'number', false], ['fee_waiver', 'Fee waiver / free status', 'text', false], ['arrears_due', 'Recorded arrears due', 'number', false], ['withdrawn_from_class', 'Withdrawn from class', 'text', false], ['source_marks_obtained', 'Source marks obtained', 'number', false], ['source_marks_percentage', 'Source marks percentage', 'number', false], ['address', 'Address', 'textarea', false], ['previous_school_details', 'Previous school details', 'textarea', false], ['note', 'Notes', 'textarea', false],
              ].map(([key, label, type, required]) => <label key={key}>{label}{type === 'select' ? <select value={studentForm[key]} onChange={(event) => setStudentForm({ ...studentForm, [key]: event.target.value })}><option value="male">Male</option><option value="female">Female</option></select> : type === 'status' ? <select value={studentForm[key] || 'active'} onChange={(event) => setStudentForm({ ...studentForm, [key]: event.target.value })}><option value="pending">Pending</option><option value="active">Active</option><option value="leave">Leave</option><option value="alumni">Alumni</option></select> : type === 'textarea' ? <textarea rows="2" value={studentForm[key]} onChange={(event) => setStudentForm({ ...studentForm, [key]: event.target.value })} /> : <input type={type} value={studentForm[key]} onChange={(event) => setStudentForm({ ...studentForm, [key]: event.target.value })} required={required} />}</label>)}
              <label>Student photo<input type="file" accept="image/*" onChange={(event) => setPhotoFile(event.target.files?.[0] || null)} /></label>
              <label>B-Form document<input type="file" accept=".pdf,image/*" onChange={(event) => setBFormFile(event.target.files?.[0] || null)} /></label>
            </div></fieldset>
            <fieldset><legend>Parent / guardian</legend><div className="student-guardian-select-row"><label>Find a guardian<input aria-label="Search guardians for student" placeholder="Search parent name, ID, phone or CNIC" value={guardianLookupSearch} onChange={(event) => setGuardianLookupSearch(event.target.value)} /></label><label>Select a guardian<select value={studentForm.guardian_id} onChange={(event) => setStudentForm((current) => ({ ...current, guardian_id: event.target.value }))}><option value="">No guardian selected</option>{guardians.map((guardian) => <option key={guardian.id} value={guardian.id}>#{guardian.source_parent_id || guardian.id} · {guardian.father_name} / {guardian.mother_name}{guardian.father_phone ? ` · ${guardian.father_phone}` : ''}</option>)}</select></label><button className="quiet-button" type="button" onClick={() => onNavigate?.('guardians')}>Manage guardians</button></div>{guardians.length === 0 ? <p className="muted-copy">Add a guardian first, then select them here.</p> : null}</fieldset>
            <div className="student-form-actions"><button className="primary-button small-button" type="submit">Save student</button><button className="quiet-button" type="button" onClick={() => setShowStudentForm(false)}>Cancel</button></div>
          </form> : null}
          {!loading && rosterVisible && filteredStudents.length ? (
            <div className="crud-table-wrap"><table className="crud-table student-roster-table"><colgroup><col className="student-column" /><col className="contact-column" /><col className="enrollment-column" /><col className="actions-column" /></colgroup><thead><tr><th>{studentSortHeader('Student', 'student_name')}</th><th>{studentSortHeader('Contact', 'phone')}</th><th>{studentSortHeader('Current enrollment', 'grade')}</th><th>Actions</th></tr></thead><tbody>
              {filteredStudents.map((student) => {
                const displayedEnrollment = enrollmentForYear(student)

                return (
                <Fragment key={student.id}>
                  <tr className="student-row">
                    <td><strong>{studentName(student)}</strong><small>{student.admission_number} · {student.status}</small></td>
                    <td>{student.phone || '—'}<small>{student.email || 'No email on file'}</small></td>
                    <td>{displayedEnrollment ? `${displayedEnrollment.grade.title} · ${displayedEnrollment.section.title}` : student.status === 'active' ? 'Not enrolled this year' : 'Archived'}<small>{displayedEnrollment?.academic_year || ''}{displayedEnrollment ? ` · ${displayedEnrollment.status}` : ''}</small></td>
                    <td><div className="academic-actions">
                      <TableAction action="view" label="View profile" onClick={() => onOpenStudent?.(student)} />
                      {canManage ? <>
                      <TableAction action="edit" label={editingStudentId === student.id ? 'Cancel edit' : 'Edit student'} onClick={() => {
                        setGuardianLookupSearch('')
                        if (student.guardian) setGuardians((current) => current.some((guardian) => guardian.id === student.guardian.id) ? current : [student.guardian, ...current])
                        setEditStudentForm(studentEditValues(student))
                        setEditingStudentId(editingStudentId === student.id ? null : student.id)
                        setActiveStudentId(null)
                        setDetailStudentId(null)
                        setEditPhotoFile(null)
                        setEditBFormFile(null)
                        setRemoveStudentPhoto(false)
                        setRemoveStudentBForm(false)
                      }} />
                      {student.status === 'active' ? (
                        <>
                          <TableAction action="assign" label={student.current_enrollment ? 'Move class' : 'Enroll'} onClick={() => {
                            setActiveStudentId(activeStudentId === student.id ? null : student.id)
                            setEditingStudentId(null)
                            setGradeId(String(grades.find((grade) => grade.status === 'active')?.id || ''))
                            setSectionId('')
                          }} />
                          <TableAction action="archive" label="Withdraw student" danger onClick={() => { setLifecycleStudentId(lifecycleStudentId === student.id ? null : student.id); setLifecycleMode('withdraw'); setLifecycleDate(todayDate()); setLifecycleReason(''); setActiveStudentId(null); setEditingStudentId(null) }} />
                        </>
                      ) : ['leave', 'inactive'].includes(student.status) ? <TableAction action="restore" label="Rejoin student" onClick={() => { setLifecycleStudentId(lifecycleStudentId === student.id ? null : student.id); setLifecycleMode('rejoin'); setLifecycleDate(todayDate()); setLifecycleReason(''); setGradeId(String(student.current_enrollment?.grade?.id || grades.find((grade) => grade.status === 'active')?.id || '')); setSectionId(String(student.current_enrollment?.section?.id || '')); setActiveStudentId(null); setEditingStudentId(null) }} /> : null}
                      </> : null}
                    </div></td>
                  </tr>
                  {detailStudentId === student.id ? <tr className="student-expanded-row"><td colSpan="4"><section className="student-detail-panel" aria-label={`Details for ${student.first_name} ${student.last_name}`}>
                    {detailLoading ? <p className="muted-copy" role="status">Loading student details…</p> : detailStudent ? <>
                      <div className="student-form-heading"><div><h4>{detailStudent.first_name} {detailStudent.last_name}</h4><p>Admission {detailStudent.admission_number} · {detailStudent.status}</p></div><span className="active-year-label">Active year <strong>{academicYear}</strong></span></div>
                      <div className="student-detail-grid"><section><h5>Student profile</h5><dl>{studentProfileFields.filter(([key]) => key !== 'admission_number').map(([key, label]) => <div key={key}><dt>{label}</dt><dd>{detailStudent[key] || '—'}</dd></div>)}<div><dt>Student photo</dt><dd>{detailStudent.photo_path ? 'Photo on file' : '—'}</dd></div><div><dt>B-Form document</dt><dd>{detailStudent.b_form_document_path ? 'Document on file' : '—'}</dd></div></dl></section>
                        <section><h5>Parent and guardian</h5><dl>{[...guardianFields, 'is_guardian_other'].map((key) => <div key={key}><dt>{fieldLabel(key)}</dt><dd>{detailStudent.guardian?.[key] != null ? String(detailStudent.guardian[key]) : '—'}</dd></div>)}</dl></section>
                        <section className="student-enrollment-history"><h5>Enrollment history</h5>{detailStudent.enrollments.length ? <div className="student-history-list">{detailStudent.enrollments.map((enrollment) => <div key={enrollment.id}><strong>{enrollment.grade.title} · {enrollment.section.title}</strong><span>{enrollment.academic_year} · {enrollment.status}</span></div>)}</div> : <p className="muted-copy">No enrollment history.</p>}</section>
                      </div>
                      {canManage ? <section className="student-fee-history"><h5>Fee history</h5>{detailFees.length ? <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Fee item</th><th>Academic year / month</th><th>Due date</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>{detailFees.map((fee) => <tr key={`${fee.kind}-${fee.id}`}><td>{fee.fee_type}<small>{fee.kind === 'monthly' ? 'Monthly charge' : 'Invoice'}{fee.payments.length ? ` · ${fee.payments.map((payment) => `${payment.paid_on}: ${payment.amount}`).join(' · ')}` : ''}</small></td><td>{fee.billing_month || fee.academic_year}</td><td>{fee.due_date || '—'}</td><td>{fee.amount}</td><td>{fee.amount_paid}</td><td>{fee.balance}</td><td><span className={`fee-status fee-status-${fee.status}`}>{fieldLabel(fee.status)}</span></td></tr>)}</tbody></table></div> : <p className="muted-copy">No fee records are linked to this student yet.</p>}</section> : null}
                    </> : null}
                  </section></td></tr> : null}
                  {activeStudentId === student.id ? <tr className="student-expanded-row"><td colSpan="4"><form className="enrollment-form" onSubmit={(event) => enrollStudent(event, student)}>
                      <label htmlFor={`enrollment-grade-${student.id}`}>Grade</label>
                      <select id={`enrollment-grade-${student.id}`} value={gradeId} onChange={(event) => {
                        setGradeId(event.target.value)
                        setSectionId('')
                      }} required>
                        <option value="">Choose a grade</option>
                        {grades.filter((grade) => grade.status === 'active').map((grade) => <option key={grade.id} value={grade.id}>{grade.title}</option>)}
                      </select>
                      <label htmlFor={`enrollment-section-${student.id}`}>Section</label>
                      <select id={`enrollment-section-${student.id}`} value={sectionId} onChange={(event) => setSectionId(event.target.value)} required>
                        <option value="">Choose a section</option>
                        {availableSections.map((section) => <option key={section.id} value={section.id}>{section.title}</option>)}
                      </select>
                      <span className="active-year-label">Academic year <strong>{school.active_session?.academic_year || academicYear}</strong></span>
                      <button className="primary-button small-button" type="submit" disabled={!sectionId}>Save enrollment</button>
                      <button className="quiet-button" type="button" onClick={() => setActiveStudentId(null)}>Cancel</button>
                  </form></td></tr> : null}
                  {lifecycleStudentId === student.id ? <tr className="student-expanded-row"><td colSpan="4"><form className="student-lifecycle-form" onSubmit={(event) => lifecycleMode === 'withdraw' ? saveWithdrawal(event, student) : saveRejoining(event, student)}><div><p className="eyebrow">Student status</p><h4>{lifecycleMode === 'withdraw' ? 'Withdraw student' : 'Rejoin student'}</h4><p>{lifecycleMode === 'withdraw' ? 'Record the leaving date and reason. Their active enrollment will be closed.' : 'Choose the class and rejoining date. A new active enrollment will be created for the current session.'}</p></div>{lifecycleMode === 'rejoin' ? <><label>Grade<select value={gradeId} onChange={(event) => { setGradeId(event.target.value); setSectionId('') }} required><option value="">Choose grade</option>{grades.filter((grade) => grade.status === 'active').map((grade) => <option key={grade.id} value={grade.id}>{grade.title}</option>)}</select></label><label>Section<select value={sectionId} onChange={(event) => setSectionId(event.target.value)} required><option value="">Choose section</option>{availableSections.map((section) => <option key={section.id} value={section.id}>{section.title}</option>)}</select></label><span className="active-year-label">Current session <strong>{academicYear}</strong></span></> : null}<label>{lifecycleMode === 'withdraw' ? 'Withdrawal date' : 'Rejoining date'}<input type="date" value={lifecycleDate} onChange={(event) => setLifecycleDate(event.target.value)} required /></label><label className="lifecycle-reason">Reason / notes<textarea rows="2" value={lifecycleReason} onChange={(event) => setLifecycleReason(event.target.value)} /></label><div className="student-form-actions"><button className="primary-button small-button" type="submit" disabled={lifecycleMode === 'rejoin' && !sectionId}>{lifecycleMode === 'withdraw' ? 'Confirm withdrawal' : 'Confirm rejoining'}</button><button className="quiet-button" type="button" onClick={() => setLifecycleStudentId(null)}>Cancel</button></div></form></td></tr> : null}
                </Fragment>
                )
              })}
            </tbody></table></div>
          ) : null}
          {!loading && rosterVisible && filteredStudents.length > 0 ? <TablePagination meta={studentMeta} page={safeStudentPage} perPage={studentPageSize} label="students" onPageChange={setStudentPage} onPerPageChange={setStudentPageSize} /> : null}
          {!loading && rosterVisible && filteredStudents.length === 0 ? <p className="muted-copy">No students match this search. Add a student or import a student CSV to get started.</p> : null}
          {canTakeAttendance && (attendanceOnly || !standalone) ? (
            <>
              <button className="team-toggle attendance-toggle" type="button" aria-expanded={attendanceOpen} onClick={() => setAttendanceOpen((current) => !current)}>
                {attendanceOpen ? 'Hide attendance register' : 'Take attendance'}
              </button>
              {attendanceOpen ? (
                <section className="attendance-panel" aria-label="Class attendance">
                  <form className="attendance-filter-form" onSubmit={loadAttendance}>
                    <div className="attendance-year-field"><span>Academic year</span><strong>{school.active_session?.academic_year || attendanceYear}</strong></div>
                    <label className="attendance-filter-field" htmlFor={`attendance-grade-${school.id}`}>Grade<select id={`attendance-grade-${school.id}`} value={attendanceGradeId} onChange={(event) => {
                      setAttendanceGradeId(event.target.value)
                      setAttendanceSectionId('')
                      setAttendanceLoaded(false)
                    }} required>
                      <option value="">Choose a grade</option>
                      {grades.filter((grade) => grade.status === 'active').map((grade) => <option key={grade.id} value={grade.id}>{grade.title}</option>)}
                    </select></label>
                    <label className="attendance-filter-field" htmlFor={`attendance-section-${school.id}`}>Section<select id={`attendance-section-${school.id}`} value={attendanceSectionId} onChange={(event) => {
                      setAttendanceSectionId(event.target.value)
                      setAttendanceLoaded(false)
                    }} required>
                      <option value="">Choose a section</option>
                      {attendanceSections.map((section) => <option key={section.id} value={section.id}>{section.title}</option>)}
                    </select></label>
                    <label className="attendance-filter-field" htmlFor={`attendance-date-${school.id}`}>Date<input id={`attendance-date-${school.id}`} type="date" value={attendanceDate} onChange={(event) => {
                      setAttendanceDate(event.target.value)
                      setAttendanceLoaded(false)
                    }} required />
                    </label>
                    <button className="primary-button small-button" type="submit" disabled={attendanceLoading}>
                      {attendanceLoading ? 'Loading…' : 'Load class'}
                    </button>
                  </form>
                  {attendanceLoaded && attendanceRows.length ? (
                    <>
                      <DataTools title={`Attendance ${attendanceDate}`} rows={attendanceRows.map((row) => ({ admission_number: row.student.admission_number, student: `${row.student.first_name} ${row.student.last_name}`, status: row.status || '' }))} fields={[{ key: 'admission_number', label: 'admission_number', required: true }, { key: 'student', label: 'student' }, { key: 'status', label: 'status', required: true }]} canImport={canTakeAttendance} onImportBatch={importAttendance} />
                      <ul className="attendance-list">
                        {attendanceRows.map((row) => (
                          <li key={row.enrollment_id}>
                            <div className="student-summary">
                              <strong>{row.student.last_name}, {row.student.first_name}</strong>
                              <span>{row.student.admission_number}</span>
                            </div>
                            <label className="visually-hidden" htmlFor={`attendance-status-${row.enrollment_id}`}>Attendance for {row.student.first_name} {row.student.last_name}</label>
                            <select id={`attendance-status-${row.enrollment_id}`} value={row.status || ''} onChange={(event) => updateAttendance(row.enrollment_id, event.target.value)} required>
                              <option value="">Select status</option>
                              <option value="present">Present</option>
                              <option value="absent">Absent</option>
                              <option value="late">Late</option>
                              <option value="excused">Excused</option>
                            </select>
                          </li>
                        ))}
                      </ul>
                      <button className="primary-button small-button" type="button" onClick={saveAttendance} disabled={attendanceSaving || attendanceRows.some((row) => !row.status)}>
                        {attendanceSaving ? 'Saving…' : 'Save attendance'}
                      </button>
                    </>
                  ) : null}
                  {attendanceLoaded && attendanceRows.length === 0 ? <p className="muted-copy">No students enrolled in this class on the selected date.</p> : null}
                </section>
              ) : null}
            </>
          ) : null}
          </>}
        </div>
      ) : null}
    </div>
  )
}
