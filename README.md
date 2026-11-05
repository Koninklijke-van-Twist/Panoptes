# Panoptes

Overzicht van Copilot- en AI-creditverbruik voor KVT, met historie per week/maand en een wekelijkse mail ("Co-Pilot Verbruik"). Vervangt de niet-uitgebrachte Power Automate-flow "Co-Pilot Verbruik".

Productie: https://sleutels.kvt.nl/panoptes

## Structuur

- `web/index.php` — pagina (Copilot-credits, AI Builder/Power Platform-credits, trend, instellingen)
- `web/panoptes_lib.php` — tokens (client credentials), API-calls, parsing, cache, snapshots, weekmail
- `web/nightly.php` — ververst de cache en slaat de dagsnapshot op (`data/history.json`)
- `web/oauth_callback.php` — eenmalig koppelen van het Copilot-account (beheerder, authorization code + PKCE)
- `web/hourly.php` — ververst de cache als die ouder is dan 1 uur; verstuurt vrijdag vanaf 07:00 eenmalig de weekmail
- `web/localization.php` — UI-teksten (nl/en)
- `web/auth_TEMPLATE.php` — kopieer naar `web/auth.php` op de server (niet in git)
- `web/tests/panoptes_test.php` — `php web/tests/panoptes_test.php`

nightly.php en hourly.php worden door het bestaande serverscript via GET vanaf localhost aangeroepen (localhost is vertrouwd in `logincheck.php`).

## Bronnen

1. `GET https://graph.microsoft.com/beta/copilot/costManagement/getTenantCreditBalance` met een **gedelegeerd** token (`CopilotCostManagement.Read.All` is alleen gedelegeerd beschikbaar, + admin consent). Een beheerder klikt eenmalig op "Koppel Copilot-account" (scopes `openid profile offline_access https://graph.microsoft.com/CopilotCostManagement.Read.All`, redirect-URI `https://sleutels.kvt.nl/panoptes/oauth_callback.php`, als Web-platform in de app-registratie). Het refresh token staat alleen in `data/delegated_token.json` (0600), wordt nooit getoond of gelogd en wordt bij elke refresh vervangen. Bij geen/verlopen token toont de pagina "Copilot-account niet gekoppeld of verlopen — opnieuw koppelen" en meldt de weekmail dat. Het inloggende account heeft een Entra-rol nodig die Copilot-kostenbeheer mag lezen; controleer in de Microsoft-docs welke.
2. `GET https://api.powerplatform.com/licensing/tenantCapacity/currencyReports?includeAllocations=true&includeConsumptions=true&api-version=2024-10-01` Met een gekoppeld account **gedelegeerd**: hetzelfde refresh token wordt ingewisseld voor scope `https://api.powerplatform.com/.default` (bij het inloggen alleen Graph-scopes, resources mogen niet gemengd worden). Daarvoor is in de app-registratie de gedelegeerde machtiging **Power Platform API** (Entra-app-id `8578e004-a5c6-46e7-913e-12f58912df43`) > `Licensing.Allocations.Read` + beheerderstoestemming nodig ([permission reference](https://learn.microsoft.com/power-platform/admin/programmability-permission-reference); [authenticatie](https://learn.microsoft.com/power-platform/admin/programmability-authentication-v2)). Ontbreekt die API in de tenantlijst, voeg eerst de service principal toe. Zonder gekoppeld account valt hij terug op app-only (app geregistreerd als Power Platform admin app). Foutteksten uit het API-antwoord worden getoond (zonder tokens).

De veldnamen zijn nog niet geverifieerd tegen een echt antwoord; bij een onverwachte vorm toont de pagina de ruwe JSON. Cowork-limieten zijn alleen delegated op te vragen en staan daarom als instelling in de app (standaard 2000 per gebruiker, totaal leeg).

## Data

Alles in `web/data/` (cache, `settings.json`, `history.json`, `mail_state.json`) staat alleen op de server; de deploy slaat `data/**` over. `panoptes_data_dir()` schrijft een `.htaccess` met `Require all denied`.

`web/analytics/analytics.sqlite` (en `*.sqlite-*`) blijft op de server staan bij een deploy (zie `web/analytics/rules.md`).

## Mail

Zelfde mechanisme als Elpis: `$reportMail` uit `auth.php` (SMTP als gezet, anders PHP `mail()`), dus geen Mail.Send-permissie nodig. Ontvanger standaard `ict@kvt.nl`, aanpasbaar op de pagina.
