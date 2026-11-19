import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from './api.js'
import DataTools from './DataTools.jsx'
import TableAction from './TableAction.jsx'

async function readData(response) {
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'That request could not be completed.')
  return result.data
}

const emptyExpense = { expense_head_id: '', name: '', invoice_number: '', expense_date: new Date().toISOString().slice(0, 10), amount: '', description: '' }
const headFields = [{ key: 'title', label: 'title', required: true }, { key: 'description', label: 'description' }]
const expenseFields = [{ key: 'expense_head_id', label: 'expense_head_id', required: true }, { key: 'name', label: 'name', required: true }, { key: 'invoice_number', label: 'invoice_number' }, { key: 'expense_date', label: 'expense_date', required: true }, { key: 'amount', label: 'amount', required: true }, { key: 'description', label: 'description' }]
const expenseExportFields = [...expenseFields, { key: 'expense_head', label: 'expense_head' }]

function useTable(rows, search, pageSize) {
  const [sort, setSort] = useState({ key: '', direction: 'asc' })
  const [page, setPage] = useState(1)
  useEffect(() => setPage(1), [search, pageSize])
  const sorted = useMemo(() => {
    const filtered = rows.filter((row) => Object.values(row).some((value) => String(value ?? '').toLowerCase().includes(search.toLowerCase())))
    if (!sort.key) return filtered
    return [...filtered].sort((a, b) => {
      const left = a[sort.key] ?? ''
      const right = b[sort.key] ?? ''
      const comparison = !Number.isNaN(Number(left)) && !Number.isNaN(Number(right)) && left !== '' && right !== '' ? Number(left) - Number(right) : String(left).localeCompare(String(right), undefined, { numeric: true, sensitivity: 'base' })
      return sort.direction === 'asc' ? comparison : -comparison
    })
  }, [rows, search, sort])
  const count = sorted.length
  const pages = Math.max(1, Math.ceil(count / pageSize))
  const safePage = Math.min(page, pages)
  return { rows: sorted.slice((safePage - 1) * pageSize, safePage * pageSize), count, page: safePage, pages, setPage, sort, setSort }
}

function SortableHeader({ label, field, sort, setSort }) {
  const active = sort.key === field
  return <button className="expense-sort-button" type="button" onClick={() => setSort({ key: field, direction: active && sort.direction === 'asc' ? 'desc' : 'asc' })} aria-label={`Sort by ${label} ${active && sort.direction === 'asc' ? 'descending' : 'ascending'}`}>{label}<span aria-hidden="true">{active ? (sort.direction === 'asc' ? '↑' : '↓') : '↕'}</span></button>
}

function TablePager({ table, pageSize, setPageSize }) {
  const start = table.count ? (table.page - 1) * pageSize + 1 : 0
  const end = Math.min(table.page * pageSize, table.count)
  return <footer className="expense-table-footer"><span>Showing {start} to {end} of {table.count} entries</span><label>Rows <select aria-label="Rows per page" value={pageSize} onChange={(event) => setPageSize(Number(event.target.value))}><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></label><nav aria-label="Table pagination"><button type="button" disabled={table.page <= 1} onClick={() => table.setPage(table.page - 1)}>‹</button>{Array.from({ length: table.pages }, (_, index) => index + 1).map((page) => <button type="button" key={page} className={table.page === page ? 'active' : ''} aria-current={table.page === page ? 'page' : undefined} onClick={() => table.setPage(page)}>{page}</button>)}<button type="button" disabled={table.page >= table.pages} onClick={() => table.setPage(table.page + 1)}>›</button></nav></footer>
}

function TableSearch({ value, onChange, label }) {
  return <label className="module-search expense-table-search"><span aria-hidden="true">⌕</span><input type="search" aria-label={`Search ${label}`} placeholder={`Search ${label.toLowerCase()}`} value={value} onChange={(event) => onChange(event.target.value)} /></label>
}

