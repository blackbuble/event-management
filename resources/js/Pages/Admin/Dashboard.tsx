import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Users, CalendarDays, TicketCheck, Wallet } from 'lucide-react';

interface Stats {
    users: { total: number; organizers: number; attendees: number };
    events: { total: number; published: number; draft: number };
    bookings: { total: number; confirmed: number; revenue: number };
    whatsapp: { topups: number; quota_sold: number };
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

export default function AdminDashboard({ stats }: { stats: Stats }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].dashboard;

    const cards = [
        { label: t.users, value: stats.users.total, sub: `${stats.users.organizers} ${t.organizers} · ${stats.users.attendees} ${t.attendees}`, icon: Users, color: 'text-indigo-600 bg-indigo-50' },
        { label: t.events, value: stats.events.total, sub: `${stats.events.published} ${t.published} · ${stats.events.draft} ${t.draft}`, icon: CalendarDays, color: 'text-emerald-600 bg-emerald-50' },
        { label: t.bookings, value: stats.bookings.total, sub: `${stats.bookings.confirmed} ${t.confirmed}`, icon: TicketCheck, color: 'text-amber-600 bg-amber-50' },
        { label: t.revenue, value: formatCurrency(stats.bookings.revenue), sub: `${stats.whatsapp.quota_sold} WA`, icon: Wallet, color: 'text-slate-700 bg-slate-100' },
    ];

    return (
        <AdminLayout title={t.title}>
            <Head title={t.title} />
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {cards.map((card) => (
                    <div key={card.label} className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-slate-500 uppercase tracking-wide">{card.label}</p>
                            <span className={`p-2 rounded-lg ${card.color}`}>
                                <card.icon size={16} />
                            </span>
                        </div>
                        <p className="mt-3 text-2xl font-bold text-slate-900">{card.value}</p>
                        <p className="text-xs text-slate-400 mt-1">{card.sub}</p>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
