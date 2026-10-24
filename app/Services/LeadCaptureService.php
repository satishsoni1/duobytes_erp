<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Notifications\CrmAlert;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Single entry point for every inbound lead – website, Meta lead ads, Instagram/Messenger DMs,
 * WhatsApp, email, Google Ads, marketplaces, CSV import and manual entry.
 * Handles normalisation, de-duplication, assignment, first follow-up and the welcome message.
 */
class LeadCaptureService
{
    public function __construct(
        protected LeadAssignmentService $assignment,
        protected CustomerNotifier $notifier,
    ) {}

    /**
     * @return array{0: Lead, 1: bool} the lead and whether it was newly created
     */
    public function capture(array $data, string $source, array $meta = [], bool $sendWelcome = true): array
    {
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        $name = Str::limit($data['name'] ?? '', 250, '') ?: 'Unknown ('.config("crm.lead_sources.$source", $source).')';
        $email = filter_var($data['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
        $phone = $data['phone'] ?? null;

        $existing = Lead::findOpenDuplicate($phone, $email);
        if (! $existing && ! empty($data['source_ref'])) {
            $existing = Lead::open()->where('source', $source)->where('source_ref', $data['source_ref'])->first();
        }

        if ($existing) {
            $this->recordReEnquiry($existing, $source, $data);

            return [$existing, false];
        }

        $lead = Lead::create([
            'name' => $name,
            'phone' => $phone,
            'whatsapp' => $data['whatsapp'] ?? $phone,
            'email' => $email,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'source' => array_key_exists($source, config('crm.lead_sources')) ? $source : 'other',
            'source_ref' => $data['source_ref'] ?? null,
            'campaign' => $data['campaign'] ?? null,
            'requirement' => $data['requirement'] ?? null,
            'budget' => is_numeric($data['budget'] ?? null) ? $data['budget'] : null,
            'priority' => $data['priority'] ?? 'warm',
            'status' => 'new',
            'customer_id' => Customer::findByContact($phone, $email)?->id,
            'meta' => $meta ?: null,
            'created_by' => auth()->id(),
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);

        $lead->logActivity('Lead captured via '.$lead->source_label.($lead->campaign ? " (campaign: {$lead->campaign})" : ''), 'system');

        if (! $lead->assigned_to) {
            $this->assignment->assign($lead, $this->assignment->nextAssignee());
        }

        if ($lead->assigned_to) {
            $minutes = (int) setting('lead_first_response_minutes', 30);
            $lead->followUps()->create([
                'user_id' => $lead->assigned_to,
                'created_by' => auth()->id(),
                'type' => $lead->phone ? 'call' : 'email',
                'title' => 'First contact with new lead',
                'due_at' => now()->addMinutes($minutes),
            ]);
            $lead->update(['next_follow_up_at' => now()->addMinutes($minutes)]);
        }

        if ($sendWelcome && $lead->whatsapp) {
            $this->notifier->send('lead_welcome', $lead, []);
        }

        return [$lead, true];
    }

    protected function recordReEnquiry(Lead $lead, string $source, array $data): void
    {
        $detail = Arr::get($data, 'requirement');
        $lead->logActivity(
            'Repeat enquiry via '.config("crm.lead_sources.$source", $source).($detail ? ': '.Str::limit($detail, 300) : ''),
            'system'
        );

        $lead->fill(array_filter([
            'email' => $lead->email ?: (filter_var($data['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null),
            'phone' => $lead->phone ?: ($data['phone'] ?? null),
            'city' => $lead->city ?: ($data['city'] ?? null),
            'priority' => 'hot',
        ]))->save();

        $lead->assignee?->notify(new CrmAlert(
            'Lead enquired again',
            "{$lead->name} reached out again via ".config("crm.lead_sources.$source", $source),
            route('leads.show', $lead),
            'arrow-repeat',
            'warning',
        ));
    }
}
