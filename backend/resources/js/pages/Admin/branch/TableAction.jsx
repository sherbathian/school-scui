const icons = {
  view: <><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /></>,
  edit: <><path d="m4 16.5-.8 4.3 4.3-.8L20 7.5 16.5 4 4 16.5Z" /><path d="m14.8 5.7 3.5 3.5" /></>,
  delete: <><path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3" /></>,
  archive: <><path d="M3 5h18v4H3zM5 9v11h14V9M10 13h4" /></>,
  restore: <><path d="M4 11a8 8 0 1 1 2 6" /><path d="M4 5v6h6" /></>,
  assign: <><circle cx="9" cy="8" r="3" /><path d="M3 20v-1a6 6 0 0 1 12 0v1M18 8v6m-3-3h6" /></>,
  balances: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  current: <><path d="m5 12 4 4L19 6" /></>,
  more: <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>,
}

export default function TableAction({ action = 'more', label, onClick, disabled = false, danger = false, expanded }) {
  return <button className={`table-action${danger ? ' table-action-danger' : ''}`} type="button" aria-label={label} title={label} aria-expanded={expanded} onClick={onClick} disabled={disabled}>
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{icons[action] || icons.more}</svg>
    <span className="visually-hidden">{label}</span>
  </button>
}
