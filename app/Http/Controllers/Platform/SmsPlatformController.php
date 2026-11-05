<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Center;
use App\Models\PlatformSetting;
use App\Models\SmsInvoice;
use App\Models\SmsMessage;
use App\Models\SmsOutbox;
use App\Services\Sms\ModemDriver;
use App\Services\SmsInvoiceService;
use App\Support\Format;
use App\Support\SafeInput;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** sAdmin: barcha markazlar SMS jurnali, modem holati, matn saqlash muddati va SMS faktura yaratish. */
class SmsPlatformController extends Controller
{
    public function __construct(private SmsInvoiceService $invoices, private ModemDriver $modem) {}

    public function index(Request $request)
    {
        $messages = SmsMessage::withoutGlobalScopes()
            ->when($request->filled('center_id'), fn ($q) => $q->where('center_id', (int) $request->input('center_id')))
            ->when(SafeInput::string($request->input('status')), fn ($q, $v) => $q->where('status', $v))
            ->when(SafeInput::string($request->input('channel')), fn ($q, $v) => $q->where('channel', $v))
            ->when(SafeInput::string($request->input('q')), fn ($q, $t) => $q->where('phone', 'like', '%'.Format::digits($t).'%'))
            ->orderByDesc('id')->paginate(40)->withQueryString();

        return view('platform.sms.index', [
            'messages' => $messages,
            'centers' => Center::orderBy('name')->pluck('name', 'id'),
            'modem' => ['alive' => $this->modem->available(), 'count' => \App\Models\SmsModem::usable()->count(), 'queue' => SmsOutbox::whereIn('status', ['queued', 'claimed'])->count()],
            'retention' => (int) PlatformSetting::value('sms_text_retention_days', '90'),
            'invoices' => SmsInvoice::withoutGlobalScopes()->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['sms_text_retention_days' => ['required', 'integer', 'min:0', 'max:3650']]);
        PlatformSetting::put('sms_text_retention_days', (string) $data['sms_text_retention_days']);
        AuditLog::record('sms.retention', null, 'SMS matn saqlash muddati: '.(int) $data['sms_text_retention_days'].' kun');

        return back()->with('success', "SMS matnini saqlash muddati yangilandi (0 = cheksiz).");
    }

    public function invoiceStore(Request $request, Center $center): RedirectResponse
    {
        $data = $request->validate([
            'sms_count' => ['required', 'integer', 'min:1', 'max:10000000'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->invoices->create($center, (int) $data['sms_count'], (int) $data['amount'], $data['note'] ?? null, $request->user()->id);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['sms_count' => $e->getMessage()]);
        }

        return back()->with('success', 'SMS faktura yuborildi.');
    }

    public function invoiceCancel(Request $request, int $invoice): RedirectResponse
    {
        try {
            $this->invoices->cancel(SmsInvoice::withoutGlobalScopes()->findOrFail($invoice), $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Faktura bekor qilindi.');
    }
}
