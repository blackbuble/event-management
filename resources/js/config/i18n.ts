import { usePage } from '@inertiajs/react';
import { getTranslations, type AppTranslations, type TranslationModule } from './i18n-core';

export {
    translationModules,
    SUPPORTED_LOCALES,
    DEFAULT_LOCALE,
    resolveLocale,
    getTranslations,
} from './i18n-core';
export type { AppTranslations, TranslationModule } from './i18n-core';

/**
 * Inertia hook returning the grouped translation bundle for the current locale
 * shared by HandleInertiaRequests (`locale` prop), plus a `t(module)` shortcut.
 */
export function useTranslations(): AppTranslations & { t: (module: TranslationModule) => unknown } {
    const { locale } = usePage().props as { locale?: string };
    const bundle: AppTranslations = getTranslations(locale);

    return {
        ...bundle,
        t: (module: TranslationModule) => bundle[module],
    };
}
