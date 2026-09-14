# Translations (i18n)

All UI copy is grouped through a single registry so every locale module lives in one place.

## Structure

```
resources/js/config/
├── i18n.ts            # registry: groups every module + useTranslations() hook
├── translations.ts    # landing / welcome page
├── event-texts.ts     # event create/edit/show/index/analytics
├── dashboard-texts.ts # dashboard + activity
├── auth-texts.ts      # login / OTP
├── booking-texts.ts   # transaction / checkout / receipt
├── whatsapp-texts.ts  # WhatsApp quota dashboard
├── admin-texts.ts     # admin panel (nav, users, settings, analytics)
└── landing-texts.ts   # marketing sections
```

Each module keeps its own typed object with `id` and `en` keys (autocomplete stays per-page), while `i18n.ts` is the **only** place that lists them all:

```ts
import { useTranslations, getTranslations } from '@/config/i18n';

// In a component (Inertia):
const { locale, event, admin } = useTranslations();

// Outside Inertia (e.g. tests/utilities):
const { booking } = getTranslations('id');
```

- `translationModules` — barrel of every module (`landing`, `event`, `dashboard`, `auth`, `booking`, `whatsapp`, `admin`).
- `getTranslations(locale)` — grouped bundle for a locale, with `resolveLocale()` normalising unknown values to the default (`en`).
- `useTranslations()` — reads the shared Inertia `locale` prop and returns the bundle plus a `t(module)` shortcut.

## Adding a new locale module

1. Create `resources/js/config/<name>-texts.ts` exporting `{ id: {...}, en: {...} }`.
2. Register it in `translationModules` inside `i18n.ts`.
3. Existing pages keep importing their own module directly; new code can use `useTranslations()`.

## Backend

The active locale is set by `SetLocale` middleware (session `locale`) and shared to Inertia as the `locale` prop by `HandleInertiaRequests`. Server-side enums (`EventCategory`, `PaymentMethod`, `EventCategory` labels) and settings are locale-aware via `app()->getLocale()`.
