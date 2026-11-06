<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PaymentCard;
use App\Models\SalesApplication;
use App\Models\TopupRequest;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Har bir ariza, murojaat va to'lov cheki BARCHA sAdminlarga bir xil matn va to'g'ridan-to'g'ri havola bilan boradi. */
class SadminTelegramLinksTest extends TestCase
{
    use RefreshDatabase;

    private $sadmin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'TESTTOKEN', 'services.telegram.username' => 'edu_bot']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->sadmin = $this->user(Role::SAdmin);
        app(TelegramLinkService::class)->link($this->sadmin, 1001);
        app(TelegramLinkService::class)->link($this->user(Role::SAdmin), 1002);
    }

    /** @return array<int,array<int,string>> chat_id => matnlar */
    private function messages(): array
    {
        $out = [];
        foreach (Http::recorded() as [$req]) {
            if (str_contains($req->url(), 'sendMessage')) {
                $out[(int) $req['chat_id']][] = (string) $req['text'];
            }
        }

        return $out;
    }

    public function test_application_goes_to_every_sadmin_with_direct_link(): void
    {
        $this->post(route('landing.apply'), ['name' => 'Ali Valiyev', 'center_name' => 'Najot Markazi', 'phone' => '+998 90 123 4567', 'city' => 'Toshkent', 'branches_count' => 2, 'students_count' => 300, 'consent' => 1]);
        $app = SalesApplication::firstOrFail();

        $m = $this->messages();
        $this->assertSame($m[1001], $m[1002]);                                     // ikkala sAdminga bir xil
        $text = $m[1001][0];
        $this->assertStringContainsString('Najot Markazi', $text);
        $this->assertStringContainsString('Ali Valiyev', $text);
        $this->assertStringContainsString(route('sales.applications', ['id' => $app->id]), $text);

        // havola faqat shu arizani ochadi
        SalesApplication::create(['name' => 'Boshqa', 'center_name' => 'Boshqa Markaz', 'phone' => '+998 91 000 1122', 'status' => 'new']);
        $this->actingAs($this->sadmin)->get(route('sales.applications', ['id' => $app->id]))->assertOk()->assertSee('Najot Markazi')->assertDontSee('Boshqa Markaz');
    }

    public function test_topup_receipt_notifies_every_sadmin_with_link_and_highlights_request(): void
    {
        Storage::fake('local');
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $card = PaymentCard::create(['number' => '8600 1111 2222 3333', 'holder' => 'ATKO', 'bank' => 'Test']);

        $this->actingAs($owner)->post(route('center.topup.store'), [
            'card_id' => $card->id, 'amount_claimed' => 250000, 'paid_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'receipt' => UploadedFile::fake()->createWithContent('chek.png', 'chek'),
        ])->assertRedirect();
        $topup = TopupRequest::withoutGlobalScopes()->firstOrFail();

        $m = $this->messages();
        $this->assertSame($m[1001], $m[1002]);
        $this->assertStringContainsString(route('platform.topups', ['id' => $topup->id]), $m[1001][0]);
        $this->assertStringContainsString('250 000', $m[1001][0]);

        $this->actingAs($this->sadmin)->get(route('platform.topups', ['id' => $topup->id]))->assertOk()->assertSee('id="topup-'.$topup->id.'"', false);
    }

    public function test_support_ticket_goes_to_every_sadmin_with_link(): void
    {
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $this->actingAs($owner)->post(route('center.support.store'), ['subject' => 'Yordam kerak', 'body' => 'Savolim bor'])->assertRedirect();

        $m = $this->messages();
        $this->assertSame($m[1001], $m[1002]);
        $this->assertStringContainsString('Yordam kerak', $m[1001][0]);
        $this->assertStringContainsString('/platform/murojaatlar/', $m[1001][0]);
    }
}
