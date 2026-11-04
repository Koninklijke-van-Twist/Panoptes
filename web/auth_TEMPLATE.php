<?php

/**
 * Kopieer naar web/auth.php op de server (en lokaal). NOOIT committen:
 * web/auth.php staat in .gitignore en de deploy slaat auth.php over.
 */

// Wie mag Panoptes bekijken. Lokaal/localhost wordt de eerste gebruiker gebruikt.
$allowedUsers = [
    'tfalken@kvt.nl',
];

// Wie mag de instellingen (Cowork-limieten, mailadres) aanpassen. Weglaten = iedereen in $allowedUsers.
$panoptesAdmins = [
    'tfalken@kvt.nl',
];

// App-registratie in Entra (client credentials, geen ingelogd account nodig).
// Entra admin center > Identiteit > Toepassingen > App-registraties > [jouw app]:
// - Overzicht: "Map-id (tenant-id)" en "Toepassings-id (client-id)".
// - Certificaten en geheimen > Nieuw clientgeheim: neem de "Waarde" (niet de geheim-id).
// - API-machtigingen > Microsoft Graph > Toepassingsmachtigingen:
//   CopilotCostManagement.Read.All, daarna "Beheerderstoestemming verlenen".
//   Gedelegeerde machtigingen zijn voor Panoptes niet nodig.
// - Power Platform-beheercentrum > Instellingen > Beheerinstellingen > Power Platform API:
//   registreer dezelfde client-id als beheer-app (of: pac admin application register --application-id <client-id>).
$panoptesTenantId = 'TENANT_ID';         // Map-id (tenant-id)
$panoptesClientId = 'CLIENT_ID';         // Toepassings-id (client-id)
$panoptesClientSecret = 'CLIENT_SECRET'; // Waarde van het clientgeheim

// Mail (zelfde als Elpis/Ponos). Zonder smtp wordt PHP mail() gebruikt.
$reportMail = [
    'from_email' => 'kvtbot@kvt.nl',
    'from_name' => 'Panoptes',
    // 'smtp' => ['host' => 'SMTP_HOST', 'port' => 587, 'encryption' => 'tls', 'username' => 'USERNAME', 'password' => 'PASSWORD'],
];
