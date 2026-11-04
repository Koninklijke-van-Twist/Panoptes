<?php

/**
 * Elk uur aanroepen (bestaand serverscript, GET vanaf localhost).
 * Ververst de cache als die verlopen is, werkt de snapshot van vandaag bij en
 * verstuurt op vrijdag vanaf 07:00 eenmalig de weekmail.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/metis_lib.php';

ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$loaded = metis_load(false);
$summary = metis_summary($loaded);
metis_store_snapshot(metis_today(), $summary);

echo "Metis hourly OK\n";
echo 'hour=' . metis_hour() . "\n";
echo 'mail=' . metis_maybe_send_weekly($summary) . "\n";
