<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Center;
use App\Models\PlatformSetting;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    public function test_default_brand_is_shown_everywhere(): void
    {
        $this->get('/')->assertOk()->assertSee('EduNova');
        $this->get(route('login'))->assertOk()->assertSee('EduNova');
        $this->getJson(route('manifest'))->assertOk()->assertJsonPath('name', 'EduNova');
    }

    public function test_sadmin_renames_platform_and_uploads_logo(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->get(route('platform.brand'))->assertOk();
        $this->actingAs($sadmin)->put(route('platform.brand.update'), [
            'brand_name' => 'YangiNom', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertSessionHasNoErrors();

        Brand::flush();
        $this->assertSame('YangiNom', Brand::name());
        $this->assertNotNull(Brand::logoUrl());
        Storage::disk('uploads')->assertExists(PlatformSetting::value('brand_logo'));

        auth()->logout();
        $this->get('/')->assertSee('YangiNom')->assertSee('uploads/brand/', false);
        $this->get(route('login'))->assertSee('YangiNom')->assertDontSee('EduNova');
        $this->getJson(route('manifest'))->assertJsonPath('name', 'YangiNom');

        $old = PlatformSetting::value('brand_logo');
        $this->actingAs($sadmin)->delete(route('platform.brand.remove', 'logo'))->assertRedirect();
        Storage::disk('uploads')->assertMissing($old);
        Brand::flush();
        $this->assertNull(Brand::logoUrl());
    }

    public function test_logo_validation_rejects_svg_big_and_non_images(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->put(route('platform.brand.update'), ['brand_name' => 'X1', 'logo' => UploadedFile::fake()->createWithContent('a.svg', '<svg onload="alert(1)"/>')])->assertSessionHasErrors('logo');
        $this->actingAs($sadmin)->put(route('platform.brand.update'), ['brand_name' => 'X1', 'logo' => UploadedFile::fake()->image('a.png', 200, 200)->size(2000)])->assertSessionHasErrors('logo');
        $this->actingAs($sadmin)->put(route('platform.brand.update'), ['brand_name' => '<b>x</b>'])->assertSessionHasErrors('brand_name');
        $this->actingAs($sadmin)->put(route('platform.brand.update'), ['brand_name' => 'X'])->assertSessionHasErrors('brand_name');
    }

    public function test_only_sadmin_manages_platform_brand(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id]);
        $this->actingAs($owner)->get(route('platform.brand'))->assertForbidden();
        $this->actingAs($owner)->put(route('platform.brand.update'), ['brand_name' => 'Hack'])->assertForbidden();
        $this->assertSame('EduNova', Brand::name());
    }

    public function test_owner_sets_center_logo_shown_in_sidebar_and_only_for_own_center(): void
    {
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);

        $this->actingAs($owner)->post(route('center.logo.update'), ['logo' => UploadedFile::fake()->image('l.png', 200, 200)])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertNotNull($c->logo_path);
        Storage::disk('uploads')->assertExists($c->logo_path);

        $this->actingAs($owner)->get(route('center.index'))->assertOk()->assertSee('uploads/centers/'.$c->id, false)->assertSee($c->name);

        $other = $this->newCenter('Boshqa');
        $this->assertNull($other->fresh()->logo_path);

        $path = $c->logo_path;
        $this->actingAs($owner)->delete(route('center.logo.destroy'))->assertRedirect();
        Storage::disk('uploads')->assertMissing($path);
    }

    public function test_staff_cannot_upload_center_logo(): void
    {
        $admin = $this->user(Role::Admin, $this->branch('F'), ['students.view']);
        $this->actingAs($admin)->post(route('center.logo.update'), ['logo' => UploadedFile::fake()->image('l.png', 200, 200)])->assertForbidden();
    }
}
