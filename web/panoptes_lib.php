<?php

/**
 * Panoptes: Copilot- en AI-creditverbruik (Microsoft Graph beta + Power Platform licensing API).
 * Configuratie in auth.php ($panoptesTenantId, $panoptesClientId, $panoptesClientSecret, $reportMail).
 * Alle data (cache, instellingen, dagsnapshots, mail-state) staat in web/data/ en blijft
 * bij een deploy staan (deploy-ftp.yml slaat data/** over).
 */

const PANOPTES_CACHE_TTL = 3600; // bron is ~4 uur oud; 1 uur cache is ruim voldoende
const PANOPTES_GRAPH_URL = 'https://graph.microsoft.com/beta/copilot/costManagement/getTenantCreditBalance';
const PANOPTES_PP_URL = 'https://api.powerplatform.com/licensing/tenantCapacity/currencyReports?includeAllocations=true&includeConsumptions=true&api-version=2024-10-01';
const PANOPTES_MAIL_SUBJECT = 'Co-Pilot Verbruik';

/**
 * Opslag
 */

function panoptes_data_dir(): string
{
    $override = $GLOBALS['PANOPTES_DATA_DIR'] ?? null;
    $dir = is_string($override) && trim($override) !== '' ? $override : (__DIR__ . '/data');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }

    return $dir;
}

function panoptes_read_json(string $name, array $default = []): array
{
    $path = panoptes_data_dir() . '/' . $name;
    $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    return is_array($decoded) ? $decoded : $default;
}

function panoptes_write_json(string $name, array $payload): void
{
    $path = panoptes_data_dir() . '/' . $name;
    $tmp = $path . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, $path);
}

function panoptes_today(): string
{
    $override = $GLOBALS['PANOPTES_TODAY'] ?? null;
    if (is_string($override) && $override !== '') {
        return $override;
    }

    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');
}

/**
 * Instellingen (door beheerders aan te passen op de pagina)
 */

function panoptes_settings(): array
{
    $raw = panoptes_read_json('settings.json');
    $email = strtolower(trim((string) ($raw['mail_to'] ?? '')));
    $total = $raw['cowork_limit_total'] ?? null;

    return [
        'cowork_limit_user' => max(0, (int) ($raw['cowork_limit_user'] ?? 2000)),
        'cowork_limit_total' => ($total === null || $total === '') ? null : max(0, (int) $total),
        'mail_to' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'ict@kvt.nl',
        'mail_enabled' => array_key_exists('mail_enabled', $raw) ? (bool) $raw['mail_enabled'] : true,
    ];
}

function panoptes_save_settings(array $input): array
{
    $total = trim((string) ($input['cowork_limit_total'] ?? ''));
    panoptes_write_json('settings.json', [
        'cowork_limit_user' => (int) ($input['cowork_limit_user'] ?? 2000),
        'cowork_limit_total' => $total === '' ? null : (int) $total,
        'mail_to' => (string) ($input['mail_to'] ?? ''),
        'mail_enabled' => !empty($input['mail_enabled']),
    ]);

    return panoptes_settings();
}

function panoptes_is_admin(string $email): bool
{
    global $panoptesAdmins, $allowedUsers;
    $list = is_array($panoptesAdmins ?? null) ? $panoptesAdmins : (is_array($allowedUsers ?? null) ? $allowedUsers : []);
    foreach ($list as $admin) {
        if (strtolower(trim((string) $admin)) === strtolower(trim($email)) && $email !== '') {
            return true;
        }
    }

    return false;
}

/**
 * HTTP en tokens (client credentials, app-only)
 */

function panoptes_http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    if (isset($GLOBALS['PANOPTES_HTTP']) && is_callable($GLOBALS['PANOPTES_HTTP'])) {
        return $GLOBALS['PANOPTES_HTTP']($method, $url, $headers, $body);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $response === false ? '' : (string) $response, 'error' => $error];
}

function panoptes_token(string $scope): string
{
    global $panoptesTenantId, $panoptesClientId, $panoptesClientSecret;
    if (empty($panoptesTenantId) || empty($panoptesClientId) || empty($panoptesClientSecret)) {
        throw new RuntimeException('Tenant ID, client ID of client secret ontbreekt in auth.php.');
    }

    $res = panoptes_http('POST', 'https://login.microsoftonline.com/' . rawurlencode((string) $panoptesTenantId) . '/oauth2/v2.0/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => (string) $panoptesClientId,
            'client_secret' => (string) $panoptesClientSecret,
            'scope' => $scope,
        ]));
    $json = json_decode($res['body'], true);
    if ($res['status'] !== 200 || !is_array($json) || empty($json['access_token'])) {
        $desc = is_array($json) ? (string) ($json['error_description'] ?? $json['error'] ?? '') : $res['error'];
        throw new RuntimeException('Token ophalen mislukt (HTTP ' . $res['status'] . '): ' . strtok($desc, "\r\n"));
    }

    return (string) $json['access_token'];
}

