<?php

namespace App\Services;

use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\CenterWallet;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Markaz balansi (platformaga to'langan mablag'). Faqat shu servis orqali o'zgaradi:
 * qulflangan tranzaksiya + har bir harakat jurnalga yoziladi, balans manfiyga tushmaydi.
 */
class CenterBalanceService
{
    public function balance(Center|int $center): int
    {
        $id = $center instanceof Center ? $center->id : $center;

        return (int) (CenterWallet::withoutGlobalScopes()->where('center_id', $id)->value('balance') ?? 0);
    }

    public function credit(Center|int $center, int $amount, string $type, ?string $note = null, ?int $userId = null): CenterBalanceTransaction
    {
        if ($amount <= 0) {
            throw new DomainException("Summa 0 dan katta bo'lishi kerak.");
        }

        return $this->move($center, $amount, $type, $note, $userId);
    }

    public function debit(Center|int $center, int $amount, string $type, ?string $note = null, ?int $userId = null): CenterBalanceTransaction
    {
        if ($amount <= 0) {
            throw new DomainException("Summa 0 dan katta bo'lishi kerak.");
        }

        return $this->move($center, -$amount, $type, $note, $userId);
    }

    /** sAdmin tuzatishi: musbat yoki manfiy, sabab majburiy. */
    public function adjust(Center|int $center, int $amount, string $reason, ?int $userId = null): CenterBalanceTransaction
    {
        if ($amount === 0 || trim($reason) === '') {
            throw new DomainException("Summa va sabab kiritilishi shart.");
        }

        return $this->move($center, $amount, CenterBalanceTransaction::ADJUSTMENT, $reason, $userId);
    }

    private function move(Center|int $center, int $signed, string $type, ?string $note, ?int $userId): CenterBalanceTransaction
    {
        $centerId = $center instanceof Center ? $center->id : $center;

        return DB::transaction(function () use ($centerId, $signed, $type, $note, $userId) {
            CenterWallet::withoutGlobalScopes()->firstOrCreate(['center_id' => $centerId], ['balance' => 0]);
            $wallet = CenterWallet::withoutGlobalScopes()->where('center_id', $centerId)->lockForUpdate()->firstOrFail();

            $after = $wallet->balance + $signed;
            if ($after < 0) {
                throw new DomainException("Markaz balansida mablag' yetarli emas.");
            }

            $wallet->balance = $after;
            $wallet->save();

            return CenterBalanceTransaction::withoutGlobalScopes()->create([
                'center_id' => $centerId, 'type' => $type, 'amount' => $signed, 'balance_after' => $after,
                'note' => $note, 'created_by' => $userId,
            ]);
        });
    }
}
