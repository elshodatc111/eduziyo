<?php

namespace Tests\Feature;

use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kirish va parol tiklash sahifalari (Telegram bot bor/yo'q holatlarida) 500 bermasligi. */
class AuthPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_render_without_bot(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/forgot-password')->assertOk()->assertSee('Botni oching');
        $this->get('/reset-password')->assertOk();
    }

    public function test_forgot_page_shows_bot_link_when_configured(): void
    {
        S::set('telegram_username', 'edu_test_bot');
        $this->get('/forgot-password')->assertOk()->assertSee('https://t.me/edu_test_bot', false);
    }
}
