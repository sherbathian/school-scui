import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { NavigationGroup, NavigationIcon, NavigationLink } from './admin-navigation.jsx';
import { Button } from './ui/button.jsx';

const directoryLinks = [
    ['boards', 'Boards and curricula'], ['education-levels', 'Education levels'], ['facilities', 'Facilities'],
    ['school-groups', 'School groups'], ['listing-assignments', 'Listing assignments'], ['listing-fees', 'Listing fees'],
    ['school-media', 'School media'], ['course-categories', 'Course categories'], ['courses', 'Courses'], ['programs', 'Programs'],
];

const communityLinks = [
    ['books', 'Books'], ['book-inquiries', 'Book inquiries'], ['study-resources', 'Study resources'],
    ['events', 'Events'], ['listing-leads', 'Listing leads'], ['reviews', 'Reviews'], ['saved-schools', 'Saved schools'],
    ['school-stats', 'Daily school statistics'], ['articles', 'Articles'],
];

const serviceLinks = [
    ['subscription-invoices', 'Subscription invoices'], ['subscription-invoice-lines', 'Invoice lines'],
    ['subscription-payments', 'Subscription payments'], ['school-pages', 'School pages'],
    ['school-site-settings', 'School site settings'], ['site-themes', 'Site themes'],
    ['device-tokens', 'Device registrations'], ['personal-access-tokens', 'Personal access tokens'],
];

const platformLinks = [
    ['organisation-members', 'Organisation members'], ['modules', 'Modules'], ['module-dependencies', 'Module dependencies'],
    ['plans', 'Plans'], ['plan-modules', 'Plan modules'], ['plan-limits', 'Plan limits'], ['subscriptions', 'School subscriptions'],
    ['subscription-modules', 'Subscription modules'], ['subscription-limits', 'Subscription limits'],
    ['demo-requests', 'Demo requests'], ['otp-codes', 'OTP activity'], ['notifications', 'Notifications'],
];

export function AdminLayout({ screen, title, adminName, signOut, busy, branchNavigation, platform = true, role, operations = false, schoolAccess = [], children }) {
    const [menuOpen, setMenuOpen] = useState(null);
    const name = adminName?.trim() || 'Administrator';
    const initials = name.split(/\s+/).slice(0, 2).map(word => Array.from(word)[0]).join('').toUpperCase();

    const servicesActive = serviceLinks.some(([key]) => key === screen);
    const communityActive = communityLinks.some(([key]) => key === screen);
    const platformActive = platformLinks.some(([key]) => key === screen);
    const directoryActive = directoryLinks.some(([key]) => key === screen);

    return <div className="admin-shell" data-menu={menuOpen === null ? 'auto' : menuOpen ? 'open' : 'closed'}>
        <aside className="sidebar" id="admin-navigation">
            <Link className="brand" href="/admin/dashboard">
                <span className="brand-mark"><NavigationIcon name="school" /></span>
                Schoolhouse
            </Link>
            <nav className="sidebar-nav" aria-label="Schoolhouse navigation">
                <NavigationLink icon="modules" href="/admin/dashboard" active={screen === 'dashboard'}>Dashboard</NavigationLink>
                {!platform && <>
                    {operations && <NavigationLink icon="school" href="/admin/operations" active={screen === 'operations' && !branchNavigation}>Branch Operations</NavigationLink>}
                    {branchNavigation}
                    {schoolAccess.includes('members') && <NavigationLink icon="membership" href="/admin/members" active={screen === 'members'}>Branch memberships</NavigationLink>}
                    {schoolAccess.includes('roles') && <NavigationLink icon="shield" href="/admin/roles" active={screen === 'roles'}>Branch roles</NavigationLink>}
                </>}
                {platform && <>
                <p className="nav-heading">Directory</p>
                <NavigationLink icon="listings" href="/admin/listings" active={screen === 'index'}>All listings</NavigationLink>
                <NavigationLink icon="create" href="/admin/listings/create" active={screen === 'create'}>Create listing</NavigationLink>
                <NavigationLink icon="claims" href="/admin/claims" active={screen === 'claims'}>Listing claims</NavigationLink>
                <NavigationGroup icon="directory" label="Directory management" active={directoryActive}>
                    {directoryLinks.map(([key, label]) => <NavigationLink key={key} href={`/admin/directory/${key}`}
                        active={screen === key}>{label}</NavigationLink>)}
                </NavigationGroup>
                <p className="nav-heading">School management</p>
                <NavigationLink icon="school" href="/admin/operations" active={screen === 'operations' && !branchNavigation}>
                    Branch Operations
                </NavigationLink>
                {branchNavigation}
                <p className="nav-heading">School access</p>
                <NavigationLink icon="users" href="/admin/users" active={screen === 'users'}>User accounts</NavigationLink>
                <NavigationLink icon="membership" href="/admin/members" active={screen === 'members'}>Branch memberships</NavigationLink>
                <NavigationLink icon="shield" href="/admin/roles" active={screen === 'roles'}>Branch roles</NavigationLink>
                <NavigationLink icon="key" href="/admin/permissions" active={screen === 'permissions'}>Permissions catalogue</NavigationLink>
                <NavigationGroup icon="directory" label="Community and records" active={communityActive}>
                    {communityLinks.map(([key, label]) => <NavigationLink key={key} href={`/admin/community/${key}`}
                        active={screen === key}>{label}</NavigationLink>)}
                </NavigationGroup>
                <NavigationGroup icon="modules" label="Platform management" active={platformActive}>
                    {platformLinks.map(([key, label]) => <NavigationLink key={key} href={`/admin/platform/${key}`}
                        active={screen === key}>{label}</NavigationLink>)}
                </NavigationGroup>
                <NavigationGroup icon="fees" label="Billing, websites and devices" active={servicesActive}>
                    {serviceLinks.map(([key, label]) => <NavigationLink key={key} href={`/admin/services/${key}`}
                        active={screen === key}>{label}</NavigationLink>)}
                </NavigationGroup>
                <p className="nav-heading">Locations</p>
                <NavigationLink icon="city" href="/admin/cities" active={screen === 'cities'}>Cities</NavigationLink>
                <NavigationLink icon="pin" href="/admin/areas" active={screen === 'areas'}>Areas</NavigationLink>
                </>}
            </nav>
        </aside>
        <div className="admin-surface">
            <header className="topbar">
                <Button variant="outline" aria-label="Toggle sidebar" aria-controls="admin-navigation" aria-expanded={menuOpen ?? undefined} onClick={() => {
                    setMenuOpen(current => !(current ?? window.matchMedia('(min-width: 761px)').matches));
                }}><NavigationIcon name="panel" /></Button>
                <nav aria-label="Breadcrumb">
                    <span>{platform ? 'Platform admin' : role ?? 'School management'}</span><span aria-hidden="true">›</span>
                    {servicesActive || communityActive || platformActive || directoryActive || ['dashboard', 'index', 'cities', 'areas', 'claims', 'users', 'members', 'roles', 'permissions', 'operations'].includes(screen) ? <span aria-current="page">{title}</span> : <>
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
