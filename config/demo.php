<?php

return [
    'admin_email' => env('ADMIN_EMAIL', 'admin@vellum.studio'),
    'admin_password' => env('ADMIN_PASSWORD', env('APP_ENV') === 'production' ? null : 'VellumAdmin!2026'),
    'studio_email' => env('DEMO_EMAIL', 'studio@vellum.studio'),
    'studio_password' => env('DEMO_PASSWORD', env('APP_ENV') === 'production' ? null : 'VellumStudio!2026'),
];
