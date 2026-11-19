const PAGE_SIZES = [10, 25, 50, 100]

export default function TablePagination({ meta, page, perPage, onPageChange, onPerPageChange, label = 'records' }) {
  const total = Number(meta?.total || 0)
  const lastPage = Math.max(1, Number(meta?.last_page || 1))
  if (!total) return null

  const first = Number(meta?.from || ((page - 1) * perPage + 1))
  const last = Number(meta?.to || Math.min(page * perPage, total))
  const windowStart = Math.max(1, Math.min(page - 2, lastPage - 4))
  const windowEnd = Math.min(lastPage, windowStart + 4)
  const pages = Array.from({ length: windowEnd - windowStart + 1 }, (_, index) => windowStart + index)

  return <footer className="student-pagination">
    <span className="student-pagination-count">Showing <strong>{first}–{last}</strong> of <strong>{total}</strong> {label}</span>
    <label className="student-pagination-size">Rows per page<select aria-label={`${label} per page`} value={perPage} onChange={(event) => onPerPageChange(Number(event.target.value))}>{PAGE_SIZES.map((size) => <option key={size} value={size}>{size}</option>)}</select></label>
    <nav aria-label={`${label} pagination`}>
      <button type="button" aria-label="First page" disabled={page <= 1} onClick={() => onPageChange(1)}>«</button>
      <button type="button" aria-label="Previous page" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>‹</button>
      {windowStart > 1 ? <><button type="button" onClick={() => onPageChange(1)}>1</button>{windowStart > 2 ? <span className="student-pagination-ellipsis">…</span> : null}</> : null}
      {pages.map((item) => <button type="button" key={item} className={page === item ? 'active' : ''} aria-label={`Page ${item}`} aria-current={page === item ? 'page' : undefined} onClick={() => onPageChange(item)}>{item}</button>)}
      {windowEnd < lastPage ? <>{windowEnd < lastPage - 1 ? <span className="student-pagination-ellipsis">…</span> : null}<button type="button" onClick={() => onPageChange(lastPage)}>{lastPage}</button></> : null}
      <button type="button" aria-label="Next page" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)}>›</button>
      <button type="button" aria-label="Last page" disabled={page >= lastPage} onClick={() => onPageChange(lastPage)}>»</button>
    </nav>
  </footer>
}
