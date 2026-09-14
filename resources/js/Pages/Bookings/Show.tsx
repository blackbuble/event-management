import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { bookingTexts, BookingLanguage, defaultBookingLanguage } from '@/config/booking-texts';
import { CalendarDays, CheckCircle2, Clock, Ticket } from 'lucide-react';

interface SummaryTicket {
    name: string | null;
    attendee_name: string | null;
    ticket_code: string;
    quantity: number;
    price: number;
    subtotal: number;
}

interface BookingSummary {
    id: number;
    booking_number: string;
    status: string;
    payment_status: string;
    payment_method: string | null;
    total_amount: number;
    event: { id: number; title: string; slug: string; start_date: string | null; end_date: string | null } | null;
    tickets: SummaryTicket[];
}

interface Props {
    booking: BookingSummary;
    pay_url: string | null;
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

export default function ShowBooking({ booking, pay_url }: Props) {
    const { locale, flash } = usePage().props as any;
    const lang = (locale as BookingLanguage) || defaultBookingLanguage;
    const t = bookingTexts[lang].show;

    const isPaid = booking.payment_status === 'paid';
    const isFree = booking.payment_status === 'free';
    const statusLabel = t.status[booking.status as keyof typeof t.status] ?? booking.status;
    const paymentLabel = t.payment[booking.payment_status as keyof typeof t.payment] ?? booking.payment_status;

    return (
        <>
            <Head title={t.title} />

            <div className="min-h-screen bg-slate-50 py-10 px-4">
                <div className="mx-auto max-w-2xl space-y-6">
                    {(flash?.message || flash?.error) && (
                        <div className="space-y-3">
                            {flash.message && (
                                <div className="flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                                    <CheckCircle2 className="h-5 w-5 shrink-0" />
                                    {flash.message}
                                </div>
                            )}
                            {flash.error && (
                                <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                                    {flash.error}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1">
                                <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{t.title}</h1>
                                <p className="text-sm text-slate-500">{t.subtitle}</p>
                            </div>
                            <span
                                className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
                                    booking.status === 'cancelled'
                                        ? 'bg-red-100 text-red-800'
                                        : isPaid || isFree
                                          ? 'bg-emerald-100 text-emerald-800'
                                          : 'bg-amber-100 text-amber-800'
                                }`}
                            >
                                {statusLabel}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="rounded-xl bg-slate-50 border border-slate-100 p-4">
                                <p className="text-xs text-slate-500">{t.booking_number}</p>
                                <p className="mt-1 font-mono font-semibold text-slate-900">{booking.booking_number}</p>
                            </div>
                            <div className="rounded-xl bg-slate-50 border border-slate-100 p-4">
                                <p className="text-xs text-slate-500">{t.payment_status_label}</p>
                                <p className="mt-1 font-semibold text-slate-900">{paymentLabel}</p>
                            </div>
                            {booking.payment_method && (
                                <div className="rounded-xl bg-slate-50 border border-slate-100 p-4 sm:col-span-2">
                                    <p className="text-xs text-slate-500">{t.payment_method_label}</p>
                                    <p className="mt-1 font-semibold text-slate-900">
                                        {booking.payment_method.replace(/_/g, ' ')}
                                    </p>
                                </div>
                            )}
                        </div>

                        {booking.event && (
                            <div className="rounded-xl border border-slate-100 p-4 space-y-2">
                                <p className="text-xs text-slate-500">{t.event_label}</p>
                                <p className="font-semibold text-slate-900">{booking.event.title}</p>
                                <p className="flex items-center gap-2 text-xs text-slate-500">
                                    <CalendarDays className="h-3.5 w-3.5" />
                                    {formatDate(booking.event.start_date, lang)}
                                </p>
                            </div>
                        )}

                        <div className="space-y-2">
                            <p className="text-xs font-semibold text-slate-700 uppercase tracking-wide">{t.tickets_label}</p>
                            {booking.tickets.map((ticket, index) => (
                                <div key={index} className="flex items-start justify-between text-sm">
                                    <span className="flex items-start gap-2 text-slate-600">
                                        <Ticket className="h-3.5 w-3.5 text-slate-400 mt-0.5" />
                                        <span>
                                            {ticket.name}
                                            <span className="text-slate-400"> × {ticket.quantity}</span>
                                            {ticket.attendee_name && (
                                                <span className="block text-xs text-slate-400">{ticket.attendee_name}</span>
                                            )}
                                            <span className="mt-0.5 block font-mono text-[11px] text-slate-400">
                                                {ticket.ticket_code}
                                            </span>
                                        </span>
                                    </span>
                                    <span className="font-medium text-slate-800">{formatPrice(ticket.subtotal)}</span>
                                </div>
                            ))}
                            <div className="flex items-center justify-between border-t border-slate-200 pt-3">
                                <span className="text-sm font-medium text-slate-600">{t.total}</span>
                                <span className="text-lg font-bold text-slate-900">{formatPrice(booking.total_amount)}</span>
                            </div>
                        </div>

                        {isFree && (
                            <p className="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                                <CheckCircle2 className="h-4 w-4 shrink-0" />
                                {t.free_notice}
                            </p>
                        )}

                        <div className="flex flex-col sm:flex-row gap-3 pt-2">
                            {!isPaid && !isFree && booking.status !== 'cancelled' && pay_url && (
                                <Link
                                    href={pay_url}
                                    className="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition-colors"
                                >
                                    <Clock className="h-4 w-4" />
                                    {t.complete_payment}
                                </Link>
                            )}
                            {booking.event && (
                                <Link
                                    href={`/events/${booking.event.slug}`}
                                    className="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                                >
                                    {t.view_event}
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
