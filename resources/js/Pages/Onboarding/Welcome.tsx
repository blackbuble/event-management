import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Sparkles, ArrowRight, User, Phone, CheckCircle2, Mail } from 'lucide-react';

export default function Welcome() {
    const user = usePage().props.auth.user;
    const [step, setStep] = useState(1);

    const { data, setData, patch, processing, errors } = useForm({
        name: user?.name?.startsWith('User ') ? '' : (user?.name || ''),
        phone: user?.phone || '',
        email: user?.email || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('onboarding.update'));
    };

    const nextStep = () => {
        setStep(step + 1);
    };

    return (
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
            <Head title="Welcome" />

            <div className="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-slate-100 relative">
                {/* Decorative Background Elements */}
                <div className="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-0 translate-y-1/2 -translate-x-1/2 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl" />

                <div className="relative p-8 md:p-12 min-h-[500px] flex flex-col">
                    <AnimatePresence mode="wait">
                        {step === 1 && (
                            <motion.div
                                key="step1"
                                initial={{ opacity: 0, x: 20 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: -20 }}
                                className="flex-1 flex flex-col items-center justify-center text-center space-y-8"
                            >
                                <motion.div
                                    initial={{ scale: 0 }}
                                    animate={{ scale: 1 }}
                                    transition={{ type: "spring", stiffness: 260, damping: 20, delay: 0.1 }}
                                    className="h-24 w-24 bg-indigo-600 rounded-3xl flex items-center justify-center shadow-indigo-500/30 shadow-xl"
                                >
                                    <Sparkles className="text-white w-12 h-12" />
                                </motion.div>

                                <div className="space-y-4">
                                    <h1 className="text-3xl font-black text-slate-900 tracking-tight">
                                        Welcome to EventHub!
                                    </h1>
                                    <p className="text-slate-500 text-lg leading-relaxed">
                                        We're thrilled to have you here. Let's get your profile set up so you can start discovering amazing events.
                                    </p>
                                </div>

                                <button
                                    onClick={nextStep}
                                    className="group flex items-center space-x-2 bg-slate-900 text-white px-8 py-4 rounded-2xl font-bold text-lg hover:bg-slate-800 transition-all shadow-lg hover:shadow-xl active:scale-95 mt-8 w-full justify-center"
                                >
                                    <span>Let's Start</span>
                                    <ArrowRight className="group-hover:translate-x-1 transition-transform" />
                                </button>
                            </motion.div>
                        )}

                        {step === 2 && (
                            <motion.div
                                key="step2"
                                initial={{ opacity: 0, x: 20 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: -20 }}
                                className="flex-1 flex flex-col h-full"
                            >
                                <div className="mb-8">
                                    <h2 className="text-2xl font-bold text-slate-900">First things first</h2>
                                    <p className="text-slate-500">How should we address you?</p>
                                </div>

                                <form onSubmit={submit} className="flex-1 flex flex-col space-y-6">
                                    <div className="space-y-2">
                                        <label htmlFor="name" className="text-sm font-bold text-slate-700 uppercase tracking-wider">Full Name</label>
                                        <div className="relative group">
                                            <User className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition-colors" size={20} />
                                            <input
                                                id="name"
                                                type="text"
                                                autoFocus
                                                required
                                                placeholder="e.g. John Doe"
                                                className="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-medium text-slate-900 placeholder:text-slate-400"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                            />
                                        </div>
                                        {errors.name && <p className="text-red-500 text-sm font-medium">{errors.name}</p>}
                                    </div>

                                    {!user.phone && (
                                        <div className="space-y-2">
                                            <label htmlFor="phone" className="text-sm font-bold text-slate-700 uppercase tracking-wider">WhatsApp Number</label>
                                            <div className="relative group">
                                                <Phone className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition-colors" size={20} />
                                                <input
                                                    id="phone"
                                                    type="tel"
                                                    required
                                                    placeholder="e.g. 08123456789"
                                                    className="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-medium text-slate-900 placeholder:text-slate-400"
                                                    value={data.phone}
                                                    onChange={(e) => setData('phone', e.target.value)}
                                                />
                                            </div>
                                            {errors.phone && <p className="text-red-500 text-sm font-medium">{errors.phone}</p>}
                                        </div>
                                    )}

                                    {!user.email && (
                                        <div className="space-y-2">
                                            <label htmlFor="email" className="text-sm font-bold text-slate-700 uppercase tracking-wider">Email Address</label>
                                            <div className="relative group">
                                                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition-colors" size={20} />
                                                <input
                                                    id="email"
                                                    type="email"
                                                    required
                                                    placeholder="e.g. john@example.com"
                                                    className="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-medium text-slate-900 placeholder:text-slate-400"
                                                    value={data.email}
                                                    onChange={(e) => setData('email', e.target.value)}
                                                />
                                            </div>
                                            {errors.email && <p className="text-red-500 text-sm font-medium">{errors.email}</p>}
                                        </div>
                                    )}

                                    <div className="mt-auto pt-8">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full bg-indigo-600 text-white px-8 py-4 rounded-2xl font-bold text-lg hover:bg-indigo-700 transition-all shadow-lg hover:shadow-indigo-500/30 active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                                        >
                                            {processing ? (
                                                <span className="animate-pulse">Saving...</span>
                                            ) : (
                                                <>
                                                    <span>Complete Setup</span>
                                                    <CheckCircle2 size={20} />
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </form>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>

                {/* Step Indicator */}
                <div className="absolute bottom-6 left-0 right-0 flex justify-center space-x-2">
                    <div className={`h-1.5 rounded-full transition-all duration-300 ${step === 1 ? 'w-8 bg-indigo-600' : 'w-2 bg-slate-200'}`} />
                    <div className={`h-1.5 rounded-full transition-all duration-300 ${step === 2 ? 'w-8 bg-indigo-600' : 'w-2 bg-slate-200'}`} />
                </div>
            </div>
        </div>
    );
}
