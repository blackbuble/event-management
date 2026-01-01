import React from 'react';
import { Head, usePage, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { User, Mail, Phone, Save, Camera, CheckCircle2 } from 'lucide-react';
import { Input, Button } from '@/Components/Form'; // Assuming we have these, or I'll use standard HTML

export default function Edit({ mustVerifyEmail, status }: { mustVerifyEmail?: boolean, status?: string }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email || '',
        phone: user.phone || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <DashboardLayout>
            <Head title="Profile" />

            <div className="max-w-4xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Profile Settings</h1>
                        <p className="text-slate-500 text-sm mt-1">Manage your account information and preferences.</p>
                    </div>
                </div>

                {/* Flash Messages */}
                {usePage().props.flash?.warning && (
                    <div className="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-amber-700">
                                    {usePage().props.flash.warning}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {usePage().props.flash?.message && (
                    <div className="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <CheckCircle2 className="h-5 w-5 text-emerald-400" />
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-emerald-700">
                                    {usePage().props.flash.message}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Profile Card */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {/* Banner/Header of Card */}
                    <div className="h-32 bg-gradient-to-r from-indigo-500 to-purple-500 relative">
                        <div className="absolute -bottom-12 left-8">
                            <div className="relative group">
                                <div className="h-24 w-24 rounded-full bg-white p-1 shadow-lg">
                                    <div className="h-full w-full rounded-full bg-slate-100 flex items-center justify-center text-2xl font-bold text-slate-600">
                                        {user?.name?.[0] || 'U'}
                                    </div>
                                </div>
                                <button className="absolute bottom-0 right-0 p-2 bg-indigo-600 rounded-full text-white shadow-md hover:bg-indigo-700 transition-colors">
                                    <Camera size={14} />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="pt-16 pb-8 px-8">
                        <form onSubmit={submit} className="space-y-6 max-w-xl">
                            <div className="grid gap-6">
                                <div className="space-y-2">
                                    <label htmlFor="name" className="text-sm font-semibold text-slate-700">Full Name</label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                                        <input
                                            id="name"
                                            type="text"
                                            autoFocus={!!usePage().props.flash?.warning || data.name.startsWith("User ")}
                                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm font-medium text-slate-900"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                        />
                                    </div>
                                    {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="email" className="text-sm font-semibold text-slate-700">Email Address</label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                                        <input
                                            id="email"
                                            type="email"
                                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm font-medium text-slate-900"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                        />
                                    </div>
                                    {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="phone" className="text-sm font-semibold text-slate-700">Phone Number</label>
                                    <div className="relative">
                                        <Phone className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                                        <input
                                            id="phone"
                                            type="text"
                                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm font-medium text-slate-900"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                        />
                                    </div>
                                    {errors.phone && <p className="text-red-500 text-xs mt-1">{errors.phone}</p>}
                                </div>
                            </div>

                            <div className="pt-4 flex items-center justify-end border-t border-slate-50 mt-6">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg shadow-indigo-500/20 active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed"
                                >
                                    <Save size={18} />
                                    <span>Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
