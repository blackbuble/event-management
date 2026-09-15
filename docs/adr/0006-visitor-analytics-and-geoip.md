# ADR-0006: Visitor analytics with proxy-aware IP + optional geolocation

- **Status:** Accepted
- **Owner:** Engineering
- **Review date:** 2027-03-15

## Context
Admins need audience insight: origin (country/city), device and OS, realtime + daily.

## Constraints & assumptions
- Traffic may sit behind nginx/Cloudflare; naive `REMOTE_ADDR` is the proxy, not the visitor.
- Privacy: raw IP/UA are personal data; must be minimised and masked in UI.

## Options considered
1. Third-party analytics (GA) — data leaves the platform, cookie/consent burden.
2. First-party `event_visits` table + local UA parsing + optional geolocation (chosen).
3. Mandatory IP geolocation provider — external dependency + cost from day one.

## Decision
- Record non-owner views in `event_visits` (event, ip, country, city, device, os, ua).
- `trustProxies('*')` so `$request->ip()` reads `X-Forwarded-For` (restrict the range in production).
- Device/OS parsed locally from UA (no dependency).
- Geolocation via `IpGeolocationService`, **disabled by default** (`GEOIP_ENABLED`/`GEOIP_ENDPOINT`); private/reserved IPs never sent; failures degrade to null.
- Admin UI exposes realtime (5 min) and daily (14 d) aggregates; realtime feed shows **masked** IPs.

## Trade-offs
- First-party analytics is approximate (no cross-site identity) — sufficient for organizers/admins.
- `trustProxies('*')` is permissive; tighten to LB ranges per environment.

## Security / cost / ops impact
- No per-request external call unless geo is enabled. Provider cost only when enabled.
- PII minimisation: masked IPs, no full UA in UI.

## Migration / rollback
- Additive table/columns; disable geo by config. Rollback = stop tracking (`EventController@show`).

## Residual risk
- Geo accuracy depends on the chosen provider; document retention with the provider.
