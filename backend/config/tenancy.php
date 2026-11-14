<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Режим резолва тенанта
    |--------------------------------------------------------------------------
    | query      — ?restaurant=slug (по умолчанию, для локальной разработки)
    | subdomain  — coffee-house.foodnotifier.by
    | path       — /coffee-house/...
    */
    'mode' => env('TENANCY_MODE', 'query'),

    'query_key' => env('TENANCY_QUERY_KEY', 'restaurant'),

    'header' => env('TENANCY_HEADER', 'X-Restaurant-Id'),

    'domain' => env('APP_DOMAIN', 'localhost'),
];
