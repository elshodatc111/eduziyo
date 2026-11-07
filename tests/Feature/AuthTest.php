<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_sales_page_and_protected_pages_redirect_to_login(): void
    {
        $this->get('/')->assertOk()->assertSee('EduNova');
        $this->get('/students')->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_username_and_email(): void
    {
        $user = $this->user(Role::Admin, attrs: ['email' => 'admin@edunova.uz']);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post('/login', ['login' => 'ADMIN@edunova.uz', 'password' => 'parol12345'])->assertRedirect(route('dashboard'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->user(Role::Admin);

        $this->from('/login')->post('/login', ['login' => $user->username, 'password' => 'xato'])
            ->assertRedirect('/login')->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = $this->user(Role::Manager, attrs: ['status' => UserStatus::Blocked]);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_user_of_closed_branch_cannot_login(): void
    {
        $branch = $this->branch();
        $user = $this->user(Role::Admin, $branch);
        $branch->update(['status' => BranchStatus::Closed]);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
    }

    public function test_student_logs_into_cabinet_but_not_staff_panel(): void
    {
        $user = $this->user(Role::Student);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertRedirect(route('cabinet.home'));
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect(route('cabinet.home'));
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->user(Role::Admin);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->username, 'password' => 'xato']);
        }

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_session_ends_when_branch_gets_closed(): void
    {
        $branch = $this->branch();
        $user = $this->user(Role::Admin, $branch);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $branch->update(['status' => BranchStatus::Closed]);

        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
    }




}
