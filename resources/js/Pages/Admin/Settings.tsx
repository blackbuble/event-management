import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Input, Button, Select } from '@/Components/Form';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';

interface Settings {
    general: { app_name: string; contact_center: string; contact_email: string };
    payment_gateway: { enabled: boolean; provider: string; api_key: string; secret_key: boolean };
    whatsapp_provider: { enabled: boolean; provider: string; token: boolean; sender: string };
    platform_fee: { type: string; amount: number };
}

type Tab = 'general' | 'payment' | 'whatsapp' | 'fee';

export default function AdminSettings({ settings }: { settings: Settings }) {
    const { locale, flash } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].settings;

    const [tab, setTab] = useState<Tab>('general');

    const general = useForm({
        app_name: settings.general.app_name,
        contact_center: settings.general.contact_center,
        contact_email: settings.general.contact_email,
    });

    const payment = useForm({
        enabled: settings.payment_gateway.enabled,
        provider: settings.payment_gateway.provider,
        api_key: settings.payment_gateway.api_key,
        secret_key: '',
    });

    const whatsapp = useForm({
        enabled: settings.whatsapp_provider.enabled,
        provider: settings.whatsapp_provider.provider,
        token: '',
        sender: settings.whatsapp_provider.sender,
    });

    const fee = useForm({
        type: settings.platform_fee.type,
        amount: String(settings.platform_fee.amount),
    });

    const tabs: { key: Tab; label: string }[] = [
        { key: 'general', label: t.tabs.general },
        { key: 'payment', label: t.tabs.payment },
        { key: 'whatsapp', label: t.tabs.whatsapp },
        { key: 'fee', label: t.tabs.fee },
    ];

    const toggleClass = (active: boolean) =>
        `text-xs font-medium px-2 py-0.5 rounded-full ${active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`;

    return (
        <AdminLayout title={t.title}>
            <Head title={t.title} />

            {flash?.message && (
                <div className="mb-5 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.message}</div>
            )}

            <div className="max-w-3xl">
                {/* Tabs */}
                <div className="flex border-b border-slate-200">
                    {tabs.map((item) => (
                        <button
                            key={item.key}
                            onClick={() => setTab(item.key)}
                            className={`border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                tab === item.key
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <div className="pt-6">
                    {/* General */}
                    {tab === 'general' && (
                        <section className="rounded-lg border border-slate-200 bg-white p-6 space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-900">{t.general_title}</h2>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    general.put(route('admin.settings.general'));
                                }}
                                className="space-y-4"
                            >
                                <Input
                                    label={t.app_name}
                                    value={general.data.app_name}
                                    onChange={(e) => general.setData('app_name', e.target.value)}
                                    error={general.errors.app_name}
                                    required
                                />
                                <Input
                                    label={t.contact_center}
                                    value={general.data.contact_center}
                                    onChange={(e) => general.setData('contact_center', e.target.value)}
                                    error={general.errors.contact_center}
                                />
                                <Input
                                    label={t.contact_email}
                                    type="email"
                                    value={general.data.contact_email}
                                    onChange={(e) => general.setData('contact_email', e.target.value)}
                                    error={general.errors.contact_email}
                                />
                                <Button type="submit" isLoading={general.processing}>
                                    {general.processing ? t.saving : t.save}
                                </Button>
                            </form>
                        </section>
                    )}

                    {/* Payment gateway */}
                    {tab === 'payment' && (
                        <section className="rounded-lg border border-slate-200 bg-white p-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-900">{t.payment_title}</h2>
                                <span className={toggleClass(settings.payment_gateway.enabled)}>
                                    {settings.payment_gateway.enabled ? t.enabled : '—'}
                                </span>
                            </div>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    payment.put(route('admin.settings.payment'));
                                }}
                                className="space-y-4"
                            >
                                <label className="flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={payment.data.enabled}
                                        onChange={(e) => payment.setData('enabled', e.target.checked)}
                                        className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                    />
                                    {t.enabled}
                                </label>
                                <Input label={t.provider} value={payment.data.provider} onChange={(e) => payment.setData('provider', e.target.value)} error={payment.errors.provider} />
                                <Input label={t.api_key} value={payment.data.api_key} onChange={(e) => payment.setData('api_key', e.target.value)} error={payment.errors.api_key} />
                                <Input
                                    label={t.secret_key}
                                    type="password"
                                    placeholder={settings.payment_gateway.secret_key ? t.secret_set : ''}
                                    value={payment.data.secret_key}
                                    onChange={(e) => payment.setData('secret_key', e.target.value)}
                                    error={payment.errors.secret_key}
                                />
                                <Button type="submit" isLoading={payment.processing}>
                                    {payment.processing ? t.saving : t.save}
                                </Button>
                            </form>
                        </section>
                    )}

                    {/* WhatsApp */}
                    {tab === 'whatsapp' && (
                        <section className="rounded-lg border border-slate-200 bg-white p-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-900">{t.whatsapp_title}</h2>
                                <span className={toggleClass(settings.whatsapp_provider.enabled)}>
                                    {settings.whatsapp_provider.enabled ? t.enabled : '—'}
                                </span>
                            </div>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    whatsapp.put(route('admin.settings.whatsapp'));
                                }}
                                className="space-y-4"
                            >
                                <label className="flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={whatsapp.data.enabled}
                                        onChange={(e) => whatsapp.setData('enabled', e.target.checked)}
                                        className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                    />
                                    {t.enabled}
                                </label>
                                <Input label={t.provider} value={whatsapp.data.provider} onChange={(e) => whatsapp.setData('provider', e.target.value)} error={whatsapp.errors.provider} />
                                <Input
                                    label={t.token}
                                    type="password"
                                    placeholder={settings.whatsapp_provider.token ? t.secret_set : ''}
                                    value={whatsapp.data.token}
                                    onChange={(e) => whatsapp.setData('token', e.target.value)}
                                    error={whatsapp.errors.token}
                                />
                                <Input label={t.sender} value={whatsapp.data.sender} onChange={(e) => whatsapp.setData('sender', e.target.value)} error={whatsapp.errors.sender} />
                                <Button type="submit" isLoading={whatsapp.processing}>
                                    {whatsapp.processing ? t.saving : t.save}
                                </Button>
                            </form>
                        </section>
                    )}

                    {/* Platform fee */}
                    {tab === 'fee' && (
                        <section className="rounded-lg border border-slate-200 bg-white p-6 space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-900">{t.fee_title}</h2>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    fee.put(route('admin.settings.fee'));
                                }}
                                className="space-y-4"
                            >
                                <Select
                                    label={t.fee_type}
                                    value={fee.data.type}
                                    onChange={(e) => fee.setData('type', e.target.value)}
                                    error={fee.errors.type}
                                    options={[
                                        { value: 'fixed', label: t.fee_fixed },
                                        { value: 'percent', label: t.fee_percent },
                                    ]}
                                />
                                <Input
                                    label={t.fee_amount}
                                    type="number"
                                    min={0}
                                    step="any"
                                    value={fee.data.amount}
                                    onChange={(e) => fee.setData('amount', e.target.value)}
                                    error={fee.errors.amount}
                                />
                                <Button type="submit" isLoading={fee.processing}>
                                    {fee.processing ? t.saving : t.save}
                                </Button>
                            </form>
                        </section>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
