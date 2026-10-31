<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $now = now()->toAtomString();

        $urls = [
            ['loc' => url('/'), 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/support'), 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.9'],
            ['loc' => url('/terms-of-use'), 'lastmod' => $now, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => url('/privacy-statement'), 'lastmod' => $now, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $url) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.e($url['loc']).'</loc>';
            $xml[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';
            $xml[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
            $xml[] = '    <priority>'.$url['priority'].'</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
