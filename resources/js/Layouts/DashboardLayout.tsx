import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Calendar, Users, Settings, LogOut, Bell, Search, Plus, Menu, X } from 'lucide-react';

interface DashboardLayoutProps {
    children: React.ReactNode;
}

export default function DashboardLayout({ children }: DashboardLayoutProps) {
    const { auth } = usePage().props as any;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const navigation = [
        { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, current: route().current('dashboard') },
        { name: 'My Events', href: '#', icon: Calendar, current: false },
        { name: 'Team', href: '#', icon: Users, current: false },
        { name: 'Settings', href: '#', icon: Settings, current: false },
    ];

    return (
        <div className="min-h-screen bg-slate-50 flex font-sans">
            {/* Mobile Sidebar Overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm md:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-100 transition-transform duration-300 ease-in-out md:static md:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="h-full flex flex-col">
                    {/* Brand */}
                    <div className="h-16 flex items-center px-6 border-b border-slate-50">
                        <div className="flex items-center space-x-3">
                            <div className="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center shadow-lg shadow-indigo-500/20">
                                <Calendar className="text-white" size={18} />
                            </div>
                            <span className="text-lg font-bold text-slate-900 tracking-tight">EventHub</span>
                        </div>
                        <button
                            onClick={() => setSidebarOpen(false)}
                            className="ml-auto md:hidden text-slate-400 hover:text-slate-600"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 ${item.current
                                    ? 'bg-indigo-50 text-indigo-600'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                    }`}
                            >
                                <item.icon size={18} strokeWidth={2} className={item.current ? 'text-indigo-600' : 'text-slate-400'} />
                                <span>{item.name}</span>
                            </Link>
                        ))}
                    </nav>

                    {/* User Profile / Logout */}
                    <div className="p-4 border-t border-slate-50">
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="w-full flex items-center space-x-3 px-3 py-2.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors group"
                        >
                            <LogOut size={18} strokeWidth={2} className="group-hover:text-red-600" />
                            <span className="text-sm font-medium">Sign Out</span>
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Main Content Wrapper */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
                {/* Top Header */}
                <header className="h-16 bg-white border-b border-slate-100 flex items-center justify-between px-4 md:px-8">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="md:hidden p-2 text-slate-500 hover:bg-slate-50 rounded-lg"
                    >
                        <Menu size={20} />
                    </button>

                    <div className="flex-1 max-w-xl mx-4 hidden md:block">
                        <div className="relative group">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition-colors" size={18} />
                            <input
                                type="text"
                                placeholder="Search events..."
                                className="w-full pl-10 pr-4 h-10 bg-slate-50 border border-transparent rounded-lg text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-indigo-200 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none"
                            />
                        </div>
                    </div>

                    <div className="flex items-center space-x-4">
                        <button className="relative p-2 text-slate-400 hover:text-slate-600 transition-colors rounded-lg hover:bg-slate-50">
                            <Bell size={20} />
                            <span className="absolute top-1.5 right-1.5 h-2 w-2 bg-red-500 rounded-full ring-2 ring-white"></span>
                        </button>

                        <div className="relative group">
                            <button className="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-xs ring-2 ring-white shadow-sm hover:ring-indigo-100 transition-all">
                                {auth?.user?.name?.[0] || 'U'}
                            </button>

                            {/* Dropdown Menu */}
                            <div className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-100 py-1 invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200 transform origin-top-right z-50">
                                <div className="px-4 py-3 border-b border-slate-50">
                                    <p className="text-xs font-medium text-slate-500">Signed in as</p>
                                    <p className="text-sm font-bold text-slate-900 truncate">{auth?.user?.name}</p>
                                </div>
                                <Link
                                    href={route('profile.edit')}
                                    className="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-indigo-600 font-medium transition-colors"
                                >
                                    Your Profile
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="w-full text-left block px-4 py-2 text-sm text-red-500 hover:bg-red-50 font-medium transition-colors rounded-b-xl"
                                >
                                    Sign Out
                                </Link>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Main Content Area */}
                <main className="flex-1 overflow-y-auto p-4 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
