<?php

namespace Tests\Feature;

use App\Models\NewsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function mkPost(array $o = []): NewsPost
    {
        return NewsPost::create(array_merge(['title' => 'Yangi imkoniyat', 'slug' => 'yangi-imkoniyat', 'excerpt' => 'Qisqa tavsif', 'body' => 'Matn', 'is_published' => true, 'published_at' => now()->subDay()], $o));
    }

    public function test_landing_has_full_seo_head(): void
    {
        $html = $this->get('/?utm_source=x&fbclid=1')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/').'">', $html);
        $this->assertStringContainsString('name="robots" content="index, follow', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertHeaderMissing($this->get('/'), 'X-Robots-Tag');
    }

    public function test_news_article_has_article_schema_and_canonical(): void
    {
        $this->mkPost();
        $html = $this->get('/yangiliklar/yangi-imkoniyat')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"NewsArticle"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        $this->assertStringContainsString('article:published_time', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/yangiliklar/yangi-imkoniyat').'"', $html);
    }

    public function test_robots_txt_whitelists_public_pages_and_points_to_sitemap(): void
    {
        $r = $this->get('/robots.txt')->assertOk();
        $this->assertStringContainsString('text/plain', $r->headers->get('Content-Type'));
        $body = $r->getContent();
        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringContainsString('Allow: /yangiliklar', $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringNotContainsString('dashboard', $body);
    }

    public function test_sitemap_lists_public_pages_and_only_published_news(): void
    {
        $this->mkPost();
        $this->mkPost(['title' => 'Qoralama', 'slug' => 'qoralama', 'is_published' => false]);
        $this->mkPost(['title' => 'Kelajak', 'slug' => 'kelajak', 'published_at' => now()->addDay()]);

        $r = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $r->headers->get('Content-Type'));
        $xml = $r->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/yangiliklar/yangi-imkoniyat').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/oferta').'</loc>', $xml);
        $this->assertStringNotContainsString('qoralama', $xml);
        $this->assertStringNotContainsString('kelajak', $xml);
        $this->assertStringNotContainsString('dashboard', $xml);
    }

    public function test_private_and_form_pages_are_noindex(): void
    {
        foreach (['/login', '/apply', '/dashboard'] as $url) {
            $res = $this->get($url);
            $this->assertStringContainsString('noindex', (string) $res->headers->get('X-Robots-Tag'), $url);
        }
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $this->get('/login')->getContent());
    }

    public function test_sitemap_and_robots_are_indexable_responses(): void
    {
        $this->assertHeaderMissing($this->get('/robots.txt'), 'X-Robots-Tag');
        $this->assertHeaderMissing($this->get('/sitemap.xml'), 'X-Robots-Tag');
    }

    private function assertHeaderMissing($res, string $h): void
    {
        $this->assertFalse($res->headers->has($h), $h.' bo\'lmasligi kerak');
    }
}
