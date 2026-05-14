<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary connection
    |--------------------------------------------------------------------------
    |
    | Prefer CLOUDINARY_URL (Dashboard → API Keys → "API environment variable").
    | Or set cloud_name, api_key, and api_secret individually.
    |
    */
    'url' => env('CLOUDINARY_URL'),

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),

    /**
     * @deprecated Use folder_profiles, folder_quests, folder_portfolios.
     */
    'folder' => env('CLOUDINARY_FOLDER', 'hustleSafe'),

    /** Upload prefixes in Cloudinary (Media Library folders). */
    'folder_profiles' => env('CLOUDINARY_FOLDER_PROFILES', 'hustleSafe/profiles'),

    'folder_quests' => env('CLOUDINARY_FOLDER_QUESTS', 'hustleSafe/quests'),

    'folder_portfolios' => env('CLOUDINARY_FOLDER_PORTFOLIOS', 'hustleSafe/portfolios'),

];
