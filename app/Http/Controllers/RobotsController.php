<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /confirm-password',
            'Disallow: /verify-email',
            'Disallow: /two-factor-challenge',
            'Disallow: /settings',
            'Disallow: /password',
            'Disallow: /user',
            'Disallow: /email',
            'Disallow: /api/',
            'Disallow: /notifications',
            'Disallow: /attachments',
            'Disallow: /locale/',
            'Disallow: /checkout',
            'Disallow: /my-orders',
            'Disallow: /order-success',
            'Disallow: /admin',
            'Disallow: /orders',
            'Disallow: /menus',
            'Disallow: /menu-items',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
