<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Sotuv arizalari: faqat sAdmin (route `role:sadmin`). */
class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));
        $only = (int) $request->query('id');   // Telegram havolasi: bitta arizani ochish

        $apps = SalesApplication::query()
            ->when($only > 0, fn ($x) => $x->whereKey($only))
            ->when(isset(SalesApplication::STATUSES[$status]), fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('center_name', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('sales.applications', [
            'apps' => $apps,
            'counts' => SalesApplication::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
            'status' => $status,
            'q' => $q,
            'only' => $only,
        ]);
    }

    public function update(Request $request, SalesApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SalesApplication::STATUSES))],
            'follow_up_at' => ['nullable', 'date'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update($data);

        return back()->with('success', "«{$application->center_name}» arizasi yangilandi.");
    }

    public function destroy(SalesApplication $application): RedirectResponse
    {
        $application->delete();

        return back()->with('success', "Ariza o'chirildi.");
    }
}
