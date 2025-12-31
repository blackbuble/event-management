import React, { createContext, useContext, useState, useEffect } from 'react';
import { Locale, translations, TranslationSchema } from '@/config/translations';

interface LanguageContextType {
    locale: Locale;
    setLocale: (locale: Locale) => void;
    t: TranslationSchema;
}

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export const LanguageProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    // Try to get from localStorage or browser language
    const [locale, setLocaleState] = useState<Locale>(() => {
        const saved = localStorage.getItem('locale') as Locale;
        if (saved && (saved === 'id' || saved === 'en')) return saved;
        return 'en';
    });

    const setLocale = (newLocale: Locale) => {
        setLocaleState(newLocale);
        localStorage.setItem('locale', newLocale);
        // Optional: Synchronize with backend or update document lang
        document.documentElement.lang = newLocale;
    };

    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    const t = translations[locale];

    return (
        <LanguageContext.Provider value={{ locale, setLocale, t }}>
            {children}
        </LanguageContext.Provider>
    );
};

export const useLanguage = () => {
    const context = useContext(LanguageContext);
    if (context === undefined) {
        throw new Error('useLanguage must be used within a LanguageProvider');
    }
    return context;
};
