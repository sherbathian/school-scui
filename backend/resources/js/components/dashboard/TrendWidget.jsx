import { CartesianGrid, Line, LineChart, ResponsiveContainer, XAxis, YAxis } from 'recharts';

export default function TrendWidget({ points, summary }) {
    return <>
        <p>{summary}</p>
        {points.some(point => point.y != null) && <>
            <ul className="sr-only">
                {points.map(({ x, y }) => <li key={x}>{x}: {y == null ? 'No attendance recorded' : `${y}%`}</li>)}
            </ul>
            <div className="dashboard-widget-chart" aria-hidden="true">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={points}>
                        <CartesianGrid vertical={false} />
                        <XAxis dataKey="x" tickFormatter={date => date.slice(5)} />
                        <YAxis domain={[0, 100]} unit="%" />
                        <Line dataKey="y" stroke="#235789" isAnimationActive={false} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </>}
    </>;
}
