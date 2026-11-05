<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\TelegramGuardian;
use App\Models\User;
use App\Services\Telegram\TelegramFamilyService;
use Illuminate\Console\Command;

/** Botga ulangan ota-ona/o'quvchilarga qarz eslatmasi (bir o'quvchiga 3 kunda bir martadan tez emas). */
class TelegramDebtReminders extends Command
{
    protected $signature = 'telegram:debts';

    protected $description = "Qarzdor o'quvchilarning ota-onasiga Telegram orqali eslatma yuboradi";

    public function handle(TelegramFamilyService $family): int
    {
        $sent = 0;
        $ids = TelegramGuardian::withoutGlobalScopes()->where('notify_debt', true)->distinct()->pluck('student_id');

        User::whereIn('id', $ids)->where('role', Role::Student->value)->where('status', 'active')->whereNull('archived_at')->where('balance', '<', 0)
            ->each(function (User $s) use ($family, &$sent) {
                $sent += $family->notifyDebt($s);
            });

        $this->info("Telegram qarz eslatmasi: {$sent} ta xabar");

        return self::SUCCESS;
    }
}
