@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php($range = ['from' => $from->toDateString(), 'to' => $to->toDateString()])
<x-page-header title="Reports" :subtitle="$from->format('d M Y').' – '.$to->format('d M Y')" />
<form class="card card-body mb-3 py-2"><input type="hidden" name="tab" value="{{ $tab }}"><div class="row g-2 align-items-center">
    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $range['from'] }}" class="form-control form-control-sm" aria-label="From"></div>
    <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $range['to'] }}" class="form-control form-control-sm" aria-label="To"></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
    <div class="col-md-6 d-flex flex-wrap gap-1 justify-content-md-end">
        @foreach([
            'This month' => [now()->startOfMonth(), today()],
            'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'This quarter' => [now()->firstOfQuarter(), today()],
            'This year' => [now()->startOfYear(), today()],
        ] as $label => [$a, $b])
            <a href="{{ route('reports.index', ['tab' => $tab, 'from' => $a->toDateString(), 'to' => $b->toDateString()]) }}" class="btn btn-sm btn-light">{{ $label }}</a>
        @endforeach
    </div>
</div></form>
<ul class="nav nav-tabs mb-3">
    @foreach(['sales' => 'Sales', 'leads' => 'Leads & conversion', 'inventory' => 'Inventory', 'service' => 'Service & installations'] as $k => $v)
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('reports.index', ['tab' => $k] + $range) }}">{{ $v }}</a></li>
    @endforeach
</ul>

@if($tab === 'sales')
    @php($s = $data['summary'])
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat label="Revenue" :value="money($s['revenue'])" icon="currency-rupee" color="orange" :sub="$s['orders'].' orders'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Collected" :value="money($s['collected'])" icon="wallet2" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Average order" :value="money($s['avg'])" icon="bag" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Tax / Discount" :value="money($s['tax'])" icon="percent" color="secondary" :sub="money($s['discount']).' discount given'" /></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>By salesperson</span><a href="{{ route('reports.export', ['type' => 'salesperson'] + $range) }}" class="small"><i class="bi bi-download"></i> CSV</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Salesperson</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Collected</th></tr></thead><tbody>
                @forelse($data['bySalesperson'] as $r)
                    <tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['orders'] }}</td><td class="text-end">{{ money($r['revenue']) }}</td><td class="text-end">{{ money($r['collected']) }}</td></tr>
                @empty <tr><td colspan="4"><x-empty message="No orders in this period." icon="bag" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header">Revenue by category</div><div class="card-body">
            @if($data['byCategory']->isEmpty())<x-empty message="No sales in this period." icon="pie-chart" />
            @else<canvas id="categoryChart" height="220" aria-label="Revenue by category chart"></canvas>@endif
        </div></div></div>
        <div class="col-xl-7"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Top products</span><a href="{{ route('reports.export', ['type' => 'products'] + $range) }}" class="small"><i class="bi bi-download"></i> CSV</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead><tbody>
                @forelse($data['topProducts'] as $p)
                    <tr><td>{{ $p->description }}</td><td class="small text-muted">{{ $p->sku ?? '—' }}</td><td class="text-end">{{ (float) $p->qty }}</td><td class="text-end">{{ money($p->revenue) }}</td></tr>
                @empty <tr><td colspan="4"><x-empty message="No products sold." icon="box-seam" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header">Collections by payment method</div><div class="list-group list-group-flush">
            @forelse($data['byMethod'] as $method => $total)
                <div class="list-group-item d-flex justify-content-between"><span>{{ config("crm.payment_methods.$method", $method) }}</span><strong>{{ money($total) }}</strong></div>
            @empty <div class="list-group-item"><x-empty message="No payments received." icon="cash" /></div> @endforelse
        </div></div></div>
        <div class="col-12"><div class="card"><div class="card-header">Outstanding receivables <span class="text-muted small fw-normal">(all periods, oldest first)</span></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead><tbody>
                @forelse($data['receivables'] as $o)
                    <tr><td><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td><td class="small">{{ $o->order_date->format('d M Y') }} <span class="text-muted">({{ $o->order_date->diffForHumans() }})</span></td>
                        <td>{{ $o->customer->name }}</td><td class="text-end">{{ money($o->total) }}</td><td class="text-end">{{ money($o->paid_amount) }}</td><td class="text-end fw-semibold text-danger">{{ money($o->total - $o->paid_amount) }}</td></tr>
                @empty <tr><td colspan="6"><x-empty message="Nothing outstanding. 🎉" icon="check-circle" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
    </div>

