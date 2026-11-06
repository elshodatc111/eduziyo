<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use App\Services\SmsService;
use App\Support\IntegrationSettings as S;
use Illuminate\Http\Request;

/**
 * SMS Gateway webhook'i: xabar yakuniy holatga o'tganda (sent / failed / expired) keladi.
 * Imzo: HMAC-SHA256(sir, timestamp + "." + xom_tana), sarlavhalar X-SMS-Timestamp, X-SMS-Signature.
 */
class SmsGatewayWebhookController extends Controller
{
    public function __invoke(Request $request, SmsService $sms)
    {
        $secret = S::get('gateway_secret');
        $ts = (string) $request->header('X-SMS-Timestamp');
        $sig = (string) $request->header('X-SMS-Signature');

        if (! filled($secret) || $ts === '' || ! ctype_digit($ts) || abs(now()->timestamp - (int) $ts) > 600
            || ! hash_equals('sha256='.hash_hmac('sha256', $ts.'.'.$request->getContent(), $secret), $sig)) {
            return response()->json(['error' => 'invalid_signature'], 403);
        }

        $data = (array) $request->json('data', []);
        $ext = (string) ($data['external_id'] ?? '');
        $sms_id = str_starts_with($ext, 'eduziyo-') ? (int) substr($ext, 8) : 0;

        $msg = $sms_id > 0 ? SmsMessage::withoutGlobalScopes()->find($sms_id) : null;
        if ($msg && $msg->channel === 'gateway' && $msg->status === SmsMessage::QUEUED) {
            $sms->applyGatewayStatus($msg, (string) ($data['status'] ?? ''), $data['error'] ?? null);
        }

        return response()->json(['ok' => true]);   // noma'lum/eskirgan xabar uchun ham 200: gateway qayta urinmasin
    }
}
