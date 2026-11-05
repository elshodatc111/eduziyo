<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorsAndLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_redirects_to_landing_and_button_asks_for_confirmation(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id]);

        $html = $this->actingAs($owner)->get(route('center.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Tizimdan chiqasizmi?', $html);

        $this->actingAs($owner)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_mobile_api_is_gone_but_telegram_webhook_remains(): void
    {
        $this->getJson('/api/v1/auth/me')->assertNotFound();
        $this->postJson('/api/v1/auth/login', [])->assertNotFound();
        $this->get('/docs')->assertNotFound();
        $this->postJson('/api/telegram/webhook', [])->assertForbidden();
    }

    public function test_owner_adds_director_to_own_center_only(): void
    {
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);

        $this->actingAs($owner)->get(route('center.directors'))->assertOk();
        $this->actingAs($owner)->post(route('center.directors.store'), ['name' => 'Yangi Direktor', 'phone' => '+998 90 555 1122'])->assertSessionHasNoErrors()->assertRedirect();

        $new = User::where('phone', '+998 90 555 1122')->firstOrFail();
        $this->assertSame(Role::Owner, $new->role);
        $this->assertSame($c->id, $new->center_id);

        // takroriy telefon
        $this->actingAs($owner)->post(route('center.directors.store'), ['name' => 'X', 'phone' => '+998 90 555 1122'])->assertSessionHasErrors('phone');
    }

    public function test_staff_roles_cannot_use_directors_section(): void
    {
        $b = $this->branch('F');
        foreach ([Role::Admin, Role::Manager, Role::Teacher] as $role) {
            $u = $this->user($role, $b, ['students.view']);
            $this->actingAs($u)->get(route('center.directors'))->assertForbidden();
            $this->actingAs($u)->post(route('center.directors.store'), ['name' => 'X', 'phone' => '+998 90 555 1133'])->assertForbidden();
        }
    }

    public function test_sadmin_adds_director_to_any_center_and_owner_cannot_reach_foreign(): void
    {
        $other = $this->newCenter('Boshqa markaz');
        $sadmin = $this->user(Role::SAdmin);
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id]);

        $this->actingAs($sadmin)->post(route('platform.directors.store', $other->id), ['name' => 'Boshqa Dir', 'phone' => '+998 91 555 1122'])->assertSessionHasNoErrors();
        $this->assertSame($other->id, User::where('phone', '+998 91 555 1122')->value('center_id'));
        $this->actingAs($sadmin)->get(route('platform.directors', $other->id))->assertOk()->assertSee('Boshqa Dir');

        $this->actingAs($owner)->get(route('platform.directors', $other->id))->assertForbidden();
    }

    public function test_block_rules_keep_one_active_director(): void
    {
        $c = $this->center();
        $a = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $b = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);

        $this->actingAs($a)->post(route('center.directors.toggle', $a->id))->assertSessionHas('error');   // o'zini
        $this->actingAs($a)->post(route('center.directors.toggle', $b->id))->assertSessionHas('success');
        $this->assertSame(UserStatus::Blocked, $b->fresh()->status);
        $this->actingAs($a)->post(route('center.directors.toggle', $b->id))->assertSessionHas('success');
        $this->assertSame(UserStatus::Active, $b->fresh()->status);
    }
}
