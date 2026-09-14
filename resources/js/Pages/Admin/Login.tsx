import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Input, Button } from '@/Components/Form';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Shield } from 'lucide-react';

export default function AdminLogin() {
    const { locale, errors: pageErrors } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].login;

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.login.store'));
    };

    return (
        <>
            <Head title={t.title} />
            <div className="min-h-screen bg-slate-900 flex items-center justify-center px-4">
                <div className="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 space-y-6">
                    <div className="flex flex-col items-center gap-2 text-center">
                        <div className="p-3 rounded-xl bg-indigo-50 text-indigo-600">
                            <Shield size={24} />
                        </div>
                        <h1 className="text-xl font-bold text-slate-900">{t.title}</h1>
                        <p className="text-sm text-slate-500">{t.subtitle}</p>
                    </div>

                    {(errors.email || pageErrors?.email) && (
                        <p className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs font-medium text-red-700">
                            {errors.email || pageErrors?.email}
                        </p>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <Input
                            label={t.email}
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                            required
                        />
                        <Input
                            label={t.password}
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            error={errors.password}
                            required
                        />
                        <Button type="submit" isLoading={processing} className="w-full">
                            {processing ? t.processing : t.submit}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
