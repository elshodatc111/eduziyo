<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\GroupStudent;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Support\Format;
use App\Models\CenterSmsAccount;
use App\Services\Sms\EskizClient;
use App\Services\Sms\SmsPlatformTemplates;
use App\Support\SmsTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SMS yuborish. Yo'nalish:
 *  1) tarifning oylik SMS limiti ichida — SMS Gateway (sms.elshod.date); gateway rad etsa/ishlamasa yoki xabar yuborilmay qolsa platforma Eskiz hisobi (sAdmin «Integratsiyalar»);
 *  2) limit tugagach — markazning O'Z Eskiz akkaunti (ulangan bo'lsa), aks holda yuborilmaydi va direktorga xabar boradi.
 * Eskiz orqali (1-gateway ishlamasa va 2) FAQAT sAdmin tasdiqlagan matnlar ketadi.
 * Xabarlar avval bazaga yoziladi va navbat (queue) orqali yuboriladi.
 */
class SmsService
{
    /** v8 B9: bitta ommaviy yuborishda maksimal qabul qiluvchi soni. */
    public const BULK_LIMIT = 1000;

    /** @return array{body:string, enabled:bool} */
    public function template(Branch $branch, string $key): array
    {
        $default = SmsTemplates::all()[$key] ?? null;
        $row = SmsTemplate::withoutGlobalScopes()->where('branch_id', $branch->id)->where('key', $key)->first();

        return [
            // filial o'zgartirmagan bo'lsa — sAdmin tasdiqlagan (yoki tahrir qilgan) umumiy matn
            'body' => $row->body ?? SmsPlatformTemplates::body($key) ?? $default['body'] ?? '',
            'enabled' => $row ? $row->is_enabled : ($default['enabled'] ?? false),
        ];
    }

    public function render(string $body, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{'.$k.'}'] = (string) $v;
        }

