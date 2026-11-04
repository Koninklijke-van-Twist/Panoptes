<?php

/**
 * Elk uur aanroepen (bestaand serverscript, GET vanaf localhost).
 * Ververst de cache als die verlopen is, werkt de snapshot van vandaag bij en
 * verstuurt op vrijdag vanaf 07:00 eenmalig de weekmail.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/panoptes_lib.php';

ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$loaded = panoptes_load(false);
$summary = panoptes_summary($loaded);
panoptes_store_snapshot(panoptes_today(), $summary);

echo "Panoptes hourly OK\n";
echo 'hour=' . panoptes_hour() . "\n";
echo 'mail=' . panoptes_maybe_send_weekly($summary, !empty($loaded['copilot']['not_linked'])) . "\n";
