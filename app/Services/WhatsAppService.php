<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * WhatsApp Business Cloud API (Meta) client.
 * Docs: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class WhatsAppService
{
    public function config(string $key): ?string
    {
        return setting("whatsapp_$key") ?: config("crm.whatsapp.$key");
    }

    public function isConfigured(): bool
    {
        return filled($this->config('phone_number_id')) && filled($this->config('access_token'));
    }

    protected function http(): PendingRequest
    {
        return Http::withToken($this->config('access_token'))
            ->baseUrl('https://graph.facebook.com/'.config('crm.whatsapp.api_version'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    /**
     * Store an outbound text message and queue it for delivery.
     * Free-form text is only delivered inside the 24h customer-service window;
     * outside it, use queueTemplate().
     */
    public function queueText(string $phone, string $body, ?Model $contact = null, ?User $user = null): WhatsappMessage
    {
        return $this->queue([
            'phone' => Phone::normalize($phone),
            'type' => 'text',
            'body' => $body,
        ], $contact, $user);
    }

    public function queueTemplate(string $phone, string $template, array $params = [], ?string $language = null, ?Model $contact = null, ?User $user = null): WhatsappMessage
    {
        $tpl = WhatsappTemplate::where('name', $template)->when($language, fn ($q) => $q->where('language', $language))->first();

        return $this->queue([
            'phone' => Phone::normalize($phone),
            'type' => 'template',
            'template_name' => $template,
            'body' => $tpl?->render($params) ?? $template.' '.implode(', ', $params),
            'meta' => ['params' => array_values($params), 'language' => $language ?? $tpl?->language ?? 'en'],
        ], $contact, $user);
    }

    public function queueMedia(string $phone, string $type, string $storagePath, ?string $caption, ?Model $contact = null, ?User $user = null): WhatsappMessage
    {
        return $this->queue([
            'phone' => Phone::normalize($phone),
            'type' => $type, // image | document
            'body' => $caption,
            'media_path' => $storagePath,
            'meta' => ['filename' => basename($storagePath)],
        ], $contact, $user);
    }

    protected function queue(array $attributes, ?Model $contact, ?User $user): WhatsappMessage
    {
        $message = WhatsappMessage::create($attributes + [
            'direction' => 'out',
            'status' => 'queued',
            'contact_type' => $contact?->getMorphClass(),
            'contact_id' => $contact?->getKey(),
            'user_id' => $user?->id ?? auth()->id(),
            'is_read' => true,
        ]);

        SendWhatsAppMessage::dispatch($message->id);

        return $message;
    }

    /**
     * Deliver a stored message via the Cloud API. Called from the queued job.
     */
    public function deliver(WhatsappMessage $message): void
    {
        if (! $this->isConfigured()) {
            $message->update(['status' => 'failed', 'error' => 'WhatsApp is not configured.']);

            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->phone,
            'type' => $message->type,
        ];

        switch ($message->type) {
            case 'template':
                $params = $message->meta['params'] ?? [];
                $payload['template'] = [
                    'name' => $message->template_name,
                    'language' => ['code' => $message->meta['language'] ?? 'en'],
                ];
                if ($params) {
                    $payload['template']['components'] = [[
                        'type' => 'body',
                        'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $params),
                    ]];
                }
                break;

            case 'image':
            case 'document':
                $media = ['link' => Storage::disk('public')->url($message->media_path)];
                if ($message->body) {
                    $media['caption'] = $message->body;
                }
                if ($message->type === 'document') {
                    $media['filename'] = $message->meta['filename'] ?? 'document.pdf';
                }
                $payload[$message->type] = $media;
                break;

            default:
                $payload['type'] = 'text';
                $payload['text'] = ['preview_url' => true, 'body' => (string) $message->body];
        }

        $response = $this->http()->post($this->config('phone_number_id').'/messages', $payload);

        if ($response->successful()) {
            $message->update([
                'wa_message_id' => $response->json('messages.0.id'),
                'status' => 'sent',
                'error' => null,
            ]);

            return;
        }

        $message->update([
            'status' => 'failed',
            'error' => Str::limit($response->json('error.message') ?? $response->body(), 500),
        ]);
    }

    public function markAsRead(string $waMessageId): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $this->http()->post($this->config('phone_number_id').'/messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ]);
    }

    /**
     * Download inbound media to local storage and return its path on the public disk.
     */
    public function downloadMedia(string $mediaId): string
    {
        $meta = $this->http()->get($mediaId);
        if (! $meta->successful()) {
            throw new RuntimeException('Unable to fetch media metadata: '.$meta->body());
        }

        $file = Http::withToken($this->config('access_token'))->timeout(60)->get($meta->json('url'));
        if (! $file->successful()) {
            throw new RuntimeException('Unable to download media.');
        }

        $ext = Str::afterLast((string) $meta->json('mime_type'), '/') ?: 'bin';
        $ext = preg_replace('/[^a-z0-9]/i', '', explode(';', $ext)[0]);
        $path = 'whatsapp/'.now()->format('Y/m').'/'.$mediaId.'.'.$ext;
        Storage::disk('public')->put($path, $file->body());

        return $path;
    }

    /**
     * Pull approved message templates from the WhatsApp Business Account.
     */
    public function syncTemplates(): int
    {
        $waba = $this->config('business_account_id');
        if (! $waba || ! $this->isConfigured()) {
            throw new RuntimeException('Set the WhatsApp Business Account ID and access token first.');
        }

        $count = 0;
        $url = "$waba/message_templates?limit=100";
        do {
            $response = $this->http()->get($url);
            if (! $response->successful()) {
                throw new RuntimeException($response->json('error.message') ?? 'Template sync failed.');
            }

            foreach ($response->json('data', []) as $tpl) {
                $body = collect($tpl['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? null;
                preg_match_all('/\{\{(\d+)\}\}/', (string) $body, $m);
                WhatsappTemplate::updateOrCreate(
                    ['name' => $tpl['name'], 'language' => $tpl['language']],
                    [
                        'category' => $tpl['category'] ?? null,
                        'status' => $tpl['status'] ?? 'APPROVED',
                        'body' => $body,
                        'variable_count' => count(array_unique($m[1])),
                        'components' => $tpl['components'] ?? [],
                    ]
                );
                $count++;
            }

            $next = $response->json('paging.next');
            $url = $next ? Str::after($next, config('crm.whatsapp.api_version').'/') : null;
        } while ($url);

        return $count;
    }

    /**
     * Validate the X-Hub-Signature-256 header Meta sends with every webhook.
     */
    public static function validSignature(string $payload, ?string $header, ?string $secret): bool
    {
        if (! $secret) {
            return true; // signature checking disabled until an app secret is configured
        }
        if (! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $payload, $secret), $header);
    }
}
