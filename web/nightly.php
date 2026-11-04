<?php

/**
 * Elke nacht aanroepen (bestaand serverscript, GET vanaf localhost).
 * Ververst de cache, slaat de dagsnapshot op en verstuurt op vrijdag (vanaf 07:00) de weekmail.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/panoptes_lib.php';

ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$loaded = panoptes_load(true);
$summary = panoptes_summary($loaded);
panoptes_store_snapshot(panoptes_today(), $summary);

echo 'Panoptes nightly ' . (($loaded['copilot']['ok'] ?? false) && ($loaded['powerplatform']['ok'] ?? false) ? 'OK' : 'PARTIAL') . "\n";
echo 'copilot=' . (($loaded['copilot']['ok'] ?? false) ? 'ok' : (string) ($loaded['copilot']['error'] ?? '')) . "\n";
echo 'powerplatform=' . (($loaded['powerplatform']['ok'] ?? false) ? 'ok' : (string) ($loaded['powerplatform']['error'] ?? '')) . "\n";
echo 'snapshot=' . panoptes_today() . "\n";
echo 'mail=' . panoptes_maybe_send_weekly($summary) . "\n";
