# Cuba Shipping Rates for WooCommerce (CSHR)

In-house WooCommerce plugin (DEVFL) that turns a WooCommerce store into a
Cuba-shipping storefront:

- Registers the 16 Cuban provinces as WooCommerce states, filtered to provinces
  with at least one active municipality.
- Replaces the checkout city field with a **Municipio** select (populated from the
  plugin's own rates table).
- Adds a required **shipping type** selector — `maritimo` / `aereo` — in cart and
  checkout, stored in the WC session and in order meta `_shipping_type`.
- Calculates shipping via `woocommerce_package_rates`: weight-based (lbs × per-lb
  rate for the destination municipality, with general fallback rates) or flat price
  per product category (`flat_price` term meta), plus an optional percentage
  surcharge.
- **Minimum billable pounds (v1.1.0)** — configurable per shipping type, with two
  modes: *bill the minimum* (charge at least N lbs) or *block checkout* below the
  minimum. Defaults to 0 (disabled) so existing sites keep their behavior.
- **Recipient fields (v1.1.0)** — optional Carnet de Identidad (11 digits) and
  Cuban phone number at checkout, saved to the order and shown in admin, order
  details and emails. Disabled by default.
- **Estimate endpoint (v1.1.0)** — `cshr_estimate` AJAX action (nonce-protected)
  returning the shipping estimate for province + municipality + type + weight,
  applying the same rules as the cart. Used by theme shipping calculators.

## ⚠ Shared plugin

This plugin is used by more than one site (elroblemarket.com and Express
Traveling). **Every change must be backward-compatible**: new behavior ships
behind settings whose defaults preserve current behavior. The two deployments
have diverged historically (different DB schema for the rates table); see the
KB note `projects/cuba-shipping-rates/overview` before making changes.

## Configuration

Admin → **Tarifas de Envío**:

- **General**: percentage surcharge, general maritime/air per-lb rates, minimum
  billable lbs per type + mode, recipient-fields toggle.
- **Tarifas por Provincia**: per-municipality maritime/air rates + active flag
  (table `{prefix}cuba_shipping_rates`).
- **Tarifas por Categoría**: flat price per product category.

## Requirements

- WordPress 5.8+, WooCommerce 3.0+ (classic cart/checkout shortcodes; the
  checkout fields integration targets the classic checkout).
- Store weight unit: lbs (rates are per pound).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
