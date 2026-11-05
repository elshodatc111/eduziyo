<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SmsModem;
use App\Services\Sms\Modem\ModemWorker;
use App\Services\SystemWatch;
use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Jarayon to'xtasa sAdminlarga Telegram xabari; chek/murojaat xabarlari. */
class SystemWatchTest extends TestCase
{
    use RefreshDatabase;

    private function bot(): void
    {
        S::set('telegram_token', '123456789:AAFakeTokenValue_1234567890abcdef');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);
    }

    private function sadminChat(int $chat = 777001): void
    {
        $u = $this->user(Role::SAdmin, null);
        DB::table('telegram_links')->insert(['user_id' => $u->id, 'chat_id' => $chat, 'linked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function healthy(): void
    {
        Cache::put('system.cron_heartbeat', now(), now()->addHour());
        Cache::put('system.queue_heartbeat', now(), now()->addHour());
        Cache::put('system.telegram_backup', ['ok' => true, 'message' => 'ok', 'at' => now()->timestamp], now()->addDay());
    }

    private function texts(): array
    {
        return Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'sendMessage'))->map(fn ($p) => (string) $p[0]['text'])->values()->all();
    }

    public function test_alerts_once_on_failure_then_on_recovery(): void
    {
        $this->bot();
        $this->sadminChat();
        $this->healthy();
        $w = app(SystemWatch::class);

        $w->run();                                                           // muhitga xos (disk/zaxira) holatlar bazaviy chiziqqa kiradi
        $base = count($this->texts());
        $w->run();
        $this->assertCount($base, $this->texts());                          // o'zgarish yo'q: sukut

        Cache::forget('system.cron_heartbeat');
        Cache::put('system.queue_heartbeat', now()->subHour(), now()->addHour());
        $r = $w->run();
        $this->assertEqualsCanonicalizing(['cron', 'queue'], $r['down']);
        $this->assertCount($base + 2, $this->texts());
        $this->assertStringContainsString('Muammo', $this->texts()[$base]);

        $w->run();                                                           // davom etyapti: takror xabar yo'q
        $this->assertCount($base + 2, $this->texts());

        $this->healthy();
        $r = $w->run();
        $this->assertEqualsCanonicalizing(['cron', 'queue'], $r['up']);
        $this->assertCount($base + 4, $this->texts());
        $this->assertStringContainsString('Tiklandi', $this->texts()[$base + 2]);
    }

    public function test_reminder_after_six_hours(): void
    {
        $this->bot();
        $this->sadminChat();
        $this->healthy();
        $w = app(SystemWatch::class);
        Cache::forget('system.cron_heartbeat');
        $w->run();

        $this->travel(7)->hours();
        $this->healthy();
        Cache::forget('system.cron_heartbeat');
        $this->assertContains('cron', $w->run()['remind']);
    }

    public function test_modem_worker_down_is_reported_only_when_a_modem_is_enabled(): void
    {
        $this->bot();
        $this->healthy();
        $w = app(SystemWatch::class);
        $this->assertArrayNotHasKey('modem_worker', $w->problems());

        SmsModem::create(['imei' => '860000000000001', 'enabled' => true, 'status' => 'ready']);
        $this->assertArrayHasKey('modem_worker', $w->problems());

        Cache::put(ModemWorker::HEARTBEAT_KEY, now()->timestamp, now()->addDay());
        $this->assertArrayNotHasKey('modem_worker', $w->problems());
    }

    public function test_extra_alert_chat_ids_receive_messages_and_are_validated(): void
    {
        $this->bot();
        $this->healthy();
        $this->actingAs($this->user(Role::SAdmin, null))->put('/platform/integratsiyalar/telegram', ['alert_chats' => '-1001234567890, 555123'])->assertSessionHasNoErrors();
        $this->put('/platform/integratsiyalar/telegram', ['alert_chats' => 'abc'])->assertSessionHasErrors('alert_chats');

        Cache::forget('system.queue_heartbeat');
        app(SystemWatch::class)->run();
        $chats = Http::recorded()->map(fn ($p) => (string) $p[0]['chat_id'])->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['-1001234567890', '555123'], $chats);
    }

    public function test_topup_and_ticket_notify_sadmins_with_details(): void
    {
        $this->bot();
        $this->sadminChat();
        \Illuminate\Support\Facades\Storage::fake('local');
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $card = \App\Models\PaymentCard::create(['number' => '8600 1111 2222 3333', 'holder' => 'ATKO', 'bank' => 'T']);

        $this->actingAs($owner)->post(route('center.topup.store'), [
            'card_id' => $card->id, 'amount_claimed' => 250000, 'paid_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'receipt' => \Illuminate\Http\UploadedFile::fake()->createWithContent('chek.png', 'x1'), 'note' => 'Noyabr',
        ])->assertRedirect();

        $t = implode("\n", $this->texts());
        $this->assertStringContainsString('250 000', $t);
        $this->assertStringContainsString('…3333', $t);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendDocument'));

        app(\App\Services\SupportService::class)->open($c, $owner, 'Yordam kerak', 'Tizimda xato chiqdi');
        $this->assertStringContainsString('Tizimda xato chiqdi', implode("\n", $this->texts()));
    }
}
