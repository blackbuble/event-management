import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { LayoutDashboard, Calendar, Users, Settings, LogOut, Bell, Search, Plus } from 'lucide-react';

export default function Dashboard() {
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex">
            <Head title="Dashboard" />

            {/* Sidebar */}
            <aside className="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col">
                <div className="p-6">
                    <div className="flex items-center space-x-3">
                        <div className="h-10 w-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20">
                            <Calendar className="text-white" size={20} />
                        </div>
                        <span className="text-lg font-black text-slate-950 dark:text-white tracking-tight italic">EVENT.HUB</span>
                    </div>
                </div>

                <nav className="flex-1 px-4 space-y-2">
                    <Link href="/dashboard" className="flex items-center space-x-3 p-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 rounded-xl font-bold transition-all">
                        <LayoutDashboard size={20} />
                        <span>Dashboard</span>
                    </Link>
                    <Link href="#" className="flex items-center space-x-3 p-3 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl font-bold transition-all">
                        <Calendar size={20} />
                        <span>My Events</span>
                    </Link>
                    <Link href="#" className="flex items-center space-x-3 p-3 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl font-bold transition-all">
                        <Users size={20} />
                        <span>Team</span>
                    </Link>
                    <Link href="#" className="flex items-center space-x-3 p-3 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl font-bold transition-all">
                        <Settings size={20} />
                        <span>Settings</span>
                    </Link>
                </nav>

                <div className="p-4 border-t border-slate-200 dark:border-slate-800">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="w-full flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 rounded-xl font-bold transition-all"
                    >
                        <LogOut size={20} />
                        <span>Sign Out</span>
                    </Link>
                </div>
            </aside>

            {/* Main Content */}
            <main className="flex-1 flex flex-col">
                {/* Top Header */}
                <header className="h-20 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-8 sticky top-0 z-10">
                    <div className="relative w-96">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                        <input
                            type="text"
                            placeholder="Search events, tickets, or venues..."
                            className="w-full pl-12 pr-4 h-11 bg-slate-100 dark:bg-slate-800 border-none rounded-2xl text-sm focus:ring-2 focus:ring-indigo-500 transition-all font-medium"
                        />
                    </div>

                    <div className="flex items-center space-x-6">
                        <button className="relative text-slate-500 hover:text-indigo-600 transition-colors">
                            <Bell size={22} />
                            <span className="absolute -top-1 -right-1 h-2 w-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-900"></span>
                        </button>
                        <div className="h-10 w-10 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white font-black text-sm shadow-md">
                            JD
                        </div>
                    </div>
                </header>

                <div className="p-8 space-y-8">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-black text-slate-950 dark:text-white tracking-tight uppercase italic">Dashboard</h1>
                            <p className="text-slate-500 font-bold uppercase tracking-widest text-xs mt-1">Welcome back to your event hub!</p>
                        </div>
                        <button className="flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 h-12 rounded-2xl font-black transition-all shadow-lg shadow-indigo-500/20 uppercase tracking-widest text-xs">
                            <Plus size={18} />
                            <span>Create New Event</span>
                        </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {[
                            { label: 'Active Events', value: '12', color: 'bg-indigo-500' },
                            { label: 'Total Tickets Sold', value: '1,280', color: 'bg-emerald-500' },
                            { label: 'Revenue', value: '$24,500', color: 'bg-amber-500' },
                        ].map((stat, i) => (
                            <div key={i} className="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden relative group transition-all hover:shadow-xl hover:shadow-slate-200/50">
                                <div className={`absolute top-0 right-0 h-32 w-32 ${stat.color} opacity-5 -mr-16 -mt-16 rounded-full group-hover:scale-150 transition-transform duration-700`}></div>
                                <p className="text-xs font-black text-slate-400 uppercase tracking-widest italic">{stat.label}</p>
                                <h3 className="text-4xl font-black text-slate-950 dark:text-white mt-2 tracking-tighter">{stat.value}</h3>
                            </div>
                        ))}
                    </div>

                    <div className="bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-200 dark:border-slate-800 shadow-sm p-8">
                        <div className="flex items-center justify-between mb-8">
                            <h2 className="text-xl font-black text-slate-950 dark:text-white tracking-tight italic uppercase">Recent Activity</h2>
                            <button className="text-xs font-black text-indigo-600 uppercase tracking-widest hover:underline decoration-2">View All</button>
                        </div>
                        <div className="space-y-6">
                            {[1, 2, 3].map((_, i) => (
                                <div key={i} className="flex items-center justify-between p-4 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border border-transparent hover:border-slate-100 dark:hover:border-slate-700">
                                    <div className="flex items-center space-x-4">
                                        <div className="h-12 w-12 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                            <Calendar size={20} />
                                        </div>
                                        <div>
                                            <h4 className="font-bold text-slate-900 dark:text-white">Music Festival 2025</h4>
                                            <p className="text-xs font-bold text-slate-500">New ticket purchased by John Doe</p>
                                        </div>
                                    </div>
                                    <span className="text-xs font-black text-slate-400 uppercase tracking-widest italic">2h Ago</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
