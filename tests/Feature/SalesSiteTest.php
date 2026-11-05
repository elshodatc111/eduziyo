<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\NewsPost;
use App\Models\PlatformSetting;
use App\Models\SalesApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** EDUZIYO E7: sotuv sahifasi, ariza, yangiliklar va sAdmin boshqaruvi. */
class SalesSiteTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $over = []): array
    {
        return ['name' => 'Ali Valiyev', 'center_name' => 'Najot Markazi', 'phone' => '+998 90 123 4567', 'city' => 'Toshkent', 'branches_count' => 2, 'students_count' => 300, ...$over];
    }

    public function test_guest_sees_landing_and_logged_in_user_sees_dashboard(): void
    {
        $this->get('/')->assertOk()->assertSee('Ariza qoldiring')->assertSee('Tariflar');

        $b = $this->branch();
        $this->actingAs($this->user(Role::Admin, $b))->get('/')->assertOk()->assertDontSee('Ariza qoldiring');
    }

    public function test_blocked_user_on_root_is_logged_out(): void
    {
        $u = $this->user(Role::Admin, $this->branch());
        $u->update(['status' => \App\Enums\UserStatus::Blocked]);
        $this->actingAs($u)->get('/')->assertRedirect(route('login'));
    }

    public function test_application_is_saved_with_utm(): void
    {
        $this->post(route('landing.apply'), $this->valid(['utm_source' => 'instagram', 'utm_campaign' => 'noyabr']))
            ->assertRedirect(route('dashboard').'#ariza')->assertSessionHas('applied', true);

        $a = SalesApplication::firstOrFail();
        $this->assertSame('new', $a->status);
        $this->assertSame('Najot Markazi', $a->center_name);
        $this->assertSame('instagram', $a->utm_source);
        $this->assertNull($a->phone_verified_at);
    }

    public function test_invalid_phone_and_missing_fields_are_rejected(): void
    {
        $this->post(route('landing.apply'), $this->valid(['phone' => '901234567']))->assertSessionHasErrors('phone');
        $this->post(route('landing.apply'), $this->valid(['center_name' => '']))->assertSessionHasErrors('center_name');
        $this->post(route('landing.apply'), $this->valid(['branches_count' => 0]))->assertSessionHasErrors('branches_count');
        $this->assertSame(0, SalesApplication::count());
    }

    public function test_honeypot_creates_nothing_and_duplicate_in_24h_is_ignored(): void
    {
        $this->post(route('landing.apply'), $this->valid(['website' => 'http://spam']))->assertSessionHas('applied', true);
        $this->assertSame(0, SalesApplication::count());

        $this->post(route('landing.apply'), $this->valid());
        $this->post(route('landing.apply'), $this->valid(['name' => 'Boshqa']));
        $this->assertSame(1, SalesApplication::count());
    }

    public function test_only_sadmin_can_open_sales_admin_pages(): void
    {
        $b = $this->branch();
        $routes = ['sales.applications', 'sales.news.index', 'sales.news.create', 'sales.settings'];
        foreach ($routes as $r) {
            $this->get(route($r))->assertRedirect(route('login'));
        }
        foreach ($routes as $r) {
            foreach ([Role::Admin, Role::Owner, Role::Manager] as $role) {
                $this->actingAs($this->user($role, $b))->get(route($r))->assertForbidden();
            }
            $this->actingAs($this->user(Role::SAdmin))->get(route($r))->assertOk();
        }
    }

    public function test_sadmin_updates_and_deletes_application_and_xss_is_escaped(): void
    {
        $app = SalesApplication::create($this->valid(['center_name' => '<script>alert(1)</script>']));
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->get(route('sales.applications'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);

        $this->actingAs($sadmin)->put(route('sales.applications.update', $app), ['status' => 'contacted', 'follow_up_at' => '2030-01-01', 'internal_note' => 'Ertaga qo\'ng\'iroq'])->assertRedirect();
        $this->assertSame('contacted', $app->fresh()->status);

        $this->actingAs($sadmin)->put(route('sales.applications.update', $app), ['status' => 'bad'])->assertSessionHasErrors('status');

        $this->actingAs($sadmin)->delete(route('sales.applications.destroy', $app))->assertRedirect();
        $this->assertSame(0, SalesApplication::count());
    }

    public function test_applications_filter_by_status(): void
    {
        SalesApplication::create($this->valid(['center_name' => 'YANGI-MARKAZ']));
        SalesApplication::create($this->valid(['center_name' => 'YUTILGAN-MARKAZ', 'phone' => '+998 90 111 2233', 'status' => 'won']));

        $this->actingAs($this->user(Role::SAdmin))->get(route('sales.applications', ['status' => 'won']))
            ->assertSee('YUTILGAN-MARKAZ')->assertDontSee('YANGI-MARKAZ');
    }

    public function test_news_lifecycle_visibility(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('sales.news.store'), ['title' => 'Birinchi yangilik', 'body' => "Matn\n<b>x</b>", 'is_published' => 1])->assertRedirect(route('sales.news.index'));
        $this->actingAs($sadmin)->post(route('sales.news.store'), ['title' => 'Qoralama', 'body' => 'yashirin'])->assertRedirect();
        $this->actingAs($sadmin)->post(route('sales.news.store'), ['title' => 'Birinchi yangilik', 'body' => 'takror nom', 'is_published' => 1]);

        $this->assertSame(['birinchi-yangilik', 'qoralama', 'birinchi-yangilik-2'], NewsPost::orderBy('id')->pluck('slug')->all());

        auth()->logout();
        $this->get('/')->assertSee('Birinchi yangilik')->assertDontSee('Qoralama');
        $this->get(route('landing.news'))->assertOk()->assertSee('Birinchi yangilik')->assertDontSee('Qoralama');
        $this->get(route('landing.news.show', 'birinchi-yangilik'))->assertOk()->assertSee('&lt;b&gt;x&lt;/b&gt;', false);
        $this->get(route('landing.news.show', 'qoralama'))->assertNotFound();
    }

    public function test_future_dated_news_is_hidden_until_its_time(): void
    {
        NewsPost::create(['title' => 'Kelajak', 'slug' => 'kelajak', 'body' => 'x', 'is_published' => true, 'published_at' => now()->addDay()]);

        $this->get(route('landing.news'))->assertDontSee('Kelajak');
        $this->get(route('landing.news.show', 'kelajak'))->assertNotFound();
    }

    public function test_news_edit_and_delete(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $p = NewsPost::create(['title' => 'Eski', 'slug' => 'eski', 'body' => 'x', 'is_published' => true, 'published_at' => now()->subDay()]);

        $this->actingAs($sadmin)->get(route('sales.news.edit', $p->id))->assertOk()->assertSee('Eski');
        $this->actingAs($sadmin)->put(route('sales.news.update', $p->id), ['title' => 'Yangi nom', 'body' => 'y'])->assertRedirect();
        $this->assertFalse($p->fresh()->is_published);   // belgi olib tashlandi -> qoralama
        $this->assertSame('eski', $p->fresh()->slug);     // havola buzilmaydi

        $this->actingAs($sadmin)->delete(route('sales.news.destroy', $p->id))->assertRedirect();
        $this->assertSame(0, NewsPost::count());
    }

    public function test_contact_settings_show_on_landing_and_validate(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->put(route('sales.settings.update'), [
            'contact_phone1' => '+998 71 200 0000', 'contact_email' => 'info@eduziyo.uz',
            'contact_telegram' => 'https://t.me/eduziyo', 'contact_instagram' => 'https://instagram.com/eduziyo',
            'tariff_text' => 'Maxsus tarif matni',
        ])->assertRedirect();

        auth()->logout();
        $this->get('/')->assertSee('+998 71 200 0000')->assertSee('info@eduziyo.uz')->assertSee('https://t.me/eduziyo')->assertSee('Maxsus tarif matni');

        $this->actingAs($sadmin)->put(route('sales.settings.update'), ['contact_telegram' => 'javascript:alert(1)'])->assertSessionHasErrors('contact_telegram');
        $this->actingAs($sadmin)->put(route('sales.settings.update'), ['contact_phone1' => '12345'])->assertSessionHasErrors('contact_phone1');
        $this->assertSame('https://t.me/eduziyo', PlatformSetting::value('contact_telegram'));
    }
}
