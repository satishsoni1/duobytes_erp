<?php

namespace App\Services;

use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Sends automated WhatsApp updates to customers/leads for business events.
 */
class CustomerNotifier
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function send(string $event, Model $contact, array $vars): ?WhatsappMessage
    {
        $definition = config("crm.notification_events.$event");
        $phone = $contact->whatsapp ?? $contact->phone ?? null;

        if (! $definition || ! $phone || setting("notify_{$event}_enabled", '0') !== '1') {
            return null;
        }
        if (isset($contact->whatsapp_opt_in) && ! $contact->whatsapp_opt_in) {
            return null;
        }

        $vars = ['name' => $contact->name, 'company' => setting('company_name', config('app.name'))] + $vars;

        try {
            if ($template = setting("notify_{$event}_template")) {
                $params = array_map(fn ($p) => (string) ($vars[$p] ?? '-'), $definition['params']);

                return $this->whatsapp->queueTemplate($phone, $template, $params, setting('whatsapp_template_language'), $contact);
            }

            $text = setting("notify_{$event}_text") ?: $definition['text'];
            $text = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $text);

            return $this->whatsapp->queueText($phone, $text, $contact);
        } catch (\Throwable $e) {
            Log::warning("WhatsApp notification [$event] failed: ".$e->getMessage());

            return null;
        }
    }
}
