import React from 'react';
import { usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Sparkles } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    subtitle?: string;
}

const AuthLayout: React.FC<AuthLayoutProps> = ({ children, title, subtitle }) => {
    return (
        <div className="min-h-screen relative flex flex-col items-center justify-center p-6 md:p-8 overflow-hidden selection:bg-indigo-500 selection:text-white">
            {/* Background Image with Eventbrite-style premium overlay */}
            <div className="absolute inset-0 z-0">
                <img
                    src="/assets/auth/auth-bg.png"
                    alt="Event background"
                    className="w-full h-full object-cover scale-105"
                />
                <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]" />
                <div className="absolute inset-0 bg-gradient-to-br from-indigo-900/40 via-transparent to-slate-900/80" />
            </div>

            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: "easeOut" }}
                className="w-full max-w-[440px] space-y-6 relative z-10"
            >
                {/* Language Switcher - Positioned Top Right */}
                <div className="fixed top-6 right-6 flex bg-white/10 backdrop-blur-md p-1 rounded-xl z-50 shadow-2xl border border-white/10">
                    {['id', 'en'].map((lang) => (
                        <a
                            key={lang}
                            href={route('language.switch', lang)}
                            className={`px-3 py-1.5 rounded-lg flex items-center text-[10px] font-black uppercase tracking-widest transition-all ${(usePage().props as any).locale === lang
                                ? 'bg-white text-indigo-600 shadow-sm'
                                : 'text-white/60 hover:text-white'
                                }`}
                        >
                            {lang}
                        </a>
                    ))}
                </div>

                {/* Logo matches landing */}
                <div className="flex flex-col items-center justify-center">
                    <div className="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-2xl shadow-indigo-500/40 rotate-6 hover:rotate-0 transition-all duration-500 border-2 border-white/20">
                        <Sparkles size={28} />
                    </div>
                </div>

                <div className="text-center space-y-2 px-4">
                    <h1 className="text-3xl font-black text-white tracking-tight leading-none uppercase drop-shadow-md">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="text-indigo-100/80 font-bold text-[10px] uppercase tracking-[0.2em] max-w-[280px] mx-auto drop-shadow-sm">
                            {subtitle}
                        </p>
                    )}
                </div>

                <div className="bg-white border border-slate-200 p-6 md:p-7 rounded-[2rem] shadow-xl shadow-slate-200/40 relative group overflow-hidden">
                    <div className="absolute bottom-0 left-0 right-0 h-1 bg-indigo-600" />
                    <div className="relative">
                        {children}
                    </div>
                </div>

                <footer className="text-center">
                    <p className="text-[10px] font-black text-white/40 uppercase tracking-[0.3em]">
                        © {new Date().getFullYear()} EVENTO • ALL RIGHTS RESERVED
                    </p>
                </footer>
            </motion.div>
        </div>
    );
};

export default AuthLayout;

