<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\WhatsAppInboundService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta webhook subscription handshake.
     */
    public function verify(Request $request, WhatsAppService $whatsapp): Response
    {
        $verifyToken = $whatsapp->config('verify_token');

        if ($request->query('hub_mode') === 'subscribe' && $verifyToken && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsAppInboundService $inbound): Response
    {
        $secret = setting('meta_app_secret') ?: config('crm.whatsapp.app_secret');
        if (! WhatsAppService::validSignature($request->getContent(), $request->header('X-Hub-Signature-256'), $secret)) {
            return response('Invalid signature', 401);
        }

        $log = WebhookLog::create(['source' => 'whatsapp', 'payload' => $request->all()]);

        try {
            $inbound->handle($request->all());
            $log->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        // Always 200 so Meta does not disable the webhook; failures are visible in the webhook log.
        return response('EVENT_RECEIVED', 200);
    }
}
