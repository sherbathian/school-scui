import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from './ui/button.jsx';

export function AdminLayout({ screen, title, adminName, signOut, busy, children }) {
    const [menuOpen, setMenuOpen] = useState(null);

    return <div className="admin-shell" data-menu={menuOpen === null ? 'auto' : menuOpen ? 'open' : 'closed'}>
        <aside className="sidebar" id="admin-navigation">
            <Link className="brand" href="/admin/listings"><span aria-hidden="true">S</span> Schoolhouse</Link>
            <nav aria-label="Platform admin">
                <p>Platform admin</p>
                <span className="nav-group">Directory</span>
                <div className="sidebar-links">
                    <Link href="/admin/listings" aria-current={screen === 'index' ? 'page' : undefined}>All listings</Link>
                    <Link href="/admin/listings/create" aria-current={screen === 'create' ? 'page' : undefined}>Create listing</Link>
                </div>
                <span className="nav-group">Locations</span>
                <div className="sidebar-links">
                    <Link href="/admin/cities" aria-current={screen === 'cities' ? 'page' : undefined}>Cities</Link>
                    <Link href="/admin/areas" aria-current={screen === 'areas' ? 'page' : undefined}>Areas</Link>
                </div>
            </nav>
            <div className="sidebar-account">
                <span className="account-mark" aria-hidden="true">{adminName?.trim().slice(0, 1).toUpperCase() || 'A'}</span>
                <div><strong>{adminName || 'Administrator'}</strong><Button variant="outline" disabled={busy} onClick={signOut}>Sign out</Button></div>
            </div>
        </aside>
        <div className="admin-surface">
            <header className="topbar">
                <Button variant="outline" aria-label="Toggle sidebar" aria-controls="admin-navigation" aria-expanded={menuOpen ?? undefined} onClick={() => {
                    setMenuOpen(current => !(current ?? window.matchMedia('(min-width: 761px)').matches));
                }}>☰</Button>
                <nav aria-label="Breadcrumb">
                    <span>Platform admin</span><span aria-hidden="true">›</span>
                    {['index', 'cities', 'areas'].includes(screen) ? <span aria-current="page">{title}</span> : <>
                        <Link href="/admin/listings">Listings</Link><span aria-hidden="true">›</span><span aria-current="page">{title}</span>
                    </>}
                </nav>
                <Button className="topbar-sign-out" variant="outline" disabled={busy} onClick={signOut}>Sign out</Button>
            </header>
            <main className="admin-main">{children}</main>
        </div>
    </div>;
}
