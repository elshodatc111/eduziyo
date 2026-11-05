<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\SupportTicket;
use App\Services\SupportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Direktor: qo'llab-quvvatlashga murojaat (bir vaqtda bitta ochiq). Ko'rish rejimida ham ishlaydi. */
class CenterSupportController extends Controller
{
    public function __construct(private SupportService $support) {}

    private function center(Request $request): Center
    {
        return Center::findOrFail($request->user()->center_id);
    }

    private function ticket(Request $request, int $id): SupportTicket
    {
        return SupportTicket::withoutGlobalScopes()->where('center_id', $request->user()->center_id)->findOrFail($id);
    }

    public function index(Request $request)
    {
        $center = $this->center($request);

        return view('support.center', [
            'center' => $center,
            'current' => $current = $this->support->openTicket($center),
            'messages' => $current?->messages,
            'history' => SupportTicket::withoutGlobalScopes()->where('center_id', $center->id)->where('status', SupportTicket::CLOSED)->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function show(Request $request, int $ticket)
    {
        $t = $this->ticket($request, $ticket);

        return view('support.center', ['center' => $this->center($request), 'current' => null, 'viewing' => $t, 'messages' => $t->messages, 'history' => collect()]);
    }

    public const FILE_RULES = ['files' => ['nullable', 'array', 'max:3'], 'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120']];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'min:3', 'max:160'], 'body' => ['required', 'string', 'min:5', 'max:5000']] + self::FILE_RULES, [], ['subject' => 'Mavzu', 'body' => 'Xabar', 'files' => 'Rasm', 'files.*' => 'Rasm']);

        try {
            $this->support->open($this->center($request), $request->user(), $data['subject'], $data['body'], $request->file('files', []));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['subject' => $e->getMessage()]);
        }

        return redirect()->route('center.support')->with('success', "Murojaat yuborildi. Javob ish vaqtida beriladi.");
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']] + self::FILE_RULES, [], ['body' => 'Xabar', 'files' => 'Rasm', 'files.*' => 'Rasm']);

        try {
            $this->support->reply($this->ticket($request, $ticket), $request->user(), $data['body'], $request->file('files', []));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Xabar yuborildi.');
    }

    /** Rasmni faqat shu markaz direktori ko'radi. */
    public function file(Request $request, int $attachment)
    {
        $a = \App\Models\SupportAttachment::withoutGlobalScopes()->where('center_id', $request->user()->center_id)->findOrFail($attachment);

        return $this->serve($a);
    }

    public static function serve(\App\Models\SupportAttachment $a)
    {
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($a->path), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($a->path), [
            'Content-Type' => $a->mime, 'Content-Disposition' => 'inline; filename="'.addslashes($a->original_name).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function close(Request $request, int $ticket): RedirectResponse
    {
        $this->support->close($this->ticket($request, $ticket), 'manual');

        return redirect()->route('center.support.show', $ticket)->with('success', "Murojaat yopildi. Xizmatga baho bering.");
    }

    public function rate(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:255']]);

        try {
            $this->support->rate($this->ticket($request, $ticket), (int) $data['rating'], $data['comment'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bahoingiz uchun rahmat!');
    }
}
