<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Support\YouTube;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Yangiliklarni boshqarish: faqat sAdmin. Yangiliklar faqat sotuv sahifasida ko'rinadi. Rasm va YouTube video qo'yish mumkin. */
class NewsController extends Controller
{
    public function index()
    {
        return view('sales.news.index', ['posts' => NewsPost::orderByDesc('created_at')->paginate(20)]);
    }

    public function create()
    {
        return view('sales.news.form', ['post' => new NewsPost(['is_published' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = NewsPost::makeSlug($data['title']);
        $data['created_by'] = $request->user()->id;
        NewsPost::create($data);

        return redirect()->route('sales.news.index')->with('success', 'Yangilik saqlandi.');
    }

    public function edit(NewsPost $post)
    {
        return view('sales.news.form', ['post' => $post]);
    }

    public function update(Request $request, NewsPost $post): RedirectResponse
    {
        $old = $post->image_path;
        $data = $this->validated($request, $post);
        $post->update($data);

        if ($old && $old !== $post->image_path) {
            Storage::disk('uploads')->delete($old);
        }

        return redirect()->route('sales.news.index')->with('success', 'Yangilik yangilandi.');
    }

    public function destroy(NewsPost $post): RedirectResponse
    {
        if ($post->image_path) {
            Storage::disk('uploads')->delete($post->image_path);
        }
        $post->delete();

        return redirect()->route('sales.news.index')->with('success', "Yangilik o'chirildi.");
    }

    private function validated(Request $request, ?NewsPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=6000,max_height=6000'],
            'youtube_url' => ['nullable', 'string', 'max:200'],
        ], [
            'image.max' => 'Rasm hajmi 3 MB dan oshmasligi kerak.',
            'image.image' => 'Faqat JPG, PNG yoki WEBP rasm yuklang.',
            'image.mimes' => 'Faqat JPG, PNG yoki WEBP rasm yuklang.',
        ], ['title' => 'Sarlavha', 'excerpt' => 'Qisqa matn', 'body' => 'Matn', 'published_at' => 'Chop etish vaqti', 'image' => 'Rasm', 'youtube_url' => 'YouTube havolasi']);

        $youtubeId = null;
        if (filled($data['youtube_url'] ?? null)) {
            $youtubeId = YouTube::id($data['youtube_url']);
            if (! $youtubeId) {
                throw ValidationException::withMessages(['youtube_url' => "YouTube havolasi noto'g'ri. Masalan: https://www.youtube.com/watch?v=..."]);
            }
        }

        $imagePath = $post?->image_path;
        if ($request->boolean('remove_image')) {
            $imagePath = null;
        }
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('news', 'uploads');
        }

        $publish = $request->boolean('is_published');
        $publishedAt = $publish ? ($data['published_at'] ?? $post?->published_at ?? now()) : ($data['published_at'] ?? $post?->published_at);

        return [
            'title' => $data['title'], 'excerpt' => $data['excerpt'] ?? null, 'body' => $data['body'],
            'is_published' => $publish, 'published_at' => $publishedAt,
            'image_path' => $imagePath, 'youtube_id' => $youtubeId,
        ];
    }
}
