<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesVideo;
use App\Support\YouTube;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Sotuv sahifasidagi YouTube videolar: faqat sAdmin. */
class VideoController extends Controller
{
    public function index()
    {
        return view('sales.videos', ['videos' => SalesVideo::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], ['title' => 'Sarlavha', 'url' => 'YouTube havolasi', 'description' => 'Tavsif']);

        $id = YouTube::id($data['url']);
        if (! $id) {
            throw ValidationException::withMessages(['url' => "YouTube havolasi noto'g'ri. Masalan: https://www.youtube.com/watch?v=..."]);
        }

        SalesVideo::create([
            'title' => $data['title'], 'youtube_id' => $id, 'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? (SalesVideo::max('sort_order') + 1), 'is_active' => true,
        ]);

        return back()->with('success', "Video qo'shildi.");
    }

    public function toggle(SalesVideo $video): RedirectResponse
    {
        $video->update(['is_active' => ! $video->is_active]);

        return back()->with('success', $video->is_active ? 'Video sahifada ko\'rinadi.' : 'Video yashirildi.');
    }

    public function destroy(SalesVideo $video): RedirectResponse
    {
        $video->delete();

        return back()->with('success', "Video o'chirildi.");
    }
}
