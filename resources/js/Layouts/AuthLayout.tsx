import React from 'react';
import { usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    subtitle?: string;
}

const AuthLayout: React.FC<AuthLayoutProps> = ({ children, title, subtitle }) => {
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col items-center justify-center p-6 md:p-8">
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: "easeOut" }}
                className="w-full max-w-[420px] space-y-8 relative"
            >
                {/* Language Switcher - Positioned Top Right */}
                <div className="fixed top-6 right-6 flex space-x-2 z-50">
                    {['id', 'en'].map((lang) => (
                        <a
                            key={lang}
                            href={route('language.switch', lang)}
                            className={`w-9 h-9 flex items-center justify-center rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ${(usePage().props as any).locale === lang
                                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20'
                                : 'bg-white dark:bg-slate-900 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200 dark:border-slate-800 shadow-sm'
                                }`}
                        >
                            {lang}
                        </a>
                    ))}
                </div>

                {/* Logo or Brand */}
                <div className="flex flex-col items-center justify-center space-y-2">
                    <div className="h-14 w-14 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-xl shadow-indigo-500/20 rotate-3 transform hover:rotate-0 transition-transform duration-300">
                        <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>

                <div className="text-center space-y-2 px-4">
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            {subtitle}
                        </p>
                    )}
                </div>

                <div className="bg-white dark:bg-slate-900/50 backdrop-blur-xl border border-slate-200 dark:border-slate-800 p-8 rounded-[2rem] shadow-2xl shadow-slate-200/50 dark:shadow-none">
                    {children}
                </div>

                <footer className="text-center">
                    <p className="text-xs text-slate-400 dark:text-slate-600 font-medium">
                        © {new Date().getFullYear()} Event Management System. All rights reserved.
                    </p>
                </footer>
            </motion.div>
        </div>
    );
};

export default AuthLayout;
