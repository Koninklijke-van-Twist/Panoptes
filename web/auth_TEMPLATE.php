<?php

/**
 * Kopieer naar web/auth.php op de server (en lokaal). NOOIT committen:
 * web/auth.php staat in .gitignore en de deploy slaat auth.php over.
 */

// Wie mag Panoptes bekijken. Lokaal/localhost wordt de eerste gebruiker gebruikt.
$allowedUsers = [
    'tfalken@kvt.nl',
];

// Wie mag de instellingen (weekmail) aanpassen. Weglaten = iedereen in $allowedUsers.
$panoptesAdmins = [
    'tfalken@kvt.nl',
];

// App-registratie in Entra (confidential client).
// Entra admin center > Identiteit > Toepassingen > App-registraties > [jouw app]:
// - Overzicht: "Map-id (tenant-id)" en "Toepassings-id (client-id)".
// - Certificaten en geheimen > Nieuw clientgeheim: neem de "Waarde" (niet de geheim-id).
// - API-machtigingen > Microsoft Graph > Gedelegeerde machtigingen:
//   CopilotCostManagement.Read.All (+ openid, profile, offline_access), daarna "Beheerderstoestemming verlenen".
//   Een toepassingsmachtiging is niet nodig (CopilotCostManagement.Read.All bestaat alleen gedelegeerd).
// - Verificatie > Platform toevoegen > Web: redirect-URI https://sleutels.kvt.nl/panoptes/oauth_callback.php
//   (of de waarde van $panoptesRedirectUri hieronder).
// - Daarna eenmalig op de pagina als beheerder "Koppel Copilot-account" klikken en inloggen met een account
//   dat Copilot-kostenbeheer mag lezen (welke Entra-rol: zie de Microsoft-docs van Copilot cost management).
//   Het refresh token staat daarna alleen in web/data/ (0600) en wordt automatisch vernieuwd.
// - Power Platform-beheercentrum > Instellingen > Beheerinstellingen > Power Platform API:
//   registreer dezelfde client-id als beheer-app (of: pac admin application register --application-id <client-id>).
//   Alleen nodig als fallback zonder gekoppeld account: met een gekoppeld account gaat Power Platform gedelegeerd.
// - API-machtigingen > API's die mijn organisatie gebruikt > "Power Platform API"
//   (Entra-app-id 8578e004-a5c6-46e7-913e-12f58912df43) > Gedelegeerde machtigingen:
//   Licensing.Allocations.Read (scope bij refresh: https://api.powerplatform.com/.default),
//   daarna beheerderstoestemming verlenen en opnieuw "Koppel Copilot-account".
//   Staat "Power Platform API" niet in de lijst: eerst service principal toevoegen
//   (New-MgServicePrincipal -AppId 8578e004-a5c6-46e7-913e-12f58912df43 of az ad sp create --id …).
//   Zie https://learn.microsoft.com/power-platform/admin/programmability-authentication-v2
//   en https://learn.microsoft.com/power-platform/admin/programmability-permission-reference
$panoptesTenantId = 'TENANT_ID';         // Map-id (tenant-id)
$panoptesClientId = 'CLIENT_ID';         // Toepassings-id (client-id)
$panoptesClientSecret = 'CLIENT_SECRET'; // Waarde van het clientgeheim
// $panoptesRedirectUri = 'https://sleutels.kvt.nl/panoptes/oauth_callback.php'; // optioneel, dit is de standaard

// Mail (zelfde als Elpis/Ponos). Zonder smtp wordt PHP mail() gebruikt.
$reportMail = [
    'from_email' => 'kvtbot@kvt.nl',
    'from_name' => 'Panoptes',
    // 'smtp' => ['host' => 'SMTP_HOST', 'port' => 587, 'encryption' => 'tls', 'username' => 'USERNAME', 'password' => 'PASSWORD'],
];

// Optioneel: overschrijf list-price schattingen (USD per unit). Zie web/lib/credit_rates.php.
// $panoptesCreditRates = [
//     'AI' => ['usd_per_unit' => 0.0004, 'kind' => 'approx', 'basis' => 'KVT-contract'],
//     'prepaid' => ['usd_per_unit' => 0.008],
// ];
