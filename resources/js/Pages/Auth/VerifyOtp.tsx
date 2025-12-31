import React, { useRef, useState, useEffect } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/Form';
import { authTexts } from '@/config/auth-texts';
import { motion } from 'framer-motion';
import { ArrowLeft, MessageSquare, Mail, RefreshCcw, Sparkles } from 'lucide-react';

const VerifyOtp = () => {
    const { identity, flash } = usePage().props as any;
    const [otp, setOtp] = useState(['', '', '', '', '', '']);
    const [timer, setTimer] = useState(60);
    const [canResend, setCanResend] = useState(false);
    const inputs = useRef<(HTMLInputElement | null)[]>([]);

    const { data, setData, post, processing, errors, reset } = useForm({
        identity: identity || '',
        otp: '',
    });

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

    const isEmail = identity?.includes('@');

    return (
        <AuthLayout
            title="Verifikasi Akun"
            subtitle={`Kami telah mengirimkan kode 6-digit ke ${isEmail ? 'email' : 'WhatsApp'} Anda.`}
        >
            <Head title="Verifikasi" />

            <div className="flex flex-col items-center space-y-8">
                <div className="flex items-center space-x-3 p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    {isEmail ? <Mail className="text-indigo-600" size={20} /> : <MessageSquare className="text-green-500" size={20} />}
                    <span className="text-sm font-black text-slate-700 dark:text-slate-200 tracking-wider transition-all">
                        {identity}
                    </span>
                </div>

                {flash?.message && (
                    <div className="w-full p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-2xl text-indigo-700 dark:text-indigo-400 text-sm font-bold flex items-center animate-in fade-in zoom-in duration-300">
                        <Sparkles size={18} className="mr-3 shrink-0" />
                        {flash.message}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="w-full space-y-8">
                    <div className="flex justify-center gap-2 md:gap-3">
                        {otp.map((digit, index) => (
                            <motion.input
                                key={index}
                                whileFocus={{ scale: 1.05 }}
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={1}
                                ref={(el) => { inputs.current[index] = el; }}
                                value={digit}
                                onPaste={handlePaste}
                                onChange={(e) => handleChange(e.target, index)}
                                onKeyDown={(e) => handleKeyDown(e, index)}
                                className="w-12 h-14 md:w-14 md:h-16 text-center text-2xl font-black bg-white dark:bg-slate-950 border-2 border-slate-200 dark:border-slate-800 rounded-2xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all duration-200 shadow-sm text-slate-900 dark:text-white"
                            />
                        ))}
                    </div>

                    {errors.otp && (
                        <p className="text-center text-sm font-bold text-red-500 animate-bounce">
                            {errors.otp}
                        </p>
                    )}

                    <div className="space-y-4">
                        <Button
                            type="submit"
                            className="w-full h-14 text-sm font-black uppercase tracking-widest shadow-lg shadow-indigo-500/20"
                            isLoading={processing}
                            disabled={otp.some(v => v === '')}
                        >
                            Verifikasi & Masuk
                        </Button>

                        <div className="text-center">
                            <div className="flex flex-col items-center space-y-2">
                                <p className="text-[11px] font-bold text-slate-400 uppercase tracking-widest leading-loose">
                                    {authTexts.otp.resend_text}
                                </p>
                                <button
                                    type="button"
                                    onClick={handleResend}
                                    disabled={!canResend || processing}
                                    className={`flex items-center space-x-2 font-black uppercase tracking-widest text-xs transition-all ${canResend
                                        ? 'text-indigo-600 hover:text-indigo-700'
                                        : 'text-slate-300 cursor-not-allowed'
                                        }`}
                                >
                                    <RefreshCcw size={14} className={processing ? 'animate-spin' : ''} />
                                    <span>
                                        {canResend ? authTexts.otp.resend_link : `Kirim Ulang dalam ${timer}s`}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div className="w-full pt-8 border-t border-slate-100 dark:border-slate-800/50">
                    <Link href={route('login')} className="flex items-center justify-center text-xs font-black text-slate-400 hover:text-indigo-600 transition-colors uppercase tracking-[0.2em] group">
                        <ArrowLeft className="w-4 h-4 mr-2 group-hover:-translate-x-1 transition-transform" />
                        Ganti Identitas
                    </Link>
                </div>
            </div>
        </AuthLayout>
    );
};

export default VerifyOtp;
