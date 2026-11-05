@php
    $tgUser = auth()->user();
    $tgChat = app(\App\Services\Telegram\TelegramLinkService::class)->chatOf($tgUser);
    $tgCabinet = request()->routeIs('cabinet.*');
@endphp
<div class="card card-body">
    <h2 class="mb-1 font-bold text-ink-900 dark:text-white">Telegram</h2>
    @if ($tgChat)
        <p class="text-sm text-ink-600 dark:text-ink-300">Hisobingiz Telegram'ga ulangan. Parol tiklash kodi va muhim xabarlar botga keladi.</p>
        <form method="POST" action="{{ route($tgCabinet ? 'cabinet.telegram.unlink' : 'telegram.unlink') }}" class="mt-3" onsubmit="return confirm('Telegram uzilsinmi? Parol tiklash imkoni vaqtincha yo\'qoladi.')">@csrf @method('DELETE')
            <button class="btn-secondary btn-sm">Uzish</button>
        </form>
    @else
        <p class="text-sm text-ink-600 dark:text-ink-300">Parolni unutsangiz, tiklash kodi Telegram bot orqali keladi. Hisobni ulab qo'ying.</p>
        @if (session('telegram_link'))
            <a href="{{ session('telegram_link') }}" target="_blank" rel="noopener" class="btn-primary mt-3 w-fit">Botni ochish va ulash</a>
            <p class="mt-2 text-xs text-ink-500">Havola 15 daqiqa amal qiladi. Botda «Start» tugmasini bosing.</p>
        @else
            <form method="POST" action="{{ route($tgCabinet ? 'cabinet.telegram.link' : 'telegram.link') }}" class="mt-3">@csrf
                <button class="btn-primary btn-sm">Telegramni ulash</button>
            </form>
        @endif
    @endif
</div>
