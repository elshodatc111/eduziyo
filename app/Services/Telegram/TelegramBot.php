<?php

namespace App\Services\Telegram;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\SalesApplication;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Telegram bot mantig'i (webhook'dan kelgan update ni qayta ishlaydi). Faqat shaxsiy (private) chat'lar.
 * Imkoniyatlar: hisobni ulash, direktorning birinchi kirishi, ariza telefon tasdig'i, parolni tiklash (kontakt orqali ulash).
 */
class TelegramBot
{
    public function __construct(private TelegramClient $tg, private TelegramLinkService $links, private TelegramNotifier $notifier, private TelegramFamilyService $family) {}

    public function handle(array $update): void
    {
        $msg = $update['message'] ?? null;
        if (! is_array($msg) || ($msg['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $chat = (int) $msg['chat']['id'];

        if (isset($msg['contact'])) {
            $this->contact($chat, $msg);

            return;
        }

        $text = trim((string) ($msg['text'] ?? ''));

        if (str_starts_with($text, '/start')) {
            $this->start($chat, $msg, trim(substr($text, 6)));
        } elseif ($text === '/parol') {
            $this->recover($chat);
        } elseif (in_array($text, ['/ulanish', '/ulash'], true)) {
            $this->startFamily($chat);
        } elseif ($this->familyCommand($chat, $text)) {
            return;
        } elseif ($text === '/holat') {
            $user = $this->links->userOfChat($chat);
            $this->tg->sendMessage($chat, $user ? "Ulangan hisob: <b>".e($user->name)."</b> (login: <code>".e($user->username)."</code>)" : "Hisob ulanmagan. /parol ni bosing yoki saytdagi «Telegramni ulash» tugmasidan foydalaning.");
        } elseif (($state = Cache::get("tg:state:{$chat}")) && ($state['type'] ?? null) === 'pick') {
            $this->pick($chat, $text, $state);
        } else {
            $this->tg->sendMessage($chat, $this->help($chat));
        }
    }

    private function start(int $chat, array $msg, string $payload): void
    {
        $username = $msg['from']['username'] ?? null;

        if (str_starts_with($payload, 'link_') && ($user = $this->links->consumeToken(substr($payload, 5), 'link'))) {
            $this->links->link($user, $chat, $username);
            $this->tg->sendMessage($chat, "✅ Telegram ulandi: <b>".e($user->name)."</b>. Endi parol tiklash kodi va muhim xabarlar shu yerga keladi.");

            return;
        }

        if (str_starts_with($payload, 'own_') && ($owner = $this->links->consumeToken(substr($payload, 4), 'own'))) {
            $password = Str::lower(Str::random(10));
            $owner->update(['password' => $password]);
            $this->links->link($owner, $chat, $username, 'birinchi kirish');
            AuditLog::record('telegram.owner_first_login', $owner, "Direktor Telegram orqali birinchi kirish ma'lumotini oldi: {$owner->name}", [], [], null);
            $this->tg->sendMessage($chat, "👋 Xush kelibsiz, <b>".e($owner->name)."</b>!\nSayt: ".url('/login')."\nLogin: <code>".e($owner->username)."</code>\nParol: <code>{$password}</code>\n\nKirgach parolni o'zgartiring. Parolni unutsangiz, saytdagi «Parolni unutdim» orqali kod shu yerga keladi.");

            return;
        }

        if (str_starts_with($payload, 'app_') && ($app = SalesApplication::where('telegram_token', substr($payload, 4))->whereNull('phone_verified_at')->first())) {
            Cache::put("tg:state:{$chat}", ['type' => 'app', 'id' => $app->id], now()->addMinutes(30));
            $this->askContact($chat, "Arizangizni tasdiqlash uchun pastdagi tugma orqali telefon raqamingizni ulashing.");

            return;
        }

        $this->tg->sendMessage($chat, "Assalomu alaykum! Bu ".\App\Support\Brand::name()." boti.\n\n👪 Ota-ona yoki o'quvchi bo'lsangiz: /ulanish — jadval, guruh, qarz va davomatni ko'rasiz.\n🔑 Xodim bo'lsangiz: /parol — parolni tiklash\n/holat — ulangan hisob");
    }

    private function recover(int $chat): void
    {
        if ($user = $this->links->userOfChat($chat)) {
            $this->tg->sendMessage($chat, "Hisobingiz ulangan (login: <code>".e($user->username)."</code>). Saytda «Parolni unutdim» sahifasiga shu loginni kiriting — kod shu yerga keladi.");

            return;
        }

        Cache::put("tg:state:{$chat}", ['type' => 'recover'], now()->addMinutes(30));
        $this->askContact($chat, "Hisobingiz ulanmagan. Tizimdagi telefon raqamingizni tasdiqlash uchun pastdagi tugma orqali kontaktingizni ulashing.");
    }

    private function contact(int $chat, array $msg): void
    {
        $contact = $msg['contact'];
        $state = Cache::get("tg:state:{$chat}");

        // Faqat o'zining kontakti (boshqaning raqamini yuborib bo'lmaydi)
        if ((int) ($contact['user_id'] ?? 0) !== (int) ($msg['from']['id'] ?? -1)) {
            $this->tg->sendMessage($chat, "Faqat o'zingizning kontaktingizni ulashing.");

            return;
        }

        $phone = Format::canonicalPhone('+'.ltrim((string) ($contact['phone_number'] ?? ''), '+'));

        if (($state['type'] ?? null) === 'app') {
            $this->verifyApplication($chat, (int) $state['id'], $phone);

            return;
        }

        if (($state['type'] ?? null) === 'family') {
            $this->finishFamily($chat, $phone, $msg['from']['username'] ?? null);

            return;
        }

        if (($state['type'] ?? null) !== 'recover') {
            $this->tg->sendMessage($chat, "Avval /ulanish (ota-ona/o'quvchi) yoki /parol buyrug'ini yuboring.");

            return;
        }

        // Sotuvchi (sAdmin) hisobi kontakt orqali ulanmaydi - faqat panel tokeni bilan
        $candidates = $phone ? User::where('phone', $phone)->where('status', 'active')->where('role', '!=', Role::SAdmin->value)->get() : collect();

        if ($candidates->isEmpty()) {
            $this->tg->sendMessage($chat, "Bu raqam tizimda topilmadi. O'quv markaz administratoriga murojaat qiling.");

            return;
        }

        if ($candidates->count() === 1) {
            $this->finishRecovery($chat, $candidates->first(), $msg['from']['username'] ?? null);

            return;
        }

        Cache::put("tg:state:{$chat}", ['type' => 'pick', 'ids' => $candidates->pluck('id')->all(), 'username' => $msg['from']['username'] ?? null], now()->addMinutes(10));
        $this->tg->sendMessage($chat, "Bu raqamga bir nechta hisob bog'langan. Loginingizni yozib yuboring.", ['remove_keyboard' => true]);
    }

    private function pick(int $chat, string $text, array $state): void
    {
        $user = User::whereIn('id', $state['ids'])->where('username', $text)->first();

        if (! $user) {
            $this->tg->sendMessage($chat, "Bunday login topilmadi. Qaytadan yozing yoki /parol ni bosing.");

            return;
        }

        $this->finishRecovery($chat, $user, $state['username'] ?? null);
    }

    private function finishRecovery(int $chat, User $user, ?string $username): void
    {
        $this->links->link($user, $chat, $username, 'kontakt');
        Cache::forget("tg:state:{$chat}");
        $this->tg->sendMessage($chat, "✅ Hisob ulandi: <b>".e($user->name)."</b> (login: <code>".e($user->username)."</code>). Endi saytda «Parolni unutdim» sahifasiga shu loginni kiriting — kod shu yerga keladi.", ['remove_keyboard' => true]);
    }

    private function verifyApplication(int $chat, int $id, ?string $phone): void
    {
        $app = SalesApplication::find($id);

        if (! $app || $app->phone_verified_at) {
            $this->tg->sendMessage($chat, "Bu ariza topilmadi yoki allaqachon tasdiqlangan.", ['remove_keyboard' => true]);

            return;
        }

        if (Format::canonicalPhone($app->phone) !== $phone) {
            $this->tg->sendMessage($chat, "Kontakt raqami arizadagi raqamga mos kelmadi. Arizada ko'rsatgan raqamingiz Telegram'ingizga ulangan bo'lishi kerak.", ['remove_keyboard' => true]);

            return;
        }

        $app->update(['phone_verified_at' => now(), 'telegram_chat_id' => $chat]);
        Cache::forget("tg:state:{$chat}");
        $this->tg->sendMessage($chat, "✅ Rahmat! Arizangiz tasdiqlandi. Tez orada siz bilan bog'lanamiz.", ['remove_keyboard' => true]);
        $this->notifier->toSadmins("🆕 Tasdiqlangan ariza\n".e($app->center_name)."\n".e($app->name).", ".e($app->phone));
    }

    // ---------------- Ota-ona / o'quvchi ----------------

    private const FAMILY_KEYBOARD = ['keyboard' => [[['text' => '📅 Jadval'], ['text' => '👥 Guruhlar']], [['text' => '💰 Qarz'], ['text' => '✅ Davomat']]], 'resize_keyboard' => true];

    private function startFamily(int $chat): void
    {
        Cache::put("tg:state:{$chat}", ['type' => 'family'], now()->addMinutes(30));
        $this->askContact($chat, "Ota-ona yoki o'quvchi sifatida ulanish uchun pastdagi tugma orqali telefon raqamingizni ulashing. Raqam markazda ota-ona yoki o'quvchi raqami sifatida kiritilgan bo'lishi kerak.");
    }

    private function finishFamily(int $chat, ?string $phone, ?string $username): void
    {
        Cache::forget("tg:state:{$chat}");
        $links = $phone ? $this->family->linkByPhone($chat, $phone, $username) : collect();

        if ($links->isEmpty()) {
            $this->tg->sendMessage($chat, "Bu raqam o'quvchi yoki ota-ona raqami sifatida topilmadi. Markaz administratoriga murojaat qilib, raqamingizni kiritishni so'rang.", ['remove_keyboard' => true]);

            return;
        }

        $names = $links->map(fn ($l) => '• '.e($l->student->name).($l->kind === 'parent' ? ' (farzandingiz)' : ''))->implode("\n");
        $this->tg->sendMessage($chat, "✅ Ulandi:\n{$names}\n\nQuyidagi tugmalardan foydalaning. Kelmagan kun, to'lov va qarz haqida xabarlar shu yerga keladi.\n/bildirishnoma — xabarlarni sozlash\n/chiqish — ulanishni uzish", self::FAMILY_KEYBOARD);
    }

    /** @return bool buyruq tanildi */
    private function familyCommand(int $chat, string $text): bool
    {
        $what = match (true) {
            in_array($text, ['/jadval', '📅 Jadval'], true) => 'schedule',
            in_array($text, ['/guruhlar', '/guruh', '👥 Guruhlar'], true) => 'groups',
            in_array($text, ['/qarz', '/balans', '💰 Qarz'], true) => 'balance',
            in_array($text, ['/davomat', '✅ Davomat'], true) => 'attendance',
            default => null,
        };

        if ($what) {
            $this->tg->sendMessage($chat, $this->family->reply($chat, $what), self::FAMILY_KEYBOARD);

            return true;
        }

        if ($text === '/chiqish') {
            $n = $this->family->unlink($chat);
            $this->tg->sendMessage($chat, $n ? "Ulanish uzildi. Qayta ulanish: /ulanish" : "Siz ulanmagansiz.", ['remove_keyboard' => true]);

            return true;
        }

        if (str_starts_with($text, '/bildirishnoma')) {
            $arg = trim(substr($text, strlen('/bildirishnoma')));
            $map = ['davomat' => 'notify_absence', 'qarz' => 'notify_debt', 'tolov' => 'notify_payment'];
            [$kind, $state] = array_pad(preg_split('/\s+/', $arg) ?: [], 2, null);

            if (isset($map[$kind]) && in_array($state, ['on', 'off'], true) && $this->family->links($chat)->isNotEmpty()) {
                $this->family->toggle($chat, $map[$kind], $state === 'on');
                $this->tg->sendMessage($chat, 'Saqlandi: '.$kind.' xabarlari '.($state === 'on' ? 'yoqildi' : "o'chirildi").'.');
            } else {
                $this->tg->sendMessage($chat, "Xabarlarni sozlash:\n/bildirishnoma davomat off — kelmagan kun xabari o'chadi\n/bildirishnoma qarz off\n/bildirishnoma tolov off\n(on — qayta yoqish)");
            }

            return true;
        }

        return false;
    }

    private function help(int $chat): string
    {
        return $this->family->links($chat)->isNotEmpty()
            ? "Buyruqlar:\n/jadval — yaqin darslar\n/guruhlar — guruh ma'lumoti\n/qarz — balans va to'lovlar\n/davomat — so'nggi davomat\n/bildirishnoma — xabarlarni sozlash\n/chiqish — ulanishni uzish"
            : "Buyruqlar:\n/ulanish — ota-ona/o'quvchi sifatida ulanish\n/parol — parolni tiklash (xodimlar)\n/holat — ulangan hisob\n\nSaytda «Parolni unutdim» sahifasida loginingizni kiriting — kod shu yerga keladi.";
    }

    private function askContact(int $chat, string $text): void
    {
        $this->tg->sendMessage($chat, $text, ['keyboard' => [[['text' => '📱 Telefon raqamni ulashish', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true]);
    }
}
