import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Input, Button } from '@/Components/Form';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { Trash2 } from 'lucide-react';

interface CityRow {
    id: number;
    name: string;
    is_active: boolean;
}

function CityItem({ city }: { city: CityRow }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].catalog;

    const form = useForm({ name: city.name, is_active: city.is_active });

    return (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end border-t border-slate-100 py-3">
            <Input label={t.name} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
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
                <Button size="sm" isLoading={form.processing} onClick={() => form.put(route('admin.cities.update', city.id))}>
                    {t.save}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                        if (confirm('Delete?')) {
                            form.delete(route('admin.cities.destroy', city.id));
                        }
                    }}
                >
                    <Trash2 size={14} />
                </Button>
            </div>
        </div>
    );
}

export default function AdminCities({ cities }: { cities: CityRow[] }) {
    const { locale } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].catalog;

    const create = useForm({ name: '', is_active: true });

    return (
        <AdminLayout title={adminTexts[lang].nav.cities}>
            <Head title={adminTexts[lang].nav.cities} />
            <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        create.post(route('admin.cities.store'), { onSuccess: () => create.reset() });
                    }}
                    className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end border-b border-slate-200 pb-4"
                >
                    <Input label={t.name} value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} error={create.errors.name} required />
                    <label className="flex items-center gap-2 text-sm text-slate-600 pb-2">
                        <input type="checkbox" checked={create.data.is_active} onChange={(e) => create.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                        {t.active}
                    </label>
                    <Button type="submit" isLoading={create.processing}>{t.add}</Button>
                </form>

                {cities.length === 0 ? (
                    <p className="pt-4 text-sm text-slate-500">{t.empty}</p>
                ) : (
                    <div className="pt-2">
                        {cities.map((city) => (
                            <CityItem key={city.id} city={city} />
                        ))}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
