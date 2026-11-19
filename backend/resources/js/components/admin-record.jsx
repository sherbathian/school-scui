import { NavigationIcon } from './admin-navigation.jsx';
import { Button } from './ui/button.jsx';

export function ListingSection({ icon, title, children, readOnly = false }) {
    return <section className="listing-page-card">
        <h2><NavigationIcon name={icon} /> {title}</h2>
        {readOnly ? <dl className="listing-detail-grid">{children}</dl> : <div className="fields">{children}</div>}
    </section>;
}

export function Detail({ label, value, wide = false }) {
    return <div className={wide ? 'detail-wide' : undefined}><dt>{label}</dt><dd>{value ?? '—'}</dd></div>;
}

export function RecordHeading({ icon, title, name, back, children }) {
    return <div className="page-heading listing-page-heading">
        <div><h1><NavigationIcon name={icon} /> {title}</h1>{name && <p>{name}</p>}</div>
        <div className="listing-page-actions">
            <Button type="button" variant="outline" onClick={back}>Back to list</Button>
            {children}
        </div>
    </div>;
}
