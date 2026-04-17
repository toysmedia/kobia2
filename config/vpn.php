<?php

return [
    'domain' => env('VPN_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    'script_ttl_minutes' => (int) env('VPN_SCRIPT_TTL_MINUTES', 30),
];
