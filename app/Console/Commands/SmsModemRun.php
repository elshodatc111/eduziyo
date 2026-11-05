<?php

namespace App\Console\Commands;

use App\Services\Sms\Modem\ModemWorker;
use Illuminate\Console\Command;

class SmsModemRun extends Command
{
    protected $signature = 'sms:modem-run {--once : Bir marta ishlab to\'xtaydi} {--sleep=3 : Tsikllar orasidagi kutish (soniya)}';

    protected $description = "USB modemlarni avtomatik topadi va SMS navbatini yuboradi (systemd xizmat sifatida doimiy ishlaydi)";

    public function handle(ModemWorker $worker): int
    {
        // Ikkinchi nusxa ishga tushmasligi uchun: oxirgi 15 soniyada boshqa ishchi yurak urishi bergan bo'lsa chiqamiz
        if (! $this->option('once') && ModemWorker::activeRecently()) {
            $this->error('Boshqa modem ishchisi allaqachon ishlayapti.');

            return self::FAILURE;
        }

        do {
            $r = $worker->tick($this->option('once'));
            if ($r['sent'] || $r['failed'] || $r['scanned']) {
                $this->line(now()->format('H:i:s')." yuborildi: {$r['sent']}, xato: {$r['failed']}".($r['scanned'] ? ' (modemlar skanerlandi)' : ''));
            }
            if ($this->option('once')) {
                break;
            }
            sleep(max(1, (int) $this->option('sleep')));
        } while (true);

        return self::SUCCESS;
    }
}
