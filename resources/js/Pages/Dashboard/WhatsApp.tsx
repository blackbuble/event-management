import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { whatsappTexts, WhatsAppLanguage, defaultWhatsAppLanguage } from '@/config/whatsapp-texts';
import { ArrowLeft, CheckCircle2, MessageCircle, Wallet } from 'lucide-react';

interface QuotaPackage {
    key: string;
    label: string;
    quota: number;
    amount: number;
    amount_label: string;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface TopUpRow {
    package: string;
    quota: number;
    amount: number;
    payment_method: string | null;
    status: string;
    created_at: string | null;
}

interface Props {
    balance: number;
    packages: QuotaPackage[];
    payment_methods: PaymentMethodOption[];
    history: TopUpRow[];
}

function formatPrice(price: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
}

function formatDate(iso: string | null, lang: WhatsAppLanguage): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(lang === 'id' ? 'id-ID' : 'en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function WhatsAppQuota({ balance, packages, payment_methods, history }: Props) {
    const { locale, flash } = usePage().props as any;
    const lang = (locale as WhatsAppLanguage) || defaultWhatsAppLanguage;
    const t = whatsappTexts[lang];

    const { data, setData, post, processing, errors } = useForm({
        package: packages[0]?.key ?? '',
        payment_method: payment_methods[0]?.value ?? '',
    });

    const selected = packages.find((p) => p.key === data.package);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('whatsapp.topup'));
    };

    return (
        <DashboardLayout>
            <Head title={t.title} />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center gap-4">
                    <Link
                        href={route('dashboard')}
                        className="p-2.5 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-900 hover:border-slate-300 transition-all"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{t.title}</h1>
                        <p className="text-slate-500 text-sm mt-0.5">{t.subtitle}</p>
                    </div>
                </div>

                {(flash?.message || flash?.error) && (
                    <div className="space-y-3">
                        {flash.message && (
                            <div className="flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                                <CheckCircle2 size={18} className="shrink-0" />
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

                {/* Balance */}
                <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-600 to-emerald-700 p-6 text-white shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-emerald-100 text-xs font-medium uppercase tracking-wide">{t.balance_label}</p>
                            <p className="mt-1 text-3xl font-bold">
                                {balance.toLocaleString('id-ID')}{' '}
                                <span className="text-base font-normal text-emerald-100">{t.balance_unit}</span>
                            </p>
                        </div>
                        <MessageCircle className="h-10 w-10 text-emerald-200" />
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Packages */}
                    <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.packages_title}</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {packages.map((pkg) => {
                                const active = data.package === pkg.key;
                                return (
                                    <button
                                        key={pkg.key}
                                        type="button"
                                        onClick={() => setData('package', pkg.key)}
                                        className={`rounded-xl border-2 p-4 text-left transition-all ${
                                            active ? 'border-emerald-600 bg-emerald-50/50' : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <p className={`text-sm font-semibold ${active ? 'text-emerald-700' : 'text-slate-900'}`}>
                                            {pkg.label}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {t.package_quota.replace('{count}', pkg.quota.toLocaleString('id-ID'))}
                                        </p>
                                        <p className="mt-2 text-lg font-bold text-slate-900">{formatPrice(pkg.amount)}</p>
                                    </button>
                                );
                            })}
                        </div>
                        {errors.package && <p className="text-xs font-medium text-red-500">{errors.package}</p>}
                    </section>

                    {/* Payment method */}
                    <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.payment_title}</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {payment_methods.map((method) => {
                                const active = data.payment_method === method.value;
                                return (
                                    <button
                                        key={method.value}
                                        type="button"
                                        onClick={() => setData('payment_method', method.value)}
                                        className={`flex items-center gap-2 rounded-xl border-2 p-3 text-left transition-all ${
                                            active ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <Wallet className={`h-4 w-4 ${active ? 'text-indigo-600' : 'text-slate-400'}`} />
                                        <span className={`text-sm font-medium ${active ? 'text-indigo-700' : 'text-slate-900'}`}>
                                            {method.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        {errors.payment_method && <p className="text-xs font-medium text-red-500">{errors.payment_method}</p>}
                    </section>

                    <button
                        type="submit"
                        disabled={processing || !data.package || !data.payment_method}
                        className="w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors"
                    >
                        {processing
                            ? t.processing
                            : `${t.topup}${selected ? ` · ${formatPrice(selected.amount)}` : ''}`}
                    </button>
                    <p className="text-xs text-slate-400 text-center leading-relaxed">{t.note}</p>
                </form>

                {/* History */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.history_title}</h2>
                    {history.length === 0 ? (
                        <p className="text-sm text-slate-500">{t.history_empty}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-slate-500 uppercase tracking-wide">
                                        <th className="py-2 pr-4">{t.history_package}</th>
                                        <th className="py-2 pr-4">{t.history_quota}</th>
                                        <th className="py-2 pr-4">{t.history_amount}</th>
                                        <th className="py-2 pr-4">{t.history_method}</th>
                                        <th className="py-2 pr-4">{t.history_status}</th>
                                        <th className="py-2">{t.history_date}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.map((row, index) => (
                                        <tr key={index} className="border-t border-slate-100">
                                            <td className="py-2.5 pr-4 text-slate-900 font-medium capitalize">{row.package}</td>
                                            <td className="py-2.5 pr-4 text-slate-600">+{row.quota.toLocaleString('id-ID')}</td>
                                            <td className="py-2.5 pr-4 text-slate-600">{formatPrice(row.amount)}</td>
                                            <td className="py-2.5 pr-4 text-slate-600 capitalize">
                                                {(row.payment_method ?? '—').replace(/_/g, ' ')}
                                            </td>
                                            <td className="py-2.5 pr-4">
                                                <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                    {row.status === 'paid' ? t.status_paid : t.status_pending}
                                                </span>
                                            </td>
                                            <td className="py-2.5 text-slate-500">{formatDate(row.created_at, lang)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </DashboardLayout>
    );
}
