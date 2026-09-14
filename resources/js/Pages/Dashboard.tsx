import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Plus, Calendar, Tag, DollarSign, Wallet, CheckCircle2, Inbox, MessageCircle } from 'lucide-react';
import { dashboardTexts, DashboardLanguage, defaultDashboardLanguage } from '@/config/dashboard-texts';

interface OrganizerStats {
    active_events: number;
    draft_events: number;
    tickets_sold: number;
    tickets_reserved: number;
    revenue: number;
    confirmed_bookings: number;
}

interface AttendeeStats {
    upcoming_bookings: number;
    total_tickets: number;
    total_spent: number;
}

interface ActivityItem {
    event_title?: string;
    event_slug?: string;
    attendee_name?: string;
    status: string;
    total_amount: number;
    created_at?: string;
}

interface DashboardProp {
    view: 'organizer' | 'attendee';
    stats: OrganizerStats | AttendeeStats;
    recent_activity: ActivityItem[];
}

function formatCurrency(value: number, locale: DashboardLanguage): string {
    return new Intl.NumberFormat(locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function timeAgo(isoDate: string | undefined, timeTexts: { now: string; minutes: string; hours: string; days: string }): string {
    if (!isoDate) return '';

    const seconds = Math.floor((Date.now() - new Date(isoDate).getTime()) / 1000);

    if (seconds < 60) return timeTexts.now;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}${timeTexts.minutes}`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}${timeTexts.hours}`;

    return `${Math.floor(seconds / 86400)}${timeTexts.days}`;
}

const statusStyles: Record<string, string> = {
    confirmed: 'bg-emerald-50 text-emerald-700',
    pending: 'bg-amber-50 text-amber-700',
    cancelled: 'bg-red-50 text-red-700',
    refunded: 'bg-slate-100 text-slate-600',
};

export default function Dashboard() {
    const { auth, flash, locale, dashboard, canCreateEvent } = usePage().props as any;
    const currentLang = (locale as DashboardLanguage) || defaultDashboardLanguage;
    const t = dashboardTexts[currentLang];
    const data = dashboard as DashboardProp;

    const isOrganizer = data.view === 'organizer';
    const organizerStats = data.stats as OrganizerStats;
    const attendeeStats = data.stats as AttendeeStats;

    const statCards = isOrganizer
        ? [
              {
                  label: t.organizer.active_events,
                  value: String(organizerStats.active_events),
                  icon: Calendar,
                  color: 'text-indigo-600',
                  bg: 'bg-indigo-50',
                  sub: t.organizer.drafts.replace('{count}', String(organizerStats.draft_events)),
              },
              {
                  label: t.organizer.tickets_sold,
                  value: new Intl.NumberFormat(currentLang === 'id' ? 'id-ID' : 'en-US').format(organizerStats.tickets_sold),
                  icon: Tag,
                  color: 'text-emerald-600',
                  bg: 'bg-emerald-50',
                  sub: t.organizer.reserved.replace('{count}', String(organizerStats.tickets_reserved)),
              },
              {
                  label: t.organizer.revenue,
                  value: formatCurrency(organizerStats.revenue, currentLang),
                  icon: DollarSign,
                  color: 'text-amber-600',
                  bg: 'bg-amber-50',
                  sub: t.organizer.confirmed.replace('{count}', String(organizerStats.confirmed_bookings)),
              },
          ]
        : [
              {
                  label: t.attendee.upcoming,
                  value: String(attendeeStats.upcoming_bookings),
                  icon: Calendar,
                  color: 'text-indigo-600',
                  bg: 'bg-indigo-50',
              },
              {
                  label: t.attendee.tickets,
                  value: String(attendeeStats.total_tickets),
                  icon: Tag,
                  color: 'text-emerald-600',
                  bg: 'bg-emerald-50',
              },
              {
                  label: t.attendee.spent,
                  value: formatCurrency(attendeeStats.total_spent, currentLang),
                  icon: Wallet,
                  color: 'text-amber-600',
                  bg: 'bg-amber-50',
              },
          ];

    return (
        <DashboardLayout>
            <Head title={t.header.title} />

            <div className="space-y-8">
                {/* Flash Message */}
                {flash?.message && (
                    <div className="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium">
                        <CheckCircle2 size={18} />
                        <span>{flash.message}</span>
                    </div>
                )}

                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{t.header.title}</h1>
                        <p className="text-slate-500 text-sm mt-1">
                            {t.header.subtitle.replace('{name}', auth?.user?.name ?? '')}
                        </p>
                    </div>
                    {canCreateEvent && (
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('whatsapp.index')}
                                className="flex items-center justify-center space-x-2 bg-white border border-slate-200 text-slate-700 hover:border-emerald-300 hover:text-emerald-700 px-4 py-2.5 rounded-lg font-medium text-sm transition-all"
                            >
                                <MessageCircle size={18} />
                                <span>{t.header.whatsapp}</span>
                            </Link>
                            <Link
                                href={route('events.create')}
                                className="flex items-center justify-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg font-medium text-sm transition-all shadow-sm shadow-indigo-500/20 active:scale-95"
                            >
                                <Plus size={18} />
                                <span>{t.header.create_event}</span>
                            </Link>
                        </div>
                    )}
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {statCards.map((stat, i) => (
                        <div key={i} className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between">
                                <div className={`p-3 rounded-lg ${stat.bg}`}>
                                    <stat.icon className={stat.color} size={24} />
                                </div>
                            </div>
                            <div className="mt-4">
                                <h3 className="text-2xl font-bold text-slate-900 tracking-tight">{stat.value}</h3>
                                <p className="text-sm font-medium text-slate-500 mt-1">{stat.label}</p>
                                {'sub' in stat && stat.sub && (
                                    <p className="text-xs font-medium text-slate-400 mt-0.5">{stat.sub}</p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Recent Activity */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-6 border-b border-slate-100">
                        <h2 className="text-lg font-bold text-slate-900">{t.activity.title}</h2>
                    </div>

                    {data.recent_activity.length === 0 ? (
                        <div className="p-10 flex flex-col items-center justify-center text-center">
                            <div className="p-3 rounded-full bg-slate-50 mb-3">
                                <Inbox size={24} className="text-slate-300" />
                            </div>
                            <h3 className="text-sm font-semibold text-slate-700">{t.activity.empty_title}</h3>
                            <p className="text-xs text-slate-400 mt-1 max-w-xs">{t.activity.empty_desc}</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {data.recent_activity.map((item, i) => (
                                <div key={i} className="p-4 hover:bg-slate-50 transition-colors flex items-center justify-between group">
                                    <div className="flex items-center space-x-4">
                                        <div className="h-10 w-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-white group-hover:shadow-sm transition-all">
                                            <Calendar size={20} />
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-semibold text-slate-900">{item.event_title}</h4>
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                {isOrganizer
                                                    ? `${item.attendee_name} • ${formatCurrency(item.total_amount, currentLang)}`
                                                    : formatCurrency(item.total_amount, currentLang)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center space-x-3">
                                        <span className={`text-xs font-medium px-2 py-1 rounded-full ${statusStyles[item.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                            {(t.status as Record<string, string>)[item.status] ?? item.status}
                                        </span>
                                        <span className="text-xs font-medium text-slate-400">
                                            {timeAgo(item.created_at, t.time)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
