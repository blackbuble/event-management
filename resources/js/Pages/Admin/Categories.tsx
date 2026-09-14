import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Input, Button } from '@/Components/Form';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Trash2 } from 'lucide-react';

interface CategoryRow {
    id: number;
    slug: string;
    name: string;
    name_en: string | null;
    is_active: boolean;
}

function CategoryItem({ category }: { category: CategoryRow }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].catalog;

    const form = useForm({
        slug: category.slug,
        name: category.name,
        name_en: category.name_en ?? '',
        is_active: category.is_active,
    });

    return (
        <div className="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end border-t border-slate-100 py-3">
            <Input label={t.slug} value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} error={form.errors.slug} />
            <Input label={t.name} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
            <Input label={t.name_en} value={form.data.name_en} onChange={(e) => form.setData('name_en', e.target.value)} error={form.errors.name_en} />
            <label className="flex items-center gap-2 text-sm text-slate-600 pb-2">
                <input
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(e) => form.setData('is_active', e.target.checked)}
                    className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                />
                {t.active}
            </label>
            <div className="flex items-center gap-2 pb-1">
                <Button size="sm" isLoading={form.processing} onClick={() => form.put(route('admin.categories.update', category.id))}>
                    {t.save}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                        if (confirm('Delete?')) {
                            form.delete(route('admin.categories.destroy', category.id));
                        }
                    }}
                >
                    <Trash2 size={14} />
                </Button>
            </div>
        </div>
    );
}

export default function AdminCategories({ categories }: { categories: CategoryRow[] }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].catalog;

    const create = useForm({ slug: '', name: '', name_en: '', is_active: true });

    return (
        <AdminLayout title={adminTexts[lang].nav.categories}>
            <Head title={adminTexts[lang].nav.categories} />
            <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        create.post(route('admin.categories.store'), { onSuccess: () => create.reset() });
                    }}
                    className="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end border-b border-slate-200 pb-4"
                >
                    <Input label={t.slug} value={create.data.slug} onChange={(e) => create.setData('slug', e.target.value)} error={create.errors.slug} required />
                    <Input label={t.name} value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} error={create.errors.name} required />
                    <Input label={t.name_en} value={create.data.name_en} onChange={(e) => create.setData('name_en', e.target.value)} error={create.errors.name_en} />
                    <label className="flex items-center gap-2 text-sm text-slate-600 pb-2">
                        <input type="checkbox" checked={create.data.is_active} onChange={(e) => create.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                        {t.active}
                    </label>
                    <Button type="submit" isLoading={create.processing}>{t.add}</Button>
                </form>

                {categories.length === 0 ? (
                    <p className="pt-4 text-sm text-slate-500">{t.empty}</p>
                ) : (
                    <div className="pt-2">
                        {categories.map((category) => (
                            <CategoryItem key={category.id} category={category} />
                        ))}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
