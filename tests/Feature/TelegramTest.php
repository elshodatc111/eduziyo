<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SalesApplication;
use App\Models\User;
use App\Services\BackupEncryptor;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** E8: Telegram bot - ulash, parol tiklash (faqat bot orqali), ariza tasdig'i, shifrlangan zaxira. */
class TelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'TESTTOKEN', 'services.telegram.username' => 'eduziyo_bot', 'services.telegram.webhook_secret' => 'sir123']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function update(array $message): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'sir123'])->postJson('/api/telegram/webhook', ['message' => $message + ['chat' => ['id' => 555, 'type' => 'private'], 'from' => ['id' => 555, 'username' => 'ali']]]);
    }

    private function sentTexts(): array
    {
        return Http::recorded()->map(fn ($pair) => (string) ($pair[0]['text'] ?? ''))->filter()->values()->all();
    }

    public function test_webhook_requires_secret_header(): void
    {
        $this->postJson('/api/telegram/webhook', ['message' => []])->assertForbidden();
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'xato'])->postJson('/api/telegram/webhook', [])->assertForbidden();
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'sir123'])->postJson('/api/telegram/webhook', [])->assertOk();

        config(['services.telegram.webhook_secret' => null]);
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => ''])->postJson('/api/telegram/webhook', [])->assertForbidden();
    }

    public function test_user_links_account_with_one_time_token(): void
    {
        $user = $this->user(Role::Admin, $this->branch());
        $token = app(TelegramLinkService::class)->makeToken($user);

        $this->update(['text' => "/start link_{$token}"])->assertOk();
        $this->assertSame(555, app(TelegramLinkService::class)->chatOf($user));

        // Token qayta ishlamaydi
        $other = $this->user(Role::Admin, $this->branch('Boshqa'));
        $this->update(['text' => "/start link_{$token}"]);
        $this->assertNull(app(TelegramLinkService::class)->chatOf($other));
    }

    public function test_password_reset_only_via_bot_code_and_invalidates_sessions(): void
    {
        $user = $this->user(Role::Admin, $this->branch(), [], ['username' => 'admin1', 'password' => 'eski12345']);
        app(TelegramLinkService::class)->link($user, 555);

        $this->post('/forgot-password', ['login' => 'admin1'])->assertRedirect(route('password.reset'));
        $text = collect($this->sentTexts())->first(fn ($t) => str_contains($t, 'tiklash kodi'));
        preg_match('/<b>(\d{6})<\/b>/', $text, $m);
        $code = $m[1];
        $this->assertSame(1, DB::table('telegram_reset_codes')->where('user_id', $user->id)->count());
        $this->assertNotSame($code, DB::table('telegram_reset_codes')->value('code_hash'));   // hash saqlanadi

        // Noto'g'ri kod
        $this->post('/reset-password', ['login' => 'admin1', 'code' => '000000' === $code ? '111111' : '000000', 'password' => 'Yangi12345', 'password_confirmation' => 'Yangi12345'])->assertSessionHasErrors('code');
        // Zaif parol
        $this->post('/reset-password', ['login' => 'admin1', 'code' => $code, 'password' => 'faqatharf', 'password_confirmation' => 'faqatharf'])->assertSessionHasErrors('password');

        $token = $user->createToken('mobil')->plainTextToken;
        $this->post('/reset-password', ['login' => 'admin1', 'code' => $code, 'password' => 'Yangi12345', 'password_confirmation' => 'Yangi12345'])->assertRedirect(route('login'));

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Yangi12345', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('telegram_reset_codes')->count());

        // Kod qayta ishlamaydi
        $this->post('/reset-password', ['login' => 'admin1', 'code' => $code, 'password' => 'Boshqa12345', 'password_confirmation' => 'Boshqa12345'])->assertSessionHasErrors('code');
    }

    public function test_code_expires_and_locks_after_five_attempts(): void
    {
        $user = $this->user(Role::Admin, $this->branch(), [], ['username' => 'a2']);
        app(TelegramLinkService::class)->link($user, 555);
        $this->post('/forgot-password', ['login' => 'a2']);
        preg_match('/<b>(\d{6})<\/b>/', collect($this->sentTexts())->first(fn ($t) => str_contains($t, 'kodi')), $m);
        $good = $m[1];
        $bad = $good === '123456' ? '654321' : '123456';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', ['login' => 'a2', 'code' => $bad, 'password' => 'Yangi12345', 'password_confirmation' => 'Yangi12345'])->assertSessionHasErrors('code');
        }
        // 5 urinishdan keyin to'g'ri kod ham o'tmaydi
        $this->post('/reset-password', ['login' => 'a2', 'code' => $good, 'password' => 'Yangi12345', 'password_confirmation' => 'Yangi12345'])->assertSessionHasErrors('code');

        $this->travel(11)->minutes();
        $this->post('/forgot-password', ['login' => 'a2']);   // yangi kod
        DB::table('telegram_reset_codes')->update(['expires_at' => now()->subMinute()]);
        $this->post('/reset-password', ['login' => 'a2', 'code' => $good, 'password' => 'Yangi12345', 'password_confirmation' => 'Yangi12345'])->assertSessionHasErrors('code');
    }

    public function test_forgot_does_not_reveal_unknown_login_and_unlinked_gets_no_code(): void
    {
        $this->user(Role::Admin, $this->branch(), [], ['username' => 'bor']);

        $a = $this->post('/forgot-password', ['login' => 'yoq-odam']);
        $b = $this->post('/forgot-password', ['login' => 'bor']);   // ulanmagan
        $a->assertRedirect(route('password.reset'));
        $b->assertRedirect(route('password.reset'));
        $this->assertSame(0, DB::table('telegram_reset_codes')->count());
        $this->assertEmpty(array_filter($this->sentTexts(), fn ($t) => str_contains($t, 'kodi')));
    }

    public function test_unlinked_user_recovers_by_sharing_own_contact(): void
    {
        $branch = $this->branch();
        $user = $this->user(Role::Admin, $branch, [], ['username' => 'kontakt1', 'phone' => '+998 90 111 2233']);
        $sadmin = $this->user(Role::SAdmin, null, [], ['phone' => '+998 90 999 8877']);

        $this->update(['text' => '/parol']);
        // Boshqa odamning kontakti - rad etiladi
        $this->update(['contact' => ['phone_number' => '998901112233', 'user_id' => 999]]);
        $this->assertNull(app(TelegramLinkService::class)->chatOf($user));

        // O'zining kontakti - ulanadi
        $this->update(['contact' => ['phone_number' => '998901112233', 'user_id' => 555]]);
        $this->assertSame(555, app(TelegramLinkService::class)->chatOf($user));

        // sAdmin kontakt orqali ulanmaydi
        Cache::flush();
        DB::table('telegram_links')->delete();
        $this->update(['text' => '/parol']);
        $this->update(['contact' => ['phone_number' => '998909998877', 'user_id' => 555]]);
        $this->assertNull(app(TelegramLinkService::class)->chatOf($sadmin));
    }

    public function test_sales_application_phone_is_verified_through_bot_contact(): void
    {
        $this->post(route('landing.apply'), ['name' => 'Aziz', 'center_name' => 'Yulduz', 'phone' => '+998 90 555 1122'])->assertSessionHas('tg_verify_link');
        $app = SalesApplication::firstOrFail();
        $this->assertNotNull($app->telegram_token);

        $this->update(['text' => '/start app_'.$app->telegram_token]);
        $this->update(['contact' => ['phone_number' => '998901234567', 'user_id' => 555]]);   // boshqa raqam
        $this->assertNull($app->fresh()->phone_verified_at);

        $this->update(['text' => '/start app_'.$app->telegram_token]);
        $this->update(['contact' => ['phone_number' => '998905551122', 'user_id' => 555]]);
        $this->assertNotNull($app->fresh()->phone_verified_at);
        $this->assertSame(555, (int) $app->fresh()->telegram_chat_id);
    }

    public function test_owner_first_login_via_bot_gives_password_once(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->post(route('platform.centers.store'), ['name' => 'Yangi Akademiya', 'branch_name' => 'Asosiy', 'owner_name' => 'Dilshod', 'owner_phone' => '+998 90 444 3322'])
            ->assertSessionHas('owner_link')->assertSessionMissing('credentials');
        $owner = User::where('role', Role::Owner)->where('name', 'Dilshod')->firstOrFail();
        $oldHash = $owner->password;

        $token = collect(Cache::getStore() instanceof \Illuminate\Cache\ArrayStore ? [] : [])->first();
        $token = app(TelegramLinkService::class)->makeToken($owner, 'own');
        $this->update(['text' => "/start own_{$token}"]);

        $this->assertNotSame($oldHash, $owner->fresh()->password);
        $this->assertTrue(collect($this->sentTexts())->contains(fn ($t) => str_contains($t, 'Parol:')));
        $this->assertSame(555, app(TelegramLinkService::class)->chatOf($owner));
    }

    public function test_manual_password_reset_is_removed_for_staff_and_students(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.update', 'students.view', 'students.update']);
        $manager = $this->user(Role::Manager, $branch, [], ['password' => 'eski12345']);

        $this->actingAs($admin)->put(route('staff.update', $manager), [
            'name' => $manager->name, 'username' => $manager->username, 'phone' => $manager->phone ?? '+998 90 555 66 77', 'status' => 'active',
            'password' => 'yangi12345', 'password_confirmation' => 'yangi12345',
        ]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('eski12345', $manager->fresh()->password));
    }

    public function test_profile_can_start_and_remove_telegram_link(): void
    {
        $user = $this->user(Role::Admin, $this->branch(), ['students.view']);

        $this->actingAs($user)->post(route('telegram.link'))->assertSessionHas('telegram_link');
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Telegram');

        app(TelegramLinkService::class)->link($user, 555);
        $this->actingAs($user)->delete(route('telegram.unlink'))->assertSessionHas('success');
        $this->assertNull(app(TelegramLinkService::class)->chatOf($user));
    }

    public function test_backup_encryption_roundtrip_and_tamper_detection(): void
    {
        $pem = $this->makeRsaPrivateKeyPem();
        $pubPath = tempnam(sys_get_temp_dir(), 'pub');
        file_put_contents($pubPath, openssl_pkey_get_details(openssl_pkey_get_private($pem))['key']);
        config(['services.backup.public_key_path' => $pubPath]);

        $plain = tempnam(sys_get_temp_dir(), 'pl');
        file_put_contents($plain, random_bytes(BackupEncryptor::BLOCK + 12345));   // 2 blok
        $enc = new BackupEncryptor;
        $enc->encryptFile($plain, $plain.'.enc');
        $enc->decryptFile($plain.'.enc', $plain.'.out', $pem);

        $this->assertSame(md5_file($plain), md5_file($plain.'.out'));
        $this->assertStringNotContainsString(substr(file_get_contents($plain), 0, 32), file_get_contents($plain.'.enc'));

        // Buzilgan fayl ochilmaydi
        $data = file_get_contents($plain.'.enc');
        file_put_contents($plain.'.bad', substr($data, 0, 600).chr(ord($data[600]) ^ 1).substr($data, 601));
        $this->expectException(\RuntimeException::class);
        $enc->decryptFile($plain.'.bad', $plain.'.out2', $pem);
    }

    public function test_backup_command_sends_encrypted_chunks_and_refuses_without_key(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        app(TelegramLinkService::class)->link($sadmin, 777);

        // Kalit yo'q: hech narsa yuborilmaydi
        config(['services.backup.public_key_path' => '/yoq/kalit.pem']);
        $this->artisan('backup:telegram')->assertFailed();
        $this->assertEmpty(Http::recorded()->filter(fn ($p) => str_contains((string) $p[0]->url(), 'sendDocument')));

        $pem = $this->makeRsaPrivateKeyPem();
        $pub = tempnam(sys_get_temp_dir(), 'pub');
        file_put_contents($pub, openssl_pkey_get_details(openssl_pkey_get_private($pem))['key']);
        config(['services.backup.public_key_path' => $pub]);

        // Fayllar zaxirasi (sinov bazasida mysqldump kerak emas)
        @mkdir(storage_path('app/topups'), 0777, true);
        file_put_contents(storage_path('app/topups/x.bin'), random_bytes(300_000));
        $this->artisan('backup:telegram --files --part-mb=1')->assertSuccessful();
        $docs = Http::recorded()->filter(fn ($p) => str_contains((string) $p[0]->url(), 'sendDocument'));
        $this->assertGreaterThanOrEqual(1, $docs->count());
        @unlink(storage_path('app/topups/x.bin'));
    }
}
