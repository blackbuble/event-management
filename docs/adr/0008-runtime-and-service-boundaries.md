# ADR-0008: PHP-FPM runtime and modular monolith boundaries

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
The platform is a Laravel 12 + Inertia/React application with growing modules (events, bookings, payments, WhatsApp, admin, analytics). We must state the runtime and service-boundary stance explicitly.

## Constraints & assumptions
- Single deployable unit; one MySQL database; DB queue in early stage.
- No Octane/persistent workers installed (a stray `swoole` extension warning exists locally but the app runs under PHP-FPM).

## Options considered
1. Octane (FrankenPHP/RoadRunner/Swoole) for throughput — only justified by a measured bottleneck.
2. Extract microservices for events/bookings/payments — premature; adds distributed transactions/ops.
3. PHP-FPM + modular monolith (chosen).

## Decision
- **Runtime:** PHP-FPM (via nginx). Octane is deferred until profiling shows bootstrap overhead is material; persistent-worker safety rules will apply then (no request state in singletons, bounded max requests, reload on deploy).
- **Boundaries:** modular monolith. Layer separation is Controllers → Services → Repositories → Models; cross-cutting concerns (settings, quota, QR, notifications, analytics) live in dedicated services. A service is extracted only when independent scaling/isolation is proven (with an API/event contract, owner, and failure model).

## Trade-offs
- PHP-FPM has higher per-request bootstrap overhead but simpler operations.
- Modular monolith requires discipline to avoid a “big ball of mud”.

## Security / cost / ops impact
- Lower operational complexity/cost than Octane or microservices; no worker state-leak class of bugs.

## Migration / rollback
- Adopting Octane later is a deployment change (add server + supervision + reload step), not a rewrite.

## Residual risk
- Without Octane, throughput per instance is lower; revisit if p95 latency/bootstrap is measured as the bottleneck (see `docs/runbooks/performance.md`).
