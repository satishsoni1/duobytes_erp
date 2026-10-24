{{-- Activity log, follow-ups and attachments for any model using HasTimeline. Requires $subject. --}}
@php($subjectType = $subject->getMorphClass())
@php($assignees = $assignees ?? collect())
<div class="card">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs px-2 pt-2" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tl-activity" type="button">Activity</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tl-followups" type="button">Follow-ups
                @php($pendingCount = $subject->followUps->whereNull('completed_at')->count())
                @if($pendingCount)<span class="badge bg-warning text-dark">{{ $pendingCount }}</span>@endif</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tl-files" type="button">Files <span class="text-muted small">({{ $subject->attachments->count() }})</span></button></li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <div class="tab-pane fade show active" id="tl-activity">
            <form method="POST" action="{{ route('activities.store') }}" class="mb-4">
                @csrf
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                <textarea name="description" rows="2" class="form-control mb-2" placeholder="Log a call, meeting, note…" required></textarea>
                <div class="d-flex gap-2">
                    <select name="type" class="form-select form-select-sm" style="max-width:160px">
                        @foreach(collect(config('crm.activity_types'))->except(['status', 'system']) as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                    <button class="btn btn-primary btn-sm ms-auto"><i class="bi bi-plus-lg"></i> Log activity</button>
                </div>
            </form>
            <ul class="timeline">
                @forelse($subject->activities as $activity)
                    <li>
                        <span class="dot"><i class="bi bi-{{ $activity->icon }}"></i></span>
                        <div class="small"><strong>{{ $activity->user?->name ?? 'System' }}</strong>
                            <span class="badge badge-soft-secondary">{{ config("crm.activity_types.{$activity->type}") }}</span>
                            <span class="text-muted" title="{{ $activity->created_at }}">· {{ $activity->created_at->diffForHumans() }}</span></div>
                        <div style="white-space:pre-line">{{ $activity->description }}</div>
                    </li>
                @empty
                    <li class="text-muted ps-0">No activity yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="tab-pane fade" id="tl-followups">
            <form method="POST" action="{{ route('follow-ups.store') }}" class="row g-2 mb-3">
                @csrf
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                <div class="col-md-5"><input name="title" class="form-control form-control-sm" placeholder="e.g. Call back with fabric options" required></div>
                <div class="col-md-2"><select name="type" class="form-select form-select-sm">@foreach(config('crm.follow_up_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-3"><input type="datetime-local" name="due_at" class="form-control form-control-sm" value="{{ now()->addDay()->setTime(11, 0)->format('Y-m-d\TH:i') }}" required></div>
                @if($assignees->isNotEmpty())
                    <div class="col-md-2"><select name="user_id" class="form-select form-select-sm">@foreach($assignees as $a)<option value="{{ $a->id }}" @selected($a->id === auth()->id())>{{ $a->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-12 text-end"><button class="btn btn-sm btn-primary"><i class="bi bi-calendar-plus"></i> Schedule</button></div>
            </form>
            <div class="list-group list-group-flush">
                @forelse($subject->followUps->sortBy(fn ($f) => [$f->completed_at ? 1 : 0, $f->due_at]) as $f)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="{{ $f->completed_at ? 'text-decoration-line-through text-muted' : '' }}">
                                    <span class="badge badge-soft-info">{{ config("crm.follow_up_types.{$f->type}") }}</span> {{ $f->title }}
                                </div>
                                <div class="small {{ $f->isOverdue() ? 'overdue' : 'text-muted' }}">
                                    <i class="bi bi-clock"></i> {{ $f->due_at->format('d M Y, h:i A') }} · {{ $f->user->name }}
                                    @if($f->completed_at) · done {{ $f->completed_at->diffForHumans() }}@endif
                                </div>
                                @if($f->outcome)<div class="small mt-1">{{ $f->outcome }}</div>@endif
                            </div>
                            @unless($f->completed_at)
                                <form method="POST" action="{{ route('follow-ups.complete', $f) }}" class="d-flex gap-1">
                                    @csrf @method('PATCH')
                                    <input name="outcome" class="form-control form-control-sm" placeholder="Outcome" style="width:150px">
                                    <button class="btn btn-sm btn-success" title="Mark done"><i class="bi bi-check-lg"></i></button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @empty
                    <div class="text-muted small">No follow-ups scheduled.</div>
                @endforelse
            </div>
        </div>

        <div class="tab-pane fade" id="tl-files">
            <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 mb-3">
                @csrf
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                <input type="file" name="files[]" multiple class="form-control form-control-sm" style="max-width:320px" required>
                <input name="label" class="form-control form-control-sm" placeholder="Label (e.g. site photo, floor plan)" style="max-width:240px">
                <button class="btn btn-sm btn-primary"><i class="bi bi-upload"></i> Upload</button>
            </form>
            <div class="row g-2">
                @forelse($subject->attachments as $file)
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="border rounded p-2 h-100 small">
                            <a href="{{ $file->url }}" target="_blank" rel="noopener">
                                @if($file->isImage())<img src="{{ $file->url }}" class="img-fluid rounded mb-1" style="height:110px;width:100%;object-fit:cover" alt="">
                                @else<i class="bi bi-file-earmark fs-2 d-block"></i>@endif
                                <span class="d-block text-truncate">{{ $file->label ?: $file->original_name }}</span>
                            </a>
                            <div class="d-flex justify-content-between text-muted"><span>{{ number_format($file->size / 1024) }} KB</span>
                                <form method="POST" action="{{ route('attachments.destroy', $file) }}" data-confirm="Delete this file?">@csrf @method('DELETE')<button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button></form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted small">No files uploaded.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
