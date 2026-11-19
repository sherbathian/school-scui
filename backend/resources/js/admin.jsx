import '../css/admin-dashboard.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import BranchModules from './pages/Admin/BranchModules.jsx';
import Claims from './pages/Admin/Claims.jsx';
import Locations from './pages/Admin/Locations.jsx';
import Listings from './pages/Admin/Listings.jsx';

createInertiaApp({
    resolve: name => {
        const pages = { 'Admin/Listings': Listings, 'Admin/Locations': Locations, 'Admin/Claims': Claims, 'Admin/BranchModules': BranchModules };
        return pages[name];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
