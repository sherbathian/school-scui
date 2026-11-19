import { Link } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';

const paths = {
    school: 'M3 10 12 3l9 7v10H3zM9 20v-6h6v6M8 10h.01M16 10h.01',
    listings: 'M4 4h16v16H4zM4 9h16M9 9v11',
    create: 'M12 5v14M5 12h14',
    claims: 'M9 4H5v16h14V4h-4M9 2h6v5H9zM8 13l2 2 5-5',
    directory: 'M3 6h7l2 2h9v12H3z',
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-4M16 3a4 4 0 0 1 0 8M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
    membership: 'M4 5h16v14H4zM8 12h4m-2-2v4m5-3h2m-2 3h2',
    shield: 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Zm-3-10 2 2 4-4',
    key: 'M14 7a5 5 0 1 0-8 4l-3 3v5h5v-3h3l3-3a5 5 0 0 0 0-6ZM10 7h.01',
    city: 'M3 21h18M5 21V7h6v14m0-11h8v11M7 11h2m-2 4h2m5-1h2m-2 3h2',
    pin: 'M19 10c0 5-7 11-7 11s-7-6-7-11a7 7 0 1 1 14 0Zm-4 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    academics: 'M2 9l10-5 10 5-10 5zM6 11v5c3 3 9 3 12 0v-5m4-2v7',
    attendance: 'M4 6h16v14H4zM8 3v6m8-6v6M4 10h16m-11 5 2 2 4-4',
    guardians: 'M12 20s-8-4-8-10a4 4 0 0 1 8-2 4 4 0 0 1 8 2c0 6-8 10-8 10Z',
    staff: 'M3 7h18v13H3zM8 7V4h8v3M3 12h18m-11 0v2h4v-2',
    fees: 'M3 5h18v14H3zM3 10h18M7 15h4',
    expenses: 'M5 3l2 2 2-2 3 2 3-2 2 2 2-2v18l-2-2-2 2-3-2-3 2-2-2-2 2zM9 10h6m-6 4h6',
    chevron: 'm9 6 6 6-6 6',
    edit: 'm4 16 12-12 4 4L8 20H4zM14 6l4 4',
    search: 'M16 10a6 6 0 1 1-12 0 6 6 0 0 1 12 0Zm-2 4 6 6',
    check: 'm5 12 4 4L19 6',
    pause: 'M9 5v14m6-14v14',
    pending: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 7v5l3 2',
    more: 'M5 12h.01M12 12h.01M19 12h.01',
    modules: 'M4 4h6v6H4zm10 0h6v6h-6zM4 14h6v6H4zm10 0h6v6h-6z',
    archive: 'M3 4h18v5H3zm2 5v11h14V9m-10 4h6',
    publish: 'M12 19V5m-5 5 5-5 5 5M5 19h14',
    play: 'm9 5 11 7-11 7z',
    panel: 'M3 4h18v16H3zM9 4v16',
};

export function NavigationIcon({ name }) {
    return <svg className="nav-icon" viewBox="0 0 24 24" width="18" height="18" fill="none"
        stroke="currentColor" strokeWidth="1.65" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d={paths[name]} />
    </svg>;
}

export function NavigationLink({ icon, active, children, ...props }) {
    return <Link className="nav-item" aria-current={active ? 'page' : undefined} {...props}>
        {icon && <NavigationIcon name={icon} />}<span>{children}</span>
    </Link>;
}

export function NavigationGroup({ icon, label, active, children }) {
    const id = useId();
    const [preference, setPreference] = useState(null);
    const open = preference?.active === active ? preference.open : active;

    useEffect(() => setPreference(null), [active]);

    return <div className="nav-disclosure" data-active={active || undefined}>
        <button className="nav-item nav-trigger" type="button" aria-controls={id} aria-expanded={open}
            onClick={() => setPreference({ active, open: !open })}>
            <NavigationIcon name={icon} /><span>{label}</span>
            <NavigationIcon name="chevron" />
        </button>
        <div className="nav-children" id={id} hidden={!open}>{children}</div>
    </div>;
}
