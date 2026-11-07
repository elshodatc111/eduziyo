<?php

namespace App\Jobs;

use App\Models\CenterSmsAccount;
use App\Services\Sms\EskizTemplateSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Eskiz akkaunt kiritilganda/o'zgarganda barcha umumiy matnlarni moderatsiyaga yuboradi (centerId yo'q bo'lsa — platforma akkaunti). */
class SyncEskizTemplatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public ?int $centerId = null) {}

    public function handle(EskizTemplateSync $sync): void
    {
        if ($this->centerId === null) {
            $sync->syncPlatform();

            return;
        }

        $acc = CenterSmsAccount::withoutGlobalScopes()->where('center_id', $this->centerId)->first();
        if ($acc && $acc->isUsable()) {
            $sync->syncCenter($acc);
        }
    }
}
