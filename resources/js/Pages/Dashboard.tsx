import React from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Plus, Calendar, Tag, DollarSign, ArrowUpRight } from 'lucide-react';

export default function Dashboard() {
    return (
        <DashboardLayout>
            <Head title="Dashboard" />

            <div className="space-y-8">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Dashboard Overview</h1>
                        <p className="text-slate-500 text-sm mt-1">Welcome back, here's what's happening today.</p>
                    </div>
                    <button className="flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg font-medium text-sm transition-all shadow-sm shadow-indigo-500/20 active:scale-95">
                        <Plus size={18} />
                        <span>Create Event</span>
                    </button>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {[
                        { label: 'Active Events', value: '12', icon: Calendar, color: 'text-indigo-600', bg: 'bg-indigo-50' },
                        { label: 'Total Tickets Sold', value: '1,280', icon: Tag, color: 'text-emerald-600', bg: 'bg-emerald-50' },
                        { label: 'Total Revenue', value: '$24,500', icon: DollarSign, color: 'text-amber-600', bg: 'bg-amber-50' },
                    ].map((stat, i) => (
                        <div key={i} className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between">
                                <div className={`p-3 rounded-lg ${stat.bg}`}>
                                    <stat.icon className={stat.color} size={24} />
                                </div>
                                <span className="flex items-center text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                                    <ArrowUpRight size={12} className="mr-1" />
                                    +12%
                                </span>
                            </div>
                            <div className="mt-4">
                                <h3 className="text-2xl font-bold text-slate-900 tracking-tight">{stat.value}</h3>
                                <p className="text-sm font-medium text-slate-500 mt-1">{stat.label}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Recent Activity */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-6 border-b border-slate-100 flex items-center justify-between">
                        <h2 className="text-lg font-bold text-slate-900">Recent Activity</h2>
                        <button className="text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:underline">View All</button>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {[1, 2, 3, 4].map((_, i) => (
                            <div key={i} className="p-4 hover:bg-slate-50 transition-colors flex items-center justify-between group">
                                <div className="flex items-center space-x-4">
                                    <div className="h-10 w-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-white group-hover:shadow-sm transition-all">
                                        <Calendar size={20} />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-semibold text-slate-900">Music Festival 2025</h4>
                                        <p className="text-xs text-slate-500 mt-0.5">New ticket purchased by John Doe</p>
                                    </div>
                                </div>
                                <span className="text-xs font-medium text-slate-400">2h ago</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
