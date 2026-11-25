import '../css/admin-dashboard.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Access from './pages/Admin/Access.jsx';
import BranchModules from './pages/Admin/BranchModules.jsx';
import Claims from './pages/Admin/Claims.jsx';
import Dashboard from './pages/Admin/Dashboard.jsx';
import Locations from './pages/Admin/Locations.jsx';
import Listings from './pages/Admin/Listings.jsx';

createInertiaApp({
    resolve: name => {
        if (name === 'Admin/Directory') return import('./pages/Admin/Directory.jsx');
        if (name === 'Admin/BranchWorkspace') return import('./pages/Admin/BranchWorkspace.jsx');
        if (name === 'Admin/Reports') return import('./pages/Admin/Reports.jsx');
        if (name === 'Admin/AuditLog') return import('./pages/Admin/AuditLog.jsx');

        const pages = {
            'Admin/Access': Access,
            'Admin/BranchModules': BranchModules,
            'Admin/Claims': Claims,
            'Admin/Dashboard': Dashboard,
            'Admin/Listings': Listings,
            'Admin/Locations': Locations,
        };
        return pages[name];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
