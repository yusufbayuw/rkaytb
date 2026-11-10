<?php

return [
    // No organization names, units, job titles, account codes, or rules are seeded.
    'currency' => env('RKA_CURRENCY', 'IDR'),
    'locale' => env('RKA_LOCALE', 'id'),
    'timezone' => env('RKA_TIMEZONE', 'Asia/Jakarta'),
    'bootstrap_roles' => ['superadmin'],
    'invitation_max_days' => (int) env('RKA_INVITATION_MAX_DAYS', 30),
    'allow_external_invitations' => false,
    // Release gate: financial publication must remain off until reconciled actuals, KPI and closing pass UAT.
    'enable_official_publication' => env('RKA_ENABLE_OFFICIAL_PUBLICATION', false),
];
