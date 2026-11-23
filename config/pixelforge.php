<?php

return [
    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),
    'api_key' => env('PIXELFORGE_API_KEY'),
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),
    'timeout' => (int) env('PIXELFORGE_TIMEOUT', 20),
    'connect_timeout' => (int) env('PIXELFORGE_CONNECT_TIMEOUT', 5),
    'pricing_ttl' => (int) env('PIXELFORGE_PRICING_TTL', 600),
    'webhook_tolerance' => (int) env('PIXELFORGE_WEBHOOK_TOLERANCE', 300),
    'image_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PIXELFORGE_IMAGE_HOSTS', 'pixelforge.promptedit.dev')),
    ))),

    'sizes' => ['1024x1024', '1536x1024', '1024x1536'],
    'qualities' => ['low', 'medium', 'high'],
    'default_size' => '1024x1024',
    'default_quality' => 'medium',
    'max_reference_images' => 4,
    'max_prompt_characters' => 4000,

    // A submission that cannot be confirmed is retried with the same
    // idempotency key. These bounds prevent an infinite retry loop.
    'submit_max_attempts' => 12,
    'submit_max_age_minutes' => 30,

    // Polling is the recovery path, not the primary lifecycle mechanism.
    'reconcile_batch' => 50,
    'reconcile_first_delay' => 30,
    'reconcile_max_delay' => 300,
    'reconcile_give_up_minutes' => 60,
];
