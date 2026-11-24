import { useEffect, useState } from 'react';
import { Bar, BarChart, Cell, Pie, PieChart, ResponsiveContainer, XAxis, YAxis } from 'recharts';
import TrendWidget from './TrendWidget.jsx';

const colors = ['#235789', '#9b4529', '#38745a', '#71558e', '#946500'];

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

    return <article className="dashboard-widget" data-test="widget-card">
        <div data-test={`widget-${widget.key}`}>
            <h2>{widget.title}</h2>
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
                    <XAxis dataKey="label" /><YAxis allowDecimals={false} /><Bar dataKey="value" fill={colors[0]} isAnimationActive={false} />
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