export default function ExpenseManagement({ school, activeView = 'expense-search', canManage = false }) {
  const [heads, setHeads] = useState([])
  const [expenses, setExpenses] = useState([])
  const [draftHead, setDraftHead] = useState({ title: '', description: '' })
  const [editingHeadId, setEditingHeadId] = useState(null)
  const [showHeadForm, setShowHeadForm] = useState(false)
  const [draftExpense, setDraftExpense] = useState(emptyExpense)
  const [attachment, setAttachment] = useState(null)
  const [editingExpenseId, setEditingExpenseId] = useState(null)
  const [showExpenseForm, setShowExpenseForm] = useState(false)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [headSearch, setHeadSearch] = useState('')
  const [expenseSearch, setExpenseSearch] = useState('')
  const [reportSearch, setReportSearch] = useState('')
  const [searchType, setSearchType] = useState('week')
  const [customFrom, setCustomFrom] = useState('')
  const [customTo, setCustomTo] = useState('')
  const [headPageSize, setHeadPageSize] = useState(10)
  const [expensePageSize, setExpensePageSize] = useState(10)
  const [reportPageSize, setReportPageSize] = useState(10)

  async function refresh() {
    setLoading(true)
    try {
      const [headRows, expenseRows] = await Promise.all([
        apiRequest(`/api/schools/${school.id}/expense-heads`).then(readData),
        apiRequest(`/api/schools/${school.id}/expenses`).then(readData),
      ])
      setHeads(headRows)
      setExpenses(expenseRows)
    } catch (loadError) { setError(loadError.message) } finally { setLoading(false) }
  }

  useEffect(() => { refresh() }, [school.id])

  const headTable = useTable(heads, headSearch, headPageSize)
  const expenseTable = useTable(expenses, expenseSearch, expensePageSize)
  const reportRows = useMemo(() => {
    const today = new Date()
    let from = null
    let to = today
    if (searchType === 'week') { from = new Date(today); from.setDate(today.getDate() - today.getDay()) }
    if (searchType === 'month') from = new Date(today.getFullYear(), today.getMonth(), 1)
    if (searchType === 'year') from = new Date(today.getFullYear(), 0, 1)
    if (searchType === 'custom') { from = customFrom ? new Date(`${customFrom}T00:00:00`) : null; to = customTo ? new Date(`${customTo}T23:59:59`) : null }
    return expenses.filter((row) => {
      const date = new Date(`${row.expense_date}T12:00:00`)
      const matchesDate = (!from || date >= from) && (!to || date <= to)
      const matchesSearch = `${row.name} ${row.invoice_number || ''} ${row.expense_head || ''} ${row.description || ''}`.toLowerCase().includes(reportSearch.toLowerCase())
      return matchesDate && matchesSearch
    })
  }, [expenses, searchType, customFrom, customTo, reportSearch])
  const reportTable = useTable(reportRows, '', reportPageSize)
  const total = reportRows.reduce((sum, row) => sum + Number(row.amount || 0), 0)

  async function importHead(row) {
    const created = await readData(await apiRequest(`/api/schools/${school.id}/expense-heads`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(row) }))
    setHeads((current) => [...current, created].sort((a, b) => a.title.localeCompare(b.title)))
  }

  async function importExpense(row) {
    const head = heads.find((item) => String(item.id) === String(row.expense_head_id) || item.title.toLowerCase() === String(row.expense_head_id).toLowerCase())
    if (!head) throw new Error('expense_head_id must be a valid head ID or title from this school.')
    const created = await readData(await apiRequest(`/api/schools/${school.id}/expenses`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...row, expense_head_id: head.id, amount: Number(row.amount) }) }))
    setExpenses((current) => [created, ...current])
  }

  async function saveHead(event) {
    event.preventDefault(); setBusy(true); setError(''); setSuccess('')
    try {
      const response = await apiRequest(`/api/schools/${school.id}/expense-heads${editingHeadId ? `/${editingHeadId}` : ''}`, { method: editingHeadId ? 'PATCH' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(draftHead) })
      const saved = await readData(response)
      setHeads((current) => editingHeadId ? current.map((item) => item.id === saved.id ? saved : item) : [...current, saved].sort((a, b) => a.title.localeCompare(b.title)))
      setDraftHead({ title: '', description: '' }); setEditingHeadId(null); setShowHeadForm(false); setSuccess(editingHeadId ? 'Expense head updated.' : 'Expense head added.')
    } catch (saveError) { setError(saveError.message) } finally { setBusy(false) }
  }

  async function saveExpense(event) {
    event.preventDefault(); setBusy(true); setError(''); setSuccess('')
    try {
      const data = new FormData()
      Object.entries(draftExpense).forEach(([key, value]) => data.append(key, value))
      if (attachment) data.append('attachment', attachment)
      const response = await apiRequest(`/api/schools/${school.id}/expenses${editingExpenseId ? `/${editingExpenseId}` : ''}`, { method: editingExpenseId ? 'POST' : 'POST', body: editingExpenseId ? (() => { data.append('_method', 'PATCH'); return data })() : data })
      const saved = await readData(response)
      setExpenses((current) => editingExpenseId ? current.map((item) => item.id === saved.id ? saved : item) : [saved, ...current])
      setDraftExpense(emptyExpense); setAttachment(null); setEditingExpenseId(null); setShowExpenseForm(false); setSuccess(editingExpenseId ? 'Expense updated.' : 'Expense recorded.')
    } catch (saveError) { setError(saveError.message) } finally { setBusy(false) }
  }

  async function removeHead(head) {
    if (!window.confirm(`Delete expense head “${head.title}”?`)) return
    try { await readData(await apiRequest(`/api/schools/${school.id}/expense-heads/${head.id}`, { method: 'DELETE' })); setHeads((current) => current.filter((item) => item.id !== head.id)); setSuccess('Expense head deleted.') } catch (deleteError) { setError(deleteError.message) }
  }

  async function removeExpense(expense) {
    if (!window.confirm(`Delete expense “${expense.name}”?`)) return
    try { await readData(await apiRequest(`/api/schools/${school.id}/expenses/${expense.id}`, { method: 'DELETE' })); setExpenses((current) => current.filter((item) => item.id !== expense.id)); setSuccess('Expense deleted.') } catch (deleteError) { setError(deleteError.message) }
  }

  function editExpense(expense) {
    setDraftExpense({ expense_head_id: String(expense.expense_head_id), name: expense.name, invoice_number: expense.invoice_number || '', expense_date: expense.expense_date, amount: expense.amount, description: expense.description || '' })
    setEditingExpenseId(expense.id); setAttachment(null); setShowExpenseForm(true)
  }

  function editHead(head) {
    setDraftHead({ title: head.title, description: head.description || '' })
    setEditingHeadId(head.id)
    setShowHeadForm(true)
  }

  function table(rows, controls, type) {
    const isHeads = type === 'heads'
    const columns = isHeads ? [['title', 'Expense head'], ['description', 'Description']] : [['name', 'Name'], ...(type === 'search' ? [] : [['description', 'Description']]), ['invoice_number', 'Invoice number'], ...(type === 'search' ? [['expense_head', 'Expense head']] : []), ['expense_date', 'Date'], ['amount', 'Amount']]
    return <><div className="crud-table-wrap"><table className="crud-table expense-table"><thead><tr>{columns.map(([key, label]) => <th key={key}><SortableHeader label={label} field={key} sort={controls.sort} setSort={controls.setSort} /></th>)}{!isHeads || activeView === 'expense-heads' ? <th>Actions</th> : null}</tr></thead><tbody>{rows.map((row) => <tr key={row.id}>{columns.map(([key]) => <td key={key}>{key === 'amount' ? Number(row[key]).toLocaleString(undefined, { minimumFractionDigits: 2 }) : row[key] || '—'}{key === 'expense_head' && row.expense_date ? <small>{row.expense_date}</small> : null}</td>)}{!isHeads ? <td><div className="expense-row-actions">{row.attachment_url ? <a className="table-action" href={row.attachment_url} target="_blank" rel="noreferrer" aria-label="Open attachment" title="Open attachment">↗</a> : null}{canManage ? <><TableAction action="edit" label="Edit expense" onClick={() => editExpense(row)} /><TableAction action="delete" label="Delete expense" danger onClick={() => removeExpense(row)} /></> : null}</div></td> : activeView === 'expense-heads' ? <td><div className="expense-row-actions">{canManage ? <><TableAction action="edit" label="Edit expense head" onClick={() => editHead(row)} /><TableAction action="delete" label="Delete expense head" danger onClick={() => removeHead(row)} /></> : null}</div></td> : null}</tr>)}{!rows.length ? <tr><td className="expense-empty-cell" colSpan={columns.length + 1}>No matching records found.</td></tr> : null}</tbody>{type === 'search' ? <tfoot><tr><td colSpan={columns.length - 1}>Grand total</td><td>{total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td></tr></tfoot> : null}</table></div><TablePager table={controls} pageSize={type === 'heads' ? headPageSize : type === 'search' ? reportPageSize : expensePageSize} setPageSize={type === 'heads' ? setHeadPageSize : type === 'search' ? setReportPageSize : setExpensePageSize} /></>
  }

  return <section className="academic-panel expense-module">
    {error ? <p className="form-error" role="alert">{error}</p> : null}{success ? <p className="expense-success" role="status">{success}</p> : null}
    {loading ? <p className="muted-copy" role="status">Loading expenses…</p> : null}
    {activeView === 'expense-heads' ? <>
      <header className="expense-page-heading"><div><p className="eyebrow">Finance</p><h2>Expense heads</h2><p>Manage the categories used to organize school expenses.</p></div></header>
      {!showHeadForm && <div className="expense-list-toolbar"><TableSearch label="expense heads" value={headSearch} onChange={setHeadSearch} /><DataTools title="Expense Heads" rows={heads} fields={headFields} canImport={canManage} onImport={importHead} /><span className="expense-record-count">{headTable.count} categories</span>{canManage ? <button className="primary-button small-button" type="button" onClick={() => { setDraftHead({ title: '', description: '' }); setEditingHeadId(null); setShowHeadForm(true) }}>＋ Add expense head</button> : null}</div>}
      {canManage && showHeadForm ? <form className="expense-form-panel" onSubmit={saveHead}><div className="expense-form-heading"><div><p className="eyebrow">Expense setup</p><h3>{editingHeadId ? 'Edit expense head' : 'Add expense head'}</h3><p>Use a clear category name so expense records stay easy to filter and report.</p></div><button className="quiet-button" type="button" onClick={() => { setShowHeadForm(false); setEditingHeadId(null); setDraftHead({ title: '', description: '' }) }}>Cancel</button></div><div className="expense-form-grid expense-head-form-grid"><label>Expense head <span>*</span><input value={draftHead.title} onChange={(event) => setDraftHead({ ...draftHead, title: event.target.value })} required maxLength="150" /></label><label>Description<textarea rows="3" value={draftHead.description} onChange={(event) => setDraftHead({ ...draftHead, description: event.target.value })} /></label></div><div className="student-form-actions"><button className="primary-button small-button" disabled={busy}>{busy ? 'Saving…' : editingHeadId ? 'Save changes' : 'Save expense head'}</button></div></form> : null}
      {!showHeadForm && <section className="expense-card expense-list-card"><header><div><h3>Expense head list</h3><p>Review and maintain the available categories.</p></div><span>{heads.length} total</span></header>{table(headTable.rows, headTable, 'heads')}</section>}
    </> : null}
    {activeView === 'expense-add' ? <>
      <header className="expense-page-heading"><div><p className="eyebrow">Finance</p><h2>Expenses</h2><p>Record school spending and review recent expense entries.</p></div></header>
      {!showExpenseForm && <div className="expense-list-toolbar"><TableSearch label="expenses" value={expenseSearch} onChange={setExpenseSearch} /><DataTools title="Expenses" rows={expenses} fields={expenseFields} exportFields={expenseExportFields} canImport={canManage} onImport={importExpense} /><span className="expense-record-count">{expenseTable.count} records</span>{canManage ? <button className="primary-button small-button" type="button" onClick={() => { setDraftExpense(emptyExpense); setEditingExpenseId(null); setAttachment(null); setShowExpenseForm(true) }}>＋ Add expense</button> : null}</div>}
      {canManage && showExpenseForm ? <form className="expense-form-panel" onSubmit={saveExpense}><div className="expense-form-heading"><div><p className="eyebrow">Expense entry</p><h3>{editingExpenseId ? 'Edit expense' : 'Add expense'}</h3><p>Enter the expense details and attach a supporting document if available.</p></div><button className="quiet-button" type="button" onClick={() => { setEditingExpenseId(null); setDraftExpense(emptyExpense); setAttachment(null); setShowExpenseForm(false) }}>Cancel</button></div><div className="expense-form-grid"><label>Expense head <span>*</span><select required value={draftExpense.expense_head_id} onChange={(event) => setDraftExpense({ ...draftExpense, expense_head_id: event.target.value })}><option value="">Select an expense head</option>{heads.map((head) => <option key={head.id} value={head.id}>{head.title}</option>)}</select></label><label>Name <span>*</span><input required value={draftExpense.name} onChange={(event) => setDraftExpense({ ...draftExpense, name: event.target.value })} /></label><label>Invoice number<input value={draftExpense.invoice_number} onChange={(event) => setDraftExpense({ ...draftExpense, invoice_number: event.target.value })} /></label><label>Date <span>*</span><input type="date" required value={draftExpense.expense_date} onChange={(event) => setDraftExpense({ ...draftExpense, expense_date: event.target.value })} /></label><label>Amount <span>*</span><input type="number" min="0.01" step="0.01" required value={draftExpense.amount} onChange={(event) => setDraftExpense({ ...draftExpense, amount: event.target.value })} /></label><label>Attach document<input type="file" accept=".pdf,image/png,image/jpeg" onChange={(event) => setAttachment(event.target.files?.[0] || null)} /><small>PDF or image · max 5 MB</small></label><label className="expense-description-field">Description<textarea rows="3" value={draftExpense.description} onChange={(event) => setDraftExpense({ ...draftExpense, description: event.target.value })} /></label></div><div className="student-form-actions"><button className="primary-button small-button" disabled={busy}>{busy ? 'Saving…' : editingExpenseId ? 'Save changes' : 'Save expense'}</button></div></form> : null}
      {!showExpenseForm && <section className="expense-card expense-list-card"><header><div><h3>Expense list</h3><p>Search, sort, and manage expense records.</p></div><span>{expenses.length} total</span></header>{table(expenseTable.rows, expenseTable, 'add')}</section>}
    </> : null}
    {activeView === 'expense-search' ? <><section className="expense-card expense-criteria"><header><h3>Select criteria</h3><p>Filter expenses by period and match a name, invoice number or expense head.</p></header><div className="expense-criteria-grid"><label>Search type<select value={searchType} onChange={(event) => setSearchType(event.target.value)}><option value="week">This week</option><option value="month">This month</option><option value="year">This year</option><option value="custom">Custom date range</option><option value="all">All dates</option></select></label>{searchType === 'custom' ? <><label>From<input type="date" value={customFrom} onChange={(event) => setCustomFrom(event.target.value)} /></label><label>To<input type="date" value={customTo} onChange={(event) => setCustomTo(event.target.value)} /></label></> : null}<label className="expense-search-wide">Search<input placeholder="Search by expense" value={reportSearch} onChange={(event) => setReportSearch(event.target.value)} /></label></div></section><section className="expense-card"><header className="expense-report-heading"><div><h3>Expense list</h3><p>{reportRows.length} matching records</p></div><strong>Total {total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong></header><TableSearch label="expenses" value={reportSearch} onChange={setReportSearch} />{table(reportTable.rows, reportTable, 'search')}</section></> : null}
  </section>
}