        return strtr($body, $map);
    }

    /**
     * Xabarni navbatga qo'yadi. Raqam noto'g'ri bo'lsa, "failed" holatida yoziladi.
     * $message tarixda/ro'yxatda ko'rinadigan matn (parol bo'lsa berkitilgan bo'lishi kerak);
     * $secret - agar berilsa, HAQIQIY (masalan, ochiq parolli) matn - faqat yuborish uchun ishlatiladi
     * va yuborilgach (muvaffaqiyatli yoki xato bo'lsa ham) darhol o'chiriladi (v8: SMS tarixida parol saqlanmaydi).
     */
    public function queue(Branch $branch, ?string $phone, string $message, ?string $templateKey = null, ?User $recipient = null, ?User $actor = null, ?string $secret = null): SmsMessage
    {
        $normalized = Format::normalizePhone($phone);

        $sms = SmsMessage::create([
            'branch_id' => $branch->id,
            'recipient_id' => $recipient?->id,
            'phone' => $normalized ?? Format::digits($phone),
            'message' => $message,
            'secret' => $normalized ? $secret : null,
            'template_key' => $templateKey,
            'status' => $normalized ? SmsMessage::QUEUED : SmsMessage::FAILED,
            'provider_response' => $normalized ? null : "Telefon raqami noto'g'ri",
            'created_by' => $actor?->id,
        ]);

        if ($normalized) {
            SendSmsJob::dispatch($sms->id);
        }

        return $sms;
    }

    /**
     * v8 B9: ommaviy yuborishdan OLDIN ko'rish — nechta o'quvchiga yetadi (telefon takrorlari va
     * noto'g'ri raqamlarsiz), birinchi qabul qiluvchiga qarab to'ldirilgan namuna matn va shu matnga
     * asoslangan taxminiy SMS segment soni (real narx tizimda yo'q, shuning uchun pul emas — segment
     * ko'rsatiladi: har 160 belgi — 1 segment).
     *
     * @return array{count:int, sample:?string, segments:int}
     */
    public function previewBulk(Branch $branch, string $audience, ?int $groupId, string $message): array
    {
        $seen = [];
        $count = 0;
        $sample = null;

        foreach ($this->bulkRecipients($audience, $groupId) as $student) {
            $phone = Format::normalizePhone($student->phone);
            if (! $phone || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $count++;

            if ($sample === null) {
                $sample = $this->render($message, $this->bulkVars($student, $branch));
            }

            if ($count >= self::BULK_LIMIT) {
                break;
            }
        }

        return ['count' => $count, 'sample' => $sample, 'segments' => $sample ? (int) ceil(mb_strlen($sample) / 160) : 0];
    }

    /**
     * v8 B9: haqiqiy ommaviy yuborish (tasdiqlangandan keyin). `previewBulk` bilan bir xil so'rov, lekin
     * ro'yxat tasdiqlash bilan yuborish payti orasida o'zgargan bo'lishi mumkin (masalan, o'quvchi to'lov
     * qilib qarzdorlikdan chiqishi mumkin) — shuning uchun HAQIQIY navbatga qo'yilgan son qaytariladi.
     */
    public function sendBulk(Branch $branch, string $audience, ?int $groupId, string $message, ?User $actor): int
    {
        $seen = [];
        $queued = 0;

        foreach ($this->bulkRecipients($audience, $groupId) as $student) {
            $phone = Format::normalizePhone($student->phone);
            if (! $phone || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;

            $text = $this->render($message, $this->bulkVars($student, $branch));
            $this->queue($branch, $phone, $text, null, $student, $actor);

            if (++$queued >= self::BULK_LIMIT) {
                break;
            }
        }

        return $queued;
    }

    /** @return Collection<int, User> */
    private function bulkRecipients(string $audience, ?int $groupId): Collection
    {
        return User::visibleToContext()->where('role', 'student')->whereNull('archived_at')
            ->when($audience === 'debtors', fn ($q) => $q->where('balance', '<', 0))
            ->when($audience === 'group', fn ($q) => $q->whereIn('users.id', GroupStudent::where('group_id', $groupId)->where('is_active', true)->select('student_id')))
            ->get();
    }

    /** @return array<string,string> */
    private function bulkVars(User $student, Branch $branch): array
    {
        return [
            'name' => $student->name,
            'branch' => $branch->name,
            'balance' => Format::money($student->balance),
            'debt' => Format::money(max(0, -$student->balance)),
        ];
    }

    /**
     * Navbatdagi xabarni Eskiz orqali yuboradi (job chaqiradi).
     * Sozlama xatolari (akkaunt yo'q, parol noto'g'ri) qayta urinilmaydi, xabar "failed" bo'ladi;
     * tarmoq xatolari job orqali qayta uriniladi. `secret` bo'lsa o'shanday yuboriladi, keyin tozalanadi.
     */
    public function deliver(SmsMessage $sms): void
    {
        $text = $sms->secret ?? $sms->message;

        // Ommaviy demo markazda haqiqiy SMS HECH QACHON yuborilmaydi (yuborilgandek yoziladi)
        if (\App\Models\Center::whereKey(\Illuminate\Support\Facades\DB::table('branches')->where('id', $sms->branch_id)->value('center_id'))->where('is_demo', true)->exists()) {
            $sms->update(['status' => SmsMessage::SENT, 'sent_at' => now(), 'provider_response' => 'demo: haqiqiy yuborilmadi', 'secret' => null]);

            return;
        }

        $centerId = \Illuminate\Support\Facades\DB::table('branches')->where('id', $sms->branch_id)->value('center_id');
        $center = $centerId ? \App\Models\Center::find($centerId) : null;

        // Tarif bo'yicha oylik SMS limiti (tarifsiz eski markaz cheksiz; tizim xabarlari limitga sanalmaydi)
        if ($center && ! in_array($sms->template_key, SmsTemplates::SYSTEM_KEYS, true) && ! app(UsageService::class)->consume($center, 'sms')) {
            // Limit tugadi → markazning o'z Eskiz akkaunti (bo'lsa)
            $own = CenterSmsAccount::usableFor($center->id);
            if ($own) {
                $this->deliverViaEskiz($sms, $own);

                return;
            }

            $sms->update(['status' => SmsMessage::FAILED, 'provider_response' => "Tarif SMS limiti tugagan. Eskiz ulanmagan: direktor «Markaz → SMS» bo'limida Eskiz'ni ulasin.", 'secret' => null]);
            $this->notifyLimit($center);

            return;
        }

        // Limit ichida: avval SMS Gateway, u ishlamasa platforma Eskiz'i
        $this->deliverViaGateway($sms, $text);
    }

    private function deliverViaGateway(SmsMessage $sms, string $text): void
    {
        $client = \App\Services\Sms\SmsGatewayClient::make();
        if (! $client) {
            $this->deliverViaEskiz($sms);   // gateway sozlanmagan

            return;
        }

        $r = $client->send($sms->phone, $text, 'eduziyo-'.$sms->id);
        if ($r['ok']) {
            // Xabar gateway navbatida: yakuniy holat webhook yoki sms:gateway-sync orqali keladi (matn zaxira uchun saqlanadi)
            $sms->update(['channel' => 'gateway', 'gateway_id' => $r['id'], 'provider_response' => 'Gateway navbatida']);

            return;
        }

        if ($r['fallback']) {
            $this->deliverViaEskiz($sms);
            $sms->refresh();
            $sms->update(['provider_response' => mb_substr($r['error'].' → Eskiz: '.$sms->provider_response, 0, 1000)]);

            return;
        }

        $sms->update(['channel' => 'gateway', 'status' => SmsMessage::FAILED, 'provider_response' => $r['error'], 'secret' => null]);
    }

    /**
     * Gateway'dan yakuniy holat keldi (webhook yoki tekshiruv). Muvaffaqiyatsiz bo'lsa — Eskiz'ga o'tkaziladi
     * (tarif hisobi qayta sanalmaydi; faqat tasdiqlangan matn ketadi). Bir xabar uchun faqat bir marta ishlaydi.
     */
    public function applyGatewayStatus(SmsMessage $sms, string $status, ?string $error = null): void
    {
        if ($sms->status !== SmsMessage::QUEUED || $sms->channel !== 'gateway') {
            return;
        }

        if ($status === 'sent') {
            $sms->update(['status' => SmsMessage::SENT, 'sent_at' => now(), 'provider_response' => 'Gateway: yuborildi', 'secret' => null]);

            return;
        }

        if (in_array($status, ['failed', 'expired'], true)) {
            $reason = 'Gateway '.$status.($error ? ': '.mb_substr($error, 0, 150) : '');
            $this->deliverViaEskiz($sms);
            $sms->refresh();
            $sms->update(['provider_response' => mb_substr($reason.' → Eskiz: '.$sms->provider_response, 0, 1000)]);

            return;
        }

        if ($status === 'cancelled') {
            $sms->update(['status' => SmsMessage::FAILED, 'provider_response' => 'Gateway: bekor qilingan', 'secret' => null]);
        }
    }

    /**
     * Direktorga "SMS limiti tugadi" xabari: oyiga bir marta — SMS (tizim matni, limitga sanalmaydi) va Telegram.
     * Eskiz'ni ulash tavsiya qilinadi.
     */
    public function notifyLimit(\App\Models\Center $center): void
    {
        if (! Cache::add("sms-limit-alert:{$center->id}:".now()->format('Y-m'), 1, now()->endOfMonth()->addDay())) {
            return;
        }

        app(CenterAlerts::class)->send($center, "SMS limiti tugadi: yangi xabarlar yuborilmayapti. Davom ettirish uchun «Markaz → SMS» bo'limida o'z Eskiz akkauntingizni ulang yoki tarifni yangilang.");

        try {
            $director = User::withoutGlobalScopes()->where('center_id', $center->id)->where('role', \App\Enums\Role::Owner)->whereNotNull('phone')->orderBy('id')->first();
            $branch = Branch::withoutGlobalScopes()->where('center_id', $center->id)->orderBy('id')->first();
            $body = SmsPlatformTemplates::body('sms_limit_reached');

            if ($director && $branch && $body) {
                $this->queue($branch, $director->phone, $this->render($body, ['name' => $director->name, 'center' => $center->name]), 'sms_limit_reached', $director);
            }
        } catch (\Throwable $e) {
            report($e);   // ogohlantirish xatosi asosiy jarayonni buzmasin
        }
    }

    /**
     * Eskiz orqali yuborish. $own — markazning o'z akkaunti (limit tugagach); yo'q bo'lsa platforma akkaunti (gateway ishlamaganda).
     * Faqat sAdmin tasdiqlagan matn yuboriladi. Limit bu yerda qayta hisoblanmaydi.
     */
    public function deliverViaEskiz(SmsMessage $sms, ?CenterSmsAccount $own = null): void
    {
        $text = $sms->secret ?? $sms->message;
        $sms->update(['channel' => $own ? 'own' : 'eskiz']);

        if (! SmsPlatformTemplates::allows($sms->template_key, $text)) {
            $sms->update(['status' => SmsMessage::FAILED, 'provider_response' => "Matn sAdmin tomonidan tasdiqlanmagan: Eskiz orqali yuborilmaydi (faqat tasdiqlangan shablonlar).", 'secret' => null]);

            return;
        }

        try {
            $client = $own ? EskizClient::forAccount($own) : (EskizClient::platform() ?? throw new RuntimeException("Platforma Eskiz hisobi sozlanmagan (sAdmin → Integratsiyalar)."));
            $response = $client->send($sms->phone, $text);
        } catch (RuntimeException $e) {
            if ($own) {
                $own->forceFill(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 250), 'checked_at' => now()])->save();
            }
            $sms->update(['status' => SmsMessage::FAILED, 'provider_response' => $e->getMessage(), 'secret' => null]);

            return;
        }

        $status = $response['status'] ?? null;
        $ok = in_array($status, ['waiting', 'success', 'delivered', 'ok'], true);

        $sms->update([
            'status' => $ok ? SmsMessage::SENT : SmsMessage::FAILED,
            'sent_at' => $ok ? now() : null,
            'provider_response' => mb_substr(json_encode($response, JSON_UNESCAPED_UNICODE), 0, 1000),
            'secret' => null,
        ]);
    }
}
