import React, { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Input, Button } from '@/Components/Form';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Mail, MessageCircle, RefreshCcw, ShieldCheck } from 'lucide-react';

interface Props {
    email: string | null;
    has_phone: boolean;
}

export default function AdminVerifyOtp({ email, has_phone }: Props) {
    const { locale, flash } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].otp;

    const [timer, setTimer] = useState(0);

    const verify = useForm({ otp: '' });
    const resend = useForm({ channel: 'email' });

    useEffect(() => {
        if (timer <= 0) return;
        const interval = setInterval(() => setTimer((prev) => prev - 1), 1000);
        return () => clearInterval(interval);
    }, [timer]);

    const submitVerify = (e: React.FormEvent) => {
        e.preventDefault();
        verify.post(route('admin.otp.verify'));
    };

    const requestCode = (channel: 'email' | 'whatsapp') => {
        if (timer > 0 || resend.processing) return;
        resend.setData('channel', channel);
        resend.post(route('admin.otp.resend'), {
            preserveScroll: true,
            onSuccess: () => setTimer(60),
        });
    };

    return (
        <>
            <Head title={t.title} />
            <div className="min-h-screen bg-slate-900 flex items-center justify-center px-4">
                <div className="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 space-y-6">
                    <div className="flex flex-col items-center gap-2 text-center">
                        <div className="p-3 rounded-xl bg-emerald-50 text-emerald-600">
                            <ShieldCheck size={24} />
                        </div>
                        <h1 className="text-xl font-bold text-slate-900">{t.title}</h1>
                        <p className="text-sm text-slate-500">{t.subtitle}</p>
                        {email && <p className="text-xs font-medium text-slate-400">{email}</p>}
                    </div>

                    {flash?.message && (
                        <p className="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs font-medium text-emerald-700">
                            {flash.message}
                        </p>
                    )}
                    {flash?.error && (
                        <p className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs font-medium text-red-700">
                            {flash.error}
                        </p>
                    )}

                    <form onSubmit={submitVerify} className="space-y-4">
                        <Input
                            label="OTP"
                            inputMode="numeric"
                            maxLength={6}
                            value={verify.data.otp}
                            onChange={(e) => verify.setData('otp', e.target.value.replace(/\D/g, ''))}
                            error={verify.errors.otp}
                            required
                        />
                        <Button type="submit" isLoading={verify.processing} className="w-full">
                            {verify.processing ? t.processing : t.submit}
                        </Button>
                    </form>

                    {/* Resend / alternative delivery */}
                    <div className="border-t border-slate-100 pt-4 space-y-3">
                        <p className="text-center text-xs font-medium text-slate-500">{t.resend_title}</p>
                        <div className="flex items-center justify-center gap-2">
                            <button
                                type="button"
                                onClick={() => requestCode('email')}
                                disabled={timer > 0 || resend.processing}
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:border-indigo-300 hover:text-indigo-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >
                                <Mail size={14} />
                                {resend.processing && resend.data.channel === 'email' ? t.sending : t.resend_email}
                            </button>
                            {has_phone && (
                                <button
                                    type="button"
                                    onClick={() => requestCode('whatsapp')}
                                    disabled={timer > 0 || resend.processing}
                                    className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:border-emerald-300 hover:text-emerald-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                >
                                    <MessageCircle size={14} />
                                    {resend.processing && resend.data.channel === 'whatsapp' ? t.sending : t.resend_whatsapp}
                                </button>
                            )}
                        </div>
                        {timer > 0 && (
                            <p className="flex items-center justify-center gap-1 text-[11px] text-slate-400">
                                <RefreshCcw size={11} />
                                {t.wait.replace('{timer}', String(timer))}
                            </p>
                        )}
                    </div>

                    <div className="text-center border-t border-slate-100 pt-4">
                        <Link href={route('admin.login')} className="text-[11px] font-medium text-slate-400 hover:text-indigo-600">
                            {t.change_account}
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
