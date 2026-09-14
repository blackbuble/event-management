import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Input } from '@/Components/Form';
import { bookingTexts, BookingLanguage, defaultBookingLanguage } from '@/config/booking-texts';
import { ArrowLeft, CalendarDays, Ticket, User, Users } from 'lucide-react';

interface SelectionItem {
    ticket_id: number;
    name: string;
    price: number;
    quantity: number;
    subtotal: number;
}

interface TransactionEvent {
    id: number;
    title: string;
    slug: string;
    venue_name: string | null;
    start_date: string | null;
}

interface Props {
    event: TransactionEvent;
    selection: SelectionItem[];
    total_amount: number;
}

type AttendeeMode = 'individual' | 'representative';

interface AttendeeField {
    name: string;
    email: string;
    phone: string;
}

function formatPrice(price: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
}

function formatDate(iso: string | null, lang: BookingLanguage): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(lang === 'id' ? 'id-ID' : 'en-US', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function Transaction({ event, selection, total_amount }: Props) {
    const { locale, auth } = usePage().props as any;
    const lang = (locale as BookingLanguage) || defaultBookingLanguage;
    const t = bookingTexts[lang].transaction;

    const { data, setData, post, processing, errors } = useForm({
        attendee_mode: 'individual' as AttendeeMode,
        contact: {
            name: auth?.user?.name ?? '',
            email: auth?.user?.email ?? '',
            phone: auth?.user?.phone ?? '',
        },
        tickets: selection.map((item) => ({
            ticket_id: item.ticket_id,
            quantity: item.quantity,
            attendees: Array.from({ length: item.quantity }, (): AttendeeField => ({ name: '', email: '', phone: '' })),
        })),
        representative: {
            name: auth?.user?.name ?? '',
            email: auth?.user?.email ?? '',
            phone: auth?.user?.phone ?? '',
        },
    });

    const setMode = (mode: AttendeeMode) => setData('attendee_mode', mode);

    const updateAttendee = (ticketIndex: number, attendeeIndex: number, key: keyof AttendeeField, value: string) => {
        const next = data.tickets.map((ticket, i) =>
            i === ticketIndex
                ? {
                      ...ticket,
                      attendees: ticket.attendees.map((attendee, j) =>
                          j === attendeeIndex ? { ...attendee, [key]: value } : attendee
                      ),
                  }
                : ticket
        );
        setData('tickets', next);
    };

    const fieldError = (path: string): string | undefined =>
        (errors as unknown as Record<string, string>)[path];

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('bookings.store', event.id));
    };

    return (
        <>
            <Head title={t.title} />

            <div className="min-h-screen bg-slate-50 py-10 px-4">
                <div className="mx-auto max-w-3xl space-y-6">
                    <Link
                        href={`/events/${event.slug}`}
                        className="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900 transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        {t.back}
                    </Link>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
                        <div className="space-y-1">
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{t.title}</h1>
                            <p className="text-sm text-slate-500">{t.subtitle}</p>
                        </div>

                        {/* Event + summary */}
                        <div className="rounded-xl bg-slate-50 border border-slate-100 p-5 space-y-3">
                            <p className="font-semibold text-slate-900">{event.title}</p>
                            <p className="flex items-center gap-2 text-xs text-slate-500">
                                <CalendarDays className="h-3.5 w-3.5" />
                                {formatDate(event.start_date, lang)}
                            </p>
                            <div className="space-y-2 border-t border-slate-200 pt-3">
                                {selection.map((item) => (
                                    <div key={item.ticket_id} className="flex items-center justify-between text-sm">
                                        <span className="flex items-center gap-2 text-slate-600">
                                            <Ticket className="h-3.5 w-3.5 text-slate-400" />
                                            {item.name} <span className="text-slate-400">× {item.quantity}</span>
                                        </span>
                                        <span className="font-medium text-slate-800">{formatPrice(item.subtotal)}</span>
                                    </div>
                                ))}
                                <div className="flex items-center justify-between border-t border-slate-200 pt-3">
                                    <span className="text-sm font-medium text-slate-600">{t.total}</span>
                                    <span className="text-lg font-bold text-slate-900">{formatPrice(total_amount)}</span>
                                </div>
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            {/* Recipient contact (tickets + QR delivery) */}
                            <div className="rounded-xl border border-slate-200 p-4 space-y-4">
                                <div>
                                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.contact_title}</h2>
                                    <p className="text-xs text-slate-500 mt-1">{t.contact_hint}</p>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <Input
                                        label={t.contact_name_label}
                                        placeholder={t.name_placeholder}
                                        value={data.contact.name}
                                        onChange={(e) => setData('contact', { ...data.contact, name: e.target.value })}
                                        error={fieldError('contact.name')}
                                        maxLength={255}
                                        required
                                    />
                                    <Input
                                        label={t.contact_email_label}
                                        placeholder={t.email_placeholder}
                                        type="email"
                                        value={data.contact.email}
                                        onChange={(e) => setData('contact', { ...data.contact, email: e.target.value })}
                                        error={fieldError('contact.email')}
                                        maxLength={255}
                                        required
                                    />
                                    <Input
                                        label={t.phone_label}
                                        placeholder={t.phone_placeholder}
                                        value={data.contact.phone}
                                        onChange={(e) => setData('contact', { ...data.contact, phone: e.target.value })}
                                        error={fieldError('contact.phone')}
                                        maxLength={20}
                                    />
                                </div>
                            </div>

                            {/* Mode switch */}
                            <div className="space-y-3">
                                <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.mode_label}</h2>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {(
                                        [
                                            { value: 'individual', label: t.mode_individual, desc: t.mode_individual_desc, icon: Users },
                                            { value: 'representative', label: t.mode_representative, desc: t.mode_representative_desc, icon: User },
                                        ] as const
                                    ).map(({ value, label, desc, icon: Icon }) => {
                                        const active = data.attendee_mode === value;
                                        return (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() => setMode(value)}
                                                className={`flex items-start gap-3 rounded-xl border-2 p-4 text-left transition-all ${
                                                    active ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:border-slate-300'
                                                }`}
                                            >
                                                <Icon className={`h-5 w-5 mt-0.5 ${active ? 'text-indigo-600' : 'text-slate-400'}`} />
                                                <span>
                                                    <span className={`block text-sm font-semibold ${active ? 'text-indigo-700' : 'text-slate-900'}`}>
                                                        {label}
                                                    </span>
                                                    <span className="block text-xs text-slate-500 mt-0.5">{desc}</span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Individual attendee fields */}
                            {data.attendee_mode === 'individual' &&
                                selection.map((item, ticketIndex) => (
                                    <div key={item.ticket_id} className="rounded-xl border border-slate-200 p-4 space-y-4">
                                        <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">{item.name}</p>
                                        {item.quantity > 1 && fieldError(`tickets.${ticketIndex}.attendees`) && (
                                            <p className="text-xs font-medium text-red-500">{fieldError(`tickets.${ticketIndex}.attendees`)}</p>
                                        )}
                                        {Array.from({ length: item.quantity }).map((_, attendeeIndex) => (
                                            <div key={attendeeIndex} className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                <Input
                                                    label={t.attendee_label.replace('{n}', String(attendeeIndex + 1))}
                                                    placeholder={t.name_placeholder}
                                                    value={data.tickets[ticketIndex]?.attendees[attendeeIndex]?.name ?? ''}
                                                    onChange={(e) => updateAttendee(ticketIndex, attendeeIndex, 'name', e.target.value)}
                                                    error={fieldError(`tickets.${ticketIndex}.attendees.${attendeeIndex}.name`)}
                                                    maxLength={255}
                                                    required
                                                />
                                                <Input
                                                    label={t.email_label}
                                                    placeholder={t.email_placeholder}
                                                    type="email"
                                                    value={data.tickets[ticketIndex]?.attendees[attendeeIndex]?.email ?? ''}
                                                    onChange={(e) => updateAttendee(ticketIndex, attendeeIndex, 'email', e.target.value)}
                                                    error={fieldError(`tickets.${ticketIndex}.attendees.${attendeeIndex}.email`)}
                                                    maxLength={255}
                                                />
                                                <Input
                                                    label={t.phone_label}
                                                    placeholder={t.phone_placeholder}
                                                    value={data.tickets[ticketIndex]?.attendees[attendeeIndex]?.phone ?? ''}
                                                    onChange={(e) => updateAttendee(ticketIndex, attendeeIndex, 'phone', e.target.value)}
                                                    error={fieldError(`tickets.${ticketIndex}.attendees.${attendeeIndex}.phone`)}
                                                    maxLength={20}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                ))}

                            {/* Representative */}
                            {data.attendee_mode === 'representative' && (
                                <div className="rounded-xl border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <Input
                                        label={t.representative_name_label}
                                        placeholder={t.name_placeholder}
                                        value={data.representative.name}
                                        onChange={(e) => setData('representative', { ...data.representative, name: e.target.value })}
                                        error={fieldError('representative.name')}
                                        maxLength={255}
                                        required
                                    />
                                    <Input
                                        label={t.representative_email_label}
                                        placeholder={t.email_placeholder}
                                        type="email"
                                        value={data.representative.email}
                                        onChange={(e) => setData('representative', { ...data.representative, email: e.target.value })}
                                        error={fieldError('representative.email')}
                                        maxLength={255}
                                    />
                                    <Input
                                        label={t.phone_label}
                                        placeholder={t.phone_placeholder}
                                        value={data.representative.phone}
                                        onChange={(e) => setData('representative', { ...data.representative, phone: e.target.value })}
                                        error={fieldError('representative.phone')}
                                        maxLength={20}
                                    />
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors"
                            >
                                {processing ? t.processing : `${t.continue} · ${formatPrice(total_amount)}`}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
