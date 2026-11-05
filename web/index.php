<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/panoptes_lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (empty($_SESSION['panoptes_csrf'])) {
    $_SESSION['panoptes_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['panoptes_csrf'];
$linkResult = $_SESSION['panoptes_link_result'] ?? null;
unset($_SESSION['panoptes_link_result']);
$userEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));

require_once __DIR__ . '/localization.php';

/**
 * Functies
 */

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function panoptes_dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '–';
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i');
    } catch (Throwable $e) {
        return $value;
    }
}

function panoptes_render_error(array $source): void
{
    if (($source['error'] ?? '') === '') {
        return;
    }
    echo '<p class="error">' . h(LOC('panoptes.error')) . ': ' . h((string) $source['error']) . '</p>';
    if (!empty($source['stale'])) {
        echo '<p class="muted">' . h(LOC('panoptes.stale', date('d-m-Y H:i', (int) $source['fetched_at']))) . '</p>';
    }
}

function panoptes_render_raw(array $source): void
{
    if (is_array($source['data'] ?? null)) {
        echo '<p class="muted">' . h(LOC('panoptes.unrecognized')) . '</p><pre>'
            . h(json_encode($source['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }
}

function panoptes_render_cost_cell(?float $consumed, string $category): string
{
    $rate = panoptes_credit_rate_for($category);
    $unknown = ($rate['usd_per_unit'] ?? null) === null;
    $cost = panoptes_estimate_cost($consumed, $category);
    $label = panoptes_fmt_usd($cost, $unknown);
    if ($unknown && $consumed === null) {
        $label = '–';
    }
    $title = h(panoptes_credit_rate_tooltip($category));
    $extra = (($rate['kind'] ?? '') === 'approx' && !$unknown) ? ' <span class="muted">(' . h(LOC('panoptes.cost.approx_note')) . ')</span>' : '';

    return '<td class="num" title="' . $title . '">' . h($label) . $extra . '</td>';
}

function panoptes_render_cost_total(array $rows): void
{
    $total = 0.0;
    $any = false;
    foreach ($rows as $row) {
        $cost = panoptes_estimate_cost($row['consumed'] ?? null, (string) ($row['name'] ?? ''));
        if ($cost !== null) {
            $total += $cost;
            $any = true;
        }
    }
    if (!$any) {
        return;
    }
    echo '<p class="muted" title="' . h(LOC('panoptes.cost.disclaimer')) . '"><strong>'
        . h(LOC('panoptes.cost.total')) . ':</strong> ' . h(panoptes_fmt_usd($total)) . '</p>';
}

function panoptes_render_trend(array $periods, string $key): void
{
    $periods = array_slice($periods, -12, null, true);
    $names = [];
    foreach ($periods as $summary) {
        foreach (array_keys($summary[$key] ?? []) as $name) {
            $names[$name] = true;
        }
    }
    if ($names === []) {
        echo '<p class="muted">' . h(LOC('panoptes.trend.source_empty')) . '</p>';
        return;
    }
    echo '<table><tr><th>' . h(LOC('panoptes.col.period')) . '</th>';
    foreach (array_keys($names) as $name) {
        echo '<th>' . h((string) $name) . '</th>';
        echo '<th class="num">' . h(LOC('panoptes.col.est_cost')) . '</th>';
    }
    echo '</tr>';
    $previous = null;
    foreach ($periods as $period => $summary) {
        echo '<tr><td>' . h((string) $period) . '</td>';
        foreach (array_keys($names) as $name) {
            $now = $summary[$key][$name]['consumed'] ?? null;
            $before = $previous[$key][$name]['consumed'] ?? null;
            $delta = $previous === null ? '' : ' <span class="muted">(' . panoptes_delta($now, $before) . ')</span>';
            echo '<td class="num">' . panoptes_fmt($now) . $delta . '</td>';
            // Kosten van het periodedelda (verbruik in deze periode), anders absolute stand.
            $units = $previous === null || $now === null || $before === null ? $now : ($now - $before);
            echo panoptes_render_cost_cell($units, (string) $name);
        }
        echo '</tr>';
        $previous = $summary;
    }
    echo '</table>';
}

/**
 * Page load
 */

$isAdmin = panoptes_is_admin($userEmail);
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $isAdmin && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
    panoptes_save_settings($_POST);
    $notice = LOC('panoptes.settings.saved');
}

$doRefresh = isset($_GET['refresh']) && $isAdmin;
$loaded = panoptes_load($doRefresh);
if ($doRefresh) {
    // Zelfde snapshot als hourly/nightly, zodat week/maand meteen de verse stand tonen.
    panoptes_store_snapshot(panoptes_today(), panoptes_summary($loaded));
}
$settings = panoptes_settings();
$copilot = panoptes_parse_copilot($loaded['copilot']['data'] ?? null);
$pp = panoptes_parse_powerplatform($loaded['powerplatform']['data'] ?? null);
$history = panoptes_history();
$weeks = panoptes_history_by_period($history, 'o-\WW');
$months = panoptes_history_by_period($history, 'Y-m');
$mailState = panoptes_read_json('mail_state.json');
$link = panoptes_delegated_status();
if ($linkResult !== null) {
    $notice = $linkResult === 'ok' ? LOC('panoptes.link.done') : LOC('panoptes.link.failed') . ': ' . $linkResult;
}
?><!DOCTYPE html>
<html lang="<?= h(getHtmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(LOC('app.title')) ?></title>
    <link rel="stylesheet" href="brand.css?v=<?= (int) @filemtime(__DIR__ . '/brand.css') ?>">
    <link rel="manifest" href="site.webmanifest">
    <link rel="icon" href="panoptes.svg" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <?php renderLanguageSwitcherStyles(); ?>
    <style>
        body { margin: 0; }
        main { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        header img { max-height: 44px; }
        section { background: var(--kvt-panel-bg); border: 1px solid var(--kvt-line); border-radius: 10px; padding: 16px 20px; margin-top: 18px; }
        h1 { color: var(--kvt-perkins-blue); margin: 12px 0 4px; }
        h2 { color: var(--kvt-perkins-blue); font-size: 1.15rem; margin-top: 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--kvt-line); }
        th { background: var(--kvt-page-bg); }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: var(--kvt-muted); font-size: .9rem; }
        .error { color: var(--kvt-danger); font-weight: 700; }
        .notice { background: var(--kvt-row-ok); padding: 8px 12px; border-radius: 6px; }
        pre { background: #f6f8fa; padding: 10px; overflow: auto; max-height: 300px; font-size: .8rem; }
        form label { display: block; margin: 8px 0 4px; }
        form input[type=number], form input[type=email] { padding: 6px; width: 260px; }
        button, .btn { background: var(--kvt-main-blue); color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; cursor: pointer; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
<?php renderLanguageSwitcher(); ?>
<main>
    <header>
        <img src="logo-website.png" alt="KVT">
        <h1><?= h(LOC('app.title')) ?></h1>
        <p class="muted"><?= h(LOC('panoptes.subtitle')) ?>
            <?= h(LOC('panoptes.fetched', isset($loaded['fetched_at']) ? date('d-m-Y H:i', (int) $loaded['fetched_at']) : '–')) ?>
            <?php if ($isAdmin): ?> · <a href="?refresh=1"><?= h(LOC('panoptes.refresh')) ?></a><?php endif; ?>
        </p>
        <?php if ($notice !== ''): ?><p class="notice"><?= h($notice) ?></p><?php endif; ?>
    </header>

    <section>
        <h2><?= h(LOC('panoptes.copilot')) ?></h2>
        <?php if (!empty($loaded['copilot']['not_linked']) || !$link['linked']): ?>
            <p class="error"><?= h(PANOPTES_NOT_LINKED) ?></p>
        <?php else: panoptes_render_error($loaded['copilot'] ?? []); endif; ?>
        <?php if ($isAdmin): ?>
            <p class="muted">
                <?= $link['linked'] ? h(LOC('panoptes.link.by', $link['by'] !== '' ? $link['by'] : '?', panoptes_dt($link['at']))) . ' · ' : '' ?>
                <a class="btn" href="oauth_callback.php?start=1"><?= h(LOC('panoptes.link.button')) ?></a>
            </p>
        <?php endif; ?>
        <?php if ($copilot['recognized']): ?>
            <?php if ($copilot['rows'] === []): ?><p class="muted"><?= h(LOC('panoptes.empty')) ?></p><?php else: ?>
            <p class="muted"><?= h(LOC('panoptes.cost.disclaimer')) ?></p>
            <table>
                <tr><th><?= h(LOC('panoptes.col.method')) ?></th><th class="num"><?= h(LOC('panoptes.col.purchased')) ?></th><th class="num"><?= h(LOC('panoptes.col.consumed')) ?></th><th class="num"><?= h(LOC('panoptes.col.remaining')) ?></th><th class="num"><?= h(LOC('panoptes.col.est_cost')) ?></th><th><?= h(LOC('panoptes.col.updated')) ?></th></tr>
                <?php foreach ($copilot['rows'] as $row): ?>
                    <tr><td><?= h($row['name']) ?></td><td class="num"><?= panoptes_fmt($row['purchased']) ?></td><td class="num"><?= panoptes_fmt($row['consumed']) ?></td><td class="num"><?= panoptes_fmt($row['remaining']) ?></td><?= panoptes_render_cost_cell($row['consumed'], $row['name']) ?><td><?= h(panoptes_dt($copilot['last_modified'])) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php panoptes_render_cost_total($copilot['rows']); ?>
            <?php endif; ?>
        <?php else: panoptes_render_raw($loaded['copilot'] ?? []); endif; ?>
    </section>

    <section>
        <h2><?= h(LOC('panoptes.pp')) ?></h2>
        <?php panoptes_render_error($loaded['powerplatform'] ?? []); ?>
        <?php if ($pp['recognized']): ?>
            <?php if ($pp['rows'] === []): ?><p class="muted"><?= h(LOC('panoptes.empty')) ?></p><?php else: ?>
            <p class="muted"><?= h(LOC('panoptes.cost.disclaimer')) ?></p>
            <table>
                <tr><th><?= h(LOC('panoptes.col.category')) ?></th><th class="num"><?= h(LOC('panoptes.col.purchased')) ?></th><th class="num"><?= h(LOC('panoptes.col.allocated')) ?></th><th class="num"><?= h(LOC('panoptes.col.consumed')) ?></th><th class="num"><?= h(LOC('panoptes.col.est_cost')) ?></th><th><?= h(LOC('panoptes.col.updated')) ?></th></tr>
                <?php foreach ($pp['rows'] as $row): ?>
                    <tr><td><?= h($row['name']) ?></td><td class="num"><?= panoptes_fmt($row['purchased']) ?></td><td class="num"><?= panoptes_fmt($row['allocated']) ?></td><td class="num"><?= panoptes_fmt($row['consumed']) ?></td><?= panoptes_render_cost_cell($row['consumed'], $row['name']) ?><td><?= h(panoptes_dt($row['last_updated'])) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php panoptes_render_cost_total($pp['rows']); ?>
            <?php endif; ?>
        <?php else: panoptes_render_raw($loaded['powerplatform'] ?? []); endif; ?>
    </section>

    <?php foreach (['panoptes.trend.week' => $weeks, 'panoptes.trend.month' => $months] as $titleKey => $periods): ?>
    <section>
        <h2><?= h(LOC($titleKey)) ?></h2>
        <?php if ($periods === []): ?>
            <p class="muted"><?= h(LOC('panoptes.trend.empty')) ?></p>
        <?php else: ?>
            <h3><?= h(LOC('panoptes.copilot')) ?></h3><?php panoptes_render_trend($periods, 'copilot'); ?>
            <h3><?= h(LOC('panoptes.pp')) ?></h3><?php panoptes_render_trend($periods, 'powerplatform'); ?>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php if ($isAdmin): ?>
    <section>
        <h2><?= h(LOC('panoptes.settings')) ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label><?= h(LOC('panoptes.settings.mail_to')) ?></label>
            <input type="email" name="mail_to" value="<?= h($settings['mail_to']) ?>">
            <label><input type="checkbox" name="mail_enabled" value="1" <?= $settings['mail_enabled'] ? 'checked' : '' ?>> <?= h(LOC('panoptes.settings.mail_enabled')) ?></label>
            <p><button type="submit"><?= h(LOC('panoptes.settings.save')) ?></button></p>
        </form>
        <p class="muted"><?= h(LOC('panoptes.settings.last_mail', isset($mailState['sent_at']) ? panoptes_dt((string) $mailState['sent_at']) . ' → ' . (string) ($mailState['to'] ?? '') : '–')) ?></p>
    </section>
    <?php endif; ?>
</main>
<?php renderLanguageSwitcherScript(); ?>
</body>
</html>
