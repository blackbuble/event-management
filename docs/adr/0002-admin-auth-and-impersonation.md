# ADR-0002: Admin authentication (password + OTP) and impersonation with re-auth

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
Admins need a stricter login than the attendee passwordless flow, and must be able to support organizers by acting as them.

## Constraints & assumptions
- The product already ships passwordless login (OTP/magic link) for regular users.
- Admin actions are high privilege; session hijacking is a real threat.

## Options considered
1. Reuse the passwordless login for admins — weaker, no password factor.
2. Separate admin login with password **and** OTP (chosen).
3. External IdP/SSO — heavier than needed at this stage.

## Decision
- `/admin/login` verifies email + password (`Hash::check`) and requires the `admin` role, then sends an OTP (`/admin/otp`) before establishing the session.
- OTP can be **resent** via email or WhatsApp (if the admin has a phone); the code is stored on the same user.
- **Impersonation** (`/admin/impersonate/{user}`) requires the acting admin to **re-enter their password**, blocks admin→admin and suspended targets, keeps `impersonator_id` in session, and is fully logged (`impersonation.*`). `profile.complete` is bypassed while impersonating.

## Trade-offs
- Two-step login adds friction for admins (acceptable for privilege level).
- Impersonation increases audit/abuse surface → mitigated with re-auth + logging.

## Security / cost / ops impact
- Password + OTP = two factors; re-auth on impersonation limits stolen-session abuse.
- Structured audit logs (`impersonation.attempt/started/stopped/blocked_*`).

## Migration / rollback
- Additive routes/controllers; rollback = disable admin routes (no data migration).

## Residual risk
- OTP delivery is still email/log until a real WhatsApp provider is wired.
