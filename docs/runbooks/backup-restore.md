# Runbook: Backup & Restore

## Coverage
- **Database (MySQL):** automated daily full backup + binary logs for point-in-time recovery.
- **Object storage:** event banners / ticket assets (S3-compatible, versioned).
- **Config/secrets:** stored in the platform secret manager, never in the repo.

## Targets
- **RPO:** ≤ 24h (full) / ≤ 15min with binlog PITR.
- **RTO:** ≤ 4h for a full restore.

## Encryption & access
- Backups encrypted at rest; access restricted to the operations role. `APP_KEY` (used for settings encryption) must be recoverable from the secret manager — losing it makes `settings` secret values unrecoverable.

## Restore drill (quarterly)
1. Provision an isolated database; restore the latest backup.
2. `php artisan migrate --force` against the restored DB (should be a no-op).
3. Point a staging app at it; verify `/health/ready`, admin login, event landing, one booking.
4. Record restore duration and any errors.

## A backup is not reliable until a restore has succeeded.
