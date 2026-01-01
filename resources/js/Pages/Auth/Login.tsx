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
    const [isFocused, setIsFocused] = useState(false);

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

            <div className="space-y-5">
                {/* Social Login Section - More Premium Style */}
                <div className="grid grid-cols-2 gap-3">
                    <a href={route('social.redirect', 'google')} className="flex items-center justify-center h-11 border border-slate-200 rounded-xl bg-white hover:bg-slate-50 transition-all group shadow-sm">
                        <svg className="w-4 h-4 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        <span className="text-xs font-bold text-slate-700 tracking-tight">Google</span>
                    </a>
                    <a href={route('social.redirect', 'facebook')} className="flex items-center justify-center h-11 bg-[#1877F2] text-white rounded-xl hover:opacity-90 transition-all shadow-sm shadow-blue-500/10 group">
                        <svg className="w-4 h-4 mr-2 fill-current" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                        <span className="text-xs font-bold tracking-tight">Facebook</span>
                    </a>
                </div>

                <div className="relative py-1">
                    <div className="absolute inset-0 flex items-center">
                        <span className="w-full border-t border-slate-100" />
                    </div>
                    <div className="relative flex justify-center text-[9px] uppercase">
                        <span className="bg-white px-4 text-slate-300 font-bold tracking-[0.3em]">{t.or_divider}</span>
                    </div>
                </div>

                {flash?.message && (
                    <div className="p-3 bg-indigo-50 border border-indigo-100 rounded-xl text-indigo-700 text-[11px] font-bold flex items-center">
                        <Sparkles size={14} className="mr-2 shrink-0" />
                        {flash.message}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="relative group">
                        <div className="flex items-center">
                            {/* Country Picker Toggle */}
                            {isPhone && (
                                <div className="absolute left-1.5 bottom-1.5 z-10">
                                    <button
                                        type="button"
                                        onClick={() => setShowCountryPicker(!showCountryPicker)}
                                        className="flex items-center space-x-1.5 bg-slate-50 hover:bg-slate-100 h-10 px-2 rounded-lg transition-all border border-slate-100 shadow-sm"
                                    >
                                        <div className="w-4 h-3 overflow-hidden rounded-[1px] flex-shrink-0 bg-slate-200">
                                            <img
                                                src={getFlagUrl(selectedCountry.code)}
                                                alt={selectedCountry.name}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <span className="text-[11px] font-bold text-slate-800">{selectedCountry.dial_code}</span>
                                        <ChevronDown size={10} className="text-slate-400" />
                                    </button>

                                    {showCountryPicker && (
                                        <div className="absolute left-0 top-[44px] z-20 w-72 max-h-[280px] overflow-hidden bg-white border border-slate-200 rounded-2xl shadow-2xl flex flex-col animate-in fade-in slide-in-from-top-1 duration-200">
                                            {/* Search Box */}
                                            <div className="p-2.5 border-b border-slate-50">
                                                <div className="relative flex items-center bg-slate-50 rounded-lg px-2.5 py-1.5 border border-slate-100 focus-within:border-indigo-300 transition-all">
                                                    <Search size={12} className="text-slate-400 mr-2" />
                                                    <input
                                                        type="text"
                                                        autoFocus
                                                        value={searchTerm}
                                                        onChange={(e) => setSearchTerm(e.target.value)}
                                                        placeholder="Cari..."
                                                        className="w-full bg-transparent border-none p-0 text-[11px] font-bold outline-none ring-0 placeholder:text-slate-300"
                                                    />
                                                </div>
                                            </div>

                                            {/* List */}
                                            <div className="flex-1 overflow-y-auto p-2 space-y-0.5 custom-scrollbar text-[11px]">
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
                                                            className="w-full flex items-center space-x-2.5 p-2 hover:bg-slate-50 rounded-lg transition-all text-left group"
                                                        >
                                                            <div className="w-6 h-4 overflow-hidden rounded-[2px] flex-shrink-0 bg-slate-50 group-hover:scale-105 transition-transform shadow-sm">
                                                                <img
                                                                    src={getFlagUrl(country.code)}
                                                                    alt={country.name}
                                                                    className="w-full h-full object-cover"
                                                                />
                                                            </div>
                                                            <div className="flex-1">
                                                                <p className="font-bold text-slate-800 leading-tight">{country.name}</p>
                                                                <p className="text-[9px] text-slate-400 font-bold tracking-wider mt-0.5">{country.dial_code}</p>
                                                            </div>
                                                            {data.country_iso === country.code && (
                                                                <div className="h-1 w-1 bg-indigo-600 rounded-full"></div>
                                                            )}
                                                        </button>
                                                    ))
                                                ) : (
                                                    <div className="p-6 text-center">
                                                        <Search size={20} className="mx-auto text-slate-100 mb-2" />
                                                        <p className="text-[9px] font-bold text-slate-300 uppercase tracking-widest">Kosong</p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="flex-1 relative">
                                <label className={`absolute ${isPhone ? 'left-[105px]' : 'left-11'} top-2 text-[9px] font-bold text-slate-400 uppercase tracking-widest pointer-events-none transition-all group-focus-within:text-indigo-600 group-focus-within:-translate-y-0.5`}>
                                    {t.identity_label}
                                </label>
                                <input
                                    type="text"
                                    value={data.identity}
                                    onFocus={() => setIsFocused(true)}
                                    onBlur={() => setIsFocused(false)}
                                    onChange={e => {
                                        setData('identity', e.target.value);
                                        if (showCountryPicker) setShowCountryPicker(false);
                                    }}
                                    placeholder={(!isFocused && !data.identity) ? t.identity_placeholder : ''}
                                    className={`w-full h-14 pt-6 pb-2 px-4 bg-slate-50/50 border border-slate-200 rounded-xl text-sm font-bold text-slate-800 placeholder:text-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all outline-none ${isPhone ? 'pl-[105px]' : 'pl-11'} ${isPhone && !isValidPhone ? 'border-red-200 bg-red-50/10' : ''}`}
                                    required
                                />

                                {!isPhone && (
                                    <div className="absolute left-4 bottom-[14px] text-slate-300 group-focus-within:text-indigo-600 transition-colors">
                                        {isEmail ? <Mail size={18} /> : <Lock size={18} />}
                                    </div>
                                )}

                                {isPhone && !showCountryPicker && (
                                    <div className={`absolute right-4 bottom-[14px] transition-colors ${isValidPhone ? 'text-green-500' : 'text-slate-200'}`}>
                                        {isValidPhone ? <MessageSquare size={18} className="animate-pulse" /> : <AlertCircle size={18} />}
                                    </div>
                                )}
                            </div>
                        </div>
                        {errors.identity && <p className="mt-1 ml-4 text-[10px] font-bold text-red-500">{errors.identity}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing || (isPhone && !isValidPhone)}
                        className="w-full h-13 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-100 disabled:text-slate-300 text-white rounded-xl font-bold text-xs uppercase tracking-[0.2em] shadow-lg shadow-indigo-600/10 hover:shadow-indigo-600/20 active:scale-[0.99] transition-all flex items-center justify-center group"
                    >
                        {processing ? (
                            <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                        ) : (
                            <span className="flex items-center">
                                {isEmail ? t.submit_magic : t.submit_otp}
                                <ArrowRight size={16} className="ml-2.5 group-hover:translate-x-2 transition-transform duration-300 ease-out" />
                            </span>
                        )}
                    </button>

                    <div className="p-4 bg-slate-50/30 rounded-xl border border-slate-100 border-dashed">
                        <p className="text-[9px] font-bold text-slate-400 leading-relaxed uppercase tracking-[0.1em] text-center">
                            {t.auto_account_info}
                        </p>
                    </div>
                </form>

                <div className="text-center pt-5 border-t border-slate-50">
                    <p className="text-[9px] text-indigo-300 font-bold tracking-[0.4em] uppercase opacity-60">
                        {t.tagline}
                    </p>
                </div>
            </div>

        </AuthLayout>
    );
}
