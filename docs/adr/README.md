# Architecture Decision Records (ADR)

Material architecture decisions for the event management platform. Format: Context, Constraints/Assumptions, Options, Decision, Trade-offs, Security/Cost/Ops impact, Migration/Rollback, Owner + review date.

| ADR | Decision | Status |
|-----|----------|--------|
| [0001](./0001-settings-store.md) | Key/value `settings` store for platform configuration | Accepted |
| [0002](./0002-admin-auth-and-impersonation.md) | Admin authentication (password + OTP) and impersonation with re-auth | Accepted |
| [0003](./0003-frontend-i18n-registry.md) | Grouped frontend translation registry | Accepted |
| [0004](./0004-whatsapp-quota-and-packages.md) | WhatsApp ticket delivery, quota ledger and DB-backed packages | Accepted |
| [0005](./0005-qr-code-dependency.md) | `endroid/qr-code` for ticket QR generation | Accepted |
| [0006](./0006-visitor-analytics-and-geoip.md) | Visitor analytics with proxy-aware IP + optional geolocation | Accepted |
| [0007](./0007-platform-fee.md) | Platform fee configuration applied at booking | Accepted |
| [0008](./0008-runtime-and-service-boundaries.md) | PHP-FPM runtime and modular monolith boundaries | Accepted |

Owner: Engineering. Review cadence: at each material change or every 6 months.
