<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Help centre domain (optional)
    |--------------------------------------------------------------------------
    |
    | Set HELP_CENTER_DOMAIN=help.yourplatform.com in production to serve the
    | help centre at the subdomain root. When null, use /help on the main app URL.
    |
    */
    'domain' => env('HELP_CENTER_DOMAIN'),

    /*
    | Alternate subdomain some teams prefer (documentation only — pick one domain).
    */
    'alternate_domain' => env('HELP_CENTER_ALT_DOMAIN', 'support.'.parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

];
