<?php

namespace App\Services\Sms;

use App\Support\IntegrationSettings as S;
use Illuminate\Support\Facades\Http;

/**
 * Bizning SMS Gateway (sms.elshod.date) API mijozi. Kalit/manzil sAdmin «Integratsiyalar» sahifasida saqlanadi.
 * Xatolar istisno chiqarmaydi: natija massivi qaytadi, chaqiruvchi Eskiz'ga o'tish-o'tmasligini hal qiladi.
 */
class SmsGatewayClient
{
    /** Eskiz'ga O'TMAYDIGAN xatolar: so'rovning o'zi noto'g'ri yoki spamdan himoya (Eskiz ham rad etadi / himoyani chetlab o'tardi). */
    private const NO_FALLBACK = ['invalid_phone', 'empty_text', 'validation_error', 'rate_limited'];

    public function __construct(private string $url, private string $key) {}

    public static function make(): ?self
    {
        $url = S::get('gateway_url');
        $key = S::get('gateway_key');

        return filled($url) && filled($key) ? new self(rtrim($url, '/'), $key) : null;
    }

    private function http(int $timeout = 10)
    {
        return Http::withToken($this->key)->acceptJson()->timeout($timeout);
    }

    /**
     * SMS'ni gateway navbatiga qo'yadi.
     *
     * @return array{ok:bool,id:?string,status:?string,fallback:bool,error:?string}
     */
    public function send(string $phone, string $text, string $externalId): array
    {
        try {
            $r = $this->http()->post($this->url.'/api/v1/sms/send', ['to' => '+'.ltrim($phone, '+'), 'text' => $text, 'external_id' => $externalId]);
        } catch (\Throwable) {
            return ['ok' => false, 'id' => null, 'status' => null, 'fallback' => true, 'error' => "Gateway bilan aloqa o'rnatilmadi."];
        }

        if ($r->successful() && $r->json('id') !== null) {
            return ['ok' => true, 'id' => (string) $r->json('id'), 'status' => $r->json('status'), 'fallback' => false, 'error' => null];
        }

        $code = (string) $r->json('error.code');
        $msg = $code !== '' ? $code.': '.mb_substr((string) $r->json('error.message'), 0, 200) : 'HTTP '.$r->status();

        return ['ok' => false, 'id' => null, 'status' => null, 'fallback' => ! in_array($code, self::NO_FALLBACK, true), 'error' => 'Gateway: '.$msg];
    }

    /**
     * Xabar holati. 404 bo'lsa 'missing' => true.
     *
     * @return array<string,mixed>|null  gateway javobi; aloqa xatosida null
     */
    public function find(string $id): ?array
    {
        try {
            $r = $this->http()->get($this->url.'/api/v1/sms/'.rawurlencode($id));
        } catch (\Throwable) {
            return null;
        }
        if ($r->status() === 404) {
            return ['missing' => true];
        }

        return $r->successful() ? (array) $r->json() : null;
    }

    /**
     * Gateway sog'lig'i: ['status' => ok|degraded|down, 'modems_usable' => int, ...]. Aloqa bo'lmasa null.
     *
     * @return array<string,mixed>|null
     */
    public function health(): ?array
    {
        try {
            $r = Http::acceptJson()->timeout(5)->get($this->url.'/api/v1/health');
        } catch (\Throwable) {
            return null;
        }

        return is_array($r->json()) && $r->json('status') !== null ? (array) $r->json() : null;
    }

    /** Gateway tirikmi (kalit talab qilinmaydi). */
    public function healthy(): bool
    {
        try {
            return Http::acceptJson()->timeout(5)->get($this->url.'/api/v1/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Ulanish va kalitni tekshirish. true yoki xato matni. */
    public function check(): true|string
    {
        if (! $this->healthy()) {
            return "Gateway javob bermayapti (health ≠ 200): manzilni yoki modem holatini tekshiring.";
        }
        try {
            $r = $this->http(8)->get($this->url.'/api/v1/me/usage');
        } catch (\Throwable) {
            return "Gateway bilan aloqa o'rnatilmadi.";
        }

        return match (true) {
            $r->successful() => true,
            in_array($r->status(), [401, 403], true) => "API kaliti noto'g'ri yoki loyiha o'chirilgan.",
            default => 'Gateway xatosi: HTTP '.$r->status(),
        };
    }
}
