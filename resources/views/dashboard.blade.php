@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<x-page-header title="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}" subtitle="{{ now()->format('l, d F Y') }}">
    @if(auth()->user()->canAccess('leads'))<a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Lead</a>@endif
    @if(auth()->user()->canAccess('sales'))<a href="{{ route('quotations.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-plus"></i> Quotation</a>@endif
    @if(auth()->user()->canAccess('service'))<a href="{{ route('tickets.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-wrench"></i> Service Request</a>@endif
</x-page-header>

<div class="row g-3 mb-3">
    @isset($leadStats)
        <div class="col-6 col-xl-3"><x-stat label="New leads today" :value="$leadStats['today']" icon="person-plus" color="primary" :href="route('leads.index', ['from' => today()->toDateString()])" :sub="$leadStats['untouched'] ? '<span class=\'text-danger\'>'.$leadStats['untouched'].' untouched > 2h</span>' : 'All leads attended'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Open pipeline" :value="$leadStats['open']" icon="funnel" color="info" :href="route('leads.board')" :sub="$leadStats['month'].' leads this month'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Won this month" :value="$leadStats['won']" icon="trophy" color="success" :sub="$leadStats['conversion'].'% conversion'" /></div>
    @endisset
    @isset($salesStats)
        <div class="col-6 col-xl-3"><x-stat label="Sales this month" :value="money($salesStats['month'])" icon="currency-rupee" color="orange" :href="route('orders.index')" :sub="$salesStats['orders'].' orders · '.money($salesStats['collected']).' collected'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Receivables" :value="money($salesStats['receivable'])" icon="wallet2" color="danger" :href="route('orders.index', ['payment_status' => 'partial'])" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Pending deliveries" :value="$salesStats['pendingDelivery']" icon="truck" color="warning" :href="route('orders.index', ['status' => 'open'])" /></div>
    @endisset
    @isset($ticketStats)
        <div class="col-6 col-xl-3"><x-stat label="Open service tickets" :value="$ticketStats['open']" icon="wrench-adjustable" color="purple" :href="route('tickets.index')" :sub="$ticketStats['overdue'] ? '<span class=\'text-danger\'>'.$ticketStats['overdue'].' past SLA</span>' : 'All within SLA'" /></div>
    @endisset
    @isset($pendingInstallations)
        <div class="col-6 col-xl-3"><x-stat label="Installations to schedule" :value="$pendingInstallations" icon="tools" color="secondary" :href="route('installations.index', ['status' => 'pending'])" /></div>
    @endisset
    @isset($lowStockCount)
        <div class="col-6 col-xl-3"><x-stat label="Low stock items" :value="$lowStockCount" icon="exclamation-diamond" color="danger" :href="route('stock.index', ['stock' => 'low'])" /></div>
    @endisset
</div>

