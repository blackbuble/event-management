# Runbook: Rollback

Goal: restore the previous working release fast, safely, with no data loss.

## Decide
Roll back when: `/health/ready` degraded, error-rate spike, booking/payment failures, or a bad migration.
Prefer **forward fix** for cosmetic issues; roll back for correctness/availability.

## Application rollback
1. Re-deploy the previous release commit/artifact.
2. `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build` for that commit.
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
4. Reload PHP-FPM; `php artisan queue:restart`.
5. Verify `/health/ready` and smoke test.

## Database
- Migrations in this project are additive (new nullable columns/tables), so the old code runs against the new schema — **do not** `migrate:rollback` unless the release was the migration itself.
- If a migration must be reverted: drop only the newly added column/table; never drop data written since.
- Restore from backup only for data corruption (see [backup-restore.md](./backup-restore.md)); announce data loss window first.

## Verification
- Readiness endpoint ok, admin login ok, create event + book a ticket ok.
- Confirm no orphaned files/jobs from the failed release.

## Kill switches
- `events.whatsapp_enabled=false` disables WhatsApp delivery.
- `GEOIP_ENABLED=false` disables external geo lookups.
- Payment/WhatsApp providers can be disabled in admin Settings without a deploy.
