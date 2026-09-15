# Runbook: Incident Response

## Severity
- **SEV1** — platform down / payments broken / data loss/leak. Page immediately.
- **SEV2** — major feature degraded (booking, WhatsApp delivery, admin panel).
- **SEV3** — minor degradation, workaround exists.

## First 10 minutes
1. Declare severity + incident owner in the on-call channel.
2. Check `/health/ready` (database/cache), error tracker, queue failed jobs, DB connections.
3. Apply the smallest safe mitigation: rollback, disable feature (settings/kill switch), scale workers.
4. Post a short status update (impact, scope, next update time).

## Common diagnostics
- 5xx spike → app logs (`storage/logs/laravel.log`), error tracker, recent deploy.
- Booking failures → `booking-create` locks, ticket inventory (`quantity_sold + reserved`), DB locks.
- Delivery issues → queue backlog, provider logs (`WA_*`), quota (`WA_TICKETS_SKIPPED_NO_QUOTA`).
- Impersonation/auth → `impersonation.*` / auth logs.

## Escalation
Engineering on-call → CTO. Notify data/privacy owner for any suspected PII exposure.

## Communication
Single incident channel; updates at least every 30 minutes for SEV1/2.

## Postmortem (blameless) — within 3 business days
Timeline, impact, detection gap, root cause, contributing factors, recovery, owned preventive actions with dates.

## Remediation tracking
File each action with owner + due date; link to this incident.