@elseif($tab === 'leads')
    @php($s = $data['summary'])
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat label="Leads received" :value="$s['total']" icon="person-plus" color="primary" :sub="$s['open'].' still open'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Won" :value="$s['won']" icon="trophy" color="success" :sub="$s['rate'].'% conversion'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Lost" :value="$s['lost']" icon="x-circle" color="danger" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Avg. days to win" :value="$data['avgDaysToWin'] ?: '—'" icon="hourglass-split" color="info" /></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>By source</span><a href="{{ route('reports.export', ['type' => 'lead-sources'] + $range) }}" class="small"><i class="bi bi-download"></i> CSV</a></div>
            @include('reports._conversion-table', ['rows' => $data['bySource'], 'labelKey' => 'label', 'heading' => 'Source'])
        </div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header">Funnel (current status of these leads)</div><div class="card-body">
            @php($maxFunnel = max(1, $data['funnel']->max('count')))
            @foreach($data['funnel'] as $status => $f)
                <div class="mb-2"><div class="d-flex justify-content-between small"><span>{{ $f['label'] }}</span><span>{{ $f['count'] }}</span></div>
                    <div class="progress" style="height:8px"><div class="progress-bar" style="width: {{ $f['count'] / $maxFunnel * 100 }}%; background: var(--brand)"></div></div></div>
            @endforeach
        </div></div></div>
        <div class="col-xl-7"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>By salesperson</span><a href="{{ route('reports.export', ['type' => 'lead-users'] + $range) }}" class="small"><i class="bi bi-download"></i> CSV</a></div>
            @include('reports._conversion-table', ['rows' => $data['byUser'], 'labelKey' => 'name', 'heading' => 'Salesperson'])
        </div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header">Lost reasons</div><div class="list-group list-group-flush">
            @forelse($data['lostReasons']->sortDesc() as $reason => $count)
                <div class="list-group-item d-flex justify-content-between"><span>{{ $reason }}</span><strong>{{ $count }}</strong></div>
            @empty <div class="list-group-item"><x-empty message="No lost leads." icon="emoji-smile" /></div> @endforelse
        </div></div></div>
        @if($data['byCampaign']->isNotEmpty())
            <div class="col-12"><div class="card"><div class="card-header">Campaigns</div>
                @include('reports._conversion-table', ['rows' => $data['byCampaign'], 'labelKey' => 'name', 'heading' => 'Campaign'])
            </div></div>
        @endif
    </div>

