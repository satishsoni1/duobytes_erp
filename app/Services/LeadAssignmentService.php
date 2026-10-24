<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmAlert;
use Illuminate\Support\Facades\DB;

class LeadAssignmentService
{
    /**
     * Pick the next sales user in round-robin order (least recently assigned first).
     */
    public function nextAssignee(): ?User
    {
        if (setting('lead_assignment', 'round_robin') !== 'round_robin') {
            return null;
        }

        return DB::transaction(function () {
            $user = User::active()->where('receives_leads', true)
                ->orderByRaw('last_lead_assigned_at IS NOT NULL, last_lead_assigned_at ASC')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $user?->forceFill(['last_lead_assigned_at' => now()])->save();

            return $user;
        });
    }

    public function assign(Lead $lead, ?User $user, bool $notify = true): void
    {
        $previous = $lead->assigned_to;
        $lead->update(['assigned_to' => $user?->id]);

        if ($user && $previous !== $user->id) {
            $lead->logActivity("Lead assigned to {$user->name}", 'system');
            if ($notify && $user->id !== auth()->id()) {
                $user->notify(new CrmAlert(
                    'New lead assigned',
                    "{$lead->name} ({$lead->source_label}) – {$lead->code}",
                    route('leads.show', $lead),
                    'person-plus',
                    'success',
                ));
            }
        }
    }
}
