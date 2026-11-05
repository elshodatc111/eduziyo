<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\LegalDocument;
use App\Models\UserConsent;
use App\Services\LegalService;
use App\Support\Xlsx\XlsxWriter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** sAdmin: huquqiy hujjatlar (qoralama, e'lon, yangi versiya) va rozilik hisoboti. */
class LegalDocumentController extends Controller
{
    public function index()
    {
        return view('platform.legal.index', [
            'docs' => LegalDocument::orderBy('type')->orderBy('audience')->orderByDesc('version')->get(),
        ]);
    }

    public function create()
    {
        return view('platform.legal.form', ['doc' => new LegalDocument(['type' => 'privacy', 'audience' => 'all', 'requires_reacceptance' => true])]);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:100000'],
            'requires_reacceptance' => ['nullable', 'boolean'],
        ];
    }

    private const ATTR = ['title' => 'Sarlavha', 'body' => 'Matn', 'type' => 'Turi', 'audience' => 'Kim uchun'];

    public function store(Request $request, LegalService $legal): RedirectResponse
    {
        $d = $request->validate($this->rules() + [
            'type' => ['required', Rule::in(array_keys(LegalDocument::TYPES))],
            'audience' => ['required', Rule::in(array_keys(LegalDocument::AUDIENCES))],
        ], [], self::ATTR);

        $doc = $legal->newDraft($d['type'], $d['audience'], $d['title'], $d['body'], $request->boolean('requires_reacceptance'));

        return redirect()->route('platform.legal.edit', $doc->id)->with('success', 'Qoralama saqlandi.');
    }

    public function edit(LegalDocument $doc)
    {
        return view('platform.legal.form', compact('doc'));
    }

    public function update(Request $request, LegalDocument $doc): RedirectResponse
    {
        if ($doc->isPublished()) {
            return back()->with('error', "E'lon qilingan hujjat o'zgartirilmaydi. Yangi versiya yarating.");
        }

        $d = $request->validate($this->rules(), [], self::ATTR);
        $doc->update(['title' => $d['title'], 'body' => $d['body'], 'requires_reacceptance' => $request->boolean('requires_reacceptance')]);

        return back()->with('success', 'Saqlandi.');
    }

    public function publish(Request $request, LegalDocument $doc, LegalService $legal): RedirectResponse
    {
        $request->validate(['effective_at' => ['nullable', 'date', 'after_or_equal:today']], [], ['effective_at' => 'Kuchga kirish sanasi']);

        try {
            $legal->publish($doc, $request->user(), $request->filled('effective_at') ? \Illuminate\Support\Carbon::parse($request->input('effective_at')) : null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('platform.legal')->with('success', "Hujjat e'lon qilindi. Foydalanuvchilardan rozilik so'raladi.");
    }

    public function newVersion(LegalDocument $doc, LegalService $legal): RedirectResponse
    {
        try {
            $new = $legal->newVersionFrom($doc);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('platform.legal.edit', $new->id)->with('success', "Yangi versiya qoralamasi yaratildi. Tahrirlab e'lon qiling.");
    }

    public function destroy(LegalDocument $doc): RedirectResponse
    {
        if ($doc->isPublished() || UserConsent::where('legal_document_id', $doc->id)->exists()) {
            return back()->with('error', "E'lon qilingan hujjatni o'chirib bo'lmaydi.");
        }
        $doc->delete();

        return redirect()->route('platform.legal')->with('success', "Qoralama o'chirildi.");
    }

    public function report(Request $request, LegalService $legal)
    {
        $centerId = $request->filled('center') ? (int) $request->input('center') : null;
        $data = $legal->report($centerId);

        $consents = UserConsent::with('document:id,type,audience,version,title')
            ->when($centerId, fn ($q) => $q->where('center_ref', $centerId))->orderByDesc('accepted_at');

        if ($request->input('export') === 'xlsx') {
            $rows = $consents->get()->map(fn ($c) => [
                $c->user_name, $c->user_role, $c->center_name, $c->document?->title, 'v'.$c->document?->version,
                $c->accepted_at->format('d.m.Y H:i'), $c->ip, $c->method,
            ])->all();
            $path = XlsxWriter::write('Rozilik', ['F.I.O', 'Rol', 'Markaz', 'Hujjat', 'Versiya', 'Qabul qilingan vaqt', 'IP', 'Usul'], $rows, 'Hujjatlarga rozilik hisoboti');

            return response()->download($path, 'rozilik-hisoboti-'.now()->format('Ymd').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return view('platform.legal.report', [
            'data' => $data, 'centers' => Center::orderBy('name')->get(['id', 'name']), 'centerId' => $centerId,
            'consents' => $consents->paginate(30)->withQueryString(),
        ]);
    }
}
