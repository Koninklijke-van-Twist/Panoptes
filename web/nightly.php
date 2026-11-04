<?php

/**
 * Elke nacht aanroepen (bestaand serverscript, GET vanaf localhost).
 * Ververst de cache, slaat de dagsnapshot op en verstuurt op vrijdag (vanaf 07:00) de weekmail.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/metis_lib.php';

ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$loaded = metis_load(true);
$summary = metis_summary($loaded);
metis_store_snapshot(metis_today(), $summary);

echo 'Metis nightly ' . (($loaded['copilot']['ok'] ?? false) && ($loaded['powerplatform']['ok'] ?? false) ? 'OK' : 'PARTIAL') . "\n";
echo 'copilot=' . (($loaded['copilot']['ok'] ?? false) ? 'ok' : (string) ($loaded['copilot']['error'] ?? '')) . "\n";
echo 'powerplatform=' . (($loaded['powerplatform']['ok'] ?? false) ? 'ok' : (string) ($loaded['powerplatform']['error'] ?? '')) . "\n";
echo 'snapshot=' . metis_today() . "\n";
echo 'mail=' . metis_maybe_send_weekly($summary) . "\n";
