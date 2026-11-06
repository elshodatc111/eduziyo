<?php

namespace App\Jobs;

use App\Models\CenterSmsAccount;
use App\Services\Sms\EskizTemplateSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Markaz Eskiz'ni ulaganda tasdiqlangan umumiy matnlarni uning akkauntiga moderatsiyaga yuboradi. */
class SyncEskizTemplatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $centerId) {}

    public function handle(EskizTemplateSync $sync): void
    {
        $acc = CenterSmsAccount::withoutGlobalScopes()->where('center_id', $this->centerId)->first();
        if ($acc && $acc->isUsable()) {
            $sync->syncCenter($acc);
        }
    }
}
