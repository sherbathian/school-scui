import { useRef, useState } from 'react'

function csvCell(value) {
  const text = value == null ? '' : typeof value === 'object' ? JSON.stringify(value) : String(value)
  const safeText = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text
  return `"${safeText.replaceAll('"', '""')}"`
}

function parseCsv(text) {
  const rows = []
  let row = []
  let cell = ''
  let quoted = false
  for (let index = 0; index < text.length; index += 1) {
    const character = text[index]
    if (quoted && character === '"' && text[index + 1] === '"') {
      cell += '"'
      index += 1
    } else if (character === '"') {
      quoted = !quoted
    } else if (!quoted && character === ',') {
      row.push(cell)
      cell = ''
    } else if (!quoted && (character === '\n' || character === '\r')) {
      if (character === '\r' && text[index + 1] === '\n') index += 1
      row.push(cell)
      if (row.some((value) => value.trim() !== '')) rows.push(row)
      row = []
      cell = ''
    } else {
      cell += character
    }
  }
  if (quoted) throw new Error('The CSV contains an unclosed quoted value.')
  row.push(cell)
  if (row.some((value) => value.trim() !== '')) rows.push(row)
  if (rows.length < 2) throw new Error('Add a header and at least one data row to the CSV.')
  if (rows.length > 1001) throw new Error('CSV files can contain up to 1,000 data rows per import.')
  const headers = rows[0].map((header) => header.trim().replace(/^\uFEFF/, ''))
  if (headers.some((header) => !header) || new Set(headers).size !== headers.length) {
    throw new Error('CSV headers must be present and unique.')
  }
  return rows.slice(1).map((values, index) => ({
    line: index + 2,
    values: Object.fromEntries(headers.map((header, column) => [header, values[column] ?? ''])),
    hasExtraValues: values.length > headers.length,
  }))
}

