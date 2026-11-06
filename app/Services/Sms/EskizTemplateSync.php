<?php

namespace App\Services\Sms;

use App\Models\CenterSmsAccount;
use App\Models\CenterSmsTemplateSync;
use App\Models\SmsPlatformTemplate;

/**
 * sAdmin tasdiqlagan matnlarni Eskiz moderatsiyasiga yuboradi (platforma hisobiga va har bir markaz hisobiga) va holatini yangilaydi.
 * Markaz Eskiz'ni ulaganda yoki sAdmin matnni tasdiqlaganda avtomatik ishlaydi; `sms:eskiz-sync` soatiga bir marta holatni yangilaydi.
 */
class EskizTemplateSync
{
    /** @return \Illuminate\Support\Collection<int,SmsPlatformTemplate> */
    private function approved()
    {
        return SmsPlatformTemplate::where('is_approved', true)->get();
    }

    private static function norm(string $t): string
    {
        return preg_replace('/\s+/u', ' ', trim($t));
    }

    // ---------- Markaz akkaunti ----------

    public function syncCenter(CenterSmsAccount $account, ?string $onlyKey = null): void
    {
        $client = EskizClient::forAccount($account);

        foreach ($this->approved()->when($onlyKey, fn ($c) => $c->where('key', $onlyKey)) as $tpl) {
            $hash = SmsPlatformTemplates::hash($tpl->body);
            $row = CenterSmsTemplateSync::withoutGlobalScopes()->firstOrNew(['center_id' => $account->center_id, 'template_key' => $tpl->key]);

            if ($row->exists && $row->body_hash === $hash && in_array($row->status, ['pending', 'approved'], true)) {
                continue;   // shu matn allaqachon yuborilgan
            }

            $r = $client->createTemplate($tpl->body);
            $row->fill([
                'center_id' => $account->center_id, 'template_key' => $tpl->key, 'body_hash' => $hash,
                'status' => $r['ok'] ? 'pending' : 'error', 'eskiz_template_id' => $r['id'], 'last_error' => $r['error'],
            ])->save();
        }
    }

    public function refreshCenter(CenterSmsAccount $account): void
    {
        $client = EskizClient::forAccount($account);
        $login = $client->check();
        $account->forceFill(['checked_at' => now()]);

        if ($login !== true) {
            $account->forceFill(['status' => 'error', 'last_error' => $login])->save();

            return;
        }
        $account->forceFill(['status' => 'connected', 'last_error' => null])->save();

        $remote = $client->templates();
        if ($remote === null) {
            return;   // ro'yxat olinmadi: eski holat qoladi
        }

        $byText = [];
        foreach ($remote as $r) {
            $byText[self::norm($r['text'])] = $r;
        }

        foreach (CenterSmsTemplateSync::withoutGlobalScopes()->where('center_id', $account->center_id)->whereIn('status', ['pending', 'approved', 'rejected'])->get() as $row) {
            $tpl = SmsPlatformTemplate::where('key', $row->template_key)->first();
            if ($tpl && ($hit = $byText[self::norm($tpl->body)] ?? null)) {
                $row->update(['status' => $hit['state'], 'eskiz_template_id' => $hit['id'] ?? $row->eskiz_template_id]);
            }
        }
    }

    // ---------- Platforma akkaunti ----------

    public function syncPlatform(?string $onlyKey = null): void
    {
        $client = EskizClient::platform();
        if (! $client) {
            return;
        }

        foreach ($this->approved()->when($onlyKey, fn ($c) => $c->where('key', $onlyKey)) as $tpl) {
            if ($onlyKey === null && in_array($tpl->eskiz_status, ['pending', 'approved'], true)) {
                continue;   // allaqachon yuborilgan
            }
            $r = $client->createTemplate($tpl->body);
            $tpl->update(['eskiz_status' => $r['ok'] ? 'pending' : 'error', 'eskiz_template_id' => $r['id'], 'eskiz_error' => $r['error']]);
        }
    }

    public function refreshPlatform(): void
    {
        $client = EskizClient::platform();
        $remote = $client?->templates();
        if ($remote === null) {
            return;
        }
        $byText = [];
        foreach ($remote as $r) {
            $byText[self::norm($r['text'])] = $r;
        }
        foreach (SmsPlatformTemplate::whereIn('eskiz_status', ['pending', 'approved', 'rejected'])->get() as $tpl) {
            if ($hit = $byText[self::norm($tpl->body)] ?? null) {
                $tpl->update(['eskiz_status' => $hit['state'], 'eskiz_template_id' => $hit['id'] ?? $tpl->eskiz_template_id]);
            }
        }
    }

    // ---------- Umumiy ----------

    /** sAdmin matnni tasdiqlaganda: platforma hisobiga va barcha ulangan markazlarga yuboriladi. */
    public function onApproved(SmsPlatformTemplate $tpl): void
    {
        $this->syncPlatform($tpl->key);
        foreach (CenterSmsAccount::withoutGlobalScopes()->where('status', 'connected')->get() as $acc) {
            $this->syncCenter($acc, $tpl->key);
        }
    }

    /** Matn o'zgartirilsa/qaytarib olinsa eski holat eskirgan hisoblanadi. */
    public function onChanged(SmsPlatformTemplate $tpl): void
    {
        $tpl->update(['eskiz_status' => 'none', 'eskiz_template_id' => null, 'eskiz_error' => null]);
        CenterSmsTemplateSync::withoutGlobalScopes()->where('template_key', $tpl->key)->delete();
    }

    /** Hammasini yangilash (jadval bo'yicha). */
    public function refreshAll(): void
    {
        $this->refreshPlatform();
        foreach (CenterSmsAccount::withoutGlobalScopes()->get() as $acc) {
            $this->refreshCenter($acc);
            if ($acc->isUsable()) {
                $this->syncCenter($acc);   // xato bo'lganlarni qayta urinish
            }
        }
    }
}
