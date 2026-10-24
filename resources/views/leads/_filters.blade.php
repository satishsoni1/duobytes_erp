<form class="card card-body mb-3 py-2">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, phone, email, requirement…"></div>
        <div class="col-6 col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open (not won/lost)</option>
            @foreach(config('crm.lead_statuses') as $k => $d)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $d['label'] }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><select name="source" class="form-select form-select-sm"><option value="">All sources</option>
            @foreach(config('crm.lead_sources') as $k => $v)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $v }}</option>@endforeach</select></div>
        @if(auth()->user()->seesAllRecords())
        <div class="col-6 col-md-2"><select name="assigned_to" class="form-select form-select-sm"><option value="">All owners</option><option value="none" @selected(request('assigned_to') === 'none')>Unassigned</option>
            @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('assigned_to') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        @endif
        <div class="col-6 col-md-1"><select name="priority" class="form-select form-select-sm"><option value="">Priority</option>
            @foreach(config('crm.lead_priorities') as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><select name="follow_up" class="form-select form-select-sm"><option value="">Follow-up</option>
            <option value="today" @selected(request('follow_up') === 'today')>Due today</option><option value="overdue" @selected(request('follow_up') === 'overdue')>Overdue</option></select></div>
        <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="From"></div>
        <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="To"></div>
        <div class="col-md-auto d-flex gap-1"><button class="btn btn-sm btn-primary">Filter</button><a href="{{ url()->current() }}" class="btn btn-sm btn-light">Reset</a></div>
    </div>
</form>
