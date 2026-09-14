import { usePage } from '@inertiajs/react';
import { translations as landingTexts, Locale as AppLocale } from './translations';
import { eventTexts } from './event-texts';
import { dashboardTexts } from './dashboard-texts';
import { authTexts } from './auth-texts';
import { bookingTexts } from './booking-texts';
import { whatsappTexts } from './whatsapp-texts';
import { adminTexts } from './admin-texts';

/**
 * Single registry that groups every translation module in the app. Each page
 * keeps its own typed module (for ergonomic autocomplete), but this barrel is
 * the one place that lists them all and exposes a locale-aware bundle.
 */
export const translationModules = {
    landing: landingTexts,
    event: eventTexts,
    dashboard: dashboardTexts,
    auth: authTexts,
    booking: bookingTexts,
    whatsapp: whatsappTexts,
    admin: adminTexts,
} as const;

export const SUPPORTED_LOCALES: readonly AppLocale[] = ['id', 'en'] as const;

export const DEFAULT_LOCALE: AppLocale = 'en';

export type TranslationModule = keyof typeof translationModules;

/** Normalise any incoming locale string to a supported one. */
export function resolveLocale(locale?: string | null): AppLocale {
    return SUPPORTED_LOCALES.includes(locale as AppLocale) ? (locale as AppLocale) : DEFAULT_LOCALE;
}

/**
 * Grouped translations for a locale, e.g. `getTranslations('id').event.show`.
 */
export function getTranslations(locale?: string | null) {
    const lang = resolveLocale(locale);

    return {
        locale: lang,
        landing: translationModules.landing[lang],
        event: translationModules.event[lang],
        dashboard: translationModules.dashboard[lang],
        auth: translationModules.auth[lang],
        booking: translationModules.booking[lang],
        whatsapp: translationModules.whatsapp[lang],
        admin: translationModules.admin[lang],
    };
}

export type AppTranslations = ReturnType<typeof getTranslations>;

/**
 * Inertia hook returning the grouped translation bundle for the current locale
 * shared by HandleInertiaRequests (`locale` prop), plus a `t(module)` shortcut.
 */
export function useTranslations(): AppTranslations & { t: (module: TranslationModule) => unknown } {
    const { locale } = usePage().props as { locale?: string };
    const bundle = getTranslations(locale);

    return {
        ...bundle,
        t: (module: TranslationModule) => bundle[module],
    };
}
