<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** robots.txt va sitemap.xml — domen so'rovdan olinadi (APP_URL ga bog'liq emas). */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Oq ro'yxat: faqat ochiq sahifalar. Panel, kirish, ariza shakllari, API, kabinet — yopiq.
            'Allow: /$',
            'Allow: /yangiliklar',
            'Allow: /oferta',
            'Allow: /maxfiylik',
            'Allow: /shartlar',
            'Allow: /build/',
            'Allow: /css/',
            'Allow: /js/',
            'Allow: /images/',
            'Allow: /icons/',
            'Allow: /uploads/',
            'Allow: /favicon.svg',
            'Disallow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $root = url('/');
        $xml = Cache::remember('seo.sitemap.'.md5($root), 600, function () {
            $posts = NewsPost::published()->orderByDesc('published_at')->limit(5000)->get(['slug', 'published_at', 'updated_at']);
            $latest = $posts->max(fn ($p) => $p->updated_at ?? $p->published_at);

            $urls = [
                [route('landing'), $latest, 'weekly', '1.0'],
                [route('landing.news'), $latest, 'daily', '0.7'],
                [route('legal.offer'), null, 'yearly', '0.2'],
                [route('legal.privacy'), null, 'yearly', '0.2'],
                [route('legal.terms'), null, 'yearly', '0.2'],
            ];
            foreach ($posts as $p) {
                $urls[] = [route('landing.news.show', $p->slug), $p->updated_at ?? $p->published_at, 'monthly', '0.6'];
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$loc, $mod, $freq, $prio]) {
                $out .= '  <url><loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'
                    .($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '')
                    .'<changefreq>'.$freq.'</changefreq><priority>'.$prio.'</priority></url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
