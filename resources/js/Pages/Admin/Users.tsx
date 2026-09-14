import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { UserCog, Ban, CircleCheck, Trash2, Search, ChevronLeft, ChevronRight } from 'lucide-react';

interface AdminUser {
    id: number;
    uuid: string;
    name: string;
    email: string | null;
    roles: string[];
    suspended: boolean;
    created_at: string | null;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    users: AdminUser[];
    pagination: Pagination;
    filters: { search: string; role: string; status: string };
}

export default function AdminUsers({ users, pagination, filters }: Props) {
    const { locale, flash } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang].users;

    const [search, setSearch] = useState(filters?.search ?? '');
    const [target, setTarget] = useState<AdminUser | null>(null);
    const [password, setPassword] = useState('');
    const [passwordError, setPasswordError] = useState<string | null>(null);

    const apply = (params: Record<string, string | undefined>) => {
        router.get(
            route('admin.users'),
            { search: search || undefined, role: filters.role || undefined, status: filters.status || undefined, ...params },
            { preserveState: true, replace: true },
        );
    };

    // Debounced search.
    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search === (filters?.search ?? '')) return;
            apply({ search: search || undefined });
        }, 400);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const pageLink = (page: number) =>
        route('admin.users', {
            page,
            search: search || undefined,
            role: filters.role || undefined,
            status: filters.status || undefined,
        });

    const submitImpersonate = () => {
        if (!target) return;
        router.post(
            route('admin.impersonate', target.uuid),
            { password },
            {
                onError: (errors) => setPasswordError(errors.password ?? 'Password admin salah.'),
                onSuccess: () => {
                    setTarget(null);
                    setPassword('');
                },
            },
        );
    };

    return (
        <AdminLayout title={t.title}>
            <Head title={t.title} />

            {flash?.message && (
                <div className="mb-4 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.message}</div>
            )}
            {flash?.error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>
            )}

            {/* Toolbar */}
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <div className="relative min-w-[240px] flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t.search_placeholder}
                        className="w-full border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                    />
                </div>
                <select
                    value={filters.role}
                    onChange={(e) => apply({ role: e.target.value || undefined })}
                    className="border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-slate-400"
                >
                    <option value="">{t.filter_role}</option>
                    <option value="organizer">Organizer</option>
                    <option value="attendee">Attendee</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
                <select
                    value={filters.status}
                    onChange={(e) => apply({ status: e.target.value || undefined })}
                    className="border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-slate-400"
                >
                    <option value="">{t.filter_status}</option>
                    <option value="active">{t.status_active}</option>
                    <option value="suspended">{t.status_suspended}</option>
                </select>
            </div>

            {/* Table */}
            <div className="border border-slate-200 bg-white">
                {users.length === 0 ? (
                    <p className="px-6 py-10 text-center text-sm text-slate-400">{t.empty}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-slate-100 text-left text-[11px] uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th className="px-5 py-3 font-medium">{t.name}</th>
                                    <th className="px-5 py-3 font-medium">{t.email}</th>
                                    <th className="px-5 py-3 font-medium">{t.roles}</th>
                                    <th className="px-5 py-3 font-medium">{t.actions}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {users.map((user) => {
                                    const isAdmin = user.roles.includes('admin');
                                    const canImpersonate = user.roles.includes('organizer') && !isAdmin && !user.suspended;
                                    return (
                                        <tr key={user.id} className={user.suspended ? 'bg-slate-50/70' : 'hover:bg-slate-50/60'}>
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-slate-900">{user.name}</span>
                                                    {user.suspended && (
                                                        <span className="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-600">
                                                            {t.status_suspended}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 text-slate-500">{user.email ?? '—'}</td>
                                            <td className="px-5 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.map((role) => (
                                                        <span key={role} className="bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                            {role}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    {canImpersonate && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setTarget(user);
                                                                setPassword('');
                                                                setPasswordError(null);
                                                            }}
                                                            className="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700"
                                                        >
                                                            <UserCog size={14} />
                                                            {t.impersonate}
                                                        </button>
                                                    )}
                                                    {!isAdmin &&
                                                        (user.suspended ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => router.post(route('admin.users.activate', user.uuid), {}, { preserveScroll: true })}
                                                                className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700"
                                                            >
                                                                <CircleCheck size={14} />
                                                                {t.activate}
                                                            </button>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => router.post(route('admin.users.suspend', user.uuid), {}, { preserveScroll: true })}
                                                                className="inline-flex items-center gap-1 text-xs font-medium text-amber-600 hover:text-amber-700"
                                                            >
                                                                <Ban size={14} />
                                                                {t.suspend}
                                                            </button>
                                                        ))}
                                                    {!isAdmin && (
                                                        <button
                                                            onClick={() => {
                                                                if (confirm(t.confirm_delete)) {
                                                                    router.delete(route('admin.users.destroy', user.uuid), { preserveScroll: true });
                                                                }
                                                            }}
                                                            className="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600"
                                                        >
                                                            <Trash2 size={14} />
                                                            {t.delete}
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Pagination */}
            <div className="mt-4 flex items-center justify-between">
                <p className="text-xs text-slate-500">
                    {t.page_info.replace('{page}', String(pagination.current_page)).replace('{last}', String(pagination.last_page)).replace('{total}', String(pagination.total))}
                </p>
                <div className="flex items-center gap-2">
                    <Link
                        href={pageLink(Math.max(1, pagination.current_page - 1))}
                        preserveScroll
                        className={`inline-flex items-center gap-1 border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 ${
                            pagination.current_page <= 1 ? 'pointer-events-none opacity-40' : 'hover:border-slate-300'
                        }`}
                    >
                        <ChevronLeft size={14} />
                        {t.prev}
                    </Link>
                    <Link
                        href={pageLink(Math.min(pagination.last_page, pagination.current_page + 1))}
                        preserveScroll
                        className={`inline-flex items-center gap-1 border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 ${
                            pagination.current_page >= pagination.last_page ? 'pointer-events-none opacity-40' : 'hover:border-slate-300'
                        }`}
                    >
                        {t.next}
                        <ChevronRight size={14} />
                    </Link>
                </div>
            </div>

            {target && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                        <h3 className="text-base font-semibold text-slate-900">{t.impersonate_title}</h3>
                        <p className="mt-1 text-sm text-slate-500">{t.impersonate_hint.replace('{name}', target.name)}</p>

                        <input
                            type="password"
                            autoFocus
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && submitImpersonate()}
                            placeholder={t.password_label}
                            className="mt-4 w-full border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500"
                        />
                        {passwordError && <p className="mt-2 text-xs font-medium text-red-500">{passwordError}</p>}

                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setTarget(null)}
                                className="border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
                            >
                                {t.cancel}
                            </button>
                            <button
                                type="button"
                                onClick={submitImpersonate}
                                className="bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                            >
                                {t.confirm}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
