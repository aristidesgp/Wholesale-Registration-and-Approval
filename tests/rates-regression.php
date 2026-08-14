<?php
// Functional test for cuba-shipping-rates 1.1.0 — runs against local WP via CLI.
error_reporting(E_ERROR | E_PARSE); // silence WP notices in CLI output

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/elroble/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require 'D:/xampp/htdocs/elroble/wp-load.php';

if (!class_exists('CSHR\\Inc\\Base\\ShippingRates')) {
    echo "FAIL: plugin class not loaded\n";
    exit(1);
}

$calc = new CSHR\Inc\Base\ShippingRates();
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label\n"; }
}

// Snapshot current options
$snap = [
    'mar' => get_option('cshr_min_lbs_maritimo', 0),
    'aer' => get_option('cshr_min_lbs_aereo', 0),
    'mode'=> get_option('cshr_min_lbs_mode', 'bill'),
    'pct' => get_option('cuba_shipping_percentage', 0),
];

// Force known state: minimums off, no percentage
update_option('cshr_min_lbs_maritimo', 0);
update_option('cshr_min_lbs_aereo', 0);
update_option('cshr_min_lbs_mode', 'bill');
update_option('cuba_shipping_percentage', 0);

$gen_mar = (float) get_option('rate_maritimo_general', 0);
$gen_aer = (float) get_option('rate_aereo_general', 0);
echo "General rates: maritimo=$gen_mar aereo=$gen_aer\n";

// T1: estimate with min disabled == weight * rate (general fallback, fake municipality)
$e = $calc->estimate('HAB', '__no_such__', 'maritimo', 10);
check('T1 estimate min-off total == 10 × general maritimo', abs($e['total'] - 10 * $gen_mar) < 0.001);
check('T1 rate_source is general', $e['rate_source'] === 'general');
check('T1 billable == weight', abs($e['billable_lbs'] - 10.0) < 0.001);

// T2: real municipality lookup (first active row)
global $wpdb;
$row = $wpdb->get_row("SELECT province, municipality, rate_maritimo, rate_aereo FROM {$wpdb->prefix}cuba_shipping_rates WHERE active=1 AND rate_maritimo > 0 LIMIT 1");
if ($row) {
    $e2 = $calc->estimate($row->province, $row->municipality, 'maritimo', 10);
    check('T2 municipality rate used', abs($e2['total'] - 10 * (float)$row->rate_maritimo) < 0.001);
    check('T2 rate_source is municipality', $e2['rate_source'] === 'municipality');
} else {
    echo "SKIP T2: no active municipality rows with rate_maritimo > 0\n";
    $anyrow = $wpdb->get_row("SELECT province, municipality FROM {$wpdb->prefix}cuba_shipping_rates WHERE active=1 LIMIT 1");
    if ($anyrow) {
        $e2b = $calc->estimate($anyrow->province, $anyrow->municipality, 'maritimo', 10);
        check('T2b municipality row with 0 rate falls back to general', abs($e2b['total'] - 10 * $gen_mar) < 0.001);
    }
}

// T3: minimum billable — bill mode clamps
update_option('cshr_min_lbs_maritimo', 30);
$e3 = $calc->estimate('HAB', '__no_such__', 'maritimo', 10);
check('T3 bill mode clamps to 30 lb', abs($e3['billable_lbs'] - 30.0) < 0.001);
check('T3 min_applied flag', $e3['min_applied'] === true);
check('T3 total == 30 × rate', abs($e3['total'] - 30 * $gen_mar) < 0.001);

// T4: aereo has its own minimum (still 0) — no clamp
$e4 = $calc->estimate('HAB', '__no_such__', 'aereo', 10);
check('T4 aereo min-off billable == 10', abs($e4['billable_lbs'] - 10.0) < 0.001);
check('T4 aereo total == 10 × general aereo', abs($e4['total'] - 10 * $gen_aer) < 0.001);

// T5: block mode — no clamp but below_min flagged
update_option('cshr_min_lbs_mode', 'block');
$e5 = $calc->estimate('HAB', '__no_such__', 'maritimo', 10);
check('T5 block mode does not clamp', abs($e5['billable_lbs'] - 10.0) < 0.001);
check('T5 below_min flag', $e5['below_min'] === true);

// T6: percentage surcharge
update_option('cshr_min_lbs_mode', 'bill');
update_option('cshr_min_lbs_maritimo', 0);
update_option('cuba_shipping_percentage', 10);
$e6 = $calc->estimate('HAB', '__no_such__', 'maritimo', 10);
check('T6 percentage applied', abs($e6['total'] - (10 * $gen_mar * 1.10)) < 0.01);

// T7: weight over cap handled by endpoint only; estimate() itself accepts
$e7 = $calc->estimate('', '', 'aereo', 1);
check('T7 empty destination falls back to general', $e7['rate_source'] === 'general');

// T8: static helpers
update_option('cshr_min_lbs_aereo', 20);
check('T8 get_min_lbs(aereo) == 20', abs(CSHR\Inc\Base\ShippingRates::get_min_lbs('aereo') - 20.0) < 0.001);
check('T8 billable_weight clamps', abs(CSHR\Inc\Base\ShippingRates::billable_weight(5, 'aereo') - 20.0) < 0.001);
check('T8 billable_weight passes above min', abs(CSHR\Inc\Base\ShippingRates::billable_weight(25, 'aereo') - 25.0) < 0.001);
check('T8 zero weight untouched', CSHR\Inc\Base\ShippingRates::billable_weight(0, 'aereo') == 0.0);

// T9: recipient fields class gated off by default
$snap_recipients = get_option('cshr_recipient_fields_enabled', 'no');
update_option('cshr_recipient_fields_enabled', 'no');
$rf = new CSHR\Inc\Base\RecipientFields();
$rf->register();
check('T9 recipient fields off: no checkout filter', has_filter('woocommerce_checkout_fields', [$rf, 'add_fields']) === false);
update_option('cshr_recipient_fields_enabled', 'yes');
$rf2 = new CSHR\Inc\Base\RecipientFields();
$rf2->register();
check('T9 recipient fields on: checkout filter registered', has_filter('woocommerce_checkout_fields', [$rf2, 'add_fields']) !== false);

// Restore snapshot
update_option('cshr_min_lbs_maritimo', $snap['mar']);
update_option('cshr_min_lbs_aereo', $snap['aer']);
update_option('cshr_min_lbs_mode', $snap['mode']);
update_option('cuba_shipping_percentage', $snap['pct']);
// Restore the site's own value: hardcoding 'no' here silently switched the
// recipient fields off on elroble after every test run.
update_option('cshr_recipient_fields_enabled', $snap_recipients);

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
