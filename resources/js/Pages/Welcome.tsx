import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { motion, useScroll, useTransform, AnimatePresence } from 'framer-motion';
import {
    Calendar,
    Users,
    Shield,
    Zap,
    Star,
    ArrowRight,
    CheckCircle2,
    Menu,
    X,
    MapPin,
    Clock,
    Globe,
    Ticket
} from 'lucide-react';
import { Button } from '@/Components/Form';
import { useLanguage } from '@/Components/LanguageContext';
import { Locale } from '@/config/translations';

// Premium Navbar Component with Language Switcher
const Navbar = () => {
    const [isScrolled, setIsScrolled] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const { locale, setLocale, t } = useLanguage();

    useEffect(() => {
        const handleScroll = () => setIsScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const languages: { code: Locale; label: string; flag: string }[] = [
        { code: 'id', label: 'Indonesian', flag: '🇮🇩' },
        { code: 'en', label: 'English', flag: '🇺🇸' }
    ];

    return (
        <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${isScrolled ? 'py-3 bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800' : 'py-6 bg-transparent'}`}>
            <div className="container mx-auto px-6 flex items-center justify-between">
                <Link href="/" className="flex items-center space-x-3 group">
                    <div className="h-10 w-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 group-hover:rotate-12 transition-transform duration-300">
                        <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Evento</span>
                </Link>

                {/* Desktop Menu */}
                <div className="hidden md:flex items-center space-x-8">
                    <a href="#features" className="text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors uppercase tracking-widest">{t.nav.features}</a>
                    <a href="#events" className="text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors uppercase tracking-widest">{t.nav.events}</a>
                    <a href="#pricing" className="text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors uppercase tracking-widest">{t.nav.pricing}</a>
                </div>

                <div className="hidden md:flex items-center space-x-6">
                    {/* Language Switcher */}
                    <div className="flex items-center bg-slate-100 dark:bg-slate-900 rounded-lg p-1">
                        {languages.map((lang) => (
                            <button
                                key={lang.code}
                                onClick={() => setLocale(lang.code)}
                                className={`px-3 py-1 text-xs font-bold rounded-md transition-all ${locale === lang.code ? 'bg-white dark:bg-slate-800 text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-600'}`}
                            >
                                {lang.code.toUpperCase()}
                            </button>
                        ))}
                    </div>

                    <Link href="/login" className="text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-indigo-600 transition-colors">{t.nav.login}</Link>
                    <Link href="/register" className="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-500/20 active:scale-95 transition-all">{t.nav.signup}</Link>
                </div>

                {/* Mobile Toggle */}
                <button className="md:hidden text-slate-900 dark:text-white" onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
                    {isMobileMenuOpen ? <X size={28} /> : <Menu size={28} />}
                </button>
            </div>

            {/* Mobile Menu */}
            <AnimatePresence>
                {isMobileMenuOpen && (
                    <motion.div
                        initial={{ opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: 'auto' }}
                        exit={{ opacity: 0, height: 0 }}
                        className="md:hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 overflow-hidden"
                    >
                        <div className="px-6 py-8 flex flex-col space-y-6 text-center">
                            {/* Mobile Language Switcher */}
                            <div className="flex justify-center gap-4 mb-4">
                                {languages.map((lang) => (
                                    <button
                                        key={lang.code}
                                        onClick={() => { setLocale(lang.code); setIsMobileMenuOpen(false); }}
                                        className={`px-4 py-2 rounded-xl border-2 transition-all ${locale === lang.code ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600' : 'border-slate-100 dark:border-slate-800 text-slate-400'}`}
                                    >
                                        <span className="text-lg mr-2">{lang.flag}</span>
                                        <span className="text-sm font-black">{lang.label}</span>
                                    </button>
                                ))}
                            </div>

                            <a href="#features" onClick={() => setIsMobileMenuOpen(false)} className="text-lg font-bold text-slate-700 dark:text-slate-300">{t.nav.features}</a>
                            <a href="#events" onClick={() => setIsMobileMenuOpen(false)} className="text-lg font-bold text-slate-700 dark:text-slate-300">{t.nav.events}</a>
                            <a href="#pricing" onClick={() => setIsMobileMenuOpen(false)} className="text-lg font-bold text-slate-700 dark:text-slate-300">{t.nav.pricing}</a>
                            <div className="pt-4 flex flex-col space-y-4">
                                <Link href="/login" className="w-full py-4 text-lg font-bold text-slate-700 dark:text-slate-300">{t.nav.login}</Link>
                                <Link href="/register" className="w-full py-4 bg-indigo-600 text-white text-lg font-bold rounded-2xl">{t.nav.signup}</Link>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </nav>
    );
};

