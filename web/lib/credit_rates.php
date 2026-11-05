<?php

/**
 * Microsoft list-price schattingen per credit/capaciteitsunit (USD ERP).
 * Geen contractprijzen van KVT — alleen openbare Microsoft-tarieven/benaderingen.
 * Override optioneel via $panoptesCreditRates in auth.php (zelfde keys als hieronder).
 *
 * Bronnen gecontroleerd op 2026-11-05.
 */

/**
 * Standaardtarieven. Keys: currencyType / billingMethod (hoofdletterongevoelig match).
 * usd_per_unit: list price in USD per verbruikte unit.
 * kind: 'list' (direct MS-tarief) of 'approx' (pakketprijs ÷ credits of vergelijkbaar).
 */
function panoptes_default_credit_rates(): array
{
    return [
        // Copilot Studio / Graph getTenantCreditBalance (billingMethod)
        'prepaid' => [
            'usd_per_unit' => 0.008,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'Copilot Credit (prepaid pack)',
            'basis' => '$200/pack ÷ 25.000 credits',
            'source_url' => 'https://www.microsoft.com/licensing/guidance/Microsoft-Copilot-Studio',
            'source_date' => '2026-11-05',
        ],
        'payasyougo' => [
            'usd_per_unit' => 0.01,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Copilot Credit (pay-as-you-go)',
            'basis' => '$0,01 per Copilot Credit PAYG',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/pay-as-you-go-meters',
            'source_date' => '2026-11-05',
        ],
        'copilotcredit' => [
            'usd_per_unit' => 0.01,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Copilot Credit',
            'basis' => '$0,01 per Copilot Credit PAYG',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/pay-as-you-go-meters',
            'source_date' => '2026-11-05',
        ],

        // Power Platform currencyReports (ExternalCurrencyType)
        'ai' => [
            'usd_per_unit' => 0.0005,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'AI Builder credit',
            'basis' => 'Tier 1 add-on $500 ÷ 1.000.000 credits',
            'source_url' => 'https://learn.microsoft.com/en-us/ai-builder/administer-licensing',
            'source_date' => '2026-11-05',
        ],
        'ai_builder_credits' => null, // alias → ai (hieronder via aliases)
        'mcsmessages' => [
            'usd_per_unit' => 0.01,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Copilot Studio message / Copilot Credit',
            'basis' => '$0,01 per Copilot Credit PAYG',
            'source_url' => 'https://www.microsoft.com/licensing/guidance/Microsoft-Copilot-Studio',
            'source_date' => '2026-11-05',
        ],
        'copilot_studio_messages' => null,
        'scmessages' => [
            'usd_per_unit' => 0.01,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'SCMessages (als Copilot Credit)',
            'basis' => 'Zelfde PAYG-tarief als Copilot Credit ($0,01)',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/pay-as-you-go-meters',
            'source_date' => '2026-11-05',
        ],
        'apppass' => [
            'usd_per_unit' => 5.0,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Power Apps per app (AppPass)',
            'basis' => '$5 per user/app/maand (per-app plan)',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'apppassforteams' => [
            'usd_per_unit' => null,
            'currency' => 'USD',
            'kind' => 'unknown',
            'label' => 'AppPass for Teams',
            'basis' => 'Meestal inbegrepen bij M365; geen apart list-tarief per unit',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'perflowplan' => [
            'usd_per_unit' => 100.0,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Power Automate Per Flow',
            'basis' => '$100 per flow/maand',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'powerautomateperprocess' => [
            'usd_per_unit' => 150.0,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Power Automate Process',
            'basis' => '$150 per bot/maand',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'paunattendedrpa' => [
            'usd_per_unit' => 150.0,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'Unattended RPA / Process',
            'basis' => 'Process/unattended $150 per bot/maand',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'pahostedrpa' => [
            'usd_per_unit' => 215.0,
            'currency' => 'USD',
            'kind' => 'list',
            'label' => 'Power Automate Hosted Process',
            'basis' => '$215 per bot/maand',
            'source_url' => 'https://www.microsoft.com/en-us/power-platform/products/power-automate/pricing',
            'source_date' => '2026-11-05',
        ],
        'powerpagesauthenticated' => [
            'usd_per_unit' => 2.0,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'Power Pages authenticated user',
            'basis' => 'Tier 1 pack $200 ÷ 100 users (= $2/user); PAYG is $4',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'powerpagesanonymous' => [
            'usd_per_unit' => 0.15,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'Power Pages anonymous user',
            'basis' => 'Tier 1 pack $75 ÷ 500 users (= $0,15/user); PAYG is $0,30',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'portallogins' => [
            'usd_per_unit' => 2.0,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'Portal logins (legacy ≈ Power Pages auth)',
            'basis' => 'Benadering via Power Pages authenticated Tier 1 ($2/user)',
            'source_url' => 'https://learn.microsoft.com/en-us/power-platform/admin/powerapps-flow-licensing-faq',
            'source_date' => '2026-11-05',
        ],
        'portalviews' => [
            'usd_per_unit' => null,
            'currency' => 'USD',
            'kind' => 'unknown',
            'label' => 'Portal views (legacy)',
            'basis' => 'Geen actueel Microsoft list-tarief per page view gevonden',
            'source_url' => 'https://learn.microsoft.com/en-us/rest/api/power-platform/licensing/currency-reports/list-currency-reports',
            'source_date' => '2026-11-05',
        ],
        'portaladdons' => [
            'usd_per_unit' => null,
            'currency' => 'USD',
            'kind' => 'unknown',
            'label' => 'Portal add-ons',
            'basis' => 'Geen eenduidig list-tarief per unit',
            'source_url' => 'https://learn.microsoft.com/en-us/rest/api/power-platform/licensing/currency-reports/list-currency-reports',
            'source_date' => '2026-11-05',
        ],
        'processminingdatastorage' => [
            'usd_per_unit' => 50.0,
            'currency' => 'USD',
            'kind' => 'approx',
            'label' => 'Process Mining data storage (GB)',
            'basis' => '$5.000 per 100 GB/maand → $50/GB',
            'source_url' => 'https://cdn-dynmedia-1.microsoft.com/is/content/microsoftcorp/microsoft/bade/documents/products-and-services/en-us/bizapps/Power-Platform-Licensing-Guide-January-2026-PUB.pdf',
            'source_date' => '2026-11-05',
        ],
        'mcssessions' => [
            'usd_per_unit' => null,
            'currency' => 'USD',
            'kind' => 'unknown',
            'label' => 'MCS sessions (legacy)',
            'basis' => 'Vervangen door Copilot Credits; geen actueel per-session list-tarief',
            'source_url' => 'https://learn.microsoft.com/en-us/microsoft-copilot-studio/requirements-messages-management',
            'source_date' => '2026-11-05',
        ],
        'vaconversations' => [
            'usd_per_unit' => null,
            'currency' => 'USD',
            'kind' => 'unknown',
            'label' => 'VA conversations (legacy)',
            'basis' => 'Legacy Virtual Agent-meter; geen actueel list-tarief',
            'source_url' => 'https://learn.microsoft.com/en-us/rest/api/power-platform/licensing/currency-reports/list-currency-reports',
            'source_date' => '2026-11-05',
        ],
        'invoice' => [
            'usd_per_unit' => null,
            'currency' => 'USD',
            'kind' => 'unknown',
            'label' => 'Invoice',
            'basis' => 'Geen openbaar Microsoft list-tarief per unit',
            'source_url' => 'https://learn.microsoft.com/en-us/rest/api/power-platform/licensing/currency-reports/list-currency-reports',
            'source_date' => '2026-11-05',
        ],
    ];
}

function panoptes_credit_rate_aliases(): array
{
    return [
        'ai_builder_credits' => 'ai',
        'copilot_studio_messages' => 'mcsmessages',
        'prepaidcredits' => 'prepaid',
        'pay_as_you_go' => 'payasyougo',
        'pay-as-you-go' => 'payasyougo',
    ];
}

/**
 * Genormaliseerde key voor matching.
 */
function panoptes_credit_rate_key(string $name): string
{
    $key = strtolower(trim($name));
    $key = str_replace([' ', '-'], ['_', ''], $key);
    $aliases = panoptes_credit_rate_aliases();

    return $aliases[$key] ?? $key;
}

/**
 * Effectieve tarieftabel (defaults + optionele auth.php-override).
 */
function panoptes_credit_rates(): array
{
    $rates = panoptes_default_credit_rates();
    global $panoptesCreditRates;
    if (is_array($panoptesCreditRates ?? null)) {
        foreach ($panoptesCreditRates as $name => $override) {
            if (!is_array($override)) {
                continue;
            }
            $key = panoptes_credit_rate_key((string) $name);
            $base = $rates[$key] ?? [
                'usd_per_unit' => null,
                'currency' => 'USD',
                'kind' => 'unknown',
                'label' => (string) $name,
                'basis' => '',
                'source_url' => '',
                'source_date' => '',
            ];
            $rates[$key] = array_merge($base, $override);
        }
    }

    return $rates;
}

/**
 * Tarief voor een categorienaam, of null-structuur als onbekend.
 */
function panoptes_credit_rate_for(string $name): array
{
    $rates = panoptes_credit_rates();
    $key = panoptes_credit_rate_key($name);
    if (isset($rates[$key]) && is_array($rates[$key])) {
        return $rates[$key] + ['key' => $key];
    }

    return [
        'key' => $key,
        'usd_per_unit' => null,
        'currency' => 'USD',
        'kind' => 'unknown',
        'label' => $name,
        'basis' => 'Geen onderbouwd Microsoft-tarief gevonden',
        'source_url' => '',
        'source_date' => '',
    ];
}

/**
 * Geschatte kosten in USD voor een aantal verbruikte units, of null.
 */
function panoptes_estimate_cost(?float $consumed, string $category): ?float
{
    if ($consumed === null) {
        return null;
    }
    $rate = panoptes_credit_rate_for($category);
    $per = $rate['usd_per_unit'] ?? null;
    if ($per === null || !is_numeric($per)) {
        return null;
    }

    return (float) $consumed * (float) $per;
}

/**
 * Format USD-bedrag (of '–' / 'tarief onbekend').
 */
function panoptes_fmt_usd(?float $amount, bool $rateUnknown = false): string
{
    if ($rateUnknown) {
        return 'tarief onbekend';
    }
    if ($amount === null) {
        return '–';
    }
    $decimals = abs($amount) > 0 && abs($amount) < 0.01 ? 4 : 2;

    return '$' . number_format($amount, $decimals, '.', ',');
}

/**
 * Korte tooltip-tekst voor UI (tarief + bron).
 */
function panoptes_credit_rate_tooltip(string $category): string
{
    $rate = panoptes_credit_rate_for($category);
    $parts = [];
    if (($rate['usd_per_unit'] ?? null) !== null) {
        $parts[] = 'List/benadering: $' . rtrim(rtrim(number_format((float) $rate['usd_per_unit'], 6, '.', ''), '0'), '.') . ' USD/unit';
    } else {
        $parts[] = 'Tarief onbekend';
    }
    if (($rate['kind'] ?? '') === 'approx') {
        $parts[] = 'benadering';
    } elseif (($rate['kind'] ?? '') === 'list') {
        $parts[] = 'list price';
    }
    if (($rate['basis'] ?? '') !== '') {
        $parts[] = (string) $rate['basis'];
    }
    if (($rate['source_url'] ?? '') !== '') {
        $parts[] = 'Bron: ' . (string) $rate['source_url'];
    }
    if (($rate['source_date'] ?? '') !== '') {
        $parts[] = 'gecheckt ' . (string) $rate['source_date'];
    }
    $parts[] = 'Schatting; KVT kan andere contractprijzen hebben.';

    return implode(' — ', $parts);
}
