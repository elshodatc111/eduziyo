<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SmsMessage;
use App\Models\SmsModem;
use App\Models\SmsOutbox;
use App\Services\Sms\Modem\ModemScanner;
use App\Services\Sms\Modem\ModemWorker;
use App\Services\Sms\Modem\PortFactory;
use App\Services\Sms\Modem\SmsPdu;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeModemPorts;
use Tests\TestCase;

/** USB modemlarni avtomatik topish, ulash, navbatdan yuborish va Eskiz'ga o'tish. */
class ModemTest extends TestCase
{
    use RefreshDatabase;

    private FakeModemPorts $ports;

    private $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        Cache::flush();
        FakeModemPorts::$sent = [];
        config(['services.eskiz.email' => 'g@eskiz.uz', 'services.eskiz.password' => 's', 'services.eskiz.from' => '4546']);
        Http::fake(['*/auth/login' => Http::response(['data' => ['token' => 'T']]), '*/message/sms/send' => Http::response(['status' => 'waiting', 'id' => 'x'])]);

        $this->ports = new FakeModemPorts;
        $this->app->instance(PortFactory::class, $this->ports);
        $this->branch = $this->branch('Toshkent');
        $this->branch->update(['sms_enabled' => true]);
    }

    private function send(string $text = 'Salom'): SmsMessage
    {
        return app(SmsService::class)->queue($this->branch, '+998 90 123 4567', $text);
    }

    public function test_pdu_encoder_matches_reference_and_handles_ucs2_and_multipart(): void
    {
        [$pdu, $len] = SmsPdu::encode('+44 7712 345678', 'hellohello')[0];
        $this->assertSame('0001000C9144772143658700000AE8329BFD4697D9EC37', $pdu);
        $this->assertSame(22, $len);

        $u = SmsPdu::encode('+998901234567', "Salom, o‘quvchi — Привет")[0][0];
        $this->assertStringContainsString('0008', substr($u, 0, 40));     // DCS 08 = UCS2

        $long = SmsPdu::encode('+998901234567', str_repeat('a', 400));
        $this->assertCount(3, $long);                                    // 153 belgidan
        $this->assertStringStartsWith('0041', $long[0][0]);              // UDHI belgisi
    }

    public function test_scanner_finds_modems_groups_ports_and_reports_problems(): void
    {
        $this->ports->devices = [
            '/dev/ttyUSB0' => FakeModemPorts::modem('860000000000001', ['at' => false]),   // xizmat porti
            '/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001'),
            '/dev/ttyUSB2' => FakeModemPorts::modem('860000000000001'),                    // o'sha modemning qo'shimcha porti
            '/dev/ttyUSB3' => FakeModemPorts::modem('860000000000002', ['sim' => 'SIM PIN']),
            '/dev/ttyUSB4' => FakeModemPorts::modem('860000000000003', ['reg' => 0]),
            '/dev/ttyUSB5' => FakeModemPorts::modem('860000000000004', ['denied' => true]),
        ];

        $r = app(ModemScanner::class)->scan();

        $this->assertSame(3, $r['found']);
        $this->assertSame(3, SmsModem::count());
        $this->assertSame('ready', SmsModem::where('imei', '860000000000001')->value('status'));
        $this->assertSame('sim_error', SmsModem::where('imei', '860000000000002')->value('status'));
        $this->assertSame('no_network', SmsModem::where('imei', '860000000000003')->value('status'));
        $this->assertSame(65, SmsModem::where('imei', '860000000000001')->value('signal'));
        $this->assertTrue((bool) SmsModem::where('imei', '860000000000001')->value('enabled'), 'yangi modem avtomatik faol');
        $this->assertStringContainsString('dialout', json_encode($r['report'], JSON_UNESCAPED_UNICODE));
    }

    public function test_modem_that_disappears_becomes_offline_and_comes_back(): void
    {
        $this->ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001')];
        app(ModemScanner::class)->scan();

        $this->ports->devices = [];
        app(ModemScanner::class)->scan();
        $this->assertSame('offline', SmsModem::first()->status);

        $this->ports->devices = ['/dev/ttyUSB7' => FakeModemPorts::modem('860000000000001')];   // boshqa port raqami bilan
        app(ModemScanner::class)->scan();
        $m = SmsModem::first();
        $this->assertSame('ready', $m->status);
        $this->assertSame('/dev/ttyUSB7', $m->device);
        $this->assertSame(1, SmsModem::count());
    }

    public function test_worker_sends_through_modems_in_rotation(): void
    {
        $this->ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001'), '/dev/ttyUSB2' => FakeModemPorts::modem('860000000000002')];
        $worker = app(ModemWorker::class);
        $worker->tick(true);                       // skaner + modem tayyor

        $a = $this->send('Birinchi')->fresh();
        $b = $this->send('Ikkinchi')->fresh();
        $this->assertSame('queued', $a->status);
        $this->assertSame('modem', $a->channel);
        Http::assertNothingSent();

        $r = $worker->tick();
        $this->assertSame(2, $r['sent']);
        $this->assertSame('sent', $a->fresh()->status);
        $this->assertSame('modem', $b->fresh()->channel);
        $this->assertNull($a->fresh()->secret);
        $this->assertCount(2, array_unique(array_column(FakeModemPorts::$sent, 'device')), 'ikki modem navbatma-navbat');
        $this->assertSame(2, (int) SmsModem::sum('sent_count'));
    }

    public function test_failed_modem_send_falls_back_to_eskiz_and_streak_marks_modem_error(): void
    {
        $this->ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001', ['send_ok' => false])];
        $worker = app(ModemWorker::class);
        $worker->tick(true);

        $sms = $this->send()->fresh();
        $r = $worker->tick();

        $this->assertSame(1, $r['failed']);
        $sms->refresh();
        $this->assertSame('sent', $sms->status);
        $this->assertSame('eskiz', $sms->channel);
        $this->assertSame(1, SmsModem::first()->fail_streak);

        $this->send(); $worker->tick();
        $this->send(); $worker->tick();
        $this->assertSame('error', SmsModem::first()->status);
        $this->assertFalse(SmsModem::usable()->exists());
    }

    public function test_without_worker_or_ready_modem_sms_goes_straight_to_eskiz(): void
    {
        $sms = $this->send()->fresh();
        $this->assertSame('eskiz', $sms->channel);
        $this->assertSame(0, SmsOutbox::count());

        // modem bor, lekin ishchi (worker) yurak urishi yo'q
        $this->ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001')];
        app(ModemScanner::class)->scan();
        $this->assertSame('eskiz', $this->send()->fresh()->channel);

        // ishchi tirik, lekin modem o'chirilgan
        app(ModemWorker::class)->tick();
        SmsModem::query()->update(['enabled' => false]);
        $this->assertSame('eskiz', $this->send()->fresh()->channel);
    }

    public function test_queued_message_when_modem_dies_moves_to_eskiz_after_timeout(): void
    {
        $this->ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001')];
        $worker = app(ModemWorker::class);
        $worker->tick(true);
        $sms = $this->send()->fresh();
        $this->assertSame('modem', $sms->channel);

        SmsModem::query()->update(['enabled' => false]);      // modem ishlamay qoldi
        Carbon::setTestNow(now()->addMinutes(3));
        $worker->tick();
        $this->assertSame('eskiz', $sms->fresh()->channel);
        $this->assertSame('sent', $sms->fresh()->status);
    }

    public function test_sadmin_modem_page_toggle_rename_scan_and_test_sms(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001')];

        $this->actingAs($sadmin)->post(route('platform.modems.scan'))->assertRedirect()->assertSessionHas('success');
        $m = SmsModem::firstOrFail();
        $this->actingAs($sadmin)->get(route('platform.modems'))->assertOk()->assertSee('860000000000001')->assertSee('Ucell');

        $this->actingAs($sadmin)->put(route('platform.modems.update', $m->id), ['label' => 'Ucell-1'])->assertRedirect();
        $this->assertSame('Ucell-1', $m->fresh()->label);

        $this->actingAs($sadmin)->post(route('platform.modems.test', $m->id), ['phone' => '+998 90 123 4567'])->assertSessionHas('success');
        $this->assertCount(1, FakeModemPorts::$sent);

        $this->actingAs($sadmin)->post(route('platform.modems.toggle', $m->id))->assertRedirect();
        $this->assertFalse($m->fresh()->enabled);
    }

    public function test_only_sadmin_can_manage_modems(): void
    {
        $m = SmsModem::create(['imei' => '1', 'status' => 'ready']);
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id]);
        $admin = $this->user(Role::Admin, $this->branch, ['sms.manage']);

        foreach ([$owner, $admin] as $u) {
            $this->actingAs($u)->get(route('platform.modems'))->assertForbidden();
            $this->actingAs($u)->post(route('platform.modems.scan'))->assertForbidden();
            $this->actingAs($u)->post(route('platform.modems.toggle', $m->id))->assertForbidden();
            $this->actingAs($u)->post(route('platform.modems.test', $m->id), ['phone' => '+998 90 123 4567'])->assertForbidden();
        }
    }
}
