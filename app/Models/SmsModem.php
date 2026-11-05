<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Serverga ulangan USB modem. Platforma darajasida. */
class SmsModem extends Model
{
    protected $fillable = ['imei', 'label', 'device', 'model', 'imsi', 'operator', 'signal', 'sim_status', 'registered', 'status', 'enabled',
        'sent_count', 'failed_count', 'fail_streak', 'last_error', 'last_seen_at', 'last_sent_at', 'daily_sent', 'daily_date'];

    protected function casts(): array
    {
        return ['registered' => 'boolean', 'enabled' => 'boolean', 'last_seen_at' => 'datetime', 'last_sent_at' => 'datetime', 'daily_date' => 'date'];
    }

    public const SEEN_SECONDS = 180;

    /** Yuborishga tayyor: faollashtirilgan, SIM va tarmoq yaxshi, yaqinda ko'rilgan. */
    public function scopeUsable($q)
    {
        return $q->where('enabled', true)->where('status', 'ready')->where('last_seen_at', '>', now()->subSeconds(self::SEEN_SECONDS));
    }

    /** Bugun yana nechta SMS yuborish mumkin (null = cheksiz). Chegara sozlamalardan. */
    public function remainingToday(): ?int
    {
        $limit = \App\Support\IntegrationSettings::int('modem_daily_limit', 0);
        if ($limit <= 0) {
            return null;
        }
        $sent = $this->daily_date && $this->daily_date->isToday() ? (int) $this->daily_sent : 0;

        return max(0, $limit - $sent);
    }

    public function name(): string
    {
        return $this->label ?: ($this->model ?: 'Modem').' …'.substr($this->imei, -4);
    }

    public function statusLabel(): string
    {
        return ['ready' => 'Tayyor', 'no_network' => "Tarmoq yo'q", 'sim_error' => 'SIM muammosi', 'offline' => 'Ulanmagan', 'error' => 'Xato'][$this->status] ?? $this->status;
    }
}
