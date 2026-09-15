# ADR-0007: Platform fee configuration applied at booking

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
The platform needs to monetise bookings with an admin-configurable fee, distinct from organizer revenue.

## Constraints & assumptions
- Fee must not change until an admin sets it (default 0).
- Analytics must separate gross, organizer and platform revenue.

## Options considered
1. Hardcoded percentage — no flexibility.
2. Config file — not admin-editable.
3. Admin setting (fixed or percent) applied at booking (chosen).

## Decision
`settings.platform_fee.{type,amount}` via `SettingsService::platformFeeFor($base)`. `BookingService` computes the fee on the ticket subtotal, stores `bookings.platform_fee`, and includes it in `bookings.total_amount`. Analytics derive organizer revenue = `total_amount − platform_fee`, platform revenue = `platform_fee`.

## Trade-offs
- Fee is snapshotted per booking; changing the setting does not retroactively alter existing bookings (correct for accounting).

## Security / cost / ops impact
- Only admins can change the fee (panel auth). No external cost.

## Migration / rollback
- Additive `bookings.platform_fee` default 0. Rollback = set fee to 0 / drop column.

## Residual risk
- Fee is collected via the (mock) payment gateway; real gateway must pass the fee as a line item when integrated.
