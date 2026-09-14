import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Users, TicketCheck, Wallet, Globe, Activity } from 'lucide-react';

interface EventRow {
    id: number;
    title: string;
    city: string | null;
    bookings: number;
    revenue?: number;
}

interface GroupRow {
    name: string;
    count: number;
    percent?: number;
}

interface CategoryRow {
    slug: string;
    name: string;
    events: number;
    bookings: number;
    revenue: number;
    platform: number;
    share: number;
}

interface RealtimeRecent {
    event: string | null;
    country: string | null;
    city: string | null;
    device: string;
    os: string;
    ip: string | null;
    created_at: string | null;
}

interface Realtime {
    window_minutes: number;
    active: number;
    unique: number;
    countries: GroupRow[];
    cities: GroupRow[];
    devices: GroupRow[];
    os: GroupRow[];
    recent: RealtimeRecent[];
}

interface Daily {
    labels: string[];
    visits: number[];
    unique: number[];
    devices: GroupRow[];
    os: GroupRow[];
}

interface Analytics {
    period: string;
    year: number;
    available_years: number[];
    labels: string[];
    events: number[];
    organizer_revenue: number[];
    platform_revenue: number[];
    totals: { gross_revenue: number; platform_revenue: number; organizer_revenue: number; events: number; bookings: number };
    most_popular: EventRow[];
    worst_performing: EventRow[];
    cancelled_events: EventRow[];
    categories: CategoryRow[];
    countries: GroupRow[];
    cities: GroupRow[];
    devices: GroupRow[];
    visits_total: number;
    realtime: Realtime;
    daily: Daily;
}

const GA_BLUE = '#1a73e8';

function money(value: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    return `${Math.floor(seconds / 3600)}j`;
}

function Sparkline({ values, color = GA_BLUE }: { values: number[]; color?: string }) {
    const max = Math.max(...values, 1);
    const points = values
        .map((value, i) => `${(i / Math.max(1, values.length - 1)) * 100},${28 - (value / max) * 26 - 1}`)
        .join(' ');

    return (
        <svg viewBox="0 0 100 28" preserveAspectRatio="none" className="h-7 w-20">
            <polyline points={points} fill="none" stroke={color} strokeWidth={2} vectorEffect="non-scaling-stroke" />
        </svg>
    );
}

function Scorecard({ label, value, series, accent }: { label: string; value: string | number; series: number[]; accent?: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <p className="text-xs font-medium text-slate-500">{label}</p>
            <div className="mt-1 flex items-end justify-between gap-2">
                <p className="text-2xl font-semibold tabular-nums tracking-tight text-slate-900">{value}</p>
                <Sparkline values={series} color={accent ?? GA_BLUE} />
            </div>
        </div>
    );
}

function AreaChart({ labels, values, color = GA_BLUE, moneyFormat = false }: { labels: string[]; values: number[]; color?: string; moneyFormat?: boolean }) {
    const width = 700;
    const height = 220;
    const padX = 8;
    const padY = 16;
    const max = Math.max(...values, 1);
    const step = (width - padX * 2) / Math.max(1, values.length - 1);

    const coords = values.map((value, i) => {
        const x = padX + i * step;
        const y = height - padY - (value / max) * (height - padY * 2);
        return [x, y] as const;
    });

    const line = coords.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
    const area = `${line} L${coords[coords.length - 1]?.[0].toFixed(1) ?? width},${height - padY} L${coords[0]?.[0].toFixed(1) ?? 0},${height - padY} Z`;
    const gridLines = [0, 0.25, 0.5, 0.75, 1].map((ratio) => height - padY - ratio * (height - padY * 2));

    return (
        <div>
            <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-56 w-full">
                {gridLines.map((y, i) => (
                    <line key={i} x1={0} x2={width} y1={y} y2={y} stroke="#e8eaed" strokeWidth={1} vectorEffect="non-scaling-stroke" />
                ))}
                <path d={area} fill={color} opacity={0.1} />
                <path d={line} fill="none" stroke={color} strokeWidth={2} vectorEffect="non-scaling-stroke" />
                {coords.map(([x, y], i) => (
                    <circle key={i} cx={x} cy={y} r={2.5} fill={color} vectorEffect="non-scaling-stroke">
                        <title>{`${labels[i]}: ${moneyFormat ? money(values[i]) : values[i]}`}</title>
                    </circle>
                ))}
            </svg>
            <div className="mt-2 flex justify-between text-[10px] text-slate-400">
                {labels.map((label, i) => (
                    <span key={label + i} className="flex-1 truncate text-center">
                        {i % 2 === 0 ? label : ''}
                    </span>
                ))}
            </div>
        </div>
    );
}