/**
 * Haalt een bron op. Resultaat: ['ok' => bool, 'status' => int, 'data' => array|null, 'error' => string, 'fetched_at' => int].
 * Fouten (403, onverwachte vorm) worden als resultaat teruggegeven zodat de pagina ze netjes toont.
 */
function panoptes_fetch_source(string $url, string $scope): array
{
    $result = ['ok' => false, 'status' => 0, 'data' => null, 'error' => '', 'fetched_at' => time()];
    try {
        $res = panoptes_http('GET', $url, ['Authorization: Bearer ' . panoptes_token($scope), 'Accept: application/json']);
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();

        return $result;
    }

    $result['status'] = $res['status'];
    $json = json_decode($res['body'], true);
    $result['data'] = is_array($json) ? $json : null;
    if ($res['status'] < 200 || $res['status'] >= 300) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? $json['message'] ?? '') : '';
        $hint = $res['status'] === 403 ? ' (ontbrekende toestemming/admin consent?)' : '';
        $result['error'] = 'HTTP ' . $res['status'] . $hint . ($msg !== '' ? ': ' . $msg : ($res['error'] !== '' ? ': ' . $res['error'] : ''));

        return $result;
    }
    if (!is_array($json)) {
        $result['error'] = 'Antwoord is geen geldige JSON.';

        return $result;
    }
    $result['ok'] = true;

    return $result;
}

/**
 * Haalt beide bronnen op (met cache in data/cache.json).
 */
function panoptes_load(bool $refresh = false): array
{
    $cache = panoptes_read_json('cache.json');
    $fresh = isset($cache['fetched_at']) && (time() - (int) $cache['fetched_at']) < PANOPTES_CACHE_TTL;
    if (!$refresh && $fresh) {
        return $cache;
    }

    $copilot = panoptes_fetch_source(PANOPTES_GRAPH_URL, 'https://graph.microsoft.com/.default');
    $pp = panoptes_fetch_source(PANOPTES_PP_URL, 'https://api.powerplatform.com/.default');
    // Bij een fout de laatste goede data bewaren, met de nieuwe foutmelding erbij.
    foreach (['copilot' => $copilot, 'powerplatform' => $pp] as $key => $source) {
        if (!$source['ok'] && !empty($cache[$key]['ok'])) {
            $cache[$key]['error'] = $source['error'];
            $cache[$key]['stale'] = true;
            continue;
        }
        $cache[$key] = $source;
    }
    $cache['fetched_at'] = time();
    panoptes_write_json('cache.json', $cache);

    return $cache;
}

/**
 * Parsing (defensief: veldnamen zijn nog niet geverifieerd tegen een echt antwoord)
 */

function panoptes_num($value): ?float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    if (is_string($value) && is_numeric(trim($value))) {
        return (float) trim($value);
    }

    return null;
}

/**
 * Graph getTenantCreditBalance -> ['rows' => [...], 'last_modified' => ?string, 'recognized' => bool].
 */
function panoptes_parse_copilot(?array $data): array
{
    $out = ['rows' => [], 'last_modified' => null, 'recognized' => false];
    if (!is_array($data)) {
        return $out;
    }
    $root = is_array($data['value'] ?? null) && !array_is_list($data['value']) ? $data['value'] : $data;
    $out['last_modified'] = isset($root['dataStatus']['lastModifiedDateTime']) ? (string) $root['dataStatus']['lastModifiedDateTime'] : null;
    $items = $root['billingMethodBalances'] ?? null;
    if (!is_array($items)) {
        return $out;
    }
    $out['recognized'] = true;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $purchased = panoptes_num($item['purchasedQuantity'] ?? null);
        $consumed = panoptes_num($item['consumedQuantity'] ?? null);
        $remaining = panoptes_num($item['remainingQuantity'] ?? null);
        if ($remaining === null && $purchased !== null && $consumed !== null) {
            $remaining = $purchased - $consumed;
        }
        $out['rows'][] = [
            'name' => (string) ($item['billingMethod'] ?? 'onbekend'),
            'purchased' => $purchased,
            'consumed' => $consumed,
            'remaining' => $remaining,
        ];
    }

    return $out;
}