// Main Landing Page Component
const Welcome = () => {
    const { scrollYProgress } = useScroll();
    const opacity = useTransform(scrollYProgress, [0, 0.2], [1, 0]);
    const scale = useTransform(scrollYProgress, [0, 0.2], [1, 0.95]);
    const { t } = useLanguage();

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 selection:bg-indigo-500/30">
            <Head title="Premium Event Management System" />
            <Navbar />

            {/* Hero Section */}
            <section className="relative pt-32 pb-20 md:pt-48 md:pb-40 overflow-hidden">
                {/* Glow Background Elements */}
                <div className="absolute top-0 right-0 -translate-y-1/2 translate-x-1/4 w-[800px] h-[800px] bg-indigo-500/10 blur-[120px] rounded-full" />
                <div className="absolute bottom-0 left-0 translate-y-1/2 -translate-x-1/4 w-[600px] h-[600px] bg-violet-500/10 blur-[120px] rounded-full" />

                <div className="container mx-auto px-6">
                    <div className="flex flex-col lg:flex-row items-center gap-16">
                        <motion.div
                            style={{ opacity, scale }}
                            className="flex-1 text-center lg:text-left space-y-8"
                        >
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                className="inline-flex items-center space-x-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800 rounded-full"
                            >
                                <Zap size={14} className="text-indigo-600" />
                                <span className="text-xs font-bold text-indigo-700 dark:text-indigo-400 uppercase tracking-widest">{t.hero.badge}</span>
                            </motion.div>

                            <motion.h1
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.1 }}
                                key={t.hero.title_start} // Force re-animation on lang change
                                className="text-5xl md:text-7xl font-extrabold text-slate-900 dark:text-white leading-[1.1] tracking-tight"
                            >
                                {t.hero.title_start} <br />
                                {t.hero.title_highlight.includes('.') ? (
                                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-violet-600 underline decoration-indigo-500/30 underline-offset-8">{t.hero.title_highlight}</span>
                                ) : (
                                    <span className="text-indigo-600 drop-shadow-sm">{t.hero.title_highlight}</span>
                                )}
                            </motion.h1>

                            <motion.p
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.2 }}
                                className="text-lg md:text-xl text-slate-500 dark:text-slate-400 max-w-xl mx-auto lg:mx-0 leading-relaxed"
                            >
                                {t.hero.subtitle}
                            </motion.p>

                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.3 }}
                                className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-4"
                            >
                                <Link
                                    href="/register"
                                    className="w-full sm:w-auto px-10 py-5 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 hover:shadow-2xl hover:shadow-indigo-500/40 active:scale-95 transition-all duration-300 text-lg flex items-center justify-center"
                                >
                                    {t.hero.cta_primary} <ArrowRight className="ml-2" size={20} />
                                </Link>
                                <Link
                                    href="/login"
                                    className="w-full sm:w-auto px-10 py-5 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold rounded-2xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all duration-300 text-lg flex items-center justify-center"
                                >
                                    {t.hero.cta_secondary}
                                </Link>
                            </motion.div>

                            <motion.div
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                transition={{ delay: 0.5 }}
                                className="flex items-center justify-center lg:justify-start -space-x-3 pt-4"
                            >
                                {[1, 2, 3, 4].map(i => (
                                    <div key={i} className="h-12 w-12 rounded-full border-4 border-white dark:border-slate-950 bg-slate-200 overflow-hidden">
                                        <img src={`https://api.dicebear.com/7.x/avataaars/svg?seed=${i * 123}`} alt="Avatar" />
                                    </div>
                                ))}
                                <div className="flex flex-col ml-6 text-left">
                                    <div className="flex items-center">
                                        {[1, 2, 3, 4, 5].map(i => <Star key={i} size={14} className="text-amber-400 fill-amber-400" />)}
                                    </div>
                                    <span className="text-xs font-bold text-slate-500 tracking-wide mt-1 uppercase">{t.hero.trusted_by}</span>
                                </div>
                            </motion.div>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, x: 50, rotate: 2 }}
                            animate={{ opacity: 1, x: 0, rotate: 0 }}
                            transition={{ duration: 1, ease: "easeOut" }}
                            className="flex-1 relative w-full"
                        >
                            <div className="relative z-10 bg-white dark:bg-slate-900 p-2 rounded-[2.5rem] shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden transform hover:-translate-y-2 transition-transform duration-500">
                                <img
                                    src="/hero_event_abstract.png"
                                    alt="Application Hero"
                                    className="rounded-[2rem] w-full h-auto object-cover"
                                />
                            </div>
                            <motion.div
                                animate={{ y: [0, -20, 0] }}
                                transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}
                                className="absolute -top-10 -right-10 z-20 bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 hidden xl:block"
                            >
                                <div className="flex items-center space-x-4">
                                    <div className="h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <CheckCircle2 className="text-green-600" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-bold text-slate-900 dark:text-white">Event Created</div>
                                        <div className="text-xs text-slate-500">Succesfully published!</div>
                                    </div>
                                </div>
                            </motion.div>
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* Stats Section */}
            <section className="py-20 bg-white dark:bg-slate-900">
                <div className="container mx-auto px-6">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-8">
                        {[
                            { label: t.stats.events, value: '500K+' },
                            { label: t.stats.tickets, value: '25M+' },
                            { label: t.stats.success, value: '99.9%' },
                            { label: t.stats.users, value: '1M+' },
                        ].map((stat, idx) => (
                            <div key={idx} className="text-center space-y-2">
                                <div className="text-4xl md:text-5xl font-black text-indigo-600 dark:text-indigo-500 tracking-tighter">{stat.value}</div>
                                <div className="text-sm font-bold text-slate-500 uppercase tracking-widest">{stat.label}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Features Section */}
            <section id="features" className="py-32">
                <div className="container mx-auto px-6">
                    <div className="text-center max-w-3xl mx-auto space-y-6 mb-20 px-4">
                        <h2 className="text-sm font-black text-indigo-600 uppercase tracking-[0.3em]">{t.features.badge}</h2>
                        <h3 className="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white tracking-tight leading-tight">
                            {t.features.title}
                        </h3>
                        <p className="text-lg text-slate-500 dark:text-slate-400">
                            {t.features.subtitle}
                        </p>
                    </div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {t.features.items.map((feature, idx) => {
                            const icons = [
                                <Calendar className="text-indigo-600" size={32} />,
                                <Users className="text-violet-600" size={32} />,
                                <Ticket className="text-fuchsia-600" size={32} />,
                                <Shield className="text-blue-600" size={32} />,
                                <Zap className="text-amber-600" size={32} />,
                                <Clock className="text-emerald-600" size={32} />,
                            ];
                            return (
                                <motion.div
                                    whileHover={{ y: -10 }}
                                    key={idx}
                                    className="group p-10 bg-white dark:bg-slate-900 rounded-[2.5rem] border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-xl hover:shadow-indigo-500/5 transition-all duration-300"
                                >
                                    <div className="mb-6 h-16 w-16 bg-slate-50 dark:bg-slate-800 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-6 transition-transform">
                                        {icons[idx]}
                                    </div>
                                    <h4 className="text-xl font-bold text-slate-900 dark:text-white mb-4 tracking-tight">{feature.title}</h4>
                                    <p className="text-slate-500 dark:text-slate-400 leading-relaxed">{feature.desc}</p>
                                </motion.div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Events Grid Section */}
            <section id="events" className="py-32 bg-slate-900 dark:bg-slate-950 text-white rounded-[4rem] mx-6">
                <div className="container mx-auto px-10">
                    <div className="flex flex-col md:flex-row items-end justify-between mb-20 gap-8">
                        <div className="space-y-4 text-left">
                            <h2 className="text-sm font-black text-indigo-400 uppercase tracking-[0.3em]">{t.events.badge}</h2>
                            <h3 className="text-4xl md:text-5xl font-bold tracking-tight whitespace-pre-line">{t.events.title}</h3>
                        </div>
                        <Link href="/login" className="flex items-center text-lg font-bold text-indigo-400 group">
                            {t.events.view_all} <ArrowRight className="ml-2 group-hover:translate-x-2 transition-transform" />
                        </Link>
                    </div>

                    <div className="grid md:grid-cols-2 gap-10">
                        {t.events.items.map((event, idx) => (
                            <motion.div
                                whileHover={{ scale: 0.98 }}
                                key={idx}
                                className="group relative h-[450px] md:h-[550px] rounded-[3rem] overflow-hidden cursor-pointer shadow-2xl"
                            >
                                <img src={idx === 0 ? '/event_concert_thumb.png' : '/event_conference_thumb.png'} alt={event.title} className="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-transparent" />

                                <div className="absolute top-8 left-8">
                                    <span className="px-4 py-1.5 bg-indigo-600/90 backdrop-blur-md rounded-full text-xs font-black uppercase tracking-widest">{event.cat}</span>
                                </div>

                                <div className="absolute bottom-10 left-10 right-10 space-y-6">
                                    <h4 className="text-3xl font-bold leading-tight tracking-tight group-hover:text-indigo-300 transition-colors uppercase">{event.title}</h4>
                                    <div className="flex flex-wrap gap-6 text-sm font-medium text-slate-300">
                                        <div className="flex items-center"><MapPin size={16} className="mr-2 text-indigo-400" /> {event.loc}</div>
                                        <div className="flex items-center"><Calendar size={16} className="mr-2 text-indigo-400" /> {event.date}</div>
                                    </div>
                                    <div className="flex items-center justify-between pt-4 border-t border-white/10">
                                        <div className="text-2xl font-black">{event.price}</div>
                                        <div className="h-14 w-14 bg-white rounded-2xl flex items-center justify-center text-slate-900 shadow-xl group-hover:bg-indigo-500 group-hover:text-white transition-colors duration-300">
                                            <ArrowRight size={28} />
                                        </div>
                                    </div>
                                </div>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Pricing Section */}
            <section id="pricing" className="py-32">
                <div className="container mx-auto px-6">
                    <div className="text-center max-w-3xl mx-auto space-y-4 mb-20 px-4">
                        <h3 className="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white tracking-tight leading-tight">{t.pricing.title}</h3>
                        <p className="text-lg text-slate-500 dark:text-slate-400">{t.pricing.subtitle}</p>
                    </div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                        {t.pricing.plans.map((plan, idx) => {
                            const isPreferred = plan.name === 'Professional';
                            return (
                                <motion.div
                                    whileHover={{ y: -5 }}
                                    key={idx}
                                    className={`p-10 rounded-[2.5rem] border ${isPreferred ? 'bg-white dark:bg-slate-900 border-indigo-600 shadow-2xl shadow-indigo-500/10 scale-105 relative z-10' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'}`}
                                >
                                    <div className="space-y-6">
                                        <div className="text-sm font-black text-indigo-600 uppercase tracking-widest">{plan.name}</div>
                                        <div className="text-5xl font-black text-slate-900 dark:text-white tracking-tighter">{plan.price} <span className="text-sm font-bold text-slate-400 capitalize">{plan.price !== 'Custom' && plan.price !== 'Kustom' && t.pricing.monthly}</span></div>
                                        <ul className="space-y-4 pt-4">
                                            {plan.items.map((item, i) => (
                                                <li key={i} className="flex items-center text-sm font-medium text-slate-600 dark:text-slate-300">
                                                    <CheckCircle2 size={18} className="text-indigo-500 mr-3 shrink-0" /> {item}
                                                </li>
                                            ))}
                                        </ul>
                                        <div className="pt-8">
                                            <Button className="w-full" variant={isPreferred ? 'primary' : 'secondary'}>{t.pricing.cta}</Button>
                                        </div>
                                    </div>
                                </motion.div>
                            )
                        })}
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="py-32">
                <div className="container mx-auto px-6">
                    <div className="relative bg-indigo-600 rounded-[4rem] p-10 md:p-24 overflow-hidden shadow-3xl shadow-indigo-500/40">
                        <div className="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 w-[600px] h-[600px] bg-indigo-400/20 blur-[100px] rounded-full" />

                        <div className="relative z-10 max-w-4xl mx-auto text-center space-y-10">
                            <h2 className="text-4xl md:text-7xl font-extrabold text-white tracking-tight leading-[1.1] whitespace-pre-line">{t.cta.title}</h2>
                            <p className="text-xl md:text-2xl text-indigo-100 font-medium leading-relaxed">{t.cta.subtitle}</p>
                            <div className="flex flex-col sm:flex-row items-center justify-center gap-6">
                                <Link href="/register" className="w-full sm:w-auto px-12 py-6 bg-white text-indigo-600 font-black rounded-3xl hover:bg-slate-50 active:scale-95 transition-all text-xl shadow-2xl">{t.cta.primary}</Link>
                                <Link href="/contact" className="w-full sm:w-auto px-12 py-6 bg-indigo-700/50 text-white font-black rounded-3xl border border-indigo-400/30 hover:bg-indigo-700 active:scale-95 transition-all text-xl">{t.cta.secondary}</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="pt-32 pb-16 bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800">
                <div className="container mx-auto px-6">
                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-16 mb-20">
                        <div className="col-span-2 space-y-8">
                            <div className="flex items-center space-x-3">
                                <div className="h-12 w-12 bg-indigo-600 rounded-xl flex items-center justify-center">
                                    <svg className="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <span className="text-2xl font-black text-slate-900 dark:text-white tracking-tighter uppercase">Evento</span>
                            </div>
                            <p className="text-slate-500 dark:text-slate-400 text-lg leading-relaxed max-w-sm font-bold">
                                {t.footer.desc}
                            </p>
                            <div className="flex items-center space-x-5">
                                {[1, 2, 3, 4].map(i => (
                                    <div key={i} className="h-10 w-10 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:border-indigo-600 transition-all cursor-pointer">
                                        <Star size={18} />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div>
                            <h5 className="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8">{t.footer.platform}</h5>
                            <ul className="space-y-4 text-slate-500 dark:text-slate-400 font-bold">
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Features</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Pricing</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Integrations</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Dashboard</a></li>
                            </ul>
                        </div>

                        <div>
                            <h5 className="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8">{t.footer.company}</h5>
                            <ul className="space-y-4 text-slate-500 dark:text-slate-400 font-bold">
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">About Us</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Careers</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Blog</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">News</a></li>
                            </ul>
                        </div>

                        <div>
                            <h5 className="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8">{t.footer.support}</h5>
                            <ul className="space-y-4 text-slate-500 dark:text-slate-400 font-bold">
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Help Center</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Security</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Privacy Policy</a></li>
                                <li><a href="#" className="hover:text-indigo-600 transition-colors uppercase text-xs tracking-wider">Terms of Service</a></li>
                            </ul>
                        </div>
                    </div>

                    <div className="pt-16 border-t border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-8">
                        <p className="text-slate-400 font-bold text-sm tracking-wide uppercase">{t.footer.copyright}</p>
                        <div className="flex items-center space-x-8 text-xs font-black text-slate-400 uppercase tracking-widest">
                            <a href="#" className="hover:text-slate-900 dark:hover:text-white transition-colors">Privacy</a>
                            <a href="#" className="hover:text-slate-900 dark:hover:text-white transition-colors">Terms</a>
                            <a href="#" className="hover:text-slate-900 dark:hover:text-white transition-colors">Cookies</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default Welcome;
