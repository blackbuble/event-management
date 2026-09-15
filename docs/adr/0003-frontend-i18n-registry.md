# ADR-0003: Grouped frontend translation registry

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
UI copy was spread across multiple `*-texts.ts` modules with per-feature locale objects and no single entry point; adding locales/modules risked a parallel mechanism.

## Constraints & assumptions
- Existing modules (`event-texts`, `dashboard-texts`, `auth-texts`, `booking-texts`, `whatsapp-texts`, `admin-texts`, `landing`) already ship `id`/`en`.
- No build-time i18n framework is installed; runtime locale comes from the Inertia `locale` prop.

## Options considered
1. Adopt an i18n framework (i18next/vue-i18n) — new dependency + migration.
2. Keep modules but add one grouping registry (chosen).
3. Merge everything into one giant file — poor ergonomics.

## Decision
`resources/js/config/i18n.ts` groups every module via `translationModules`, exposes `getTranslations(locale)`, `resolveLocale()` (fallback `en`) and the Inertia `useTranslations()` hook. The framework-free core lives in `i18n-core.ts` so it is unit-testable without React. Per-page modules keep their own types for autocomplete.

## Trade-offs
- Not a full i18n framework: pluralization/date-number-currency remain per-helper.
- Two files (`i18n.ts` + `i18n-core.ts`) instead of one.

## Security / cost / ops impact
- No runtime dependency added; bundle impact negligible.

## Migration / rollback
- Additive; existing imports keep working. Rollback = delete the registry.

## Residual risk
- Locale set is still `id|en`; adding `ms` requires new module keys + QA of long copy.
