<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Center;
use App\Models\PlatformSetting;
use App\Models\SmsMessage;
use App\Services\Sms\SmsGatewayClient;
use App\Support\Format;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** sAdmin: barcha markazlar SMS jurnali, gateway holati, matn saqlash muddati. */
class SmsPlatformController extends Controller
{
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
            'gateway' => ['configured' => ($gw = SmsGatewayClient::make()) !== null, 'alive' => $gw?->healthy() ?? false, 'queue' => SmsMessage::withoutGlobalScopes()->where('channel', 'gateway')->where('status', SmsMessage::QUEUED)->count()],
            'retention' => (int) PlatformSetting::value('sms_text_retention_days', '90'),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['sms_text_retention_days' => ['required', 'integer', 'min:0', 'max:3650']]);
        PlatformSetting::put('sms_text_retention_days', (string) $data['sms_text_retention_days']);
        AuditLog::record('sms.retention', null, 'SMS matn saqlash muddati: '.(int) $data['sms_text_retention_days'].' kun');

        return back()->with('success', "SMS matnini saqlash muddati yangilandi (0 = cheksiz).");
    }
}
