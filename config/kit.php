<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    |
    | Toggle optional kit capabilities. Core auth/RBAC/i18n stay always on.
    |
    */

    'features' => [
        'seo' => true,
        'api' => (bool) env('KIT_API', false),
        'audit' => true,
        'attachments' => true,
        'translations_ui' => (bool) env('KIT_TRANSLATIONS_UI', true),
        'exports' => (bool) env('KIT_EXPORTS', true),
        /*
         * Excel-compatible SpreadsheetML downloads (users export ?format=xlsx).
         * Requires features.exports. No PhpSpreadsheet dependency.
         */
        'excel_exports' => (bool) env('KIT_EXCEL_EXPORTS', true),
        'tours' => true,
        'mail_alerts' => (bool) env('KIT_MAIL_ALERTS', true),
        /*
         * Offline foundation (PWA shell, Dexie outbox, sync API).
         * When true, also registers API/Sanctum sync routes even if api is false.
         * See docs/OFFLINE.md.
         */
        'offline' => (bool) env('KIT_OFFLINE', false),
        /*
         * AI writing assist on opted-in text fields (Settings → AI).
         * OrgSetting can override at runtime. See docs/AI.md.
         */
        'ai' => (bool) env('KIT_AI', false),
        /*
         * Multi-language switcher (Settings → System Control can override).
         */
        'multilingual' => true,
        /*
         * Google OAuth sign-in on login/register (requires GOOGLE_CLIENT_*).
         */
        'google_auth' => (bool) env('KIT_GOOGLE_AUTH', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public SEO defaults (used when features.seo is enabled)
    |--------------------------------------------------------------------------
    */

    'seo' => [
        'default_description' => 'Oscar CMS — takeaway order management for pizza & kebab: live orders, menu, delivery, promotions, and staff.',
        'default_image' => '/brand/hero-pizza.jpg',
        'keywords' => 'Oscar, takeaway, pizza, kebab, orders, delivery, restaurant CMS, kitchen',
    ],

];
