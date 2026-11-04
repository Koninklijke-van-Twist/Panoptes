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

// App-registratie (client credentials). Nodig:
// - Graph application permission CopilotCostManagement.Read.All + admin consent
// - registratie als Power Platform admin app (PPAC)
$panoptesTenantId = 'TENANT_ID';
$panoptesClientId = 'CLIENT_ID';
$panoptesClientSecret = 'CLIENT_SECRET';

// Mail (zelfde als Elpis/Ponos). Zonder smtp wordt PHP mail() gebruikt.
$reportMail = [
    'from_email' => 'kvtbot@kvt.nl',
    'from_name' => 'Panoptes',
    // 'smtp' => ['host' => 'SMTP_HOST', 'port' => 587, 'encryption' => 'tls', 'username' => 'USERNAME', 'password' => 'PASSWORD'],
];
