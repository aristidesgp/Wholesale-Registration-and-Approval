# Cuba Shipping Rates — test suites

Plain PHP scripts run against a real WordPress install (no PHPUnit). They boot
`wp-load.php`, snapshot every option they touch and restore it at the end.

```bash
php tests/rates-regression.php    # behaviour of the rate/minimum/field logic
php tests/estimate-edges.php      # estimate() edge cases and failure modes
```

Both must print `0 failed`. Run them after touching anything under
`inc/Base/ShippingRates.php`, and **before merging into a site that shares this
plugin** — the same codebase serves elroblemarket.com and expresstraveling.

## Rules for adding tests here

- Snapshot and restore: an earlier version of `rates-regression.php` finished by
  hardcoding `cshr_recipient_fields_enabled = 'no'` instead of restoring the
  saved value, which silently switched the recipient fields off on the live
  site after every run. Never write a fixed value in teardown.
- Assert on the number, not just on "no error": the per-product estimate bug
  (every product quoting the shipment minimum) produced perfectly valid output.
