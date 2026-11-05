<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureUserIsActive;
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
 * EDUZIYO sotuv sahifasi (ochiq). `/` — mehmon uchun sotuv sahifasi, kirgan foydalanuvchi uchun boshqaruv paneli
 * (shuning uchun eski `route('dashboard')` va barcha yo'naltirishlar o'zgarishsiz ishlaydi).
 */
class LandingController extends Controller
{
    public const CONTACT_KEYS = ['contact_instagram', 'contact_telegram', 'contact_email', 'contact_phone1', 'contact_phone2', 'contact_address', 'tariff_text', 'demo_text', 'demo_login', 'demo_password'];

    public function index(Request $request)
    {
        if ($request->user()) {
            // Kirgan foydalanuvchi: avvalgidek panel (faollik tekshiruvi bilan)
            return app(EnsureUserIsActive::class)->handle($request, function ($r) {
                return app('router')->prepareResponse($r, app()->call([app(DashboardController::class), 'index']));
            });
        }

        return view('landing.index', [
            'c' => PlatformSetting::many(self::CONTACT_KEYS),
            'news' => NewsPost::published()->orderByDesc('published_at')->limit(3)->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'image_path']),
            'tariffs' => Tariff::where('is_active', true)->orderBy('sort_order')->get(),
            'videos' => SalesVideo::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'applied' => session('applied', false),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('dashboard').'#ariza');

        // Botlardan himoya: yashirin maydon to'lgan bo'lsa jimgina "yuborildi" deymiz
        if ($request->filled('website')) {
            return $back->with('applied', true);
        }

        $data = $request->validate([
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
            'branches_count' => 'Filiallar soni', 'students_count' => "O'quvchilar soni", 'message' => 'Izoh',
        ]);

        // Takror yuborishdan himoya: oxirgi 24 soatda shu raqamdan hali ochiq ariza bo'lsa, yangisi yaratilmaydi
        $exists = SalesApplication::where('phone', $data['phone'])->where('status', 'new')->where('created_at', '>=', now()->subDay())->exists();

        if (! $exists) {
            SalesApplication::create(array_merge($data, ['wants_demo' => $request->boolean('wants_demo'), 'ip' => $request->ip()]));
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
