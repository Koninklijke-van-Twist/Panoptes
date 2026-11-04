# Panoptes

Overzicht van Copilot- en AI-creditverbruik voor KVT, met historie per week/maand en een wekelijkse mail ("Co-Pilot Verbruik"). Vervangt de niet-uitgebrachte Power Automate-flow "Co-Pilot Verbruik".

Productie: https://sleutels.kvt.nl/panoptes

## Structuur

- `web/index.php` — pagina (Copilot-credits, Cowork-limieten, AI Builder/Power Platform-credits, trend, instellingen)
- `web/panoptes_lib.php` — tokens (client credentials), API-calls, parsing, cache, snapshots, weekmail
- `web/nightly.php` — ververst de cache en slaat de dagsnapshot op (`data/history.json`)
- `web/hourly.php` — ververst de cache als die ouder is dan 1 uur; verstuurt vrijdag vanaf 07:00 eenmalig de weekmail
- `web/localization.php` — UI-teksten (nl/en)
- `web/auth_TEMPLATE.php` — kopieer naar `web/auth.php` op de server (niet in git)
- `web/tests/panoptes_test.php` — `php web/tests/panoptes_test.php`

nightly.php en hourly.php worden door het bestaande serverscript via GET vanaf localhost aangeroepen (localhost is vertrouwd in `logincheck.php`).

## Bronnen

1. `GET https://graph.microsoft.com/beta/copilot/costManagement/getTenantCreditBalance` (scope `https://graph.microsoft.com/.default`, application permission `CopilotCostManagement.Read.All`).
2. `GET https://api.powerplatform.com/licensing/tenantCapacity/currencyReports?includeAllocations=true&includeConsumptions=true&api-version=2024-10-01` (scope `https://api.powerplatform.com/.default`, app geregistreerd als Power Platform admin app).

De veldnamen zijn nog niet geverifieerd tegen een echt antwoord; bij een onverwachte vorm toont de pagina de ruwe JSON. Cowork-limieten zijn alleen delegated op te vragen en staan daarom als instelling in de app (standaard 2000 per gebruiker, totaal leeg).

## Data

Alles in `web/data/` (cache, `settings.json`, `history.json`, `mail_state.json`) staat alleen op de server; de deploy slaat `data/**` over. `panoptes_data_dir()` schrijft een `.htaccess` met `Require all denied`.

`web/analytics/analytics.sqlite` (en `*.sqlite-*`) blijft op de server staan bij een deploy (zie `web/analytics/rules.md`).

## Mail

Zelfde mechanisme als Elpis: `$reportMail` uit `auth.php` (SMTP als gezet, anders PHP `mail()`), dus geen Mail.Send-permissie nodig. Ontvanger standaard `ict@kvt.nl`, aanpasbaar op de pagina.
