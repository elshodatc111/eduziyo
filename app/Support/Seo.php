<?php

namespace App\Support;

/**
 * Qidiruv tizimlari (SEO) yordamchisi: qaysi sahifalar indekslanadi, canonical, rasm, JSON-LD.
 * Ochiq sahifalar: sotuv sahifasi, yangiliklar, huquqiy hujjatlar. Qolgan hammasi (panel, kirish,
 * ariza shakllari, kabinet) — noindex: markazlar ma'lumoti hech qachon qidiruvga tushmasligi kerak.
 */
class Seo
{
    public const PUBLIC_ROUTES = ['landing', 'landing.news', 'landing.news.show', 'legal.offer', 'legal.privacy', 'legal.terms', 'seo.*'];

    public static function indexable(): bool
    {
        return request()->routeIs(...self::PUBLIC_ROUTES);
    }

    /** Canonical: so'rov parametrlari (utm, fbclid...) tashlanadi; faqat yangiliklar sahifalash saqlanadi. */
    public static function canonical(): string
    {
        $r = request();
        $url = $r->url();

        if ($r->routeIs('landing.news') && ($page = (int) $r->query('page')) > 1) {
            $url .= '?page='.$page;
        }

        return $url;
    }

    /** Ulashish rasmi: berilgan bo'lmasa — brend logotipi, u ham bo'lmasa standart. */
    public static function image(?string $given = null): string
    {
        return $given ?: (Brand::logoUrl() ?: asset('images/logo-edunova.png'));
    }

    public static function limit(string $text, int $max = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }

    /** JSON-LD'ni <script> ichiga xavfsiz joylash (</script> va & chiqmasin). */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public static function organization(array $c): array
    {
        $same = array_values(array_filter([$c['contact_telegram'] ?? null, $c['contact_instagram'] ?? null]));
        $contact = array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'sales',
            'telephone' => ! empty($c['contact_phone1']) ? preg_replace('/\s+/', '', $c['contact_phone1']) : null,
            'email' => $c['contact_email'] ?? null,
            'availableLanguage' => ['uz', 'ru'],
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => Brand::name(),
            'url' => url('/'),
            'logo' => self::image(),
            'sameAs' => $same ?: null,
            'contactPoint' => count($contact) > 3 ? $contact : null,
        ]);
    }
}