/**
 * Power Platform currencyReports -> ['rows' => [...], 'recognized' => bool].
 */
function panoptes_parse_powerplatform(?array $data): array
{
    $out = ['rows' => [], 'recognized' => false];
    if (!is_array($data)) {
        return $out;
    }
    $items = array_is_list($data) ? $data : ($data['value'] ?? null);
    if (!is_array($items)) {
        return $out;
    }
    $out['recognized'] = true;
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['currencyType'])) {
            continue;
        }
        $consumed = $item['consumed'] ?? null;
        $out['rows'][] = [
            'name' => (string) $item['currencyType'],
            'purchased' => panoptes_num($item['purchased'] ?? null),
            'allocated' => panoptes_num($item['allocated'] ?? null),
            'consumed' => panoptes_num(is_array($consumed) ? ($consumed['unitsConsumed'] ?? null) : $consumed),
            'last_updated' => is_array($consumed) && isset($consumed['lastUpdatedDay']) ? (string) $consumed['lastUpdatedDay'] : null,
        ];
    }

    return $out;
}

/**
 * Compacte stand voor snapshots en mail: ['copilot' => [naam => [...]], 'powerplatform' => [naam => [...]]].
 */
function panoptes_summary(array $loaded): array
{
    $summary = ['copilot' => [], 'powerplatform' => []];
    foreach (panoptes_parse_copilot($loaded['copilot']['data'] ?? null)['rows'] as $row) {
        $summary['copilot'][$row['name']] = ['purchased' => $row['purchased'], 'consumed' => $row['consumed'], 'remaining' => $row['remaining']];
    }
    foreach (panoptes_parse_powerplatform($loaded['powerplatform']['data'] ?? null)['rows'] as $row) {
        $summary['powerplatform'][$row['name']] = ['purchased' => $row['purchased'], 'allocated' => $row['allocated'], 'consumed' => $row['consumed']];
    }

    return $summary;
}

/**
 * Historie (data/history.json: datum => summary)
 */

function panoptes_history(): array
{
    $history = panoptes_read_json('history.json');
    ksort($history);

    return $history;
}

function panoptes_store_snapshot(string $date, array $summary): void
{
    if ($summary['copilot'] === [] && $summary['powerplatform'] === []) {
        return; // geen lege dagen opslaan bij een fout
    }
    $history = panoptes_history();
    $history[$date] = $summary;
    panoptes_write_json('history.json', $history);
}

/**
 * Laatste snapshot per periode (ISO-week 'o-\WW' of maand 'Y-m'), oudste eerst.
 */
function panoptes_history_by_period(array $history, string $format): array
{
    $periods = [];
    foreach ($history as $date => $summary) {
        $periods[(new DateTimeImmutable($date))->format($format)] = $summary;
    }

    return $periods;
}

/**
 * Snapshot van (ongeveer) een week geleden: de laatste snapshot op of voor $date - 7 dagen.
 */
function panoptes_snapshot_week_before(array $history, string $date): ?array
{
    $limit = (new DateTimeImmutable($date))->modify('-7 days')->format('Y-m-d');
    $found = null;
    foreach ($history as $day => $summary) {
        if ($day <= $limit) {
            $found = $summary;
        }
    }

    return $found;
}

/**
 * Wekelijkse mail (vrijdag, max 1x per ISO-week)
 */

function panoptes_week_key(string $date): string
{
    return (new DateTimeImmutable($date))->format('o-\WW');
}

const PANOPTES_MAIL_HOUR = 7; // vrijdag vanaf 07:00 (Europe/Amsterdam)

