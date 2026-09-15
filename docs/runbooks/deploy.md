# Runbook: Deploy

Runtime: PHP-FPM behind nginx, MySQL, queue workers (`queue:work`), database cache/queue in early stage.

## Pre-deploy checks
- CI green on the release commit: `pint --test`, `phpstan analyse`, `php artisan test`, `npm run typecheck`, `npm run lint`, `npm run test:js`, `npm run build`.
- Migration review: additive/backward-compatible (see ADR conventions); no dropping/renaming active columns in the same release.
- Feature flags / toggles noted (e.g. `whatsapp_enabled`, `GEOIP_ENABLED`).

## Deploy
1. Put the app in maintenance mode if a migration locks tables: `php artisan down --render=errors::503`.
2. Pull release; `composer install --no-dev --optimize-autoloader`.
3. `php artisan migrate --force` (additive first; backfills as bounded jobs, never in-migration).
4. Build assets: `npm ci && npm run build`.
5. Warm caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Reload PHP-FPM. Restart queue workers separately (`php artisan queue:restart`).
7. `php artisan up`.

## Smoke test
- `GET /health/live` → `{"status":"ok"}`.
- `GET /health/ready` → `{"status":"ok","checks":{"database":true,"cache":true}}`.
- Public event landing loads; admin login (password+OTP) succeeds; one booking → payment method page renders.

## Post-deploy
- Watch error logs, p95 latency, queue wait, failed jobs for 15 minutes.
- Rollback trigger: readiness degraded, error rate spike, or booking/payment failures.

## Notes
- Never run unbounded backfills inside a migration.
- Queue workers must be supervised (Supervisor/systemd/container), not `queue:listen` in production.