<div class="row g-3">
    @isset($salesTrend)
        <div class="col-xl-8">
            <div class="card h-100"><div class="card-header">Sales – last 12 months</div>
                <div class="card-body"><canvas id="salesChart" height="110" aria-label="Monthly sales chart"></canvas></div></div>
        </div>
    @endisset
    @isset($leadsBySource)
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-header">Leads by source (30 days)</div>
                <div class="card-body">
                    @if($leadsBySource->isEmpty())<x-empty message="No leads in the last 30 days." icon="pie-chart" />
                    @else<canvas id="sourceChart" height="220" aria-label="Lead source chart"></canvas>@endif
                </div></div>
        </div>
    @endisset

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>My follow-ups due today</span><a href="{{ route('follow-ups.index') }}" class="small">All</a></div>
            <div class="list-group list-group-flush">
                @forelse($myFollowUps as $f)
                    @php($s = $f->followable)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div>{{ $f->title }}</div>
                            <div class="small {{ $f->isOverdue() ? 'overdue' : 'text-muted' }}">{{ $f->due_at->format('h:i A') }}{{ $f->due_at->isToday() ? '' : ' · '.$f->due_at->format('d M') }}
                                @if($s) · {{ $s->name ?? $s->number ?? '' }}@endif</div>
                        </div>
                        @if($s instanceof \App\Models\Lead)<a href="{{ route('leads.show', $s) }}" class="btn btn-sm btn-light">Open</a>
                        @elseif($s instanceof \App\Models\Customer)<a href="{{ route('customers.show', $s) }}" class="btn btn-sm btn-light">Open</a>
                        @elseif($s instanceof \App\Models\ServiceTicket)<a href="{{ route('tickets.show', $s) }}" class="btn btn-sm btn-light">Open</a>@endif
                    </div>
                @empty
                    <div class="list-group-item"><x-empty message="No follow-ups due. 🎉" icon="calendar-check" /></div>
                @endforelse
            </div>
        </div>
    </div>

    @isset($pipeline)
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header">Pipeline</div>
                <div class="card-body">
                    @php($maxCount = max(1, $pipeline->max('total') ?? 1))
                    @foreach(collect(config('crm.lead_statuses'))->except(['won', 'lost']) as $status => $def)
                        @php($row = $pipeline[$status] ?? null)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><a href="{{ route('leads.index', ['status' => $status]) }}" class="text-reset">{{ $def['label'] }}</a>
                                <span><strong>{{ $row->total ?? 0 }}</strong> · {{ money($row->value ?? 0) }}</span></div>
                            <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ (($row->total ?? 0) / $maxCount) * 100 }}%;background:var(--brand)"></div></div>
                        </div>
                    @endforeach
                </div></div>
        </div>
    @endisset

    @isset($upcomingInstallations)
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Installations – next 7 days</span><a href="{{ route('installations.calendar') }}" class="small">Calendar</a></div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <tbody>
                    @forelse($upcomingInstallations as $i)
                        <tr><td><a href="{{ route('installations.show', $i) }}">{{ $i->number }}</a><div class="small text-muted">{{ $i->customer->name }}</div></td>
                            <td class="small">{{ $i->scheduled_date->format('D, d M') }}<div class="text-muted">{{ $i->slot_label }}</div></td>
                            <td class="small">{{ $i->technician?->name ?? '—' }}</td><td>{!! status_badge('installation_statuses', $i->status) !!}</td></tr>
                    @empty
                        <tr><td><x-empty message="No installations scheduled this week." icon="tools" /></td></tr>
                    @endforelse
                    </tbody></table></div></div>
        </div>
    @endisset

    @isset($recentLeads)
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Latest leads</span><a href="{{ route('leads.index') }}" class="small">All leads</a></div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle"><tbody>
                    @forelse($recentLeads as $l)
                        <tr><td><a href="{{ route('leads.show', $l) }}">{{ $l->name }}</a><div class="small text-muted">{{ $l->source_label }} · {{ $l->created_at->diffForHumans() }}</div></td>
                            <td>{!! status_badge('lead_statuses', $l->status) !!}</td><td class="small text-muted">{{ $l->assignee?->name ?? 'Unassigned' }}</td></tr>
                    @empty
                        <tr><td><x-empty message="No leads yet." /></td></tr>
                    @endforelse
                </tbody></table></div></div>
        </div>
    @endisset

    @isset($lowStock)
        @if($lowStock->isNotEmpty())
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Low stock</span>
                @if(auth()->user()->canAccess('purchases'))<a href="{{ route('purchase-orders.create', ['low_stock' => 1]) }}" class="small">Create reorder PO</a>@endif</div>
                <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                    @foreach($lowStock as $p)
                        <tr><td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a><div class="small text-muted">{{ $p->sku }}</div></td>
                            <td class="text-end"><span class="badge badge-soft-danger">{{ $p->total_stock }} left</span><div class="small text-muted">reorder at {{ $p->reorder_level }}</div></td></tr>
                    @endforeach
                </tbody></table></div></div>
        </div>
        @endif
    @endisset
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const palette = CRM.palette;
    @isset($salesTrend)
    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: { labels: @json($salesTrend->keys()), datasets: [{ label: 'Sales', data: @json($salesTrend->values()), backgroundColor: palette[0], hoverBackgroundColor: palette[1], borderRadius: 4 }] },
        options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => CRM.currency + ' ' + c.parsed.y.toLocaleString() } } },
            scales: { y: { ticks: { callback: v => CRM.currency + ' ' + Number(v).toLocaleString() } }, x: { grid: { display: false } } } }
    });
    @endisset
    @if(isset($leadsBySource) && $leadsBySource->isNotEmpty())
    new Chart(document.getElementById('sourceChart'), {
        type: 'doughnut',
        data: { labels: @json($leadsBySource->keys()), datasets: [{ data: @json($leadsBySource->values()), backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }] },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, cutout: '62%' }
    });
    @endif
</script>
@endpush
