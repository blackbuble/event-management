import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { eventTexts, EventLanguage, defaultEventLanguage } from '@/config/event-texts';
import { ArrowLeft, BarChart3, CheckCircle2, Clock, Ticket, TrendingUp, Users, Wallet } from 'lucide-react';

interface AnalyticsSummary {
    revenue: number;
    tickets_sold: number;
    tickets_reserved: number;
    tickets_available: number;
    total_quota: number;
    sell_through: number;
    total_bookings: number;
    confirmed_bookings: number;
    pending_bookings: number;
    cancelled_bookings: number;
    checked_in: number;
    capacity: number | null;
}

interface TicketPerformance {
    id: number;
    name: string;
    price: number;
    quantity: number;
    sold: number;
    reserved: number;
    remaining: number;
    is_active: boolean;
    sell_through: number;
    revenue: number;
    paid_quantity: number;
}

interface TimelinePoint {
    date: string;
    bookings: number;
    revenue: number;
}

interface Analytics {
    event: {
        id: number;
        title: string;
        slug: string;
        status: string;
        type: string;
        category: string | null;
        category_label: string | null;
        start_date: string | null;
        end_date: string | null;
    };
    summary: AnalyticsSummary;
    tickets: TicketPerformance[];
    timeline: TimelinePoint[];
}

interface Props {
    analytics: Analytics;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDate(iso: string | null, lang: EventLanguage): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(lang === 'id' ? 'id-ID' : 'en-US', {
        day: 'numeric',
        month: 'short',
    });
}

export default function EventAnalytics({ analytics }: Props) {
    const { locale } = usePage().props as any;
    const lang = (locale as EventLanguage) || defaultEventLanguage;
    const t = eventTexts[lang].analytics;

    const { summary, tickets, timeline, event } = analytics;
    const maxRevenue = Math.max(...timeline.map((point) => point.revenue), 1);

    const statusLabel =
        event.status === 'published' ? t.status_published : event.status === 'cancelled' ? t.status_cancelled : t.status_draft;

    const kpis = [
        { label: t.kpi_revenue, value: formatCurrency(summary.revenue), icon: Wallet, color: 'text-emerald-600 bg-emerald-50' },
        { label: t.kpi_sold, value: summary.tickets_sold.toLocaleString('id-ID'), icon: Ticket, color: 'text-indigo-600 bg-indigo-50' },
        {
            label: t.kpi_sell_through,
            value: `${summary.sell_through}%`,
            sub: t.kpi_of_quota.replace('{count}', summary.total_quota.toLocaleString('id-ID')),
            icon: TrendingUp,
            color: 'text-blue-600 bg-blue-50',
        },
        {
            label: t.kpi_confirmed,
            value: summary.confirmed_bookings.toLocaleString('id-ID'),
            icon: Users,
            color: 'text-slate-700 bg-slate-100',
        },
        {
            label: t.kpi_checked_in,
            value: summary.checked_in.toLocaleString('id-ID'),
            icon: CheckCircle2,
            color: 'text-emerald-600 bg-emerald-50',
        },
        {
            label: t.kpi_remaining,
            value: summary.tickets_available.toLocaleString('id-ID'),
            icon: BarChart3,
            color: 'text-amber-600 bg-amber-50',
        },
    ];

    return (
        <DashboardLayout>
            <Head title={`${t.title} — ${event.title}`} />

            <div className="max-w-5xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link
                        href={route('events.index')}
                        className="p-2.5 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-900 hover:border-slate-300 transition-all"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{event.title}</h1>
                            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                {statusLabel}
                            </span>
                            {event.category_label && (
                                <span className="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                    {event.category_label}
                                </span>
                            )}
                        </div>
                        <p className="text-slate-500 text-sm mt-0.5">{t.subtitle}</p>
                    </div>
                </div>

                {/* KPI grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {kpis.map((kpi) => (
                        <div key={kpi.label} className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-medium text-slate-500 uppercase tracking-wide">{kpi.label}</p>
                                <span className={`p-2 rounded-lg ${kpi.color}`}>
                                    <kpi.icon size={16} />
                                </span>
                            </div>
                            <p className="mt-3 text-2xl font-bold text-slate-900">{kpi.value}</p>
                            {kpi.sub && <p className="text-xs text-slate-400 mt-1">{kpi.sub}</p>}
                        </div>
                    ))}
                </div>

                {/* Pending notice */}
                {summary.pending_bookings > 0 && (
                    <div className="flex items-center gap-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        <Clock size={18} className="shrink-0" />
                        {t.kpi_pending}: {summary.pending_bookings.toLocaleString('id-ID')}
                    </div>
                )}

                {/* Per-ticket performance */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.tickets_title}</h2>

                    {tickets.length === 0 ? (
                        <p className="text-sm text-slate-500">{t.tickets_empty}</p>
                    ) : (
                        <div className="space-y-4">
                            {tickets.map((ticket) => (
                                <div key={ticket.id} className="space-y-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-sm text-slate-900">{ticket.name}</span>
                                            <span className="text-xs text-slate-400">{formatCurrency(ticket.price)}</span>
                                            {!ticket.is_active && (
                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                                    {t.status_cancelled}
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-xs text-slate-500">
                                            {ticket.sold.toLocaleString('id-ID')}/{ticket.quantity.toLocaleString('id-ID')} · {ticket.sell_through}%
                                        </span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                        <div
                                            className="h-full rounded-full bg-indigo-500 transition-all"
                                            style={{ width: `${Math.min(100, ticket.sell_through)}%` }}
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-slate-500">
                                        <span>{t.col_remaining}: {ticket.remaining.toLocaleString('id-ID')}</span>
                                        <span>{t.col_revenue}: {formatCurrency(ticket.revenue)}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                {/* Sales timeline */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.timeline_title}</h2>

                    {timeline.every((point) => point.revenue === 0) ? (
                        <p className="text-sm text-slate-500">{t.timeline_empty}</p>
                    ) : (
                        <div className="flex items-end gap-1.5 h-40">
                            {timeline.map((point) => {
                                const height = Math.max(2, Math.round((point.revenue / maxRevenue) * 100));
                                return (
                                    <div key={point.date} className="flex-1 flex flex-col items-center gap-1 min-w-0">
                                        <div className="w-full flex items-end h-32">
                                            <div
                                                className="w-full rounded-t bg-indigo-500/80 hover:bg-indigo-600 transition-colors"
                                                style={{ height: `${height}%` }}
                                                title={`${point.date}: ${formatCurrency(point.revenue)} (${point.bookings} ${t.timeline_bookings})`}
                                            />
                                        </div>
                                        <span className="text-[10px] text-slate-400 truncate w-full text-center">
                                            {formatDate(point.date, lang)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </DashboardLayout>
    );
}
