<?php
// Edge-case audit of the shipping calculation entry points.
error_reporting(E_ERROR | E_PARSE);
define('DISABLE_WP_CRON', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/elroble/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require 'D:/xampp/htdocs/elroble/wp-load.php';

$pass = 0; $fail = 0;
function check($label, $cond, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label $extra\n"; }
}

$snap = [
    'mar'  => get_option('rate_maritimo_general'),
    'aer'  => get_option('rate_aereo_general'),
    'min'  => get_option('cshr_min_lbs_maritimo'),
    'mode' => get_option('cshr_min_lbs_mode'),
    'pct'  => get_option('cuba_shipping_percentage'),
];

update_option('rate_maritimo_general', 2.99);
update_option('rate_aereo_general', 4.99);
update_option('cshr_min_lbs_maritimo', 30);
update_option('cshr_min_lbs_mode', 'bill');
update_option('cuba_shipping_percentage', 0);

$calc = new CSHR\Inc\Base\ShippingRates();

// E1: a standalone instance can price without register() being called.
$e = $calc->estimate('HOL', 'Banes', 'maritimo', 50);
check('E1 standalone instance prices (no register)', abs($e['total'] - 149.5) < 0.01, json_encode($e));

// E2: minimum applies to a shipment estimate.
$e = $calc->estimate('HOL', 'Banes', 'maritimo', 10);
check('E2 shipment estimate applies minimum (30 x 2.99)', abs($e['total'] - 89.7) < 0.01 && $e['min_applied'] === true);

// E3: per-product estimate must NOT apply the minimum.
$e = $calc->estimate('', '', 'maritimo', 10, false);
check('E3 product estimate ignores minimum', abs($e['total'] - 29.9) < 0.01 && $e['min_applied'] === false);

// E4: unknown destination falls back to general rates instead of erroring.
$e = $calc->estimate('ZZZ', 'Nowhere', 'aereo', 10);
check('E4 unknown destination -> general rate', abs($e['total'] - 49.9) < 0.01 && $e['rate_source'] === 'general');

// E5: garbage shipping type is coerced to maritime, never fatal.
$e = $calc->estimate('HOL', 'Banes', '<script>', 50);
check('E5 invalid type coerced to maritimo', $e['shipping_type'] === 'maritimo' && abs($e['total'] - 149.5) < 0.01);

// E6: negative weight cannot produce a negative price.
$e = $calc->estimate('HOL', 'Banes', 'maritimo', -20);
check('E6 negative weight -> 0', $e['total'] >= 0);

// E7: percentage surcharge is applied on top.
update_option('cuba_shipping_percentage', 10);
$e = $calc->estimate('HOL', 'Banes', 'maritimo', 100);
check('E7 percentage applied (299 + 10%)', abs($e['total'] - 328.9) < 0.01, json_encode($e));
update_option('cuba_shipping_percentage', 0);

// E8: with rates unset the estimate is 0 and reports the general source,
// so callers can tell "free" from "unknown".
update_option('rate_maritimo_general', 0);
$e = $calc->estimate('HOL', 'Banes', 'maritimo', 100);
check('E8 no rate configured -> 0 total', $e['total'] == 0.0 && $e['rate_per_lb'] == 0.0);
update_option('rate_maritimo_general', 2.99);

// E9: block mode reports below_min instead of clamping the price.
update_option('cshr_min_lbs_mode', 'block');
$e = $calc->estimate('HOL', 'Banes', 'maritimo', 10);
check('E9 block mode: below_min true, no clamp', $e['below_min'] === true && abs($e['total'] - 29.9) < 0.01, json_encode($e));
update_option('cshr_min_lbs_mode', 'bill');

// E10: huge weight stays finite and formatted.
$e = $calc->estimate('HOL', 'Banes', 'maritimo', 9999);
check('E10 large weight finite', is_numeric($e['total']) && $e['total'] > 0);

// Restore
update_option('rate_maritimo_general', $snap['mar']);
update_option('rate_aereo_general', $snap['aer']);
update_option('cshr_min_lbs_maritimo', $snap['min']);
update_option('cshr_min_lbs_mode', $snap['mode']);
update_option('cuba_shipping_percentage', $snap['pct']);

echo "\nEDGE RESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
