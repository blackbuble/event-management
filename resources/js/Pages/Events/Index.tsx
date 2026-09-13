import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Input, Button } from '@/Components/Form';
import { eventTexts, EventLanguage, defaultEventLanguage } from '@/config/event-texts';
import {
    Calendar,
    MapPin,
    Video,
    Globe2,
    Plus,
    Inbox,
    CheckCircle2,
    Link2,
    Send,
    Users,
    AlertCircle,
    Eye,
    Pencil,
    Rocket,
    XCircle,
    Trash2,
} from 'lucide-react';

interface EventRow {
    id: number;
    title: string;
    slug: string;
    type: 'online' | 'offline' | 'hybrid';
    status: 'draft' | 'published' | 'cancelled';
    start_date: string | null;
    end_date: string | null;
    meeting_link: string | null;
    confirmed_bookings: number;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const typeIcons = { offline: MapPin, online: Video, hybrid: Globe2 } as const;

const statusStyles: Record<string, string> = {
    published: 'bg-emerald-50 text-emerald-700',
    draft: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-red-50 text-red-700',
};

function formatDate(iso: string | null, locale: EventLanguage): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(locale === 'id' ? 'id-ID' : 'en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function EventIndex() {
    const { locale, flash, events, pagination } = usePage().props as any;
    const currentLang = (locale as EventLanguage) || defaultEventLanguage;
    const t = eventTexts[currentLang].index;
    const rows = (events ?? []) as EventRow[];
    const meta = pagination as Pagination | undefined;

    const [linkEditingId, setLinkEditingId] = useState<number | null>(null);
    const [sendingId, setSendingId] = useState<number | null>(null);

    const { data: linkData, setData: setLinkData, patch, processing: savingLink, reset, errors } = useForm<{
        meeting_link: string;
    }>({ meeting_link: '' });

    const { post: postSend, processing: sending } = useForm();

    const openLinkEditor = (eventRow: EventRow) => {
        setLinkEditingId(eventRow.id);
        setLinkData('meeting_link', eventRow.meeting_link ?? '');
    };

    const saveLink = (eventRow: EventRow) => (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('events.meeting-link.update', eventRow.id), {
            onSuccess: () => setLinkEditingId(null),
        });
    };

    const sendLink = (eventRow: EventRow) => {
        setSendingId(eventRow.id);
        postSend(route('events.meeting-link.send', eventRow.id), {
            onFinish: () => setSendingId(null),
        });
    };

