import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Input, Button } from '@/Components/Form';
import { authTexts } from '@/config/auth-texts';
import { Mail, ArrowRight, Sparkles, Key, Lock, MessageSquare } from 'lucide-react';

export default function Login() {
    const { flash } = usePage().props as any;

    const { data, setData, post, processing, errors } = useForm({
        identity: '',
    });

    const isEmail = data.identity.includes('@');
    const isPhone = /^[0-9+]+$/.test(data.identity) && data.identity.length > 5;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('auth.unified'));
    };

    return (
        <AuthLayout
            title="Masuk atau Daftar"
            subtitle="Gunakan akun Google, Email, atau WhatsApp Anda."
        >
            <Head title="Login" />

            <div className="space-y-6">
                {/* Social Login Section */}
                <div className="grid grid-cols-2 gap-3">
                    <a href={route('social.redirect', 'google')} className="flex items-center justify-center h-12 border border-slate-200 dark:border-slate-800 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all group">
                        <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        <span className="text-sm font-bold text-slate-700 dark:text-slate-300">Google</span>
                    </a>
                    <a href={route('social.redirect', 'facebook')} className="flex items-center justify-center h-12 bg-[#1877F2] text-white rounded-xl hover:bg-[#166fe5] transition-all">
                        <svg className="w-5 h-5 mr-2 fill-current" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                        <span className="text-sm font-bold">Facebook</span>
                    </a>
                </div>

                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <span className="w-full border-t border-slate-200 dark:border-slate-800" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                        <span className="bg-slate-50 dark:bg-slate-950 px-4 text-slate-400 font-bold tracking-[0.2em]">Atau Masukkan Identitas</span>
                    </div>
                </div>

                {flash?.message && (
                    <div className="p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-2xl text-indigo-700 dark:text-indigo-400 text-sm font-bold flex items-center animate-in fade-in zoom-in duration-300">
                        <Sparkles size={18} className="mr-3 shrink-0" />
                        {flash.message}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    <div className="relative group">
                        <Input
                            label="Email atau No. WhatsApp"
                            type="text"
                            value={data.identity}
                            onChange={e => setData('identity', e.target.value)}
                            error={errors.identity}
                            placeholder="contoh@email.com atau 0812..."
                            className="h-16 pl-14 text-lg font-medium"
                            required
                        />
                        <div className="absolute left-5 top-[46px] text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                            {isEmail ? <Mail size={24} /> : (isPhone ? <MessageSquare size={24} className="text-green-500" /> : <Lock size={24} />)}
                        </div>
                    </div>

                    <Button
                        type="submit"
                        isLoading={processing}
                        className="w-full h-16 text-base font-black uppercase tracking-[0.2em]"
                    >
                        <span className="flex items-center justify-center">
                            {processing ? 'Memproses...' : (isEmail ? 'Kirim Magic Link' : 'Kirim Kode')}
                            {!processing && <ArrowRight size={20} className="ml-2" />}
                        </span>
                    </Button>

                    <div className="flex items-center justify-center p-5 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 border-dashed">
                        <p className="text-[10px] font-bold text-slate-400 leading-relaxed uppercase tracking-widest text-center">
                            Jika belum punya akun, kami akan otomatis membuatkan akun baru untuk Anda.
                        </p>
                    </div>
                </form>

                <div className="text-center pt-6 border-t border-slate-100 dark:border-slate-800/50">
                    <p className="text-xs text-slate-400 font-bold tracking-widest uppercase italic">
                        Cepat • Aman • Tanpa Password
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}
