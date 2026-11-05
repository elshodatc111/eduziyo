<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\VideoGuide;
use App\Support\YouTube;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** sAdmin: rol bo'yicha video qo'llanmalar (havola kiritish, rolga biriktirish). O'quvchi roli ruxsat etilmaydi. */
class VideoGuideController extends Controller
{
    private function roles(): array
    {
        return collect(Role::cases())->reject(fn ($r) => $r === Role::Student)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all();
    }

    public function index()
    {
        return view('guides.manage', ['guides' => VideoGuide::orderBy('sort_order')->orderBy('id')->get(), 'roles' => $this->roles()]);
    }

    private function data(Request $request): array
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:500'],
            'roles' => ['required', 'array', 'min:1'], 'roles.*' => ['string', Rule::in(array_keys($this->roles()))],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], ['title' => 'Sarlavha', 'url' => 'YouTube havolasi', 'roles' => 'Rollar', 'cover' => 'Muqova rasmi']);

        $id = YouTube::id($d['url']);
        if (! $id) {
            throw ValidationException::withMessages(['url' => "YouTube havolasi noto'g'ri. Masalan: https://www.youtube.com/watch?v=..."]);
        }

        return ['title' => $d['title'], 'url' => 'https://www.youtube.com/watch?v='.$id, 'roles' => array_values($d['roles']), 'sort_order' => $d['sort_order'] ?? 100];
    }

    private function saveCover(Request $request, ?VideoGuide $guide, array $data): array
    {
        if ($request->hasFile('cover')) {
            if ($guide?->cover_path) {
                \Illuminate\Support\Facades\Storage::disk('uploads')->delete($guide->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store('guides', 'uploads');
        }

        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        VideoGuide::create($this->saveCover($request, null, $this->data($request)));

        return back()->with('success', "Video qo'llanma qo'shildi.");
    }

    public function update(Request $request, VideoGuide $guide): RedirectResponse
    {
        $guide->update($this->saveCover($request, $guide, $this->data($request)));

        return back()->with('success', 'Saqlandi.');
    }

    public function setCover(Request $request, VideoGuide $guide): RedirectResponse
    {
        $request->validate(['cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']], [], ['cover' => 'Muqova rasmi']);
        $guide->update($this->saveCover($request, $guide, []));

        return back()->with('success', 'Muqova saqlandi.');
    }

    public function removeCover(VideoGuide $guide): RedirectResponse
    {
        if ($guide->cover_path) {
            \Illuminate\Support\Facades\Storage::disk('uploads')->delete($guide->cover_path);
            $guide->update(['cover_path' => null]);
        }

        return back()->with('success', "Muqova olib tashlandi (YouTube rasmi ko'rsatiladi).");
    }

    public function toggle(VideoGuide $guide): RedirectResponse
    {
        $guide->update(['is_active' => ! $guide->is_active]);

        return back()->with('success', $guide->is_active ? 'Yoqildi.' : "O'chirildi (foydalanuvchilarga ko'rinmaydi).");
    }

    public function destroy(VideoGuide $guide): RedirectResponse
    {
        if ($guide->cover_path) {
            \Illuminate\Support\Facades\Storage::disk('uploads')->delete($guide->cover_path);
        }
        $guide->delete();

        return back()->with('success', "Video qo'llanma o'chirildi.");
    }
}
