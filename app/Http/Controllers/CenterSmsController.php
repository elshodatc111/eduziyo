<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\SmsInvoice;
use App\Models\SmsMessage;
use App\Services\SmsInvoiceService;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use App\Support\Format;
use App\Support\SafeInput;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Direktor: o'z markazining SMS jurnali, limit holati va sAdmin yuborgan SMS fakturani qabul/rad etish. */
class CenterSmsController extends Controller
{
    public function __construct(private SmsInvoiceService $invoices, private UsageService $usage, private SubscriptionService $subs) {}

    public function index(Request $request)
    {
        $center = Center::findOrFail($request->user()->center_id);
        $limits = $this->subs->limits($center);

        $messages = SmsMessage::withoutGlobalScopes()->where('center_id', $center->id)
            ->when(SafeInput::string($request->input('status')), fn ($q, $v) => $q->where('status', $v))
            ->when(SafeInput::string($request->input('q')), fn ($q, $t) => $q->where('phone', 'like', '%'.Format::digits($t).'%'))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('center.sms', [
            'center' => $center,
            'messages' => $messages,
            'limit' => $limits['sms_monthly'] ?? null,
            'used' => $this->usage->used($center, 'sms'),
            'extra' => $this->usage->extra($center),
            'invoice' => $this->invoices->active($center),
            'history' => SmsInvoice::withoutGlobalScopes()->where('center_id', $center->id)->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    private function own(Request $request, int $id): SmsInvoice
    {
        return SmsInvoice::withoutGlobalScopes()->where('center_id', $request->user()->center_id)->findOrFail($id);
    }

    public function accept(Request $request, int $invoice): RedirectResponse
    {
        try {
            $this->invoices->accept($this->own($request, $invoice), $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "SMS paketi qo'shildi.");
    }

    public function decline(Request $request, int $invoice): RedirectResponse
    {
        try {
            $this->invoices->cancel($this->own($request, $invoice), $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Faktura rad etildi.');
    }
}
