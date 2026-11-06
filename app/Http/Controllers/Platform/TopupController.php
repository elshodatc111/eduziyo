<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CenterBalanceTransaction;
use App\Models\PaymentCard;
use App\Models\TopupRequest;
use App\Services\CenterBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** sAdmin: markaz balansini to'ldirish so'rovlarini ko'rib chiqish va to'lov kartalari. */
class TopupController extends Controller
{
    public function index(Request $request)
    {
        return view('platform.topups', [
            'focus' => (int) $request->query('id'),
            'pending' => TopupRequest::withoutGlobalScopes()->where('status', 'pending')->with('card')->orderBy('id')->get(),
            'done' => TopupRequest::withoutGlobalScopes()->where('status', '!=', 'pending')->with('card')->latest('decided_at')->limit(30)->get(),
            'centers' => \App\Models\Center::pluck('name', 'id'),
            'cards' => PaymentCard::orderBy('id')->get(),
        ]);
    }

    public function receipt(int $topup)
    {
        $row = TopupRequest::withoutGlobalScopes()->findOrFail($topup);
        abort_unless($row->receipt_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($row->receipt_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($row->receipt_path);
    }

    public function approve(Request $request, int $topup, CenterBalanceService $balance): RedirectResponse
    {
        $data = $request->validate(['approved_amount' => ['required', 'integer', 'min:1', 'max:2000000000']]);

        DB::transaction(function () use ($topup, $data, $request, $balance) {
            $row = TopupRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($topup);
            abort_unless($row->status === TopupRequest::PENDING, 409, "Bu so'rov allaqachon ko'rib chiqilgan.");

            $balance->credit($row->center_id, (int) $data['approved_amount'], CenterBalanceTransaction::TOPUP, "To'ldirish #{$row->id}", $request->user()->id);
            $row->update(['status' => TopupRequest::APPROVED, 'approved_amount' => $data['approved_amount'], 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            AuditLog::record('topup.approved', null, "Markaz #{$row->center_id} balansi to'ldirildi: {$data['approved_amount']}");
        });

        return back()->with('success', "Tasdiqlandi, balans to'ldirildi.");
    }

    public function reject(Request $request, int $topup): RedirectResponse
    {
        $data = $request->validate(['reject_reason' => ['required', 'string', 'max:255']]);

        $row = TopupRequest::withoutGlobalScopes()->findOrFail($topup);
        abort_unless($row->status === TopupRequest::PENDING, 409, "Bu so'rov allaqachon ko'rib chiqilgan.");
        $row->update(['status' => TopupRequest::REJECTED, 'reject_reason' => $data['reject_reason'], 'decided_by' => $request->user()->id, 'decided_at' => now()]);

        return back()->with('success', "So'rov rad etildi.");
    }

    public function cardStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:30'], 'holder' => ['required', 'string', 'max:120'],
            'bank' => ['nullable', 'string', 'max:80'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        PaymentCard::create($data);

        return back()->with('success', "Karta qo'shildi.");
    }

    public function cardToggle(PaymentCard $card): RedirectResponse
    {
        $card->update(['is_active' => ! $card->is_active]);

        return back()->with('success', $card->is_active ? 'Karta yoqildi.' : "Karta o'chirildi (markazlarga ko'rinmaydi).");
    }

    public function cardDestroy(PaymentCard $card): RedirectResponse
    {
        $card->delete();

        return back()->with('success', "Karta o'chirildi.");
    }
}