    return (
        <DashboardLayout>
            <Head title={t.title} />

            <div className="max-w-4xl mx-auto space-y-8">
                {/* Flash */}
                {flash?.message && (
                    <div className="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium">
                        <CheckCircle2 size={18} />
                        <span>{flash.message}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm font-medium">
                        <AlertCircle size={18} />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{t.title}</h1>
                        <p className="text-slate-500 text-sm mt-0.5">{t.subtitle}</p>
                    </div>
                    <Link
                        href={route('events.create')}
                        className="flex items-center justify-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg font-medium text-sm transition-all shadow-sm shadow-indigo-500/20 active:scale-95"
                    >
                        <Plus size={18} />
                        <span>{t.create_event}</span>
                    </Link>
                </div>

                {/* List */}
                {rows.length === 0 ? (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-10 flex flex-col items-center justify-center text-center">
                        <div className="p-3 rounded-full bg-slate-50 mb-3">
                            <Inbox size={24} className="text-slate-300" />
                        </div>
                        <h3 className="text-sm font-semibold text-slate-700">{t.empty_title}</h3>
                        <p className="text-xs text-slate-400 mt-1 max-w-xs">{t.empty_desc}</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {rows.map((eventRow) => {
                            const TypeIcon = typeIcons[eventRow.type];
                            const isRemote = eventRow.type === 'online' || eventRow.type === 'hybrid';
                            const isEditing = linkEditingId === eventRow.id;
                            const isSending = sendingId === eventRow.id;

                            return (
                                <div key={eventRow.id} className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                                    <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                        <div className="flex items-start gap-4">
                                            <div className="p-3 rounded-lg bg-indigo-50">
                                                <TypeIcon className="text-indigo-600" size={22} />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <h3 className="text-base font-bold text-slate-900">{eventRow.title}</h3>
                                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusStyles[eventRow.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                        {(t as Record<string, string>)[`status_${eventRow.status}`]}
                                                    </span>
                                                    <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">
                                                        {(t as Record<string, string>)[`type_${eventRow.type}`]}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-slate-500 mt-1.5 flex items-center gap-1.5">
                                                    <Calendar size={13} />
                                                    {t.starts.replace('{date}', formatDate(eventRow.start_date, currentLang))}
                                                </p>
                                                <p className="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                                    <Users size={13} />
                                                    {t.confirmed_bookings.replace('{count}', String(eventRow.confirmed_bookings))}
                                                </p>
                                            </div>
                                        </div>

                                    {/* Primary actions: manage meeting link (online/hybrid) */}
                                    {isRemote && !isEditing && (
                                        <div className="flex flex-col sm:items-end gap-2">
                                            <span className={`inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full ${
                                                eventRow.meeting_link
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-amber-50 text-amber-700'
                                            }`}>
                                                <Link2 size={12} />
                                                {eventRow.meeting_link ? t.link_set : t.link_pending}
                                            </span>
                                            <div className="flex gap-2">
                                                <Button variant="secondary" size="sm" onClick={() => openLinkEditor(eventRow)}>
                                                    {eventRow.meeting_link ? t.save_link : t.set_link}
                                                </Button>
                                                {eventRow.meeting_link && eventRow.confirmed_bookings > 0 && (
                                                    <Button size="sm" onClick={() => sendLink(eventRow)} isLoading={isSending || sending}>
                                                        <span className="flex items-center gap-1.5">
                                                            <Send size={13} />
                                                            {isSending || sending ? t.sending_link : t.send_link}
                                                        </span>
                                                    </Button>
                                                )}
                                            </div>
                                            {eventRow.meeting_link && eventRow.confirmed_bookings === 0 && (
                                                <p className="text-xs text-slate-400">{t.no_attendees_hint}</p>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* General actions */}
                                <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                                    <Link
                                        href={route('events.show', eventRow.slug)}
                                        className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors"
                                    >
                                        <Eye size={15} />
                                        {t.view}
                                    </Link>
                                    <Link
                                        href={route('events.edit', eventRow.id)}
                                        className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors"
                                    >
                                        <Pencil size={15} />
                                        {t.edit}
                                    </Link>
                                    {eventRow.status === 'draft' && (
                                        <Link
                                            href={route('events.publish', eventRow.id)}
                                            method="patch"
                                            as="button"
                                            className="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 px-3 py-1.5 rounded-lg hover:bg-emerald-50 transition-colors"
                                        >
                                            <Rocket size={15} />
                                            {t.publish}
                                        </Link>
                                    )}
                                    {eventRow.status !== 'cancelled' && (
                                        <Link
                                            href={route('events.cancel', eventRow.id)}
                                            method="patch"
                                            as="button"
                                            className="inline-flex items-center gap-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 px-3 py-1.5 rounded-lg hover:bg-amber-50 transition-colors"
                                        >
                                            <XCircle size={15} />
                                            {t.cancel_event}
                                        </Link>
                                    )}
                                    <Link
                                        href={route('events.destroy', eventRow.id)}
                                        method="delete"
                                        as="button"
                                        onClick={(e: React.MouseEvent) => {
                                            if (!window.confirm(t.delete_confirm)) {
                                                e.preventDefault();
                                            }
                                        }}
                                        className="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors ml-auto"
                                    >
                                        <Trash2 size={15} />
                                        {t.delete_event}
                                    </Link>
                                </div>

                                    {/* Inline link editor */}
                                    {isRemote && isEditing && (
                                        <form onSubmit={saveLink(eventRow)} className="border-t border-slate-100 pt-4">
                                            <div className="flex flex-col sm:flex-row sm:items-end gap-3">
                                                <Input
                                                    label={t.link_label}
                                                    placeholder={t.link_input_placeholder}
                                                    value={linkData.meeting_link}
                                                    onChange={(e) => setLinkData('meeting_link', e.target.value)}
                                                    error={errors.meeting_link}
                                                    type="url"
                                                    maxLength={255}
                                                    required
                                                    containerClassName="flex-1"
                                                />
                                                <div className="flex gap-2">
                                                    <Button type="submit" isLoading={savingLink}>
                                                        {savingLink ? t.saving : t.save_link}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            setLinkEditingId(null);
                                                            reset();
                                                        }}
                                                    >
                                                        ✕
                                                    </Button>
                                                </div>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between bg-white rounded-xl border border-slate-200 shadow-sm px-6 py-4">
                        <Link
                            href={route('events.index', { page: meta.current_page - 1 })}
                            className={`text-sm font-medium px-4 py-2 rounded-lg transition-colors ${
                                meta.current_page > 1
                                    ? 'text-slate-700 hover:bg-slate-100'
                                    : 'text-slate-300 pointer-events-none'
                            }`}
                        >
                            ← {t.prev_page}
                        </Link>
                        <span className="text-xs text-slate-500 font-medium">
                            {t.page_info
                                .replace('{page}', String(meta.current_page))
                                .replace('{last}', String(meta.last_page))
                                .replace('{total}', String(meta.total))}
                        </span>
                        <Link
                            href={route('events.index', { page: meta.current_page + 1 })}
                            className={`text-sm font-medium px-4 py-2 rounded-lg transition-colors ${
                                meta.current_page < meta.last_page
                                    ? 'text-slate-700 hover:bg-slate-100'
                                    : 'text-slate-300 pointer-events-none'
                            }`}
                        >
                            {t.next_page} →
                        </Link>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
