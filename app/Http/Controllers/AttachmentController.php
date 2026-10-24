<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSubject;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    use ResolvesSubject;

    public function store(Request $request): RedirectResponse
    {
        $subject = $this->resolveSubject($request);
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,pdf,doc,docx,xls,xlsx,mp4'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($request->file('files') as $file) {
            $subject->attachments()->create([
                'user_id' => $request->user()->id,
                'path' => $file->store('attachments/'.now()->format('Y/m'), 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'label' => $request->input('label'),
            ]);
        }

        return back()->with('success', 'Files uploaded.');
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        abort_unless($request->user()->id === $attachment->user_id || $request->user()->hasRole('admin', 'manager'), 403);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
