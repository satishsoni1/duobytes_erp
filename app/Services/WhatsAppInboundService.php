<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Notifications\CrmAlert;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

/**
 * Processes WhatsApp Cloud API webhook payloads: inbound messages and delivery statuses.
 */
class WhatsAppInboundService
{
    public function __construct(protected LeadCaptureService $leads) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] => $c['profile']['name'] ?? null]);

                foreach ($value['messages'] ?? [] as $message) {
                    $this->storeInbound($message, $names[$message['from']] ?? null);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->updateStatus($status);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function storeInbound(array $message, ?string $profileName): void
    {
        if (WhatsappMessage::where('wa_message_id', $message['id'])->exists()) {
            return; // Meta retries webhooks; keep processing idempotent.
        }

        $phone = Phone::normalize($message['from']);
        $type = $message['type'] ?? 'text';
        [$body, $mediaId] = $this->extractContent($message, $type);

        $contact = $this->resolveContact($phone, $profileName, $body);

        WhatsappMessage::create([
            'wa_message_id' => $message['id'],
            'phone' => $phone,
            'contact_name' => $profileName,
            'direction' => 'in',
            'type' => $type,
            'body' => $body,
            'media_id' => $mediaId,
            'status' => 'received',
            'contact_type' => $contact?->getMorphClass(),
            'contact_id' => $contact?->getKey(),
            'is_read' => false,
            'meta' => $message,
        ]);

        if ($contact instanceof Lead) {
            $contact->update(['last_contacted_at' => now()]);
        }

        $owner = $contact?->assignee;
        $owner?->notify(new CrmAlert(
            'WhatsApp from '.($contact->name ?? $phone),
            str($body ?: "[$type]")->limit(80)->toString(),
            route('whatsapp.show', $phone),
            'whatsapp',
            'success',
        ));
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{0: ?string, 1: ?string}
     */
    protected function extractContent(array $message, string $type): array
    {
        return match ($type) {
            'text' => [$message['text']['body'] ?? null, null],
            'image', 'video', 'audio', 'document', 'sticker' => [
                $message[$type]['caption'] ?? ($message[$type]['filename'] ?? null),
                $message[$type]['id'] ?? null,
            ],
            'button' => [$message['button']['text'] ?? null, null],
            'interactive' => [
                $message['interactive']['button_reply']['title'] ?? ($message['interactive']['list_reply']['title'] ?? null),
                null,
            ],
            'location' => [
                trim(($message['location']['name'] ?? '').' '.($message['location']['address'] ?? '')
                    .' ('.($message['location']['latitude'] ?? '').','.($message['location']['longitude'] ?? '').')'),
                null,
            ],
            'contacts' => ['Shared contact: '.($message['contacts'][0]['name']['formatted_name'] ?? ''), null],
            default => ['['.$type.' message]', null],
        };
    }

    /**
     * Match the sender to an open lead or customer; otherwise create a WhatsApp lead.
     */
    protected function resolveContact(string $phone, ?string $name, ?string $body): ?Model
    {
        if ($lead = Lead::findOpenDuplicate($phone, null)) {
            return $lead;
        }
        if ($customer = Customer::findByContact($phone)) {
            return $customer;
        }
        if (setting('whatsapp_auto_create_lead', '1') !== '1') {
            return null;
        }

        [$lead] = $this->leads->capture([
            'name' => $name,
            'phone' => $phone,
            'requirement' => $body,
        ], 'whatsapp');

        return $lead;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function updateStatus(array $status): void
    {
        $message = WhatsappMessage::where('wa_message_id', $status['id'] ?? null)->first();
        if (! $message) {
            return;
        }

        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        $new = $status['status'] ?? 'sent';

        // Webhooks may arrive out of order; never downgrade (e.g. read → delivered).
        if (($rank[$new] ?? 0) >= ($rank[$message->status] ?? 0)) {
            $message->update([
                'status' => $new,
                'error' => $new === 'failed' ? ($status['errors'][0]['title'] ?? 'Delivery failed') : $message->error,
            ]);
        }
    }
}
