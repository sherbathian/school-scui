import { useEffect, useState } from 'react';
import { Bar, BarChart, Cell, Pie, PieChart, ResponsiveContainer, XAxis, YAxis } from 'recharts';
import TrendWidget from './TrendWidget.jsx';
import { NavigationIcon } from '../admin-navigation.jsx';

const colors = ['#235789', '#9b4529', '#38745a', '#71558e', '#946500'];
const widgetIcons = {
    'branch.attendance_today': 'attendance',
    'branch.attendance_trend': 'attendance',
    'branch.unmarked_classes': 'pending',
    'branch.class_strength': 'academics',
    'branch.new_students_month': 'users',
    'branch.admissions_funnel': 'claims',
    'student.attendance_month': 'attendance',
    'student.attendance_trend': 'attendance',
    'student.recent_absences': 'pending',
    'platform.listings_by_status': 'listings',
    'platform.listings_by_city': 'city',
    'platform.claims_queue': 'claims',
    'platform.verification_queue': 'shield',
    'platform.new_demo_requests': 'membership',
    'platform.subscription_watch': 'pending',
    'platform.module_adoption': 'modules',
};

function WidgetCard({ widget }) {
    const [response, setResponse] = useState();
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        fetch(widget.endpoint, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(result => {
                if (!result.ok) throw new Error('Widget request failed');
                return result.json();
            })
            .then(setResponse)
            .catch(error => {
                if (error.name !== 'AbortError') setFailed(true);
            });
        return () => controller.abort();
    }, [widget.endpoint]);

    return <article className="dashboard-widget" data-test="widget-card" data-icon={widgetIcons[widget.key]}>
        <div data-test={`widget-${widget.key}`}>
            <h2><span className="metric-icon"><NavigationIcon name={widgetIcons[widget.key] ?? 'modules'} /></span>{widget.title}</h2>
            {failed ? <p role="alert">Could not load this widget.</p> : response ? <>
                <time dateTime={response.asOf}>As of {new Date(response.asOf).toLocaleString()}</time>
                <WidgetData type={response.type} data={response.data} summary={response.summary} />
            </> : <p className="dashboard-widget-loading" role="status">Loading…</p>}
        </div>
    </article>;
}

function WidgetData({ type, data, summary }) {
    if (type === 'trend') return <TrendWidget points={data.points} summary={summary} />;
    if (type === 'list') {
        return data.rows.length ? <ul>{data.rows.map((row, index) => <li key={index}>
            {row.href ? <a href={row.href}>{row.title}</a> : row.title} · {row.meta}
        </li>)}</ul> : <p>{data.empty}</p>;
    }
    if (type === 'kpi') return <>
        {data.value != null && <p><strong>{data.value}{data.unit}</strong></p>}
        <p>{summary}</p>
    </>;
    if (!data.items.some(item => item.value > 0)) return <p>No data to display.</p>;

    return <>
        <p>{summary}</p>
        <div className="dashboard-widget-chart" aria-hidden="true">
            <ResponsiveContainer width="100%" height="100%">
                {type === 'bar' ? <BarChart data={data.items}>
                    <XAxis dataKey="label" /><YAxis allowDecimals={false} />
                    <Bar dataKey="value" radius={[5, 5, 0, 0]} isAnimationActive={false}>
                        {data.items.map((item, index) => <Cell key={index} fill={colors[index % colors.length]} />)}
                    </Bar>
                </BarChart> : <PieChart>
                    <Pie data={data.items} dataKey="value" nameKey="label" innerRadius="45%" outerRadius="75%" isAnimationActive={false}>
                        {data.items.map((item, index) => <Cell key={item.label} fill={colors[index % colors.length]} />)}
                    </Pie>
                </PieChart>}
            </ResponsiveContainer>
        </div>
    </>;
}

export default function WidgetGrid({ widgets }) {
    return <section className="dashboard-widget-grid" aria-label="Dashboard widgets">
        {widgets.map(widget => <WidgetCard key={widget.endpoint} widget={widget} />)}
    </section>;
}
