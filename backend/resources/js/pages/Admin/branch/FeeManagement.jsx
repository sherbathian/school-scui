import { Fragment, useCallback, useEffect, useRef, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) {
    const validationMessage = Object.values(result.errors || {}).flat()[0]
    throw new Error(validationMessage || result.message || 'That request could not be completed.')
  }
  return result.data
}

function currentAcademicYear() {
  const year = new Date().getFullYear()
  return `${year}-${year + 1}`
}

function formatAmount(amount) {
  return Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function FeeManagement({ school, expanded = false, standalone = false, activeView = 'all', canManage = false }) {
  const [open, setOpen] = useState(expanded)
  const currentYear = school.active_session?.academic_year || currentAcademicYear()
  const [academicYear, setAcademicYear] = useState(currentYear)
  const [years, setYears] = useState([])
  const historical = academicYear !== currentYear
  const [feeTypes, setFeeTypes] = useState([])
  const [invoices, setInvoices] = useState([])
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [title, setTitle] = useState('')
  const [amount, setAmount] = useState('')
  const [studentId, setStudentId] = useState('')
  const [feeTypeId, setFeeTypeId] = useState('')
  const [dueDate, setDueDate] = useState('')
  const [editingFeeTypeId, setEditingFeeTypeId] = useState(null)
  const [editFeeTitle, setEditFeeTitle] = useState('')
  const [editFeeAmount, setEditFeeAmount] = useState('')
  const [payments, setPayments] = useState({})
  const [busyKey, setBusyKey] = useState('')
  const [search, setSearch] = useState('')
  const visibleInvoices = invoices.filter((invoice) => `${invoice.student.last_name} ${invoice.student.first_name} ${invoice.student.admission_number} ${invoice.fee_type.title} ${invoice.status} ${invoice.due_date}`.toLowerCase().includes(search.toLowerCase()))
  const paymentImportReferences = useRef(new Set())

  const refreshData = useCallback(async (signal) => {
    const query = new URLSearchParams({ academic_year: academicYear, historical: historical ? '1' : '0' })
    const [feeTypeResponse, invoiceResponse, studentResponse] = await Promise.all([
      apiRequest(`/api/schools/${school.id}/legacy-fee-types?${query}`, signal ? { signal } : undefined),
      apiRequest(`/api/schools/${school.id}/fee-invoices?${query}`, signal ? { signal } : undefined),
      apiRequest(`/api/schools/${school.id}/fee-students?${query}`, signal ? { signal } : undefined),
    ])
    const [feeTypeData, invoiceData, studentData] = await Promise.all([
      readData(feeTypeResponse),
      readData(invoiceResponse),
      readData(studentResponse),
    ])
    setFeeTypes(feeTypeData)
    setInvoices(invoiceData)
    setStudents(studentData)
    setFeeTypeId((current) => current && feeTypeData.some((feeType) => String(feeType.id) === current && feeType.status === 'active')
      ? current
      : String(feeTypeData.find((feeType) => feeType.status === 'active')?.id || ''))
    setStudentId((current) => current && studentData.some((student) => String(student.id) === current && student.status === 'active')
      ? current
      : String(studentData.find((student) => student.status === 'active')?.id || ''))
  }, [academicYear, historical, school.id])

  useEffect(() => {
    if (!open) return undefined
    const controller = new AbortController()
    apiRequest(`/api/schools/${school.id}/fee-years`, { signal: controller.signal })
      .then(readData).then(setYears)
      .catch(error => { if (!controller.signal.aborted) setError(error.message) })
    return () => controller.abort()
  }, [open, school.id])

  useEffect(() => {
    if (!open) return undefined
    const controller = new AbortController()

    async function loadFees() {
      setLoading(true)
      setError('')
      try {
        await refreshData(controller.signal)
      } catch (loadError) {
        if (!controller.signal.aborted) setError(loadError.message)
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }

    loadFees()
    return () => controller.abort()
  }, [open, academicYear, refreshData])

  async function createFeeType(event) {
    event.preventDefault()
    setError('')
    setBusyKey('fee-type')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/legacy-fee-types`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, amount, academic_year: academicYear }),
      }))
      setTitle('')
      setAmount('')
      await refreshData()
    } catch (createError) {
      setError(createError.message)
    } finally {
      setBusyKey('')
    }
  }

  async function importFeeType(row) {
    if (row.academic_year && row.academic_year !== academicYear) throw new Error('Academic year must match the selected year.')
    const type = await readData(await apiRequest(`/api/schools/${school.id}/legacy-fee-types`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: row.title, amount: row.amount, academic_year: academicYear, historical }),
    }))
    setFeeTypes((current) => [...current, type])
  }

  async function importInvoice(row) {
    if (row.academic_year && row.academic_year !== academicYear) throw new Error('Academic year must match the selected year.')
    if (row.status === 'void') throw new Error('Void invoices cannot be imported as outstanding balances.')
    const student = students.find((item) => item.admission_number.toLowerCase() === row.admission_number.toLowerCase())
    const feeType = feeTypes.find((item) => item.title.toLowerCase() === row.fee_item.toLowerCase() && item.status === 'active')
    if (!student) throw new Error('Admission number does not match an eligible enrollment in the selected year.')
    if (!feeType) throw new Error('Fee item does not match an active fee item for this academic year.')
    await readData(await apiRequest(`/api/schools/${school.id}/fee-invoices`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ student_id: student.id, fee_type_id: feeType.id, academic_year: academicYear, historical, due_date: row.due_date || null, import_amount: row.amount || undefined }),
    }))
    await refreshData()
  }

  async function importPayment(row) {
    if (row.academic_year && row.academic_year !== academicYear) throw new Error('Academic year must match the selected year.')
    if (Boolean(row.admission_number) !== Boolean(row.fee_item)) {
      throw new Error('Provide both admission number and fee item to match by student.')
    }
    const invoice = row.admission_number
      ? invoices.find(item => item.student.admission_number.toLowerCase() === row.admission_number.toLowerCase()
        && item.fee_type.title.toLowerCase() === row.fee_item.toLowerCase())
      : invoices.find(item => String(item.id) === row.invoice_id)
    if (!invoice) throw new Error('No matching invoice in this branch and academic year.')
    const reference = row.reference.trim()
    if (!reference) throw new Error('A payment reference is required for safe CSV duplicate checks.')
    const referenceKey = `${invoice.id}:${reference.toLowerCase()}`
    if (invoice.payments.some((payment) => payment.reference?.toLowerCase() === reference.toLowerCase()) || paymentImportReferences.current.has(referenceKey)) {
      throw new Error('This payment reference is already recorded on the invoice.')
    }
    await readData(await apiRequest(`/api/schools/${school.id}/fee-invoices/${invoice.id}/payments`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount: row.amount, paid_on: row.paid_on, reference, csv_import: true }),
    }))
    paymentImportReferences.current.add(referenceKey)
    await refreshData()
  }

  async function issueInvoice(event) {
    event.preventDefault()
    setError('')
    setBusyKey('issue')
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-invoices`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id: Number(studentId), fee_type_id: Number(feeTypeId), academic_year: academicYear, due_date: dueDate || null }),
      }))
      setDueDate('')
      await refreshData()
    } catch (issueError) {
      setError(issueError.message)
    } finally {
      setBusyKey('')
    }
  }

  async function archiveFeeType(feeType) {
    setError('')
    setBusyKey(`fee-type-${feeType.id}`)
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/legacy-fee-types/${feeType.id}`, { method: 'DELETE' }))
      await refreshData()
    } catch (archiveError) {
      setError(archiveError.message)
    } finally {
      setBusyKey('')
    }
  }

  async function saveFeeType(event, feeType) {
    event.preventDefault()
    setError('')
    setBusyKey(`fee-type-${feeType.id}`)
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/legacy-fee-types/${feeType.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: editFeeTitle, amount: editFeeAmount }),
      }))
      setEditingFeeTypeId(null)
      await refreshData()
    } catch (saveError) {
      setError(saveError.message)
    } finally {
      setBusyKey('')
    }
  }

  async function restoreFeeType(feeType) {
    setError('')
    setBusyKey(`fee-type-${feeType.id}`)
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/legacy-fee-types/${feeType.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: 'active' }),
      }))
      await refreshData()
    } catch (restoreError) {
      setError(restoreError.message)
    } finally {
      setBusyKey('')
    }
  }

  async function recordPayment(event, invoice) {
    event.preventDefault()
    setError('')
    setBusyKey(`payment-${invoice.id}`)
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-invoices/${invoice.id}/payments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ amount: payments[invoice.id]?.amount || '', reference: payments[invoice.id]?.reference || null }),
      }))
      setPayments((current) => ({ ...current, [invoice.id]: { amount: '', reference: '' } }))
      await refreshData()
    } catch (paymentError) {
      setError(paymentError.message)
    } finally {
      setBusyKey('')
    }
  }

  async function voidInvoice(invoice) {
    setError('')
    setBusyKey(`invoice-${invoice.id}`)
    try {
      await readData(await apiRequest(`/api/schools/${school.id}/fee-invoices/${invoice.id}`, { method: 'DELETE' }))
      await refreshData()
    } catch (voidError) {
      setError(voidError.message)
    } finally {
      setBusyKey('')
    }
  }

  return (
    <div className="academic-structure">
      {!standalone ? <button className="team-toggle" type="button" aria-expanded={open} onClick={() => setOpen((current) => !current)}>
        {open ? 'Hide fee management' : 'Manage fees'}
      </button> : null}
      {open ? (
        <div className="academic-panel fee-panel">
          <div className="academic-section-heading">
            <h4>Fees</h4>
            <label>Academic year
              <select value={academicYear} disabled={loading || Boolean(busyKey)} onChange={event => {
                setLoading(true)
                setFeeTypes([])
                setInvoices([])
                setStudents([])
                setEditingFeeTypeId(null)
                setAcademicYear(event.target.value)
              }}>
                {[...new Set([currentYear, ...years])].map(year => <option key={year} value={year}>{year}{year === currentYear ? ' (current)' : ''}</option>)}
              </select>
            </label>
          </div>
          {error ? <p className="form-error" role="alert">{error}</p> : null}
          {loading ? <p className="muted-copy" role="status">Loading fees…</p> : null}
          {!loading ? (
            <>
              {(activeView === 'all' || activeView === 'fee-items') ? <section aria-labelledby={`fee-items-heading-${school.id}`}>
                <div className="academic-section-heading"><h4 id={`fee-items-heading-${school.id}`}>Invoice fee items</h4><span>{feeTypes.length}</span></div>
                <DataTools title="Invoice fee items" rows={feeTypes} fields={[{ key: 'title', label: 'title', required: true }, { key: 'amount', label: 'amount', required: true }, { key: 'academic_year', label: 'academic_year' }, { key: 'status', label: 'status' }]} canImport={canManage} onImport={importFeeType} />
                {canManage && !historical ? <form className="fee-item-form" onSubmit={createFeeType}>
                  <label htmlFor={`fee-title-${school.id}`}>Fee item name</label>
                  <input id={`fee-title-${school.id}`} value={title} onChange={(event) => setTitle(event.target.value)} required />
                  <label htmlFor={`fee-amount-${school.id}`}>Amount</label>
                  <input id={`fee-amount-${school.id}`} type="number" min="0.01" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} required />
                  <button className="primary-button small-button" type="submit" disabled={busyKey === 'fee-type'}>Add fee item</button>
                </form> : null}
                <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search invoice fee items" placeholder="Search invoice fee items" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
                {feeTypes.length ? (
                  <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Fee item</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>{feeTypes.filter((feeType) => `${feeType.title} ${feeType.status}`.toLowerCase().includes(search.trim().toLowerCase())).map((feeType) => (
                    <Fragment key={feeType.id}>
                      <tr><td>{feeType.title}</td><td>{formatAmount(feeType.amount)}</td><td>{feeType.status}</td><td>{canManage ? <><TableAction action="edit" label="Edit fee item" onClick={() => { setEditFeeTitle(feeType.title); setEditFeeAmount(feeType.amount); setEditingFeeTypeId(feeType.id) }} />{feeType.status === 'active' ? <TableAction action="archive" label="Archive fee item" danger onClick={() => archiveFeeType(feeType)} disabled={busyKey === `fee-type-${feeType.id}`} /> : <TableAction action="restore" label="Restore fee item" onClick={() => restoreFeeType(feeType)} disabled={busyKey === `fee-type-${feeType.id}`} />}</> : null}</td></tr>
                      {canManage && editingFeeTypeId === feeType.id ? <tr><td colSpan="4"><form className="fee-type-edit-form" onSubmit={(event) => saveFeeType(event, feeType)}>
                        <label htmlFor={`edit-fee-title-${feeType.id}`}>Fee item name</label><input id={`edit-fee-title-${feeType.id}`} value={editFeeTitle} onChange={(event) => setEditFeeTitle(event.target.value)} required />
                        <label htmlFor={`edit-fee-amount-${feeType.id}`}>Fee item amount</label><input id={`edit-fee-amount-${feeType.id}`} type="number" min="0.01" step="0.01" value={editFeeAmount} onChange={(event) => setEditFeeAmount(event.target.value)} required />
                        <button className="quiet-button" type="submit" disabled={busyKey === `fee-type-${feeType.id}`}>Save</button><button className="quiet-button" type="button" onClick={() => setEditingFeeTypeId(null)}>Cancel</button>
                      </form></td></tr> : null}
                    </Fragment>
                  ))}</tbody></table></div>
                ) : <p className="muted-copy">No fee items for this academic year yet.</p>}
              </section> : null}

              {(activeView === 'all' || activeView === 'invoices') ? <>
              <section aria-labelledby={`issue-fee-heading-${school.id}`}>
                <div className="academic-section-heading"><h4 id={`issue-fee-heading-${school.id}`}>{historical ? 'Historical invoices' : 'Issue a student invoice'}</h4></div>
                {canManage && !historical ? <form className="fee-issue-form" onSubmit={issueInvoice}>
                  <label htmlFor={`fee-student-${school.id}`}>Student</label>
                  <select id={`fee-student-${school.id}`} value={studentId} onChange={(event) => setStudentId(event.target.value)} required>
                    <option value="">Choose a student</option>
                    {students.map((student) => <option key={student.id} value={student.id}>{student.last_name}, {student.first_name} · {student.admission_number}</option>)}
                  </select>
                  <label htmlFor={`fee-type-${school.id}`}>Fee item</label>
                  <select id={`fee-type-${school.id}`} value={feeTypeId} onChange={(event) => setFeeTypeId(event.target.value)} required>
                    <option value="">Choose a fee item</option>
                    {feeTypes.filter((feeType) => feeType.status === 'active').map((feeType) => <option key={feeType.id} value={feeType.id}>{feeType.title} · {formatAmount(feeType.amount)}</option>)}
                  </select>
                  <label htmlFor={`fee-due-date-${school.id}`}>Due date <span>(optional)</span></label>
                  <input id={`fee-due-date-${school.id}`} type="date" value={dueDate} onChange={(event) => setDueDate(event.target.value)} />
                  <button className="primary-button small-button" type="submit" disabled={busyKey === 'issue' || !students.length || !feeTypes.some((feeType) => feeType.status === 'active')}>Issue invoice</button>
                </form> : null}
                {historical ? <p className="muted-copy">Import historical invoices below using fee items from the selected year.</p> : !students.length ? <p className="muted-copy">No active students are enrolled in {academicYear}.</p> : null}
              </section>

              <section aria-labelledby={`invoices-heading-${school.id}`}>
                <div className="academic-section-heading"><h4 id={`invoices-heading-${school.id}`}>Student invoices</h4><span>{invoices.length}</span></div>
                <DataTools title="Student invoices" rows={invoices.map((invoice) => ({ admission_number: invoice.student.admission_number, student: `${invoice.student.last_name}, ${invoice.student.first_name}`, fee_item: invoice.fee_type.title, academic_year: invoice.academic_year, amount: invoice.amount, amount_paid: invoice.amount_paid, balance: invoice.balance, due_date: invoice.due_date, status: invoice.status }))} fields={[{ key: 'admission_number', label: 'admission_number', required: true }, { key: 'fee_item', label: 'fee_item', required: true }, { key: 'due_date', label: 'due_date' }, { key: 'academic_year', label: 'academic_year' }, { key: 'amount', label: 'amount' }, { key: 'status', label: 'status' }]} exportFields={[{ key: 'admission_number', label: 'admission_number' }, { key: 'student', label: 'student' }, { key: 'fee_item', label: 'fee_item' }, { key: 'academic_year', label: 'academic_year' }, { key: 'amount', label: 'amount' }, { key: 'amount_paid', label: 'amount_paid' }, { key: 'balance', label: 'balance' }, { key: 'due_date', label: 'due_date' }, { key: 'status', label: 'status' }]} canImport={canManage} onImport={importInvoice} />
                <DataTools title="Payment history" rows={invoices.flatMap((invoice) => invoice.payments.map((payment) => ({ invoice_id: invoice.id, academic_year: invoice.academic_year, admission_number: invoice.student.admission_number, student: `${invoice.student.last_name}, ${invoice.student.first_name}`, fee_item: invoice.fee_type.title, amount: payment.amount, paid_on: payment.paid_on, reference: payment.reference || '' })))} fields={[{ key: 'invoice_id', label: 'invoice_id' }, { key: 'admission_number', label: 'admission_number' }, { key: 'fee_item', label: 'fee_item' }, { key: 'amount', label: 'amount', required: true }, { key: 'paid_on', label: 'paid_on', required: true }, { key: 'reference', label: 'reference', required: true }, { key: 'academic_year', label: 'academic_year' }]} exportFields={[{ key: 'invoice_id', label: 'invoice_id' }, { key: 'academic_year', label: 'academic_year' }, { key: 'admission_number', label: 'admission_number' }, { key: 'student', label: 'student' }, { key: 'fee_item', label: 'fee_item' }, { key: 'amount', label: 'amount' }, { key: 'paid_on', label: 'paid_on' }, { key: 'reference', label: 'reference' }]} canImport={canManage} onImport={importPayment} />
                <label className="module-search table-search"><span aria-hidden="true">⌕</span><input aria-label="Search invoices" placeholder="Search student, invoice or status" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
                {invoices.length ? (
                  <div className="crud-table-wrap"><table className="crud-table"><thead><tr><th>Student</th><th>Invoice</th><th>Record payment</th><th>Actions / history</th></tr></thead><tbody>
                    {visibleInvoices.map((invoice) => <tr key={invoice.id}>
                      <td>{invoice.student.last_name}, {invoice.student.first_name}<small>{invoice.student.admission_number}</small></td>
                      <td>{invoice.fee_type.title} · {invoice.status}<small>Amount {formatAmount(invoice.amount)} · Paid {formatAmount(invoice.amount_paid)} · Balance {formatAmount(invoice.balance)}{invoice.due_date ? ` · Due ${invoice.due_date}` : ''}</small></td>
                      <td>{canManage && invoice.status !== 'void' && Number(invoice.balance) > 0 ? <form className="fee-payment-form" onSubmit={(event) => recordPayment(event, invoice)}>
                        <label className="visually-hidden" htmlFor={`payment-amount-${invoice.id}`}>Payment amount for invoice {invoice.id}</label><input id={`payment-amount-${invoice.id}`} type="number" min="0.01" max={invoice.balance} step="0.01" placeholder="Amount" value={payments[invoice.id]?.amount || ''} onChange={(event) => setPayments((current) => ({ ...current, [invoice.id]: { ...current[invoice.id], amount: event.target.value } }))} required />
                        <label className="visually-hidden" htmlFor={`payment-reference-${invoice.id}`}>Payment reference for invoice {invoice.id}</label><input id={`payment-reference-${invoice.id}`} placeholder="Receipt reference (optional)" value={payments[invoice.id]?.reference || ''} onChange={(event) => setPayments((current) => ({ ...current, [invoice.id]: { ...current[invoice.id], reference: event.target.value } }))} />
                        <button className="primary-button small-button" type="submit" disabled={busyKey === `payment-${invoice.id}`}>Record payment</button>
                      </form> : '—'}</td>
                      <td>{canManage && invoice.status !== 'void' && invoice.amount_paid === '0.00' ? <TableAction action="delete" label="Void invoice" danger onClick={() => voidInvoice(invoice)} disabled={busyKey === `invoice-${invoice.id}`} /> : null}{invoice.payments.length ? <details className="fee-payment-history"><summary>Payments ({invoice.payments.length})</summary><ul>{invoice.payments.map((payment) => <li key={payment.id}>{formatAmount(payment.amount)} · {payment.paid_on}{payment.reference ? ` · ${payment.reference}` : ''}</li>)}</ul></details> : null}</td>
                    </tr>)}
                  </tbody></table></div>
                ) : <p className="muted-copy">No invoices for {academicYear} yet.</p>}
              </section>
              </> : null}
            </>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
