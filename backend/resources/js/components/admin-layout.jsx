import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from './ui/button.jsx';

export function AdminLayout({ screen, title, adminName, signOut, busy, children }) {
    const [menuOpen, setMenuOpen] = useState(null);
    const name = adminName?.trim() || 'Administrator';
    const initials = name.split(/\s+/).slice(0, 2).map(word => Array.from(word)[0]).join('').toUpperCase();

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
                <Link href="/admin/claims" aria-current={screen === 'claims' ? 'page' : undefined}>Listing claims</Link>
                <span className="nav-group">School access</span>
                <div className="sidebar-links">
                    <Link href="/admin/users" aria-current={screen === 'users' ? 'page' : undefined}>User accounts</Link>
                    <Link href="/admin/members" aria-current={screen === 'members' ? 'page' : undefined}>Branch memberships</Link>
                    <Link href="/admin/roles" aria-current={screen === 'roles' ? 'page' : undefined}>Branch roles</Link>
                    <Link href="/admin/permissions" aria-current={screen === 'permissions' ? 'page' : undefined}>Permissions catalogue</Link>
                </div>
                <span className="nav-group">Locations</span>
                <div className="sidebar-links">
                    <Link href="/admin/cities" aria-current={screen === 'cities' ? 'page' : undefined}>Cities</Link>
                    <Link href="/admin/areas" aria-current={screen === 'areas' ? 'page' : undefined}>Areas</Link>
                </div>
            </nav>
        </aside>
        <div className="admin-surface">
            <header className="topbar">
                <Button variant="outline" aria-label="Toggle sidebar" aria-controls="admin-navigation" aria-expanded={menuOpen ?? undefined} onClick={() => {
                    setMenuOpen(current => !(current ?? window.matchMedia('(min-width: 761px)').matches));
                }}>☰</Button>
                <nav aria-label="Breadcrumb">
                    <span>Platform admin</span><span aria-hidden="true">›</span>
                    {['index', 'cities', 'areas', 'claims', 'users', 'members', 'roles', 'permissions'].includes(screen) ? <span aria-current="page">{title}</span> : <>
                        <Link href="/admin/listings">Listings</Link><span aria-hidden="true">›</span><span aria-current="page">{title}</span>
                    </>}
                </nav>
                <div className="admin-account" aria-label="Signed-in account">
                    <span className="account-mark" aria-hidden="true">{initials}</span>
                    <span className="account-name" title={name}>{name}</span>
                    <Button className="account-sign-out" variant="outline" disabled={busy} onClick={signOut}>Sign out</Button>
                </div>
            </header>
            <main className="admin-main">{children}</main>
        </div>
    </div>;
}
