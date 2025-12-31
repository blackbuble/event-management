import React, { useState, useMemo, useEffect } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Input, Button } from '@/Components/Form';
import { authTexts, Language, defaultLanguage } from '@/config/auth-texts';
import countriesData from '@/config/countries.json';
import { Mail, ArrowRight, Sparkles, Key, Lock, MessageSquare, ChevronDown, Search, AlertCircle } from 'lucide-react';
import { parsePhoneNumberFromString, CountryCode } from 'libphonenumber-js';

export default function Login() {
    const { flash, locale } = usePage().props as any;
    const currentLang = (locale as Language) || defaultLanguage;
    const t = authTexts[currentLang].login;

    const { data, setData, post, processing, errors, transform } = useForm({
        identity: '',
        country_iso: countriesData[453]?.code || countriesData[0].code, // Default to Indonesia (ID) if possible
    });

    const [showCountryPicker, setShowCountryPicker] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [isValidPhone, setIsValidPhone] = useState(true);

    const selectedCountry = useMemo(() => {
        return countriesData.find(c => c.code === data.country_iso) || countriesData[0];
    }, [data.country_iso]);

    const isEmail = data.identity.includes('@');
    const isNumeric = /^[0-9+]+$/.test(data.identity);
    const isPhone = isNumeric && data.identity.length > 5;

    // Advanced Phone Validation using libphonenumber-js
    useEffect(() => {
        if (isPhone) {
            try {
                // Prepend dial code if missing for validation purposes
                let phoneToValidate = data.identity;
                if (!phoneToValidate.startsWith('+')) {
                    phoneToValidate = selectedCountry.dial_code + phoneToValidate.replace(/^0+/, '');
                }

                const phoneNumber = parsePhoneNumberFromString(phoneToValidate);
                setIsValidPhone(phoneNumber ? phoneNumber.isValid() : false);
            } catch (e) {
                setIsValidPhone(false);
            }
        } else {
            setIsValidPhone(true);
        }
    }, [data.identity, selectedCountry]);

    // Auto-clean input if user pastes/types identity with dial code
    useEffect(() => {
        if (data.identity.startsWith('+')) {
            const sortedCountries = [...countriesData].sort((a, b) => b.dial_code.length - a.dial_code.length);
            const matched = sortedCountries.find(c => data.identity.startsWith(c.dial_code));

            if (matched) {
                const cleanNumber = data.identity.replace(matched.dial_code, '').replace(/^0+/, '').trim();
                setData(d => ({
                    ...d,
                    identity: cleanNumber,
                    country_iso: matched.code
                }));
            }
        }
    }, [data.identity]);

    const filteredCountries = useMemo(() => {
        return countriesData.filter(country =>
            country.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            country.dial_code.includes(searchTerm)
        );
    }, [searchTerm]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isPhone && !isValidPhone) return;

        transform((data) => {
            let finalIdentity = data.identity;

            if (isPhone && !data.identity.startsWith('+') && !data.identity.includes('@')) {
                const cleanNumber = data.identity.replace(/^0+/, '').trim();
                const dialCodeNoPlus = selectedCountry.dial_code.replace('+', '');
                if (cleanNumber.startsWith(dialCodeNoPlus)) {
                    finalIdentity = '+' + cleanNumber;
                } else {
                    finalIdentity = selectedCountry.dial_code + cleanNumber;
                }
            }

            return {
                ...data,
                identity: finalIdentity
            };
        });

        post(route('auth.unified'));
    };

    const getFlagUrl = (isoCode: string) => {
        return `https://flagcdn.com/w80/${isoCode.toLowerCase()}.png`;
    };

    return (
        <AuthLayout
            title={t.title}
            subtitle={t.subtitle}
        >
            <Head title="Login" />

            <div className="space-y-6">
                {/* Social Login Section */}
                <div className="grid grid-cols-2 gap-3">
                    <a href={route('social.redirect', 'google')} className="flex items-center justify-center h-12 border border-slate-200 dark:border-slate-800 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all group">
                        <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        <span className="text-sm font-bold text-slate-700 dark:text-slate-300">Google</span>
                    </a>
                    <a href={route('social.redirect', 'facebook')} className="flex items-center justify-center h-12 bg-[#1877F2] text-white rounded-xl hover:bg-[#166fe5] transition-all">
                        <svg className="w-5 h-5 mr-2 fill-current" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                        <span className="text-sm font-bold">Facebook</span>
                    </a>
                </div>

                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <span className="w-full border-t border-slate-200 dark:border-slate-800" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                        <span className="bg-slate-50 dark:bg-slate-950 px-4 text-slate-400 font-bold tracking-[0.2em]">{t.or_divider}</span>
                    </div>
                </div>

                {flash?.message && (
                    <div className="p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-2xl text-indigo-700 dark:text-indigo-400 text-sm font-bold flex items-center animate-in fade-in zoom-in duration-300">
                        <Sparkles size={18} className="mr-3 shrink-0" />
                        {flash.message}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    <div className="relative group">
                        <div className="flex items-center">
                            {/* Country Picker Toggle */}
                            {isPhone && (
                                <div className="absolute left-1.5 bottom-1.5 z-10">
                                    <button
                                        type="button"
                                        onClick={() => setShowCountryPicker(!showCountryPicker)}
                                        className="flex items-center space-x-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 h-[52px] px-3 rounded-[14px] transition-all border border-slate-200 dark:border-slate-700 hover:border-indigo-500/50 shadow-sm"
                                    >
                                        <div className="w-6 h-4 overflow-hidden rounded-sm flex-shrink-0 bg-slate-200">
                                            <img
                                                src={getFlagUrl(selectedCountry.code)}
                                                alt={selectedCountry.name}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <span className="text-sm font-black text-slate-900 dark:text-white">{selectedCountry.dial_code}</span>
                                        <ChevronDown size={14} className="text-slate-400" />
                                    </button>

                                    {showCountryPicker && (
                                        <div className="absolute left-0 top-[60px] z-20 w-72 max-h-[400px] overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl flex flex-col animate-in fade-in slide-in-from-top-2 duration-200">
                                            {/* Search Box */}
                                            <div className="p-2 border-b border-slate-100 dark:border-slate-800">
                                                <div className="relative">
                                                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                                    <input
                                                        type="text"
                                                        autoFocus
                                                        value={searchTerm}
                                                        onChange={(e) => setSearchTerm(e.target.value)}
                                                        placeholder="Cari negara..."
                                                        className="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-800/50 border-none rounded-xl text-xs font-bold outline-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-indigo-500 transition-all"
                                                    />
                                                </div>
                                            </div>

                                            {/* List */}
                                            <div className="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar">
                                                {filteredCountries.length > 0 ? (
                                                    filteredCountries.map((country) => (
                                                        <button
                                                            key={country.code}
                                                            type="button"
                                                            onClick={() => {
                                                                setData('country_iso', country.code);
                                                                setShowCountryPicker(false);
                                                                setSearchTerm('');
                                                            }}
                                                            className="w-full flex items-center space-x-3 p-3 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl transition-colors text-left group"
                                                        >
                                                            <div className="w-8 h-5 overflow-hidden rounded-sm flex-shrink-0 bg-slate-100 group-hover:scale-110 transition-transform">
                                                                <img
                                                                    src={getFlagUrl(country.code)}
                                                                    alt={country.name}
                                                                    className="w-full h-full object-cover"
                                                                />
                                                            </div>
                                                            <div className="flex-1">
                                                                <p className="text-sm font-black text-slate-900 dark:text-white leading-tight">{country.name}</p>
                                                                <p className="text-[10px] text-slate-500 font-bold">{country.dial_code}</p>
                                                            </div>
                                                            {data.country_iso === country.code && (
                                                                <div className="h-2 w-2 bg-indigo-600 rounded-full shadow-lg shadow-indigo-500/40"></div>
                                                            )}
                                                        </button>
                                                    ))
                                                ) : (
                                                    <div className="p-8 text-center">
                                                        <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Tidak ditemukan</p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            <Input
                                label={t.identity_label}
                                type="text"
                                value={data.identity}
                                onChange={e => {
                                    setData('identity', e.target.value);
                                    if (showCountryPicker) setShowCountryPicker(false);
                                }}
                                error={errors.identity || (isPhone && !isValidPhone ? 'Format nomor telepon tidak valid' : null)}
                                placeholder={t.identity_placeholder}
                                className={`h-16 pl-14 text-lg font-medium transition-all ${isPhone ? 'pl-[125px]' : ''} ${isPhone && !isValidPhone ? 'border-red-500 focus:border-red-500 focus:ring-red-500/10' : ''}`}
                                required
                            />

                            {!isPhone && (
                                <div className="absolute left-5 top-[46px] text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    {isEmail ? <Mail size={24} /> : <Lock size={24} />}
                                </div>
                            )}

                            {isPhone && !showCountryPicker && (
                                <div className={`absolute right-5 top-[46px] transition-colors ${isValidPhone ? 'text-green-500' : 'text-red-500'}`}>
                                    {isValidPhone ? <MessageSquare size={24} className="animate-pulse" /> : <AlertCircle size={24} />}
                                </div>
                            )}
                        </div>
                    </div>

                    <Button
                        type="submit"
                        isLoading={processing}
                        disabled={isPhone && !isValidPhone}
                        className="w-full h-16 text-base font-black uppercase tracking-[0.2em]"
                    >
                        <span className="flex items-center justify-center">
                            {processing ? t.processing : (isEmail ? t.submit_magic : t.submit_otp)}
                            {!processing && <ArrowRight size={20} className="ml-2" />}
                        </span>
                    </Button>

                    <div className="flex items-center justify-center p-5 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 border-dashed">
                        <p className="text-[10px] font-bold text-slate-400 leading-relaxed uppercase tracking-widest text-center">
                            {t.auto_account_info}
                        </p>
                    </div>
                </form>

                <div className="text-center pt-6 border-t border-slate-100 dark:border-slate-800/50">
                    <p className="text-xs text-slate-400 font-bold tracking-widest uppercase italic">
                        {t.tagline}
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}