function downloadFile(filename, content, type) {
  const url = URL.createObjectURL(new Blob([content], { type }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 0)
}

function summarizeImport(title, imported, failures) {
  if (failures.length) {
    const report = [['row', 'error'], ...failures.map((failure) => {
      const separator = failure.indexOf(': ')
      return separator === -1 ? ['', failure] : [failure.slice(0, separator).replace(/^Row /, ''), failure.slice(separator + 2)]
    })].map((line) => line.map(csvCell).join(',')).join('\r\n')
    downloadFile(`${title.toLowerCase().replaceAll(' ', '-')}-import-errors.csv`, report, 'text/csv;charset=utf-8')
  }
  return `${imported} imported; ${failures.length} skipped or rejected.${failures.length ? ' A CSV error report was downloaded.' : ''}`
}

function printableText(value) {
  return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;')
}

function xmlText(value) {
  return printableText(/^[=+\-@\t\r]/.test(String(value ?? '')) ? `'${value}` : value).replaceAll("'", '&apos;')
}

export default function DataTools({ title, rows, fields, exportFields = fields, canImport = false, onImport, onImportBatch, loadExportRows, onImportComplete }) {
  const fileInput = useRef(null)
  const [preparedRows, setPreparedRows] = useState([])
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  const importKeys = fields.map((field) => field.key)
  const exportKeys = exportFields.map((field) => field.key)
  const headers = fields.map((field) => field.label)
  const exportHeaders = exportFields.map((field) => field.label)

  async function exportData(format) {
    setBusy(true)
    setMessage('')
    try {
    const exportRows = loadExportRows ? await loadExportRows() : rows
    const stamp = new Date().toISOString().slice(0, 10)
    const values = exportRows.map((row) => exportKeys.map((key) => row[key] ?? ''))
    if (format === 'csv') {
      downloadFile(`${title.toLowerCase().replaceAll(' ', '-')}-${stamp}.csv`, [exportHeaders, ...values].map((line) => line.map(csvCell).join(',')).join('\r\n'), 'text/csv;charset=utf-8')
    } else if (format === 'json') {
      downloadFile(`${title.toLowerCase().replaceAll(' ', '-')}-${stamp}.json`, JSON.stringify(exportRows.map((row) => Object.fromEntries(exportKeys.map((key) => [key, row[key] ?? null]))), null, 2), 'application/json')
    } else if (format === 'excel') {
      const sheetRows = [exportHeaders, ...values].map((line) => `<Row>${line.map((value) => `<Cell><Data ss:Type="String">${xmlText(value)}</Data></Cell>`).join('')}</Row>`).join('')
      const workbook = `<?xml version="1.0"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="${xmlText(title.slice(0, 31))}"><Table>${sheetRows}</Table></Worksheet></Workbook>`
      downloadFile(`${title.toLowerCase().replaceAll(' ', '-')}-${stamp}.xls`, workbook, 'application/vnd.ms-excel;charset=utf-8')
    } else {
      const table = [exportHeaders, ...values].map((line, index) => `<tr>${line.map((value) => `${index === 0 ? '<th>' : '<td>'}${printableText(value)}${index === 0 ? '</th>' : '</td>'}`).join('')}</tr>`).join('')
      const printWindow = window.open('', '_blank')
      if (!printWindow) {
        setMessage('Allow pop-ups to print or save this report as PDF.')
        return
      }
      printWindow.opener = null
      printWindow.document.write(`<html><head><title>${printableText(title)}</title><style>body{font:12px Arial;padding:24px;color:#26324d}h1{font-size:20px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #dfe3eb;text-align:left}tr:nth-child(even){background:#f3f5fa}</style></head><body><h1>${printableText(title)}</h1><table>${table}</table><script>window.onload=()=>window.print()</script></body></html>`)
      printWindow.document.close()
    }
    setMessage(`${exportRows.length} ${title.toLowerCase()} records exported.`)
    } catch (error) {
      setMessage(error.message || `Could not export ${title.toLowerCase()} records.`)
    } finally {
      setBusy(false)
    }
  }

  async function selectFile(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    setMessage('')
    if (!file) return
    if (!file.name.toLowerCase().endsWith('.csv') || file.size > 5 * 1024 * 1024) {
      setMessage('Choose a CSV file smaller than 5 MB.')
      return
    }
    try {
      const parsed = parseCsv(await file.text())
      const expected = new Set([...importKeys, ...headers, ...exportHeaders].map((header) => header.toLowerCase()))
      const actualHeaders = Object.keys(parsed[0].values)
      const unknownHeaders = actualHeaders.filter((header) => !expected.has(header.toLowerCase()))
      if (unknownHeaders.length) throw new Error(`Unknown column${unknownHeaders.length > 1 ? 's' : ''}: ${unknownHeaders.join(', ')}.`)
      const normalized = parsed.map(({ line, values, hasExtraValues }) => {
        const record = Object.fromEntries(fields.map((field) => {
          const matchedHeader = Object.keys(values).find((header) => header.toLowerCase() === field.key.toLowerCase() || header.toLowerCase() === field.label.toLowerCase())
          return [field.key, matchedHeader ? values[matchedHeader].trim() : '']
        }))
        const missing = fields.filter((field) => field.required && !record[field.key]).map((field) => field.label)
        return { line, record, error: hasExtraValues ? 'Row has more values than headers.' : missing.length ? `Missing ${missing.join(', ')}.` : '' }
      })
      setPreparedRows(normalized)
    } catch (error) {
      setPreparedRows([])
      setMessage(error.message || 'Could not read this CSV file.')
    }
  }

  async function importRows() {
    setBusy(true)
    setMessage('')
    let imported = 0
    const failures = []
    const validRows = preparedRows.filter((entry) => !entry.error)
    if (onImportBatch && validRows.length === preparedRows.length) {
      try {
        const result = await onImportBatch(validRows.map((entry) => entry.record))
        imported = result?.count ?? validRows.length
      } catch (error) {
        failures.push(error.message || 'Import failed.')
      }
      setPreparedRows([])
      setBusy(false)
      onImportComplete?.()
      setMessage(summarizeImport(title, imported, failures))
      return
    }
    for (const entry of preparedRows) {
      if (entry.error) {
        failures.push(`Row ${entry.line}: ${entry.error}`)
        continue
      }
      try {
        await onImport(entry.record)
        imported += 1
      } catch (error) {
        failures.push(`Row ${entry.line}: ${error.message || 'Import failed.'}`)
      }
    }
    setPreparedRows([])
    setBusy(false)
    onImportComplete?.()
    setMessage(summarizeImport(title, imported, failures))
  }

  return (
    <section className="data-tools" aria-label={`${title} import and export`}>
      <div className="data-tools-actions">
        {canImport ? <>
          <button className="quiet-button" type="button" onClick={() => downloadFile(`${title.toLowerCase().replaceAll(' ', '-')}-template.csv`, [headers].map((line) => line.map(csvCell).join(',')).join('\r\n'), 'text/csv;charset=utf-8')}>CSV template</button>
          <button className="secondary-button" type="button" onClick={() => fileInput.current?.click()}>Import CSV</button>
          <input ref={fileInput} className="visually-hidden" type="file" accept=".csv,text/csv" onChange={selectFile} />
        </> : null}
        <label className="export-control">Export
          <select aria-label={`Export ${title} as`} value="" disabled={busy} onChange={(event) => { const format = event.target.value; event.target.value = ''; if (format) void exportData(format) }}>
            <option value="">Choose format</option><option value="csv">CSV</option><option value="excel">Excel (.xls)</option><option value="json">JSON</option><option value="pdf">Print / PDF</option>
          </select>
        </label>
      </div>
      {preparedRows.length ? <div className="import-preview">
        <div><strong>CSV preview</strong><span>{preparedRows.length} rows ready. Existing validation will run when each row is saved.</span></div>
        <ul>{preparedRows.slice(0, 4).map((entry) => <li key={entry.line} className={entry.error ? 'invalid' : ''}>Row {entry.line}: {entry.error || Object.values(entry.record).filter(Boolean).join(' · ')}</li>)}</ul>
        <div className="import-preview-actions"><button className="primary-button small-button" type="button" disabled={busy || preparedRows.every((entry) => entry.error) || (onImportBatch && preparedRows.some((entry) => entry.error))} onClick={importRows}>{busy ? 'Importing…' : 'Import valid rows'}</button><button className="quiet-button" type="button" onClick={() => setPreparedRows([])}>Cancel</button></div>
      </div> : null}
      {message ? <p className="data-tools-message" role="status">{message}</p> : null}
    </section>
  )
}
