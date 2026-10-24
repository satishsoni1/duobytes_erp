<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSubject;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    use ResolvesSubject;

    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $request->query('scope', 'pending');
        $userId = $user->seesAllRecords() && $request->filled('user') ? $request->integer('user') : $user->id;

        $followUps = FollowUp::with(['followable', 'user'])
            ->when(! ($user->seesAllRecords() && $request->query('user') === 'all'), fn ($q) => $q->where('user_id', $userId))
            ->when($scope === 'pending', fn ($q) => $q->pending())
            ->when($scope === 'overdue', fn ($q) => $q->overdue())
            ->when($scope === 'today', fn ($q) => $q->pending()->whereDate('due_at', today()))
            ->when($scope === 'done', fn ($q) => $q->whereNotNull('completed_at'))
            ->orderBy($scope === 'done' ? 'completed_at' : 'due_at', $scope === 'done' ? 'desc' : 'asc')
            ->paginate(25)->withQueryString();

        return view('follow-ups.index', [
            'followUps' => $followUps,
            'scope' => $scope,
            'users' => $user->seesAllRecords() ? User::active()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $subject = $this->resolveSubject($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', array_keys(config('crm.follow_up_types')))],
            'due_at' => ['required', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $assignee = $data['user_id'] ?? $request->user()->id;
        $followUp = $subject->followUps()->create($data + ['user_id' => $assignee, 'created_by' => $request->user()->id]);

        if ($subject instanceof Lead) {
            $subject->update(['next_follow_up_at' => $subject->followUps()->pending()->min('due_at')]);
        }

        if ($assignee !== $request->user()->id) {
            User::find($assignee)?->notify(new CrmAlert('Follow-up assigned', $followUp->title, url()->previous(), 'calendar-check'));
        }

        return back()->with('success', 'Follow-up scheduled.');
    }

    public function complete(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorizeFollowUp($request, $followUp);
        $data = $request->validate(['outcome' => ['nullable', 'string', 'max:2000']]);

        $followUp->update(['completed_at' => now(), 'outcome' => $data['outcome'] ?? null]);
        $subject = $followUp->followable;

        if ($subject) {
            $subject->logActivity('Follow-up done: '.$followUp->title.(! empty($data['outcome']) ? ' – '.$data['outcome'] : ''), $followUp->type === 'whatsapp' ? 'whatsapp' : ($followUp->type === 'email' ? 'email' : 'call'));
        }
        if ($subject instanceof Lead) {
            $subject->update([
                'last_contacted_at' => now(),
                'next_follow_up_at' => $subject->followUps()->pending()->min('due_at'),
                'status' => $subject->status === 'new' ? 'contacted' : $subject->status,
            ]);
        }

        return back()->with('success', 'Follow-up completed.');
    }

    public function destroy(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorizeFollowUp($request, $followUp);
        $followUp->delete();

        return back()->with('success', 'Follow-up removed.');
    }

    protected function authorizeFollowUp(Request $request, FollowUp $followUp): void
    {
        abort_unless($followUp->user_id === $request->user()->id || $request->user()->seesAllRecords(), 403);
    }
}