@elseif($tab === 'inventory')
    @php($t = $data['totals'])
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat label="Units in stock" :value="number_format($t['units'])" icon="boxes" color="primary" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Stock value (cost)" :value="money($t['cost'])" icon="cash-stack" color="orange" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Stock value (retail)" :value="money($t['retail'])" icon="tag" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Low stock items" :value="$t['low']" icon="exclamation-diamond" color="danger" :href="route('stock.index', ['stock' => 'low'])" /></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="card h-100"><div class="card-header">Dead stock <span class="text-muted small fw-normal">(in stock, not sold in 90 days)</span></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Cost value</th></tr></thead><tbody>
                @forelse($data['deadStock'] as $p)
                    <tr><td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a><div class="small text-muted">{{ $p->sku }}</div></td><td class="text-end">{{ $p->total_stock }}</td><td class="text-end">{{ money($p->total_stock * $p->cost_price) }}</td></tr>
                @empty <tr><td colspan="3"><x-empty message="No dead stock – everything is moving." icon="check-circle" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header">Stock movements in period</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Type</th><th class="text-end">Entries</th><th class="text-end">Qty</th></tr></thead><tbody>
                @forelse($data['movementSummary'] as $m)
                    <tr><td>{{ config("crm.stock_movement_types.{$m->type}", $m->type) }}</td><td class="text-end">{{ $m->entries }}</td><td class="text-end {{ $m->qty < 0 ? 'text-danger' : 'text-success' }}">{{ $m->qty > 0 ? '+' : '' }}{{ (float) $m->qty }}</td></tr>
                @empty <tr><td colspan="3"><x-empty message="No movements." icon="journal" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
        <div class="col-12"><div class="card">
            <div class="card-header d-flex justify-content-between"><span>Stock valuation</span><a href="{{ route('reports.export', ['type' => 'stock'] + $range) }}" class="small"><i class="bi bi-download"></i> CSV</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Stock</th><th class="text-end">Reorder at</th><th class="text-end">Cost value</th><th class="text-end">Retail value</th></tr></thead><tbody>
                @forelse($data['valuation'] as $p)
                    <tr><td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td><td class="small text-muted">{{ $p->sku }}</td>
                        <td class="text-end {{ $p->total_stock <= $p->reorder_level ? 'overdue' : '' }}">{{ $p->total_stock }}</td><td class="text-end text-muted">{{ $p->reorder_level }}</td>
                        <td class="text-end">{{ money($p->total_stock * $p->cost_price) }}</td><td class="text-end">{{ money($p->total_stock * $p->selling_price) }}</td></tr>
                @empty <tr><td colspan="6"><x-empty message="No stock-tracked products." icon="box-seam" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
    </div>

@else
    @php($s = $data['summary'])
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat label="Service tickets" :value="$s['tickets']" icon="wrench-adjustable" color="purple" :sub="$s['resolved'].' resolved · '.$s['warranty'].' under warranty'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Avg. resolution" :value="$s['avgHours'] ? $s['avgHours'].' h' : '—'" icon="stopwatch" color="info" :sub="$s['slaMet'].'% within SLA'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Installations done" :value="$s['installed'].' / '.$s['installations']" icon="tools" color="success" :sub="'Rating '.($s['installRating'] ?: '—').' ★'" /></div>
        <div class="col-6 col-xl-3"><x-stat label="Service charges" :value="money($s['charges'])" icon="cash" color="orange" :sub="'Ticket rating '.($s['rating'] ?: '—').' ★'" /></div>
    </div>
    <div class="row g-3">
        @foreach(['byType' => 'Tickets by type', 'byStatus' => 'Tickets by status', 'topProducts' => 'Products with most complaints'] as $key => $title)
            <div class="col-xl-4"><div class="card h-100"><div class="card-header">{{ $title }}</div><div class="list-group list-group-flush">
                @forelse($data[$key] as $label => $count)
                    <div class="list-group-item d-flex justify-content-between"><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                @empty <div class="list-group-item"><x-empty message="No tickets." icon="wrench-adjustable" /></div> @endforelse
            </div></div></div>
        @endforeach
        <div class="col-12"><div class="card">
            <div class="card-header d-flex justify-content-between"><span>Technician performance</span><a href="{{ route('reports.export', ['type' => 'technicians'] + $range) }}" class="small"><i class="bi bi-download"></i> CSV</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Technician</th><th class="text-end">Installations completed</th><th class="text-end">Avg. rating</th><th class="text-end">Tickets resolved</th></tr></thead><tbody>
                @forelse($data['technicians'] as $r)
                    <tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['installations'] }}</td><td class="text-end">{{ $r['rating'] ?: '—' }}</td><td class="text-end">{{ $r['tickets'] }}</td></tr>
                @empty <tr><td colspan="4"><x-empty message="No technician activity." icon="person-gear" /></td></tr> @endforelse
            </tbody></table></div>
        </div></div>
    </div>
@endif
@endsection
@if($tab === 'sales' && $data['byCategory']->isNotEmpty())
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: { labels: @json($data['byCategory']->keys()), datasets: [{ data: @json($data['byCategory']->values()->map(fn ($v) => round((float) $v, 2))),
            backgroundColor: CRM.palette, borderWidth: 2, borderColor: '#fff' }] },
        options: { cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } },
            tooltip: { callbacks: { label: c => c.label + ': ' + CRM.currency + ' ' + c.parsed.toLocaleString() } } } }
    });
</script>
@endpush
@endif
