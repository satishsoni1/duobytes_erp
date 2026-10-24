<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSubject;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use ResolvesSubject;

    public function store(Request $request): RedirectResponse
    {
        $subject = $this->resolveSubject($request);
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(config('crm.activity_types')))],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $subject->logActivity($data['description'], $data['type']);

        if ($subject instanceof Lead && in_array($data['type'], ['call', 'email', 'whatsapp', 'meeting'], true)) {
            $subject->update([
                'last_contacted_at' => now(),
                'status' => $subject->status === 'new' ? 'contacted' : $subject->status,
            ]);
        }

        return back()->with('success', 'Activity logged.');
    }
}
