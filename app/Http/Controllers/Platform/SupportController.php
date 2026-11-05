<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\PlatformSetting;
use App\Models\SupportTicket;
use App\Services\SupportService;
use App\Support\WorkHours;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** sAdmin: barcha markazlar murojaatlari, javob berish, yopish va ish vaqti/SLA sozlamasi. */
class SupportController extends Controller
{
    public function __construct(private SupportService $support) {}

    public function index(Request $request)
    {
        $status = in_array($request->input('status'), ['open', 'pending_customer', 'closed'], true) ? $request->input('status') : null;

        return view('support.platform', [
            'tickets' => SupportTicket::withoutGlobalScopes()->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'open' then 0 when 'pending_customer' then 1 else 2 end")->orderByDesc('id')->paginate(30)->withQueryString(),
            'centers' => Center::pluck('name', 'id'),
            'cfg' => WorkHours::config(),
            'avg' => round((float) SupportTicket::withoutGlobalScopes()->whereNotNull('rating')->avg('rating'), 2),
        ]);
    }

    public function show(int $ticket)
    {
        $t = SupportTicket::withoutGlobalScopes()->findOrFail($ticket);

        return view('support.platform-show', ['ticket' => $t, 'center' => Center::find($t->center_id), 'messages' => $t->messages]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']] + \App\Http\Controllers\CenterSupportController::FILE_RULES, [], ['files' => 'Rasm', 'files.*' => 'Rasm']);

        try {
            $this->support->reply(SupportTicket::withoutGlobalScopes()->findOrFail($ticket), $request->user(), $data['body'], $request->file('files', []));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Javob yuborildi.');
    }

    public function file(int $attachment)
    {
        return \App\Http\Controllers\CenterSupportController::serve(\App\Models\SupportAttachment::withoutGlobalScopes()->findOrFail($attachment));
    }

    public function close(int $ticket): RedirectResponse
    {
        $this->support->close(SupportTicket::withoutGlobalScopes()->findOrFail($ticket), 'manual');

        return back()->with('success', 'Murojaat yopildi.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'support_start' => ['required', 'date_format:H:i'], 'support_end' => ['required', 'date_format:H:i', 'after:support_start'],
            'support_days' => ['required', 'array', 'min:1'], 'support_days.*' => ['integer', 'between:1,7'],
            'support_sla_hours' => ['required', 'numeric', 'min:0.5', 'max:72'],
        ], [], ['support_start' => 'Boshlanish', 'support_end' => 'Tugash', 'support_days' => 'Ish kunlari', 'support_sla_hours' => 'SLA (soat)']);

        PlatformSetting::put('support_start', $d['support_start']);
        PlatformSetting::put('support_end', $d['support_end']);
        PlatformSetting::put('support_days', implode(',', array_unique(array_map('intval', $d['support_days']))));
        PlatformSetting::put('support_sla_hours', (string) $d['support_sla_hours']);

        return back()->with('success', 'Ish vaqti va SLA saqlandi.');
    }
}
