<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\NewsPost;
use App\Models\SalesApplication;
use App\Models\SalesVideo;
use App\Models\Tariff;
use App\Support\YouTube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** EDUZIYO E7b: tariflar (3 ta), demo, yangilik rasmi va YouTube, sotuv videolari. */
class SalesTariffMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Sinov paytida yuklangan haqiqiy fayllarni tozalaymiz
        \Illuminate\Support\Facades\File::deleteDirectory(public_path('uploads/news'));
        parent::tearDown();
    }

    public function test_there_are_exactly_three_seeded_tariffs_without_prices(): void
    {
        $this->assertSame(['start', 'standart', 'premium'], Tariff::orderBy('sort_order')->pluck('slug')->all());
        $this->assertSame(1, Tariff::where('is_featured', true)->count());
        $this->assertSame(0, Tariff::whereNotNull('price_monthly')->count());

        $this->get('/')->assertOk()->assertSee('Start')->assertSee('Standart')->assertSee('Premium')->assertSee('Narxni bilish')->assertSee('Mashhur');
    }

    public function test_prices_show_on_landing_with_yearly_saving(): void
    {
        $t = Tariff::where('slug', 'standart')->first();
        $t->update(['price_monthly' => 399000, 'price_yearly' => 3990000]);

        $this->get('/')->assertSee('399 000')->assertSee('3 990 000')->assertSee('798 000 so\'m tejaysiz', false)->assertSee('Yillik');
    }

    public function test_sadmin_edits_tariff_with_money_format_and_only_one_featured(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $start = Tariff::where('slug', 'start')->first();

        $this->actingAs($sadmin)->get(route('sales.tariffs'))->assertOk()->assertSee('Start');
        $this->actingAs($sadmin)->put(route('sales.tariffs.update', $start), [
            'name' => 'Start', 'tagline' => 'Kichik', 'price_monthly' => '199 000', 'price_yearly' => '1 990 000',
            'max_branches' => 1, 'max_students' => '', 'sms_monthly' => 600, 'ai_monthly' => 0,
            'features' => "Bir\n\nIkki ", 'sort_order' => 1, 'is_featured' => 1, 'is_active' => 1,
        ])->assertRedirect(route('sales.tariffs'));

        $start->refresh();
        $this->assertSame(199000, (int) $start->price_monthly);
        $this->assertSame(1990000, (int) $start->price_yearly);
        $this->assertNull($start->max_students);
        $this->assertSame(['Bir', 'Ikki'], $start->featureList());
        $this->assertSame(1, Tariff::where('is_featured', true)->count());
        $this->assertTrue($start->is_featured);
        $this->assertSame(3, Tariff::count());
    }

    public function test_tariff_validation_and_access(): void
    {
        $start = Tariff::where('slug', 'start')->first();
        $payload = ['name' => 'Start', 'sms_monthly' => 1, 'ai_monthly' => 0, 'sort_order' => 1];

        $this->actingAs($this->user(Role::SAdmin))->put(route('sales.tariffs.update', $start), $payload + ['price_yearly' => '500 000'])->assertSessionHasErrors('price_monthly');
        $this->actingAs($this->user(Role::SAdmin))->put(route('sales.tariffs.update', $start), ['name' => ''] + $payload)->assertSessionHasErrors('name');
        $this->actingAs($this->user(Role::SAdmin))->put(route('sales.tariffs.update', $start), array_merge($payload, ['sms_monthly' => -5]))->assertSessionHasErrors('sms_monthly');

        $b = $this->branch();
        foreach ([Role::Admin, Role::Owner] as $r) {
            $this->actingAs($this->user($r, $b))->put(route('sales.tariffs.update', $start), $payload)->assertForbidden();
            $this->actingAs($this->user($r, $b))->get(route('sales.tariffs'))->assertForbidden();
            $this->actingAs($this->user($r, $b))->get(route('sales.videos'))->assertForbidden();
        }
    }

    public function test_inactive_tariff_is_hidden_and_cannot_be_chosen(): void
    {
        Tariff::where('slug', 'premium')->update(['is_active' => false]);

        $this->get('/')->assertDontSee('Premium');
        $this->post(route('landing.apply'), ['name' => 'A', 'center_name' => 'B', 'phone' => '+998 90 123 4567', 'tariff_slug' => 'premium'])->assertSessionHasErrors('tariff_slug');
    }

    public function test_application_stores_demo_and_tariff_choice(): void
    {
        $this->post(route('landing.apply'), ['name' => 'A', 'center_name' => 'B', 'phone' => '+998 90 123 4567', 'tariff_slug' => 'standart', 'wants_demo' => 1])->assertSessionHasNoErrors();

        $a = SalesApplication::firstOrFail();
        $this->assertTrue($a->wants_demo);
        $this->assertSame('standart', $a->tariff_slug);

        $this->actingAs($this->user(Role::SAdmin))->get(route('sales.applications'))->assertSee("Demo so'radi", false)->assertSee('Tarif: standart');
    }

    public function test_demo_section_shows_credentials_only_when_both_set(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->get('/')->assertSee("Demo so'rash", false)->assertDontSee('Demo hisobi');

        $this->actingAs($sadmin)->put(route('sales.settings.update'), ['demo_login' => 'demo.direktor', 'demo_password' => 'demo12345', 'demo_text' => 'Maxsus demo matni'])->assertRedirect();
        auth()->logout();
        $this->get('/')->assertSee('Demo hisobi')->assertSee('demo.direktor')->assertSee('Maxsus demo matni');

        $this->actingAs($sadmin)->put(route('sales.settings.update'), ['demo_login' => 'bad login!', 'demo_password' => 'x'])->assertSessionHasErrors('demo_login');
    }

    public function test_youtube_id_parser(): void
    {
        $id = 'dQw4w9WgXcQ';
        foreach ([
            "https://www.youtube.com/watch?v=$id", "http://youtube.com/watch?v=$id&t=10s", "https://youtu.be/$id?si=abc",
            "https://m.youtube.com/watch?feature=share&v=$id", "https://www.youtube.com/embed/$id", "https://www.youtube.com/shorts/$id",
            "https://www.youtube.com/live/$id?feature=share", $id,
        ] as $url) {
            $this->assertSame($id, YouTube::id($url), $url);
        }
        foreach (['https://evil.com/watch?v='.$id, 'https://youtube.com.evil.com/watch?v='.$id, 'javascript:alert(1)', 'https://www.youtube.com/watch?v=short', '', null, '<script>'] as $bad) {
            $this->assertNull(YouTube::id($bad), (string) $bad);
        }
    }

    public function test_news_with_image_and_youtube(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('sales.news.store'), [
            'title' => 'Rasmli yangilik', 'body' => 'Matn', 'is_published' => 1,
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 400),
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertRedirect(route('sales.news.index'));

        $post = NewsPost::firstOrFail();
        $this->assertSame('dQw4w9WgXcQ', $post->youtube_id);
        $this->assertNotNull($post->image_path);
        $this->assertFileExists(public_path('uploads/'.$post->image_path));

        auth()->logout();
        $this->get('/')->assertSee('uploads/'.$post->image_path, false);
        $this->get(route('landing.news'))->assertSee('uploads/'.$post->image_path, false);
        $this->get(route('landing.news.show', $post->slug))->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('og:image', false);

        $old = $post->image_path;
        $this->actingAs($sadmin)->put(route('sales.news.update', $post->id), ['title' => 'Rasmli yangilik', 'body' => 'Matn', 'is_published' => 1, 'image' => UploadedFile::fake()->image('new.png', 300, 300), 'youtube_url' => ''])->assertRedirect();
        $post->refresh();
        $this->assertNotSame($old, $post->image_path);
        $this->assertFileDoesNotExist(public_path('uploads/'.$old));
        $this->assertNull($post->youtube_id);

        $path = $post->image_path;
        $this->actingAs($sadmin)->delete(route('sales.news.destroy', $post->id));
        $this->assertFileDoesNotExist(public_path('uploads/'.$path));
    }

    public function test_news_image_and_video_validation(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $base = ['title' => 'T', 'body' => 'B'];

        $this->actingAs($sadmin)->post(route('sales.news.store'), $base + ['image' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('image');
        $this->actingAs($sadmin)->post(route('sales.news.store'), $base + ['image' => UploadedFile::fake()->create('x.php', 10, 'application/x-php')])->assertSessionHasErrors('image');
        $this->actingAs($sadmin)->post(route('sales.news.store'), $base + ['image' => UploadedFile::fake()->image('big.jpg')->size(4000)])->assertSessionHasErrors('image');
        $this->actingAs($sadmin)->post(route('sales.news.store'), $base + ['youtube_url' => 'https://vimeo.com/123'])->assertSessionHasErrors('youtube_url');
        $this->assertSame(0, NewsPost::count());
    }

    public function test_remove_image_checkbox(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->post(route('sales.news.store'), ['title' => 'T', 'body' => 'B', 'image' => UploadedFile::fake()->image('a.jpg')]);
        $post = NewsPost::firstOrFail();
        $path = $post->image_path;

        $this->actingAs($sadmin)->put(route('sales.news.update', $post->id), ['title' => 'T', 'body' => 'B', 'remove_image' => 1])->assertRedirect();
        $this->assertNull($post->fresh()->image_path);
        $this->assertFileDoesNotExist(public_path('uploads/'.$path));
    }

    public function test_sales_videos_crud_and_landing_visibility(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->get('/')->assertDontSee('Video qo\'llanmalar', false);

        $this->actingAs($sadmin)->post(route('sales.videos.store'), ['title' => 'Birinchi video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->assertRedirect();
        $this->actingAs($sadmin)->post(route('sales.videos.store'), ['title' => 'Yomon', 'url' => 'https://evil.com/x'])->assertSessionHasErrors('url');
        $v = SalesVideo::firstOrFail();

        auth()->logout();
        $this->get('/')->assertSee('Video qo\'llanmalar', false)->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('Birinchi video');

        $this->actingAs($sadmin)->post(route('sales.videos.toggle', $v))->assertRedirect();
        auth()->logout();
        $this->get('/')->assertDontSee('Birinchi video');

        $this->actingAs($sadmin)->delete(route('sales.videos.destroy', $v))->assertRedirect();
        $this->assertSame(0, SalesVideo::count());
    }
}
