import { describe, expect, it } from 'vitest';
import { DEFAULT_LOCALE, getTranslations, resolveLocale, SUPPORTED_LOCALES } from './i18n-core';

describe('i18n registry', () => {
    it('resolves supported locales', () => {
        expect(resolveLocale('id')).toBe('id');
        expect(resolveLocale('en')).toBe('en');
        expect(SUPPORTED_LOCALES).toContain('id');
        expect(SUPPORTED_LOCALES).toContain('en');
    });

    it('falls back to the default locale for unknown input', () => {
        expect(resolveLocale('fr')).toBe(DEFAULT_LOCALE);
        expect(resolveLocale(null)).toBe(DEFAULT_LOCALE);
        expect(resolveLocale(undefined)).toBe(DEFAULT_LOCALE);
    });

    it('groups every module for a locale', () => {
        const id = getTranslations('id');
        const en = getTranslations('en');

        expect(id.locale).toBe('id');
        expect(id.event.create.title).toBe('Buat Event Baru');
        expect(id.admin.nav.dashboard).toBe('Dashboard');

        expect(en.event.create.title).toBe('Create New Event');
        expect(en.admin.nav.dashboard).toBe('Dashboard');
    });
});
