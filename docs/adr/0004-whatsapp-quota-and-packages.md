# ADR-0004: WhatsApp ticket delivery, quota ledger and DB-backed packages

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
Tickets must be deliverable via WhatsApp, funded by the organizer, with admin-managed packages.

## Constraints & assumptions
- No WhatsApp provider is integrated yet (log driver, like the OTP flow).
- Quota must never go negative; sends must be idempotent under retries.

## Options considered
1. Free/unmetered WhatsApp sending — no cost control.
2. Per-organizer quota ledger funded by top-up packages (chosen).
3. External billing service — premature.

## Decision
- `users.whatsapp_quota` + `whatsapp_topups` ledger; `WhatsAppRepository::credit` credits under a row lock; `consume` is a conditional atomic decrement.
- Packages are `whatsapp_packages` rows (admin CRUD), seeded from `config/whatsapp.php`.
- `NotificationService::sendPaymentSuccess` sends WhatsApp per ticket only when `events.whatsapp_enabled` and quota allows, consuming one unit per ticket; logs `WA_TICKETS_SENDING` / `WA_TICKETS_SKIPPED_NO_QUOTA`.

## Trade-offs
- Quota is consumed at send time (mock). With a real provider, move consumption after confirmed delivery and refund on failure.

## Security / cost / ops impact
- Prevents runaway messaging cost; per-event toggle gives organizers control.

## Migration / rollback
- Additive tables/columns; rollback drops them.

## Residual risk
- Provider integration, delivery receipts and reconciliation still pending.
