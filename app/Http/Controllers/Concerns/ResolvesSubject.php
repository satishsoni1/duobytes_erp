<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Resolves a polymorphic "subject_type/subject_id" pair from a request and
 * authorises it against the module the subject belongs to.
 */
trait ResolvesSubject
{
    protected const SUBJECT_ABILITIES = [
        'lead' => 'leads',
        'customer' => 'customers',
        'quotation' => 'sales',
        'sales_order' => 'sales',
        'purchase_order' => 'purchases',
        'installation' => 'installations',
        'service_ticket' => 'service',
    ];

    protected function resolveSubject(Request $request): Model
    {
        $request->validate([
            'subject_type' => ['required', 'in:'.implode(',', array_keys(self::SUBJECT_ABILITIES))],
            'subject_id' => ['required', 'integer'],
        ]);

        $type = $request->input('subject_type');
        abort_unless($request->user()->canAccess(self::SUBJECT_ABILITIES[$type]), 403);

        $class = Relation::getMorphedModel($type);

        return $class::findOrFail($request->integer('subject_id'));
    }
}