function BarRow({ name, sub, value, max, color = GA_BLUE }: { name: string; sub?: string; value: string | number; max: number; color?: string }) {
    const raw = typeof value === 'string' ? parseFloat(value.replace(/[^0-9.-]/g, '')) : value;
    return (
        <div className="py-2.5">
            <div className="flex items-baseline justify-between text-sm">
                <span className="min-w-0 truncate text-slate-800">
                    {name}
                    {sub && <span className="ml-2 text-[11px] text-slate-400">{sub}</span>}
                </span>
                <span className="ml-3 shrink-0 tabular-nums text-slate-600">{value}</span>
            </div>
            <div className="mt-1.5 h-1.5 w-full rounded-full bg-slate-100">
                <div className="h-full rounded-full" style={{ width: `${Math.min(100, (raw / Math.max(1, max)) * 100)}%`, backgroundColor: color }} />
            </div>
        </div>
    );
}

export default function AdminAnalytics(analytics: Analytics) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].analytics;

    const [metric, setMetric] = useState<'events' | 'organizer' | 'platform'>('events');

    // Auto-refresh the realtime + daily blocks without a full page reload.
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['realtime', 'daily'] });
        }, 30000);

        return () => clearInterval(interval);
    }, []);

    const periods = [
        { key: 'week', label: t.period_week },
        { key: 'month', label: t.period_month },
        { key: 'year', label: t.period_year },
    ];

    const chartConfig = {
        events: { values: analytics.events, color: GA_BLUE, money: false },
        organizer: { values: analytics.organizer_revenue, color: GA_BLUE, money: true },
        platform: { values: analytics.platform_revenue, color: '#34a853', money: true },
    }[metric];

    const categoryMax = Math.max(...analytics.categories.map((c) => c.revenue), 1);
    const countryMax = Math.max(...analytics.countries.map((c) => c.count), 1);
    const cityMax = Math.max(...analytics.cities.map((c) => c.count), 1);

    return (
        <AdminLayout>
            <Head title={t.title} />

            <div className="mx-auto max-w-[1200px] space-y-4">
                {/* Top bar: property + date range */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                    <div className="flex items-center gap-2">
                        <span className="flex h-6 w-6 items-center justify-center rounded bg-[#1a73e8] text-[11px] font-bold text-white">A</span>
                        <span className="text-sm font-medium text-slate-800">{t.title}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <select
                            value={analytics.year}
                            onChange={(e) => router.get(route('admin.analytics', { period: analytics.period, year: e.target.value }))}
                            className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 outline-none focus:border-[#1a73e8]"
                        >
                            {analytics.available_years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </select>
                        <div className="inline-flex overflow-hidden rounded-md border border-slate-200 bg-white">
                            {periods.map((period) => (
                                <Link
                                    key={period.key}
                                    href={route('admin.analytics', { period: period.key, year: analytics.year })}
                                    className={`px-3 py-1.5 text-xs font-medium transition-colors ${
                                        analytics.period === period.key ? 'bg-[#1a73e8] text-white' : 'text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    {period.label}
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Realtime */}
                <div className="rounded-lg border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <span className="relative flex h-2.5 w-2.5">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500" />
                            </span>
                            <h2 className="text-sm font-medium text-slate-800">{t.realtime_title}</h2>
                            <span className="text-xs text-slate-400">{t.realtime_active.replace('{minutes}', String(analytics.realtime.window_minutes))}</span>
                        </div>
                        <div className="flex items-center gap-4 text-xs">
                            <span className="text-slate-500">
                                <span className="text-base font-semibold tabular-nums text-slate-900">{analytics.realtime.active}</span> {t.visits}
                            </span>
                            <span className="text-slate-500">
                                <span className="text-base font-semibold tabular-nums text-slate-900">{analytics.realtime.unique}</span> {t.realtime_unique}
                            </span>
                        </div>
                    </div>

                    {analytics.realtime.active === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-400">
                            {t.realtime_empty.replace('{minutes}', String(analytics.realtime.window_minutes))}
                        </p>
                    ) : (
                        <div className="grid grid-cols-1 divide-y divide-slate-100 lg:grid-cols-4 lg:divide-x lg:divide-y-0">
                            {[
                                { title: t.countries, rows: analytics.realtime.countries },
                                { title: t.cities, rows: analytics.realtime.cities },
                                { title: t.devices, rows: analytics.realtime.devices },
                                { title: t.os, rows: analytics.realtime.os },
                            ].map((block) => (
                                <div key={block.title} className="p-4">
                                    <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{block.title}</h3>
                                    {block.rows.length === 0 ? (
                                        <p className="text-xs text-slate-300">—</p>
                                    ) : (
                                        <ul className="space-y-1.5">
                                            {block.rows.map((row) => (
                                                <li key={row.name} className="flex items-center justify-between text-xs">
                                                    <span className="min-w-0 truncate capitalize text-slate-600">{row.name}</span>
                                                    <span className="ml-2 shrink-0 tabular-nums font-medium text-slate-900">{row.count}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {analytics.realtime.recent.length > 0 && (
                        <div className="border-t border-slate-100 px-4 py-3">
                            <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{t.realtime_recent}</h3>
                            <ul className="divide-y divide-slate-50">
                                {analytics.realtime.recent.map((row, index) => (
                                    <li key={index} className="flex items-center gap-3 py-2 text-xs">
                                        <span className="w-8 shrink-0 tabular-nums text-slate-400">{timeAgo(row.created_at)}</span>
                                        <span className="min-w-0 flex-1 truncate text-slate-700">{row.event ?? '—'}</span>
                                        <span className="shrink-0 text-slate-500">{[row.city, row.country].filter(Boolean).join(', ') || '—'}</span>
                                        <span className="shrink-0 capitalize text-slate-500">{row.device} · {row.os}</span>
                                        <span className="shrink-0 font-mono text-slate-400">{row.ip ?? '—'}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>

                {/* Scorecards */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Scorecard label={t.gross_revenue} value={money(analytics.totals.gross_revenue)} series={analytics.organizer_revenue.map((v, i) => v + analytics.platform_revenue[i])} />
                    <Scorecard label={t.organizer_revenue} value={money(analytics.totals.organizer_revenue)} series={analytics.organizer_revenue} />
                    <Scorecard label={t.platform_revenue} value={money(analytics.totals.platform_revenue)} series={analytics.platform_revenue} accent="#34a853" />
                    <Scorecard label={t.total_events} value={analytics.totals.events} series={analytics.events} />
                </div>

                {/* Primary chart card with metric tabs */}
                <div className="rounded-lg border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4">
                        <div className="flex">
                            {(
                                [
                                    { key: 'events', label: t.events_chart },
                                    { key: 'organizer', label: t.organizer_revenue },
                                    { key: 'platform', label: t.platform_revenue },
                                ] as const
                            ).map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setMetric(tab.key)}
                                    className={`border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                                        metric === tab.key ? 'border-[#1a73e8] text-[#1a73e8]' : 'border-transparent text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                        <span className="flex items-center gap-2 text-xs text-slate-400">
                            <Activity size={14} />
                            {analytics.totals.bookings} {t.total_bookings} · {analytics.visits_total} {t.visits}
                        </span>
                    </div>
                    <div className="p-4">
                        <AreaChart labels={analytics.labels} values={chartConfig.values} color={chartConfig.color} moneyFormat={chartConfig.money} />
                    </div>
                </div>

                {/* Daily visits */}
                <div className="rounded-lg border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-medium text-slate-800">{t.daily_title}</h2>
                        <span className="flex items-center gap-3 text-[11px] text-slate-400">
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-[#1a73e8]" /> {t.daily_visits}</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-[#34a853]" /> {t.daily_unique}</span>
                        </span>
                    </div>
                    <div className="p-4">
                        <AreaChart labels={analytics.daily.labels} values={analytics.daily.visits} color="#1a73e8" />
                    </div>
                </div>

                {/* Category + audience */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <h2 className="text-sm font-medium text-slate-800">{t.categories_title}</h2>
                            <span className="text-xs tabular-nums text-slate-400">{analytics.categories.length}</span>
                        </div>
                        {analytics.categories.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">—</p>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {analytics.categories.map((row) => (
                                    <BarRow
                                        key={row.slug}
                                        name={row.name}
                                        sub={`${row.events} ${t.col_events} · ${row.bookings} ${t.col_bookings}`}
                                        value={money(row.revenue)}
                                        max={categoryMax}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-medium text-slate-800">
                                <Globe size={15} className="text-slate-400" /> {t.countries}
                            </h2>
                            <span className="text-xs tabular-nums text-slate-400">{analytics.visits_total}</span>
                        </div>
                        {analytics.countries.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">—</p>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {analytics.countries.map((row) => (
                                    <BarRow key={row.name} name={row.name} value={row.count} max={countryMax} color="#f9ab00" />
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Leaderboards */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {[
                        { title: t.most_popular, rows: analytics.most_popular, revenue: true, icon: TicketCheck },
                        { title: t.worst_performing, rows: analytics.worst_performing, revenue: false, icon: Users },
                        { title: t.cancelled_events, rows: analytics.cancelled_events, revenue: false, icon: Wallet },
                    ].map((board) => (
                        <div key={board.title} className="rounded-lg border border-slate-200 bg-white p-4">
                            <h2 className="mb-2 text-sm font-medium text-slate-800">{board.title}</h2>
                            {board.rows.length === 0 ? (
                                <p className="py-8 text-center text-sm text-slate-400">—</p>
                            ) : (
                                <ol className="divide-y divide-slate-50">
                                    {board.rows.map((row, index) => (
                                        <li key={row.id} className="flex items-baseline gap-3 py-2.5">
                                            <span className="w-4 shrink-0 text-[11px] tabular-nums text-slate-300">{index + 1}</span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm text-slate-800">{row.title}</span>
                                                <span className="block truncate text-[11px] text-slate-400">{row.city ?? '—'}</span>
                                            </span>
                                            <span className="shrink-0 text-sm tabular-nums text-slate-600">
                                                {board.revenue ? money(row.revenue ?? 0) : row.bookings}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    ))}
                </div>

                {/* Cities + devices */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <h2 className="mb-2 text-sm font-medium text-slate-800">{t.cities}</h2>
                        {analytics.cities.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">—</p>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {analytics.cities.map((row) => (
                                    <BarRow key={row.name} name={row.name} value={row.count} max={cityMax} color="#9334e6" />
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <h2 className="mb-2 text-sm font-medium text-slate-800">{t.devices}</h2>
                        {analytics.devices.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">—</p>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {analytics.devices.map((row, index) => (
                                    <BarRow
                                        key={row.name}
                                        name={row.name.charAt(0).toUpperCase() + row.name.slice(1)}
                                        value={`${row.count} · ${row.percent}%`}
                                        max={Math.max(...analytics.devices.map((d) => d.count), 1)}
                                        color={index === 0 ? GA_BLUE : '#dadce0'}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
