<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Profil sahifasidagi «Telegramni ulash / uzish» tugmalari. */
class TelegramLinkController extends Controller
{
    public function link(Request $request, TelegramLinkService $links, TelegramClient $tg): RedirectResponse
    {
        $link = $tg->deepLink('link_'.$links->makeToken($request->user()));

        if (! $link) {
            return back()->with('error', "Telegram bot hali sozlanmagan. Administratorga murojaat qiling.");
        }

        return back()->with('telegram_link', $link);
    }

    public function unlink(Request $request, TelegramLinkService $links): RedirectResponse
    {
        $links->unlink($request->user());

        return back()->with('success', "Telegram uzildi.");
    }
}
