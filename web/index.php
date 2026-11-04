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
        return;
    }
    echo '<table><tr><th>' . h(LOC('panoptes.col.period')) . '</th>';
    foreach (array_keys($names) as $name) {
        echo '<th>' . h((string) $name) . '</th>';
    }
    echo '</tr>';
    $previous = null;
    foreach ($periods as $period => $summary) {
        echo '<tr><td>' . h((string) $period) . '</td>';
        foreach (array_keys($names) as $name) {
            $now = $summary[$key][$name]['consumed'] ?? null;
            $delta = $previous === null ? '' : ' <span class="muted">(' . panoptes_delta($now, $previous[$key][$name]['consumed'] ?? null) . ')</span>';
            echo '<td class="num">' . panoptes_fmt($now) . $delta . '</td>';
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

$loaded = panoptes_load(isset($_GET['refresh']) && $isAdmin);
$settings = panoptes_settings();
$copilot = panoptes_parse_copilot($loaded['copilot']['data'] ?? null);
$pp = panoptes_parse_powerplatform($loaded['powerplatform']['data'] ?? null);
$history = panoptes_history();
$weeks = panoptes_history_by_period($history, 'o-\WW');
$months = panoptes_history_by_period($history, 'Y-m');
$mailState = panoptes_read_json('mail_state.json');
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
        <?php panoptes_render_error($loaded['copilot'] ?? []); ?>
        <?php if ($copilot['recognized']): ?>
            <?php if ($copilot['rows'] === []): ?><p class="muted"><?= h(LOC('panoptes.empty')) ?></p><?php else: ?>
            <table>
                <tr><th><?= h(LOC('panoptes.col.method')) ?></th><th class="num"><?= h(LOC('panoptes.col.purchased')) ?></th><th class="num"><?= h(LOC('panoptes.col.consumed')) ?></th><th class="num"><?= h(LOC('panoptes.col.remaining')) ?></th><th><?= h(LOC('panoptes.col.updated')) ?></th></tr>
                <?php foreach ($copilot['rows'] as $row): ?>
                    <tr><td><?= h($row['name']) ?></td><td class="num"><?= panoptes_fmt($row['purchased']) ?></td><td class="num"><?= panoptes_fmt($row['consumed']) ?></td><td class="num"><?= panoptes_fmt($row['remaining']) ?></td><td><?= h(panoptes_dt($copilot['last_modified'])) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        <?php else: panoptes_render_raw($loaded['copilot'] ?? []); endif; ?>
    </section>

    <section>
        <h2><?= h(LOC('panoptes.cowork')) ?></h2>
        <table>
            <tr><td><?= h(LOC('panoptes.limit.user')) ?></td><td class="num"><?= panoptes_fmt((float) $settings['cowork_limit_user']) ?></td></tr>
            <tr><td><?= h(LOC('panoptes.limit.total')) ?></td><td class="num"><?= $settings['cowork_limit_total'] === null ? h(LOC('panoptes.limit.unset')) : panoptes_fmt((float) $settings['cowork_limit_total']) ?></td></tr>
        </table>
        <p class="muted"><?= h(LOC('panoptes.limit.note')) ?></p>
    </section>

    <section>
        <h2><?= h(LOC('panoptes.pp')) ?></h2>
        <?php panoptes_render_error($loaded['powerplatform'] ?? []); ?>
        <?php if ($pp['recognized']): ?>
            <?php if ($pp['rows'] === []): ?><p class="muted"><?= h(LOC('panoptes.empty')) ?></p><?php else: ?>
            <table>
                <tr><th><?= h(LOC('panoptes.col.category')) ?></th><th class="num"><?= h(LOC('panoptes.col.purchased')) ?></th><th class="num"><?= h(LOC('panoptes.col.allocated')) ?></th><th class="num"><?= h(LOC('panoptes.col.consumed')) ?></th><th><?= h(LOC('panoptes.col.updated')) ?></th></tr>
                <?php foreach ($pp['rows'] as $row): ?>
                    <tr><td><?= h($row['name']) ?></td><td class="num"><?= panoptes_fmt($row['purchased']) ?></td><td class="num"><?= panoptes_fmt($row['allocated']) ?></td><td class="num"><?= panoptes_fmt($row['consumed']) ?></td><td><?= h(panoptes_dt($row['last_updated'])) ?></td></tr>
                <?php endforeach; ?>
            </table>
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
            <label><?= h(LOC('panoptes.limit.user')) ?></label>
            <input type="number" min="0" name="cowork_limit_user" value="<?= (int) $settings['cowork_limit_user'] ?>">
            <label><?= h(LOC('panoptes.limit.total')) ?></label>
            <input type="number" min="0" name="cowork_limit_total" value="<?= $settings['cowork_limit_total'] === null ? '' : (int) $settings['cowork_limit_total'] ?>">
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
