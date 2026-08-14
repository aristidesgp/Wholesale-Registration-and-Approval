# Changelog

## 1.1.0 — 2026-08-13 (branch feature/elroble-fase1)

Backward-compatible feature release. All new behavior is opt-in; with default
settings the plugin behaves exactly like 1.0.0.

### Added
- Minimum billable pounds, configurable per shipping type
  (`cshr_min_lbs_maritimo`, `cshr_min_lbs_aereo`, default 0 = disabled) with two
  modes (`cshr_min_lbs_mode`): `bill` (charge at least the minimum weight, shows
  an informational row in cart totals) or `block` (checkout validation error +
  cart notice below the minimum).
- Recipient fields for Cuba shipments (`cshr_recipient_fields_enabled`, default
  off): Carnet de Identidad (11-digit validation) and Cuban phone, required at
  checkout when shipping to CU, saved as `_shipping_ci` / `_shipping_cuba_phone`
  order meta, displayed in admin order screen, customer order details and emails.
- Session-independent `ShippingRates::estimate()` and nonce-protected
  `cshr_estimate` AJAX endpoint for theme shipping calculators; `cubaShippingRates`
  JS object now includes `ajax_url` and `estimate_nonce`.

### Fixed / hardened
- **Granma was unavailable at checkout**: the states list used code `GRM` while the
  rates table and the admin municipality catalog use `GRA` — the province never
  passed the active-provinces filter. Now aligned on `GRA`.
- `rate_source` in estimates now reports `municipality` only when the used rate
  actually came from the municipality row (per shipping type), not merely because
  a row exists.
- `update_shipping_type` AJAX now requires a nonce.
- All three admin settings forms now include and verify nonces.
- Removed all `error_log` debug calls from the rate-calculation path.
- Frontend script was loaded twice (enqueue + hardcoded footer tag); now enqueued
  once with proper versioning. CSS handle renamed to `cshr-main`.
- Bootstrap: standard `ABSPATH` guard; empty `Schedule`/`ShortCodes` services no
  longer registered.

### Internal
- Rate calculation restructured: per-package weight aggregation (weight-based
  items) + flat-price items, same math as 1.0.0 when minimums are disabled.
- `calculate_shipping_rate()` kept for backward compatibility.
- README rewritten (previous README belonged to a different plugin template).

## 1.0.0

Initial version as deployed on elroblemarket.com (never tagged; recovered as
git baseline `baseline-elroble-2026-08`).
