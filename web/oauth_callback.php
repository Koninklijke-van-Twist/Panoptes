<?php

/**
 * Koppelt het Copilot-account (gedelegeerd, eenmalig door een beheerder).
 * ?start=1 stuurt door naar Microsoft; Microsoft stuurt terug met ?code=&state=.
 * Deze URL moet als Web-redirect-URI in de app-registratie staan ($panoptesRedirectUri).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/panoptes_lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
if (!panoptes_is_admin($email)) {
    http_response_code(403);
    exit('Alleen beheerders kunnen het Copilot-account koppelen.');
}

if (isset($_GET['start'])) {
    $start = panoptes_oauth_start();
    $_SESSION['panoptes_oauth'] = ['state' => $start['state'], 'verifier' => $start['verifier']];
    header('Location: ' . $start['url']);
    exit;
}

$pending = $_SESSION['panoptes_oauth'] ?? [];
unset($_SESSION['panoptes_oauth']);
$result = 'ok';
if (isset($_GET['error'])) {
    $result = 'Microsoft: ' . (string) ($_GET['error_description'] ?? $_GET['error']);
} elseif (!is_array($pending) || !hash_equals((string) ($pending['state'] ?? ''), (string) ($_GET['state'] ?? '')) || ($pending['state'] ?? '') === '') {
    $result = 'Ongeldige of verlopen koppelpoging (state). Probeer opnieuw.';
} else {
    try {
        panoptes_oauth_exchange((string) ($_GET['code'] ?? ''), (string) $pending['verifier']);
        @unlink(panoptes_data_dir() . '/cache.json'); // direct opnieuw ophalen met het nieuwe token
    } catch (Throwable $e) {
        $result = $e->getMessage();
    }
}
$_SESSION['panoptes_link_result'] = $result;
header('Location: index.php');
