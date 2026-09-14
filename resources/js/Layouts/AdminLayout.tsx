import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { adminTexts, AdminLanguage, defaultAdminLanguage } from '@/config/admin-texts';
import { LayoutDashboard, BarChart3, Users, Settings, Tags, MapPin, Package, LogOut, Shield, ArrowLeft } from 'lucide-react';

interface AdminLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const { locale, auth, impersonating } = usePage().props as any;
    const lang = (locale as AdminLanguage) || defaultAdminLanguage;
    const t = adminTexts[lang];

    const nav = [
        { href: route('admin.dashboard'), label: t.nav.dashboard, icon: LayoutDashboard },
        { href: route('admin.analytics'), label: t.nav.analytics, icon: BarChart3 },
        { href: route('admin.users'), label: t.nav.users, icon: Users },
        { href: route('admin.settings'), label: t.nav.settings, icon: Settings },
        { href: route('admin.categories'), label: t.nav.categories, icon: Tags },
        { href: route('admin.cities'), label: t.nav.cities, icon: MapPin },
        { href: route('admin.packages'), label: t.nav.packages, icon: Package },
    ];

    return (
        <div className="min-h-screen bg-slate-50 lg:flex">
            {/* Sidebar */}
            <aside className="lg:w-64 shrink-0 bg-slate-900 text-slate-300 lg:min-h-screen">
                <div className="px-5 py-5 flex items-center gap-2 border-b border-white/10">
                    <Shield className="h-5 w-5 text-indigo-400" />
                    <span className="font-bold text-white tracking-tight">{t.brand}</span>
                </div>
                <nav className="p-3 space-y-1">
                    {nav.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-white/10 hover:text-white transition-colors"
                        >
                            <item.icon size={16} />
                            {item.label}
                        </Link>
                    ))}
                </nav>
                <div className="p-3 mt-2 border-t border-white/10 space-y-1">
                    <Link
                        href={route('dashboard')}
                        className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-white/10 hover:text-white transition-colors"
                    >
                        <ArrowLeft size={16} />
                        {t.nav.back_to_app}
                    </Link>
                    <Link
                        href={route('admin.logout')}
                        method="post"
                        as="button"
                        className="w-full flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-red-300 hover:bg-red-500/20 transition-colors"
                    >
                        <LogOut size={16} />
                        {t.nav.logout}
                    </Link>
                </div>
            </aside>

            {/* Content */}
            <main className="flex-1 min-w-0">
                {impersonating && (
                    <div className="bg-amber-100 border-b border-amber-200 px-4 py-2 text-xs font-medium text-amber-900 flex items-center justify-between">
                        <span>{t.impersonating}</span>
                        <Link href={route('impersonation.stop')} method="post" as="button" className="underline font-semibold">
                            {t.stop_impersonating}
                        </Link>
                    </div>
                )}
                <div className="px-5 sm:px-8 py-6">
                    {title && <h1 className="text-xl font-bold text-slate-900 mb-5">{title}</h1>}
                    {children}
                </div>
            </main>
        </div>
    );
}
