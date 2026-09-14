import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { bookingTexts, BookingLanguage, defaultBookingLanguage } from '@/config/booking-texts';
import {
    ArrowLeft,
    Building2,
    CreditCard,
    Landmark,
    QrCode,
    ShieldCheck,
    Ticket,
    Wallet,
} from 'lucide-react';

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

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface Props {
    booking: BookingSummary;
    payment_methods: PaymentMethodOption[];
    submit_url: string;
}

const methodIcons: Record<string, typeof Landmark> = {
    bank_transfer: Landmark,
    virtual_account: Building2,
    ewallet: Wallet,
    qris: QrCode,
    credit_card: CreditCard,
};

function formatPrice(price: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
}

export default function PayBooking({ booking, payment_methods, submit_url }: Props) {
    const { locale } = usePage().props as any;
    const lang = (locale as BookingLanguage) || defaultBookingLanguage;
    const t = bookingTexts[lang].pay;

    const { data, setData, post, processing, errors } = useForm({
        payment_method: payment_methods[0]?.value ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(submit_url);
    };

    return (
        <>
            <Head title={t.title} />

            <div className="min-h-screen bg-slate-50 py-10 px-4">
                <div className="mx-auto max-w-2xl space-y-6">
                    <Link
                        href={booking.event ? `/events/${booking.event.slug}` : '/'}
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

                        {/* Order summary */}
                        <div className="rounded-xl bg-slate-50 border border-slate-100 p-5 space-y-4">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-slate-500">{t.booking_number}</span>
                                <span className="font-mono font-semibold text-slate-900">{booking.booking_number}</span>
                            </div>

                            {booking.event && (
                                <p className="text-sm font-semibold text-slate-900">{booking.event.title}</p>
                            )}

                            <div className="space-y-2 border-t border-slate-200 pt-3">
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
                                            </span>
                                        </span>
                                        <span className="font-medium text-slate-800">{formatPrice(ticket.subtotal)}</span>
                                    </div>
                                ))}
                            </div>

                            <div className="flex items-center justify-between border-t border-slate-200 pt-3">
                                <span className="text-sm font-medium text-slate-600">{t.total}</span>
                                <span className="text-lg font-bold text-slate-900">{formatPrice(booking.total_amount)}</span>
                            </div>
                        </div>

                        {/* Payment methods */}
                        <form onSubmit={submit} className="space-y-4">
                            <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">
                                {t.choose_method}
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {payment_methods.map((method) => {
                                    const Icon = methodIcons[method.value] ?? CreditCard;
                                    const active = data.payment_method === method.value;
                                    return (
                                        <button
                                            key={method.value}
                                            type="button"
                                            onClick={() => setData('payment_method', method.value)}
                                            className={`flex items-center gap-3 rounded-xl border-2 p-4 text-left transition-all ${
                                                active
                                                    ? 'border-indigo-600 bg-indigo-50/50'
                                                    : 'border-slate-200 hover:border-slate-300'
                                            }`}
                                        >
                                            <Icon className={`h-5 w-5 ${active ? 'text-indigo-600' : 'text-slate-400'}`} />
                                            <span className={`text-sm font-medium ${active ? 'text-indigo-700' : 'text-slate-900'}`}>
                                                {method.label}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            {errors.payment_method && (
                                <p className="text-xs font-medium text-red-500">{errors.payment_method}</p>
                            )}

                            <button
                                type="submit"
                                disabled={processing || !data.payment_method}
                                className="w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors"
                            >
                                {processing ? t.paying : `${t.pay_now} · ${formatPrice(booking.total_amount)}`}
                            </button>

                            <p className="flex items-start gap-2 text-xs text-slate-400 leading-relaxed">
                                <ShieldCheck className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                                <span>{t.sandbox_note}</span>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
