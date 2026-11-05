<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\PlatformSetting;
use App\Models\SalesApplication;
use App\Models\SalesVideo;
use App\Models\Tariff;
use App\Rules\UzPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * EDUZIYO sotuv sahifasi (ochiq). `/` da doim ko'rinadi (kirgan foydalanuvchiga ham); boshqaruv paneli — `/dashboard`.
 */
class LandingController extends Controller
{
    public const CONTACT_KEYS = ['contact_instagram', 'contact_telegram', 'contact_email', 'contact_phone1', 'contact_phone2', 'contact_address', 'tariff_text', 'demo_text'];

    public function index(Request $request)
    {
        return view('landing.index', [
            'c' => PlatformSetting::many(self::CONTACT_KEYS),
            'news' => NewsPost::published()->orderByDesc('published_at')->limit(3)->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'image_path']),
            'tariffs' => Tariff::where('is_active', true)->orderBy('sort_order')->get(),
            'videos' => SalesVideo::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'applied' => session('applied', false),
            'demoRoles' => app(\App\Services\DemoService::class)->available() ? \App\Services\DemoService::ROLES : [],
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('landing').'#ariza');

        // Botlardan himoya: yashirin maydon to'lgan bo'lsa jimgina "yuborildi" deymiz
        if ($request->filled('website')) {
            return $back->with('applied', true);
        }

        $legal = app(\App\Services\LegalService::class);

        $data = $request->validate([
            'consent' => $legal->consentRequired() ? ['accepted'] : ['nullable'],
            'name' => ['required', 'string', 'max:120'],
            'center_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', new UzPhone],
            'city' => ['nullable', 'string', 'max:80'],
            'branches_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'students_count' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'wants_demo' => ['nullable', 'boolean'],
            'tariff_slug' => ['nullable', 'string', 'max:40', \Illuminate\Validation\Rule::exists('tariffs', 'slug')->where('is_active', true)],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ], [], [
            'name' => 'Ism familiya', 'center_name' => "O'quv markaz nomi", 'phone' => 'Telefon', 'city' => 'Shahar',
            'branches_count' => 'Filiallar soni', 'students_count' => "O'quvchilar soni", 'message' => 'Izoh', 'consent' => 'Rozilik',
        ]);

        // Takror yuborishdan himoya: oxirgi 24 soatda shu raqamdan hali ochiq ariza bo'lsa, yangisi yaratilmaydi
        $exists = SalesApplication::where('phone', $data['phone'])->where('status', 'new')->where('created_at', '>=', now()->subDay())->exists();

        if (! $exists) {
            $seen = array_values(array_intersect((array) $request->session()->get('demo_roles_seen', []), array_keys(\App\Services\DemoService::ROLES)));
            $application = SalesApplication::create(array_merge(collect($data)->except('consent')->all(), $legal->formConsent(), [
                'wants_demo' => $request->boolean('wants_demo'), 'ip' => $request->ip(),
                'demo_roles' => $seen ? implode(',', $seen) : null,
                'telegram_token' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24)),
            ]));
            $back = $back->with('tg_verify_link', app(\App\Services\Telegram\TelegramClient::class)->deepLink('app_'.$application->telegram_token));
            app(\App\Services\Telegram\TelegramNotifier::class)->toSadmins("🆕 Yangi ariza (Telegram tasdig'isiz)\n".e($data['center_name']).' — '.e($data['name']).', '.e($data['phone']));
            Log::info('Yangi sotuv arizasi', ['center' => $data['center_name']]);
        }

        return $back->with('applied', true);
    }

    public function newsIndex()
    {
        return view('landing.news', [
            'c' => PlatformSetting::many(self::CONTACT_KEYS),
            'posts' => NewsPost::published()->orderByDesc('published_at')->paginate(10),
        ]);
    }

    public function newsShow(NewsPost $post)
    {
        abort_unless($post->is_published && $post->published_at && $post->published_at->isPast(), 404);

        return view('landing.news-show', ['c' => PlatformSetting::many(self::CONTACT_KEYS), 'post' => $post]);
    }
}