function panoptes_hour(): int
{
    $override = $GLOBALS['PANOPTES_HOUR'] ?? null;

    return is_int($override) ? $override : (int) (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('G');
}

function panoptes_mail_due(string $date, array $state, int $hour = PANOPTES_MAIL_HOUR): bool
{
    if ((new DateTimeImmutable($date))->format('N') !== '5' || $hour < PANOPTES_MAIL_HOUR) {
        return false;
    }

    return ($state['last_week'] ?? '') !== panoptes_week_key($date);
}

function panoptes_fmt(?float $value): string
{
    return $value === null ? '–' : number_format($value, 0, ',', '.');
}

function panoptes_delta(?float $now, ?float $before): string
{
    if ($now === null || $before === null) {
        return '–';
    }
    $diff = $now - $before;

    return ($diff > 0 ? '+' : '') . number_format($diff, 0, ',', '.');
}

function panoptes_mail_tables(array $summary, ?array $previous, array $settings): array
{
    $sections = [
        'copilot' => ['Copilot-credits', ['purchased' => 'Gekocht', 'consumed' => 'Verbruikt', 'remaining' => 'Resterend']],
        'powerplatform' => ['AI Builder / Power Platform', ['purchased' => 'Gekocht', 'allocated' => 'Toegewezen', 'consumed' => 'Verbruikt']],
    ];
    $plain = '';
    $html = '';
    foreach ($sections as $key => [$title, $cols]) {
        $plain .= "\n" . $title . "\n";
        $html .= '<h3>' . htmlspecialchars($title) . '</h3><table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse"><tr><th>Categorie</th>';
        foreach ($cols as $label) {
            $html .= '<th>' . $label . '</th>';
        }
        $html .= '<th>Verbruik t.o.v. vorige week</th></tr>';
        if (($summary[$key] ?? []) === []) {
            $plain .= "  geen data\n";
            $html .= '<tr><td colspan="' . (count($cols) + 2) . '">geen data</td></tr>';
        }
        foreach ($summary[$key] ?? [] as $name => $values) {
            $delta = panoptes_delta($values['consumed'] ?? null, $previous[$key][$name]['consumed'] ?? null);
            $parts = [];
            $html .= '<tr><td>' . htmlspecialchars((string) $name) . '</td>';
            foreach ($cols as $field => $label) {
                $parts[] = $label . ' ' . panoptes_fmt($values[$field] ?? null);
                $html .= '<td align="right">' . panoptes_fmt($values[$field] ?? null) . '</td>';
            }
            $html .= '<td align="right">' . $delta . '</td></tr>';
            $plain .= '  ' . $name . ': ' . implode(', ', $parts) . ' (verbruik vs vorige week: ' . $delta . ")\n";
        }
        $html .= '</table>';
    }
    $limits = 'Cowork-limiet per gebruiker: ' . panoptes_fmt((float) $settings['cowork_limit_user'])
        . ', totaal per maand: ' . ($settings['cowork_limit_total'] === null ? 'niet ingesteld' : panoptes_fmt((float) $settings['cowork_limit_total']));
    $plain .= "\n" . $limits . "\n\nhttps://sleutels.kvt.nl/panoptes/\n";
    $html .= '<p>' . $limits . '</p><p><a href="https://sleutels.kvt.nl/panoptes/">sleutels.kvt.nl/panoptes</a></p>';

    return ['plain' => 'Copilot- en AI-creditverbruik KVT' . "\n" . $plain, 'html' => '<h2>Copilot- en AI-creditverbruik KVT</h2>' . $html];
}

/**
 * Verstuurt de weekmail als die vandaag (vrijdag) nog niet ging. Geeft een korte status terug.
 */
function panoptes_maybe_send_weekly(array $summary): string
{
    $date = panoptes_today();
    $settings = panoptes_settings();
    $state = panoptes_read_json('mail_state.json');
    if (!$settings['mail_enabled']) {
        return 'uitgeschakeld';
    }
    if (!panoptes_mail_due($date, $state, panoptes_hour())) {
        return 'niet nodig';
    }
    if ($summary['copilot'] === [] && $summary['powerplatform'] === []) {
        return 'geen data';
    }

    $lock = fopen(panoptes_data_dir() . '/mail.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return 'bezig';
    }
    try {
        $state = panoptes_read_json('mail_state.json'); // opnieuw lezen binnen de lock
        if (!panoptes_mail_due($date, $state, panoptes_hour())) {
            return 'niet nodig';
        }
        $body = panoptes_mail_tables($summary, panoptes_snapshot_week_before(panoptes_history(), $date), $settings);
        if (!panoptes_mail_send($settings['mail_to'], PANOPTES_MAIL_SUBJECT, $body['plain'], $body['html'])) {
            return 'mislukt';
        }
        panoptes_write_json('mail_state.json', ['last_week' => panoptes_week_key($date), 'sent_at' => date('c'), 'to' => $settings['mail_to']]);

        return 'verzonden naar ' . $settings['mail_to'];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Mail versturen: zelfde mechanisme als Elpis/Ponos ($reportMail uit auth.php, SMTP of PHP mail()).
 */

function panoptes_mail_smtp_settings(): ?array
{
    global $reportMail;
    if (!is_array($reportMail ?? null)) {
        return null;
    }

    $smtp = $reportMail['smtp'] ?? null;
    if (!is_array($smtp)) {
        return null;
    }

    $host = trim((string) ($smtp['host'] ?? ''));
    if ($host === '') {
        return null;
    }

    return [
        'host' => $host,
        'port' => (int) ($smtp['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($smtp['encryption'] ?? 'tls'))),
        'username' => trim((string) ($smtp['username'] ?? '')),
        'password' => (string) ($smtp['password'] ?? ''),
        'timeout' => max(5, (int) ($smtp['timeout'] ?? 20)),
    ];
}

function panoptes_mail_smtp_read($socket): string
{
    $data = '';
    while (is_resource($socket) && !feof($socket)) {
        $line = fgets($socket, 8192);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function panoptes_mail_smtp_expect(string $response, array $codes): bool
{
    if ($response === '') {
        return false;
    }

    return in_array((int) substr($response, 0, 3), $codes, true);
}

function panoptes_mail_smtp_dot_stuff(string $body): string
{
    $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
    $stuffed = [];
    foreach ($lines as $line) {
        if ($line !== '' && $line[0] === '.') {
            $line = '.' . $line;
        }
        $stuffed[] = $line;
    }

    return implode("\r\n", $stuffed);
}

function panoptes_mail_send_smtp(
    string $to,
    string $fromEmail,
    string $fromName,
    string $encodedSubject,
    string $mimeHeaders,
    string $body
): bool {
    $smtp = panoptes_mail_smtp_settings();
    if ($smtp === null) {
        return false;
    }

    $remote = ($smtp['encryption'] === 'ssl' ? 'ssl://' : 'tcp://') . $smtp['host'] . ':' . $smtp['port'];
    $socket = @stream_socket_client($remote, $errno, $errstr, $smtp['timeout'], STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, $smtp['timeout']);
    $fail = static function ($socket): bool {
        fclose($socket);

        return false;
    };

    if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [220])) {
        return $fail($socket);
    }

    fwrite($socket, "EHLO elpis.local\r\n");
    if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [250])) {
        return $fail($socket);
    }

    if ($smtp['encryption'] === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [220])) {
            return $fail($socket);
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            return $fail($socket);
        }

        fwrite($socket, "EHLO elpis.local\r\n");
        if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [250])) {
            return $fail($socket);
        }
    }

    if ($smtp['username'] !== '' && $smtp['password'] !== '') {
        fwrite($socket, "AUTH LOGIN\r\n");
        if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [334])) {
            return $fail($socket);
        }
        fwrite($socket, base64_encode($smtp['username']) . "\r\n");
        if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [334])) {
            return $fail($socket);
        }
        fwrite($socket, base64_encode($smtp['password']) . "\r\n");
        if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [235])) {
            return $fail($socket);
        }
    }

    fwrite($socket, 'MAIL FROM:<' . $fromEmail . ">\r\n");
    if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [250])) {
        return $fail($socket);
    }
    fwrite($socket, 'RCPT TO:<' . $to . ">\r\n");
    if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [250, 251])) {
        return $fail($socket);
    }
    fwrite($socket, "DATA\r\n");
    if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [354])) {
        return $fail($socket);
    }

    $payload = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail) . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $encodedSubject . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n\r\n"
        . panoptes_mail_smtp_dot_stuff($body);
    fwrite($socket, $payload . "\r\n.\r\n");
    if (!panoptes_mail_smtp_expect(panoptes_mail_smtp_read($socket), [250])) {
        return $fail($socket);
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}

function panoptes_mail_send(string $to, string $subject, string $plain, string $html): bool
{
    $to = strtolower(trim($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (isset($GLOBALS['PANOPTES_MAIL_SENDER']) && is_callable($GLOBALS['PANOPTES_MAIL_SENDER'])) {
        return (bool) $GLOBALS['PANOPTES_MAIL_SENDER']($to, $subject, $plain, $html);
    }

    global $reportMail;
    $fromEmail = 'kvtbot@kvt.nl';
    $fromName = 'Panoptes';
    if (is_array($reportMail ?? null)) {
        $fromEmail = (string) ($reportMail['from_email'] ?? $fromEmail);
        $fromName = (string) ($reportMail['from_name'] ?? $fromName);
    }

    $boundary = 'panoptes_' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $mimeHeaders = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $plain . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n"
        . '--' . $boundary . '--';

    if (panoptes_mail_smtp_settings() !== null && panoptes_mail_send_smtp($to, $fromEmail, $fromName, $encodedSubject, $mimeHeaders, $body)) {
        return true;
    }

    $headers = "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n"
        . 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);

    return @mail($to, $encodedSubject, $body, $headers);
}
