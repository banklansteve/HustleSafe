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
     * Root folder for this app (e.g. hustlesafe). Avatars use {folder}/avatars.
     */
    'folder' => env('CLOUDINARY_FOLDER', 'hustlesafe'),

];
