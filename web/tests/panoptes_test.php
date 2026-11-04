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
$body = panoptes_mail_tables($summary, panoptes_snapshot_week_before(panoptes_history(), $friday));
check(str_contains($body['plain'], '+15'), 'verschil t.o.v. vorige week');

// Gedelegeerd token: niet gekoppeld, opslag 0600, rotatie, invalid_grant
$panoptesTenantId = 'tenant';
$panoptesClientId = 'client';
$panoptesClientSecret = 'secret';
$calls = [];
$nextToken = [];
$GLOBALS['PANOPTES_HTTP'] = function (string $method, string $url, array $headers, ?string $body) use (&$calls, &$nextToken): array {
    parse_str((string) $body, $form);
    $calls[] = $form;
    if (str_contains($url, '/oauth2/v2.0/token')) {
        return ['status' => isset($nextToken['error']) ? 400 : 200, 'body' => json_encode($nextToken), 'error' => ''];
    }
    return ['status' => 200, 'body' => '{"billingMethodBalances":[]}', 'error' => ''];
};
$fetched = panoptes_fetch_source(PANOPTES_GRAPH_URL, 'panoptes_delegated_token');
check(!$fetched['ok'] && $fetched['not_linked'] && $fetched['error'] === PANOPTES_NOT_LINKED, 'niet gekoppeld');

$idToken = 'x.' . rtrim(strtr(base64_encode(json_encode(['preferred_username' => 'admin@kvt.nl'])), '+/', '-_'), '=') . '.y';
$nextToken = ['access_token' => 'AT1', 'refresh_token' => 'RT1', 'expires_in' => 3600, 'id_token' => $idToken];
panoptes_oauth_exchange('code123', 'verifier123');
check($calls[count($calls) - 1]['grant_type'] === 'authorization_code' && $calls[count($calls) - 1]['code_verifier'] === 'verifier123', 'code + PKCE verifier');
$tokenPath = $GLOBALS['PANOPTES_DATA_DIR'] . '/' . PANOPTES_TOKEN_FILE;
check((fileperms($tokenPath) & 0777) === 0600, 'tokenbestand 0600');
check(panoptes_delegated_status()['linked'] && panoptes_delegated_status()['by'] === 'admin@kvt.nl', 'gekoppeld door');
check(!isset(panoptes_delegated_status()['refresh_token']), 'status bevat geen token');
$count = count($calls);
check(panoptes_delegated_token() === 'AT1' && count($calls) === $count, 'geldig access token uit cache');

$stored = panoptes_delegated_read();
$stored['expires_at'] = time() - 1;
panoptes_delegated_store(['access_token' => 'AT1', 'refresh_token' => 'RT1', 'expires_in' => 0], $stored);
$nextToken = ['access_token' => 'AT2', 'refresh_token' => 'RT2', 'expires_in' => 3600];
check(panoptes_delegated_token() === 'AT2', 'ververst');
check($calls[count($calls) - 1]['refresh_token'] === 'RT1', 'oude refresh token gebruikt');
check(panoptes_delegated_read()['refresh_token'] === 'RT2' && panoptes_delegated_read()['linked_by'] === 'admin@kvt.nl', 'refresh token geroteerd, koppelinfo behouden');

// Power Platform: zelfde refresh token ingewisseld voor andere resource
$nextToken = ['access_token' => 'PP1', 'refresh_token' => 'RT3', 'expires_in' => 3600];
check(panoptes_delegated_pp_token() === 'PP1', 'PP-token via refresh token');
check($calls[count($calls) - 1]['scope'] === PANOPTES_PP_SCOPE . ' offline_access' && $calls[count($calls) - 1]['refresh_token'] === 'RT2', 'PP-scope en refresh token');
check(panoptes_delegated_read()['refresh_token'] === 'RT3' && panoptes_delegated_read()['access_token'] === 'AT2', 'PP roteert refresh token, Graph-token blijft');
$count = count($calls);
check(panoptes_delegated_pp_token() === 'PP1' && count($calls) === $count, 'PP-token uit cache');
$stored = panoptes_delegated_read();
$stored['pp_expires_at'] = time() - 1;
panoptes_delegated_write($stored);
$nextToken = ['error' => 'invalid_grant', 'error_description' => "AADSTS65001: consent\r\nTrace"];
try {
    panoptes_delegated_pp_token();
    check(false, 'PP zonder consent moet falen');
} catch (RuntimeException $e) {
    check(!($e instanceof PanoptesNotLinkedException) && str_contains($e->getMessage(), 'Power Platform API') && !str_contains($e->getMessage(), 'RT3'), 'PP consent-melding');
}
check(panoptes_delegated_status()['linked'], 'Graph-koppeling blijft na PP-consentfout');
$saved = $GLOBALS['PANOPTES_HTTP'];
$GLOBALS['PANOPTES_HTTP'] = fn () => ['status' => 403, 'body' => '{"error":{"code":"Forbidden","message":"User lacks Licensing permission"}}', 'error' => ''];
$fetched = panoptes_fetch_source(PANOPTES_PP_URL, fn () => 'x');
check(str_contains($fetched['error'], 'User lacks Licensing permission'), 'API-fouttekst getoond');
$GLOBALS['PANOPTES_HTTP'] = $saved;
$stored = panoptes_delegated_read();
$stored['refresh_token'] = 'RT2';
panoptes_delegated_write($stored);

$stored = panoptes_delegated_read();
$stored['expires_at'] = time() - 1;
panoptes_delegated_store(['access_token' => '', 'refresh_token' => 'RT2'], $stored);
$nextToken = ['error' => 'invalid_grant', 'error_description' => 'AADSTS70043: expired'];
try {
    panoptes_delegated_token();
    check(false, 'invalid_grant moet falen');
} catch (PanoptesNotLinkedException $e) {
    check($e->getMessage() === PANOPTES_NOT_LINKED, 'invalid_grant -> niet gekoppeld');
}
check(!panoptes_delegated_status()['linked'], 'na invalid_grant ontkoppeld');

// Weekmail vermeldt niet-gekoppeld i.p.v. te falen
$sent = [];
$GLOBALS['PANOPTES_MAIL_SENDER'] = function (string $to, string $subject, string $plain) use (&$sent): bool {
    $sent[] = $plain;
    return true;
};
$GLOBALS['PANOPTES_TODAY'] = '2026-11-13';
check(str_starts_with(panoptes_maybe_send_weekly(['copilot' => [], 'powerplatform' => []], true), 'verzonden'), 'mail ondanks niet gekoppeld');
check(str_contains($sent[0], PANOPTES_NOT_LINKED), 'mail noemt niet gekoppeld');

foreach (array_diff(scandir($GLOBALS['PANOPTES_DATA_DIR']) ?: [], ['.', '..']) as $file) {
    unlink($GLOBALS['PANOPTES_DATA_DIR'] . '/' . $file);
}
@rmdir($GLOBALS['PANOPTES_DATA_DIR']);
echo "OK\n";
