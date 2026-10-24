<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model an activity timeline, follow-up tasks and file attachments.
 */
trait HasTimeline
{
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable')->orderBy('due_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function logActivity(string $description, string $type = 'system', array $meta = []): Activity
    {
        return $this->activities()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
    }
}
