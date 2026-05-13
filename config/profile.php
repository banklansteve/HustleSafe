<?php

return [

    /**
     * Default visibility for public freelancer profiles.
     * Users may override individual keys in users.public_profile_settings (JSON).
     */
    'public_defaults' => [
        'show_bio' => true,
        'show_headline' => true,
        'show_location' => true,
        'show_rates' => true,
        'show_phone' => false,
        'show_email' => false,
        'show_credentials' => true,
        'show_cac' => true,
        'show_portfolio' => true,
        'show_experience' => true,
    ],

    /**
     * Defaults for clients / sponsors (subset stored in users.public_profile_settings).
     */
    'client_public_defaults' => [
        'show_bio' => true,
        'show_headline' => true,
        'show_location' => true,
        'show_company' => true,
        'show_phone' => false,
        'show_email' => false,
    ],

];
