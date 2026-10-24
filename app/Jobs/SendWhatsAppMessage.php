<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $message = WhatsappMessage::find($this->messageId);

        if ($message && in_array($message->status, ['queued', 'failed'], true)) {
            $whatsapp->deliver($message);
        }
    }
}
