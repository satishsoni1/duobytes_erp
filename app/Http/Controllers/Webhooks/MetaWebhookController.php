<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WebhookLog;
use App\Services\LeadCaptureService;
use App\Services\MetaLeadService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Facebook Page + Instagram webhooks: Lead Ads ("leadgen") and direct messages.
 */
class MetaWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = setting('meta_verify_token') ?: config('crm.meta.verify_token');

        if ($request->query('hub_mode') === 'subscribe' && $verifyToken && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, MetaLeadService $meta, LeadCaptureService $capture): Response
    {
        $secret = setting('meta_app_secret') ?: config('crm.meta.app_secret');
        if (! WhatsAppService::validSignature($request->getContent(), $request->header('X-Hub-Signature-256'), $secret)) {
            return response('Invalid signature', 401);
        }

        $log = WebhookLog::create(['source' => 'meta', 'payload' => $request->all()]);
        $platform = $request->input('object') === 'instagram' ? 'instagram' : 'facebook';

        try {
            foreach ($request->input('entry', []) as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? null) === 'leadgen' && ! empty($change['value']['leadgen_id'])) {
                        $lead = $meta->fetchLead((string) $change['value']['leadgen_id']);
                        $capture->capture($lead['data'], $lead['source'], ['meta' => $lead['raw']]);
                    }
                }

                foreach ($entry['messaging'] ?? [] as $event) {
                    $this->handleDirectMessage($event, $platform, $entry['id'] ?? null, $meta, $capture);
                }
            }
            $log->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function handleDirectMessage(array $event, string $platform, ?string $pageId, MetaLeadService $meta, LeadCaptureService $capture): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $text = $event['message']['text'] ?? null;

        // Ignore echoes of our own replies and non-message events.
        if (! $senderId || $senderId === $pageId || ! empty($event['message']['is_echo']) || ! isset($event['message'])) {
            return;
        }

        $existing = Lead::where('source', $platform)->where('source_ref', $senderId)->first();
        if ($existing && ! $existing->isClosed()) {
            $existing->logActivity(ucfirst($platform).' DM: '.($text ?? '[attachment]'), 'note');

            return;
        }

        $capture->capture([
            'name' => $meta->profileName($senderId, $platform),
            'source_ref' => $senderId,
            'requirement' => $text,
        ], $platform, ['dm' => $event], sendWelcome: false);
    }
}
