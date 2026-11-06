<?php

namespace App\Services\Sms;

use App\Models\CenterSmsAccount;
use App\Support\IntegrationSettings as S;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Eskiz.uz API mijozi (bitta akkaunt uchun). Akkaunt: platformaniki (sAdmin «Integratsiyalar») yoki markazniki.
 * Token 25 kun keshlanadi, eskirsa bir marta yangilanadi.
 */
class EskizClient
{
    public function __construct(private string $email, private string $password, private string $from = '4546') {}

    public static function platform(): ?self
    {
        $email = S::get('eskiz_email');
        $password = S::get('eskiz_password');

        return filled($email) && filled($password) ? new self($email, $password, S::get('eskiz_from') ?: '4546') : null;
    }

    public static function forAccount(CenterSmsAccount $a): self
    {
        return new self($a->email, $a->password, $a->sender ?: '4546');
    }

    private function base(): string
    {
        return rtrim((string) config('services.eskiz.base_url'), '/');
    }

    /** @return array<string,mixed> */
    public function send(string $phone, string $message, bool $retry = true): array
    {
        $response = Http::withToken($this->token())->timeout(20)->post($this->base().'/message/sms/send', [
            'mobile_phone' => $phone, 'message' => $message, 'from' => $this->from,
        ]);

        if ($response->status() === 401 && $retry) {   // token eskirgan
            Cache::forget($this->tokenKey());

            return $this->send($phone, $message, retry: false);
        }

        return (array) $response->json();
    }

    /** Login to'g'riligini tekshiradi (yangi token olinadi). true yoki xato matni. */
    public function check(): true|string
    {
        Cache::forget($this->tokenKey());
        try {
            $this->token();

            return true;
        } catch (RuntimeException $e) {
            return $e->getMessage();
        } catch (\Throwable $e) {
            return "Eskiz bilan aloqa o'rnatilmadi.";
        }
    }

    /**
     * Shablonni Eskiz moderatsiyasiga yuboradi.
     * DIQQAT: Eskiz shablon API'si hujjatlardan olingan; haqiqiy hisobda sinab ko'rilmagan (xato bo'lsa sabab saqlanadi).
     *
     * @return array{ok:bool,id:?string,error:?string}
     */
    public function createTemplate(string $text, bool $retry = true): array
    {
        try {
            $response = Http::withToken($this->token())->timeout(20)->post($this->base().'/user/template', ['template' => $text]);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        } catch (\Throwable) {
            return ['ok' => false, 'id' => null, 'error' => "Eskiz bilan aloqa o'rnatilmadi."];
        }

        if ($response->status() === 401 && $retry) {
            Cache::forget($this->tokenKey());

            return $this->createTemplate($text, retry: false);
        }

        if (! $response->successful()) {
            return ['ok' => false, 'id' => null, 'error' => mb_substr((string) ($response->json('message') ?: 'HTTP '.$response->status()), 0, 250)];
        }

        $id = $response->json('data.id') ?? $response->json('id');

        return ['ok' => true, 'id' => $id !== null ? (string) $id : null, 'error' => null];
    }

    /**
     * Akkauntdagi shablonlar: [['id'=>..., 'text'=>..., 'state'=>'approved|rejected|pending']]. Xatoda null.
     *
     * @return list<array{id:?string,text:string,state:string}>|null
     */
    public function templates(): ?array
    {
        try {
            $response = Http::withToken($this->token())->timeout(20)->get($this->base().'/user/templates');
        } catch (\Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        $rows = $response->json('data') ?? $response->json('result') ?? [];
        $out = [];
        foreach ((array) (is_array($rows) && isset($rows['result']) ? $rows['result'] : $rows) as $r) {
            if (! is_array($r)) {
                continue;
            }
            $status = mb_strtolower((string) ($r['status'] ?? ''));
            $state = match (true) {
                in_array($status, ['service', 'reklama', 'active', 'approved', 'accepted', 'tasdiqlangan'], true) => 'approved',
                str_contains($status, 'reject') || str_contains($status, 'cancel') || str_contains($status, 'block') => 'rejected',
                default => 'pending',
            };
            $out[] = ['id' => isset($r['id']) ? (string) $r['id'] : null, 'text' => (string) ($r['template'] ?? $r['original_text'] ?? ''), 'state' => $state];
        }

        return $out;
    }

    private function token(): string
    {
        return Cache::remember($this->tokenKey(), now()->addDays(25), function () {
            $response = Http::timeout(20)->post($this->base().'/auth/login', ['email' => $this->email, 'password' => $this->password]);

            if (! $response->successful() || ! $response->json('data.token')) {
                throw new RuntimeException("Eskiz akkauntiga kirib bo'lmadi (login yoki parol noto'g'ri).");
            }

            return $response->json('data.token');
        });
    }

    private function tokenKey(): string
    {
        return 'eskiz_token_'.md5($this->email);
    }
}
