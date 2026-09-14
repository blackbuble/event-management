import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Input, Button } from '@/Components/Form';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Trash2 } from 'lucide-react';

interface PackageRow {
    id: number;
    slug: string;
    label: string;
    quota: number;
    amount: number;
    is_active: boolean;
}

function PackageItem({ pkg }: { pkg: PackageRow }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].catalog;

    const form = useForm({
        slug: pkg.slug,
        label: pkg.label,
        quota: String(pkg.quota),
        amount: String(pkg.amount),
        is_active: pkg.is_active,
    });

    return (
        <div className="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end border-t border-slate-100 py-3">
            <Input label={t.slug} value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} error={form.errors.slug} />
            <Input label={t.label} value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} error={form.errors.label} />
            <Input label={t.quota} type="number" value={form.data.quota} onChange={(e) => form.setData('quota', e.target.value)} error={form.errors.quota} />
            <Input label={t.amount} type="number" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
            <label className="flex items-center gap-2 text-sm text-slate-600 pb-2">
                <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                {t.active}
            </label>
            <div className="flex items-center gap-2 pb-1">
                <Button size="sm" isLoading={form.processing} onClick={() => form.put(route('admin.packages.update', pkg.id))}>
                    {t.save}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                        if (confirm('Delete?')) {
                            form.delete(route('admin.packages.destroy', pkg.id));
                        }
                    }}
                >
                    <Trash2 size={14} />
                </Button>
            </div>
        </div>
    );
}

export default function AdminPackages({ packages }: { packages: PackageRow[] }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].catalog;

    const create = useForm({ slug: '', label: '', quota: '', amount: '', is_active: true });

    return (
        <AdminLayout title={adminTexts[lang].nav.packages}>
            <Head title={adminTexts[lang].nav.packages} />
            <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        create.post(route('admin.packages.store'), { onSuccess: () => create.reset() });
                    }}
                    className="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end border-b border-slate-200 pb-4"
                >
                    <Input label={t.slug} value={create.data.slug} onChange={(e) => create.setData('slug', e.target.value)} error={create.errors.slug} required />
                    <Input label={t.label} value={create.data.label} onChange={(e) => create.setData('label', e.target.value)} error={create.errors.label} required />
                    <Input label={t.quota} type="number" value={create.data.quota} onChange={(e) => create.setData('quota', e.target.value)} error={create.errors.quota} required />
                    <Input label={t.amount} type="number" value={create.data.amount} onChange={(e) => create.setData('amount', e.target.value)} error={create.errors.amount} required />
                    <label className="flex items-center gap-2 text-sm text-slate-600 pb-2">
                        <input type="checkbox" checked={create.data.is_active} onChange={(e) => create.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                        {t.active}
                    </label>
                    <Button type="submit" isLoading={create.processing}>{t.add}</Button>
                </form>

                {packages.length === 0 ? (
                    <p className="pt-4 text-sm text-slate-500">{t.empty}</p>
                ) : (
                    <div className="pt-2">
                        {packages.map((pkg) => (
                            <PackageItem key={pkg.id} pkg={pkg} />
                        ))}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
