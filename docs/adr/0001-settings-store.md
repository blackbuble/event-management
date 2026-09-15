# ADR-0001: Key/value settings store for platform configuration

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
Admins must configure payment gateway keys, WhatsApp provider credentials, platform fee and general app/contact info without a deploy.

## Constraints & assumptions
- Small number of keys; read frequently, written rarely.
- Values may include secrets (API/secret keys, tokens).
- Single-tenant deployment.

## Options considered
1. `.env`/config only — requires deploy + secret file edits, not admin-editable.
2. Dedicated typed columns per setting — schema churn per new setting.
3. Generic `settings` key/value table (chosen).

## Decision
A `settings(key unique, value text)` table behind `SettingsRepository` + `SettingsService`. Values are JSON-encoded; **sensitive keys are encrypted at rest** with `Crypt` (`payment_gateway.secret_key`, `whatsapp_provider.token`). Admin UI only ever receives a boolean “is set” flag for secrets; a blank secret on update preserves the stored value.

## Trade-offs
- Generic store is not queryable by column; acceptable for config-scale reads.
- Encryption key rotation must be handled via `APP_KEY` rotation procedure (see runbook).

## Security / cost / ops impact
- Secrets no longer plaintext in DB; still requires DB access control + backups encryption.
- Negligible storage/compute cost.

## Migration / rollback
- Migration creates the table; rollback drops it (config values are re-enterable).

## Residual risk
- Legacy plaintext secret values are decrypted transparently on read; re-save to encrypt.
