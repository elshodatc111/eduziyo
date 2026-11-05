<?php

namespace App\Support;

/** YouTube havolasidan video ID ajratadi va xavfsiz embed manzilini beradi. Boshqa saytlar qabul qilinmaydi. */
class YouTube
{
    public static function id(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }
        if (! preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be)/~i', $url)) {
            return null;
        }
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})(?:[?&/#]|$)~i',
            '~[?&]v=([A-Za-z0-9_-]{11})(?:[&#]|$)~i',
            '~/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})(?:[?&/#]|$)~i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public static function embedUrl(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0&modestbranding=1';
    }
}
