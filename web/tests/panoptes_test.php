<?php
/**
 * Parsing van de API-antwoorden en de 1x-per-week mailguard. Verstuurt nooit echte mail.
 * Run: php web/tests/panoptes_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['PANOPTES_DATA_DIR'] = sys_get_temp_dir() . '/panoptes-test-' . getmypid();
$sent = [];
$GLOBALS['PANOPTES_MAIL_SENDER'] = function (string $to, string $subject) use (&$sent): bool {
    $sent[] = [$to, $subject];
    return true;
};
require dirname(__DIR__) . '/panoptes_lib.php';

function check(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

// Graph getTenantCreditBalance (verwachte vorm, nog niet geverifieerd)
$copilot = panoptes_parse_copilot(json_decode('{
  "@odata.context": "https://graph.microsoft.com/beta/$metadata#microsoft.graph.copilotTenantCreditBalance",
  "billingMethodBalances": [
    {"billingMethod": "prepaid", "purchasedQuantity": 25000, "consumedQuantity": 7300.5, "remainingQuantity": 17699.5},
    {"billingMethod": "payAsYouGo", "purchasedQuantity": "0", "consumedQuantity": "120"}
  ],
  "dataStatus": {"lastModifiedDateTime": "2026-11-04T08:00:00Z"}
}', true));
check($copilot['recognized'] && count($copilot['rows']) === 2, 'copilot rijen');
check($copilot['rows'][0]['remaining'] === 17699.5, 'copilot resterend');
check($copilot['rows'][1]['remaining'] === -120.0, 'resterend berekend als het ontbreekt');
check($copilot['last_modified'] === '2026-11-04T08:00:00Z', 'lastModifiedDateTime');
check(!panoptes_parse_copilot(['foo' => 1])['recognized'], 'onbekende vorm wordt herkend als onbekend');
check(!panoptes_parse_copilot(null)['recognized'], 'null');

// Power Platform currencyReports (verwachte vorm, nog niet geverifieerd)
$pp = panoptes_parse_powerplatform(json_decode('[
  {"currencyType": "AI_BUILDER_CREDITS", "purchased": 1000000, "allocated": 500000, "consumed": {"unitsConsumed": 123456, "lastUpdatedDay": "2026-11-03"}},
  {"currencyType": "COPILOT_STUDIO_MESSAGES", "purchased": 25000, "allocated": 0, "consumed": {"unitsConsumed": 900}}
]', true));
check($pp['recognized'] && count($pp['rows']) === 2, 'pp rijen');
check($pp['rows'][0]['consumed'] === 123456.0 && $pp['rows'][0]['last_updated'] === '2026-11-03', 'pp verbruik');
check(count(panoptes_parse_powerplatform(['value' => [['currencyType' => 'X', 'purchased' => 1]]])['rows']) === 1, 'pp met value-wrapper');

// Mailguard: alleen vrijdag vanaf 07:00, max 1x per ISO-week
$friday = '2026-11-06';
check(!panoptes_mail_due('2026-11-05', [], 9), 'donderdag niet');
check(!panoptes_mail_due($friday, [], 6), 'vrijdag 06:00 nog niet');
check(panoptes_mail_due($friday, [], 7), 'vrijdag 07:00 wel');
check(!panoptes_mail_due($friday, ['last_week' => '2026-W45'], 9), 'zelfde week niet nog eens');
check(panoptes_mail_due('2026-11-13', ['last_week' => '2026-W45'], 9), 'volgende week weer');

$summary = panoptes_summary(['copilot' => ['data' => ['billingMethodBalances' => [['billingMethod' => 'prepaid', 'purchasedQuantity' => 100, 'consumedQuantity' => 40]]]], 'powerplatform' => ['data' => []]]);
panoptes_store_snapshot('2026-10-30', ['copilot' => ['prepaid' => ['purchased' => 100, 'consumed' => 25, 'remaining' => 75]], 'powerplatform' => []]);
$GLOBALS['PANOPTES_TODAY'] = $friday;
$GLOBALS['PANOPTES_HOUR'] = 8;
check(panoptes_maybe_send_weekly($summary) === 'verzonden naar ict@kvt.nl', 'eerste run verstuurt');
check(panoptes_maybe_send_weekly($summary) === 'niet nodig', 'tweede run niet');
check(count($sent) === 1 && $sent[0] === ['ict@kvt.nl', 'Co-Pilot Verbruik'], 'precies 1 mail met juist onderwerp');
$body = panoptes_mail_tables($summary, panoptes_snapshot_week_before(panoptes_history(), $friday), panoptes_settings());
check(str_contains($body['plain'], '+15'), 'verschil t.o.v. vorige week');

foreach (array_diff(scandir($GLOBALS['PANOPTES_DATA_DIR']) ?: [], ['.', '..']) as $file) {
    unlink($GLOBALS['PANOPTES_DATA_DIR'] . '/' . $file);
}
@rmdir($GLOBALS['PANOPTES_DATA_DIR']);
echo "OK\n";
