# Runbook: Performance Baseline & Regression Gate

## Workflow (per material change)
1. Define the user journey/operation (e.g. event landing, booking checkout, admin analytics).
2. Record the baseline: environment, dataset size, tool, p50/p95/p99, query count, payload size.
3. Profile to find the dominant cost (DB, PHP, queue, external).
4. Apply the smallest safe optimization; re-measure under identical conditions.
5. Roll out gradually; watch error rate + latency.

## Budgets (starting targets — tune with real data)
| Flow | p95 target | Query budget |
|------|-----------|--------------|
| Event landing (`/events/{slug}`) | < 300 ms | ≤ 12 |
| Checkout (`/bookings/{id}/pay`) | < 400 ms | ≤ 15 |
| Admin analytics (30d, small data) | < 800 ms | ≤ 20 |
| Booking create (POST) | < 500 ms | ≤ 20 |

## Tooling
- Query counts/latency: Laravel Telescope (local) or Pulse (production sampling).
- DB: `EXPLAIN` for changed queries; slow-query log.
- HTTP load: `wrk`/`k6`/`ab` against a **staging** environment with production-like data.

## Baseline capture (example)
```
k6 run --vus 20 --duration 60s scripts/k6/event-landing.js   # staging only
```

## Regression gate
- Block a release that causes an unexplained material regression (latency, query count, payload size, error rate).
- Exceptions require an owner, business justification, monitoring plan, and remediation date.

## Notes
- Never load-test production or third-party provider quotas.
- Record results in the PR so trends are visible over time.
