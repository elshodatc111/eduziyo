<?php

namespace App\Services;

use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\SmsInvoice;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * SMS faktura: sAdmin markazga qo'shimcha SMS paketini taklif qiladi (soni va summa qo'lda). Bir vaqtda bitta faol faktura;
 * 3 kunda qabul qilinmasa avtomatik bekor. Direktor qabul qilsa summa balansdan atomik yechiladi va SMS soni joriy tarif davriga qo'shiladi.
 */
class SmsInvoiceService
{
    public const EXPIRE_DAYS = 3;

    public function __construct(private CenterBalanceService $balance, private SubscriptionService $subs, private CenterAlerts $alerts) {}

    public function active(Center|int $center): ?SmsInvoice
    {
        $id = $center instanceof Center ? $center->id : $center;

        return SmsInvoice::withoutGlobalScopes()->where('center_id', $id)->where('status', SmsInvoice::PENDING)->where('expires_at', '>', now())->first();
    }

    public function create(Center $center, int $count, int $amount, ?string $note, int $userId): SmsInvoice
    {
        if ($count < 1 || $amount < 1) {
            throw new DomainException("SMS soni va summa 0 dan katta bo'lishi kerak.");
        }

        return DB::transaction(function () use ($center, $count, $amount, $note, $userId) {
            // Markaz qatorini qulflab, bir vaqtda ikkita faol faktura yaratilishining oldini olamiz
            Center::whereKey($center->id)->lockForUpdate()->first();

            if ($this->active($center)) {
                throw new DomainException("Bu markazda allaqachon faol faktura bor. Avval uni bekor qiling yoki qabul qilinishini kuting.");
            }

            $invoice = SmsInvoice::withoutGlobalScopes()->create([
                'center_id' => $center->id, 'sms_count' => $count, 'amount' => $amount, 'note' => $note,
                'created_by' => $userId, 'expires_at' => now()->addDays(self::EXPIRE_DAYS),
            ]);

            $invoice->refresh();
            $this->alerts->send($center, "SMS faktura: {$count} ta SMS — ".number_format($amount, 0, '', ' ')." so'm. «Markaz → SMS» bo'limida ".self::EXPIRE_DAYS." kun ichida qabul qiling.");

            return $invoice;
        });
    }

    public function cancel(SmsInvoice $invoice, int $userId): void
    {
        if ($invoice->status !== SmsInvoice::PENDING) {
            throw new DomainException('Faqat kutilayotgan fakturani bekor qilish mumkin.');
        }

        $invoice->update(['status' => SmsInvoice::CANCELLED, 'decided_by' => $userId, 'decided_at' => now()]);
    }

    /** Direktor qabul qiladi. Balans yetmasa hech narsa o'zgarmaydi. */
    public function accept(SmsInvoice $invoice, int $userId): void
    {
        DB::transaction(function () use ($invoice, $userId) {
            $row = SmsInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);

            if ($row->status !== SmsInvoice::PENDING || $row->expires_at->isPast()) {
                throw new DomainException("Faktura muddati o'tgan yoki allaqachon ko'rib chiqilgan.");
            }

            $sub = $this->subs->current($row->center_id);
            if (! $sub) {
                throw new DomainException("Faol tarif yo'q: avval tarifni yoqing.");
            }

            $this->balance->debit($row->center_id, $row->amount, CenterBalanceTransaction::SMS_PACK, "{$row->sms_count} ta SMS (faktura #{$row->id})", $userId);
            $sub->increment('extra_sms', $row->sms_count);
            $row->update(['status' => SmsInvoice::ACCEPTED, 'decided_by' => $userId, 'decided_at' => now()]);
        });

        $center = Center::find($invoice->center_id);
        app(\App\Services\Telegram\TelegramNotifier::class)->toSadmins('✅ SMS faktura qabul qilindi: '.e($center?->name).' — '.$invoice->sms_count.' ta SMS');
    }

    /** 3 kundan oshgan faol fakturalarni bekor qiladi. */
    public function expireOld(): int
    {
        return SmsInvoice::withoutGlobalScopes()->where('status', SmsInvoice::PENDING)->where('expires_at', '<=', now())
            ->update(['status' => SmsInvoice::EXPIRED, 'decided_at' => now()]);
    }
}
