<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use App\Models\WebhookLog;
use App\Services\LeadCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Token-protected lead intake endpoints for website forms, landing pages, Zapier/Make,
 * inbound email parsers, Google Ads lead forms and marketplaces such as IndiaMART.
 */
class LeadWebhookController extends Controller
{
    public function __construct(protected LeadCaptureService $capture) {}

    /**
     * Generic JSON / form-encoded endpoint (website, landing pages, Zapier, Make, Pabbly).
     */
    public function generic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'city' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:5000'],
            'product' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string', 'max:30'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
        ]);

        $log = WebhookLog::create(['source' => 'website', 'payload' => $request->except('token')]);

        $source = $validated['source'] ?? $this->sourceFromUtm($validated['utm_source'] ?? null);
        [$lead, $created] = $this->capture->capture([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'city' => $validated['city'] ?? null,
            'budget' => $validated['budget'] ?? null,
            'campaign' => $validated['campaign'] ?? $validated['utm_campaign'] ?? null,
            'requirement' => trim(($validated['product'] ?? '')."\n".($validated['message'] ?? '')) ?: null,
        ], $source, ['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'utm' => $request->only(['utm_source', 'utm_medium', 'utm_campaign'])]);

        $log->update(['status' => 'processed']);

        return response()->json(['success' => true, 'lead' => $lead->code, 'duplicate' => ! $created], $created ? 201 : 200);
    }

    /**
     * Inbound email parse webhook – supports Mailgun, SendGrid Inbound Parse and Postmark payloads.
     * Emails quoting a service ticket number (e.g. "SR-2026-00012") are appended to that ticket.
     */
    public function email(Request $request): JsonResponse
    {
        $log = WebhookLog::create(['source' => 'email', 'payload' => $request->except(['attachments', 'token'])]);

        $fromRaw = $request->input('FromFull.Email')
            ? $request->input('FromName').' <'.$request->input('FromFull.Email').'>'
            : ($request->input('from') ?? $request->input('sender') ?? '');
        [$name, $email] = $this->parseAddress((string) $fromRaw);

        $subject = (string) ($request->input('Subject') ?? $request->input('subject') ?? '');
        $body = (string) ($request->input('TextBody') ?? $request->input('stripped-text') ?? $request->input('body-plain') ?? $request->input('text') ?? '');
        $body = Str::limit(trim($body), 3000);

        if (! $email) {
            $log->update(['status' => 'failed', 'error' => 'No sender address']);

            return response()->json(['success' => false, 'message' => 'Sender email missing.'], 422);
        }

        if (preg_match('/SR-\d{4}-\d{5}/', $subject, $m) && $ticket = ServiceTicket::where('number', $m[0])->first()) {
            $ticket->logActivity("Email from $email: $subject\n\n".Str::limit($body, 1500), 'email');
            $log->update(['status' => 'processed']);

            return response()->json(['success' => true, 'ticket' => $ticket->number]);
        }

        preg_match('/(?:\+?\d[\d\s-]{8,14}\d)/', $body, $phone);

        [$lead, $created] = $this->capture->capture([
            'name' => $name ?: Str::before($email, '@'),
            'email' => $email,
            'phone' => $phone[0] ?? null,
            'requirement' => trim($subject."\n\n".$body),
        ], 'email');

        $log->update(['status' => 'processed']);

        return response()->json(['success' => true, 'lead' => $lead->code, 'duplicate' => ! $created]);
    }

    /**
     * Google Ads lead form extension webhook.
     */
    public function googleAds(Request $request): JsonResponse
    {
        if (! hash_equals((string) setting('webhook_token'), (string) $request->input('google_key'))) {
            return response()->json(['message' => 'Invalid key.'], 401);
        }

        $log = WebhookLog::create(['source' => 'google_ads', 'payload' => $request->all()]);
        $cols = collect($request->input('user_column_data', []))->mapWithKeys(fn ($c) => [strtoupper($c['column_id'] ?? '') => $c['string_value'] ?? null]);

        $this->capture->capture([
            'name' => $cols['FULL_NAME'] ?? trim(($cols['FIRST_NAME'] ?? '').' '.($cols['LAST_NAME'] ?? '')),
            'phone' => $cols['PHONE_NUMBER'] ?? null,
            'email' => $cols['EMAIL'] ?? null,
            'city' => $cols['CITY'] ?? null,
            'source_ref' => $request->input('lead_id'),
            'campaign' => (string) $request->input('campaign_id'),
            'requirement' => $cols->except(['FULL_NAME', 'FIRST_NAME', 'LAST_NAME', 'PHONE_NUMBER', 'EMAIL', 'CITY'])
                ->map(fn ($v, $k) => Str::headline(strtolower($k)).": $v")->implode("\n") ?: null,
        ], 'google_ads', ['google' => $request->all()]);

        $log->update(['status' => 'processed']);

        return response()->json(['success' => true]);
    }

    /**
     * IndiaMART / TradeIndia push API.
     */
    public function indiamart(Request $request): JsonResponse
    {
        $log = WebhookLog::create(['source' => 'indiamart', 'payload' => $request->all()]);
        $r = $request->input('RESPONSE', $request->all());

        $this->capture->capture([
            'name' => $r['SENDER_NAME'] ?? null,
            'phone' => $r['SENDER_MOBILE'] ?? null,
            'email' => $r['SENDER_EMAIL'] ?? null,
            'city' => $r['SENDER_CITY'] ?? null,
            'address' => $r['SENDER_ADDRESS'] ?? null,
            'source_ref' => $r['UNIQUE_QUERY_ID'] ?? null,
            'requirement' => trim(($r['SUBJECT'] ?? '')."\n".($r['QUERY_PRODUCT_NAME'] ?? '')."\n".strip_tags($r['QUERY_MESSAGE'] ?? '')) ?: null,
        ], 'indiamart', ['indiamart' => $r]);

        $log->update(['status' => 'processed']);

        return response()->json(['CODE' => 200, 'STATUS' => 'SUCCESS']);
    }

    protected function sourceFromUtm(?string $utm): string
    {
        return match (strtolower((string) $utm)) {
            'facebook', 'fb' => 'facebook',
            'instagram', 'ig' => 'instagram',
            'google', 'adwords', 'google_ads' => 'google_ads',
            default => 'website',
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseAddress(string $raw): array
    {
        if (preg_match('/^\s*"?([^"<]*)"?\s*<([^>]+)>/', $raw, $m)) {
            return [trim($m[1]) ?: null, filter_var(trim($m[2]), FILTER_VALIDATE_EMAIL) ?: null];
        }

        return [null, filter_var(trim($raw), FILTER_VALIDATE_EMAIL) ?: null];
    }
}
