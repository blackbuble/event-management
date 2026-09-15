# ADR-0005: `endroid/qr-code` for ticket QR generation

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
Each issued ticket needs a scannable QR (payload = `ticket_code`) attached to the payment-success email.

## Constraints & assumptions
- PHP runtime has GD available.
- No existing QR library in the project.

## Options considered
1. External QR image service — leaks ticket codes to a third party, network dependency.
2. Hand-rolled QR encoder — high complexity/risk.
3. `endroid/qr-code` (chosen) — mature, MIT, GD-based.

## Decision
Use `endroid/qr-code` (^6) through `TicketQrService` (`png()` / `dataUri()`), isolating the dependency behind one service.

## Trade-offs
- Adds 1 Composer dependency (+ bacon/bacon-qr-code). No runtime service, no recurring cost.
- Requires the `gd` PHP extension in every environment.

## Security / cost / ops impact
- Offline generation, no data leaves the server. Zero marginal cost.
- CI image must include `gd` (already in `.github/workflows/ci.yml`).

## Migration / rollback
- Remove the package + `TicketQrService` callers; emails fall back to ticket codes only.

## Residual risk
- None material; error-correction level fixed at Medium.
