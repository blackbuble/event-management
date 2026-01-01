import React, { useRef, useState, useEffect, useMemo } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/Form';
import { authTexts, Language, defaultLanguage } from '@/config/auth-texts';
import countries from '@/config/countries.json';
import { motion } from 'framer-motion';
import { ArrowLeft, ArrowRight, MessageSquare, Mail, RefreshCcw, Sparkles } from 'lucide-react';

const VerifyOtp = () => {
    const { identity, flash, locale } = usePage().props as any;
    const currentLang = (locale as Language) || defaultLanguage;
    const t = authTexts[currentLang].otp;

    const [otp, setOtp] = useState(['', '', '', '', '', '']);
    const [timer, setTimer] = useState(60);
    const [canResend, setCanResend] = useState(false);
    const inputs = useRef<(HTMLInputElement | null)[]>([]);

    const { data, setData, post, processing, errors, reset } = useForm({
        identity: identity || '',
        otp: '',
    });

    // Helper to get flag URL from FlagCDN
    const getFlagUrl = (isoCode: string) => {
        return `https://flagcdn.com/w80/${isoCode.toLowerCase()}.png`;
    };

    // Detect country flag for phone identity
    const identityDisplay = useMemo(() => {
        if (!identity || identity.includes('@')) return { type: 'email', value: identity, flagUrl: null };

        // Match dial code to find flag
        const matchedCountry = countries.find(c => identity.startsWith(c.dial_code));
        if (matchedCountry) {
            return {
                type: 'phone',
                flagUrl: getFlagUrl(matchedCountry.code),
                prefix: matchedCountry.dial_code,
                number: identity.replace(matchedCountry.dial_code, ''),
                full: identity,
                countryName: matchedCountry.name
            };
        }
        return { type: 'phone', value: identity, flagUrl: null };
    }, [identity]);

    // Handle timer
    useEffect(() => {
        let interval: any;
        if (timer > 0) {
            interval = setInterval(() => {
                setTimer((prev) => prev - 1);
            }, 1000);
        } else {
            setCanResend(true);
            clearInterval(interval);
        }
        return () => clearInterval(interval);
    }, [timer]);

    // Update form data when local otp state changes
    useEffect(() => {
        setData('otp', otp.join(''));
    }, [otp]);

    // Auto-clear OTP on error
    useEffect(() => {
        if (errors.otp) {
            setOtp(['', '', '', '', '', '']);
            inputs.current[0]?.focus();
        }
    }, [errors]);

    const handleChange = (element: HTMLInputElement, index: number) => {
        const value = element.value.replace(/[^0-9]/g, '');
        if (value.length > 1) return; // Prevent multi-character input in single box

        const newOtp = [...otp];
        newOtp[index] = value;
        setOtp(newOtp);

        // Focus next input
        if (value !== '' && index < 5) {
            inputs.current[index + 1]?.focus();
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>, index: number) => {
        if (e.key === 'Backspace') {
            if (otp[index] === '' && index > 0) {
                inputs.current[index - 1]?.focus();
            }
        }
    };

    const handlePaste = (e: React.ClipboardEvent) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').slice(0, 6).replace(/[^0-9]/g, '');
        const newOtp = [...otp];

        pastedData.split('').forEach((char, index) => {
            if (index < 6) newOtp[index] = char;
        });

        setOtp(newOtp);

        // Focus the appropriate input after paste
        const nextIndex = pastedData.length < 6 ? pastedData.length : 5;
        inputs.current[nextIndex]?.focus();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('otp.login'));
    };

    const handleResend = () => {
        if (!canResend) return;

        post(route('auth.unified'), {
            onSuccess: () => {
                setTimer(60);
                setCanResend(false);
                setOtp(['', '', '', '', '', '']);
                inputs.current[0]?.focus();
            }
        });
    };

    const isEmail = identityDisplay.type === 'email';

    return (
        <AuthLayout
            title={t.title}
            subtitle={isEmail ? t.subtitle_email : t.subtitle_phone}
        >
            <Head title="Verifikasi" />

            <div className="flex flex-col items-center space-y-6">
                <div className="flex items-center space-x-3 p-3 bg-slate-50 border border-slate-200 rounded-xl shadow-sm">
                    {isEmail ? (
                        <Mail className="text-indigo-600" size={18} />
                    ) : (
                        <div className="w-6 h-4 overflow-hidden rounded-[1px] flex-shrink-0 bg-slate-200">
                            {identityDisplay.flagUrl ? (
                                <img
                                    src={identityDisplay.flagUrl}
                                    alt={identityDisplay.countryName}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <MessageSquare className="text-green-500" size={14} />
                            )}
                        </div>
                    )}
                    <div className="text-xs font-bold text-slate-800 tracking-tight">
                        {isEmail ? identityDisplay.value : (
                            <div className="flex items-center space-x-2">
                                <span className="bg-indigo-600 shadow-sm text-white px-1.5 py-0.5 rounded text-[9px] uppercase font-black">
                                    {identityDisplay.prefix}
                                </span>
                                <span className="text-slate-900">
                                    {identityDisplay.number}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {flash?.message && (
                    <div className="w-full p-3 bg-indigo-50 border border-indigo-100 rounded-xl text-indigo-700 text-[11px] font-bold flex items-center animate-in fade-in zoom-in duration-300">
                        <Sparkles size={14} className="mr-2 shrink-0" />
                        {flash.message}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="w-full space-y-8">
                    <div className="flex justify-center gap-2 md:gap-2.5">
                        {otp.map((digit, index) => (
                            <motion.input
                                key={index}
                                whileFocus={{ scale: 1.05, y: -1 }}
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={1}
                                ref={(el) => { inputs.current[index] = el; }}
                                value={digit}
                                onPaste={handlePaste}
                                onChange={(e) => handleChange(e.target, index)}
                                onKeyDown={(e) => handleKeyDown(e, index)}
                                className="w-9 h-12 md:w-11 md:h-14 text-center text-xl font-black bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-600 focus:ring-0 outline-none transition-all duration-300 shadow-sm text-slate-900"
                            />
                        ))}
                    </div>

                    {errors.otp && (
                        <p className="text-center text-[10px] font-black text-red-500 uppercase tracking-widest animate-bounce">
                            {errors.otp.includes('Terlalu banyak') ? authTexts[currentLang].otp.invalid_otp : errors.otp}
                        </p>
                    )}

                    <div className="space-y-4">
                        <button
                            type="submit"
                            disabled={processing || otp.some(v => v === '')}
                            className="w-full h-13 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-100 disabled:text-slate-300 text-white rounded-xl font-bold text-xs uppercase tracking-[0.2em] shadow-lg shadow-indigo-600/10 hover:shadow-indigo-600/20 active:scale-[0.99] transition-all flex items-center justify-center group"
                        >
                            {processing ? (
                                <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                            ) : (
                                <span className="flex items-center">
                                    {t.submit_button}
                                    <ArrowRight size={16} className="ml-2.5 group-hover:translate-x-2 transition-transform duration-300 ease-out" />
                                </span>
                            )}
                        </button>

                        <div className="text-center">
                            <div className="flex flex-col items-center space-y-2">
                                <p className="text-[9px] font-bold text-slate-300 uppercase tracking-widest leading-loose">
                                    {t.resend_text}
                                </p>
                                <button
                                    type="button"
                                    onClick={handleResend}
                                    disabled={!canResend || processing}
                                    className={`flex items-center space-x-2 font-bold uppercase tracking-widest text-[10px] transition-all px-4 py-2 rounded-lg ${canResend
                                        ? 'text-indigo-600 bg-indigo-50 border border-indigo-100 hover:scale-105 shadow-sm'
                                        : 'text-slate-300 bg-slate-50 border border-slate-100 cursor-not-allowed text-[9px]'
                                        }`}
                                >
                                    <RefreshCcw size={10} className={processing ? 'animate-spin' : ''} />
                                    <span>
                                        {canResend ? t.resend_link : t.resend_wait.replace('{timer}', timer.toString())}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div className="w-full pt-6 border-t border-slate-50">
                    <Link href={route('login')} className="flex items-center justify-center text-[10px] font-bold text-slate-400 hover:text-indigo-600 transition-colors uppercase tracking-[0.2em] group">
                        <ArrowLeft className="w-3.5 h-3.5 mr-2 group-hover:-translate-x-1 transition-transform" />
                        {t.change_identity}
                    </Link>
                </div>
            </div>



        </AuthLayout>
    );
};

export default VerifyOtp;
