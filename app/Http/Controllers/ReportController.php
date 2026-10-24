<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $tab = $request->query('tab', 'sales');

        return view('reports.index', [
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'data' => match ($tab) {
                'leads' => $this->leadReport($from, $to),
                'inventory' => $this->inventoryReport($from, $to),
                'service' => $this->serviceReport($from, $to),
                default => $this->salesReport($from, $to),
            },
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        [$headings, $rows] = match ($type) {
            'salesperson' => [['Salesperson', 'Orders', 'Revenue', 'Collected'], $this->salesReport($from, $to)['bySalesperson']
                ->map(fn ($r) => [$r['name'], $r['orders'], $r['revenue'], $r['collected']])],
            'products' => [['Product', 'SKU', 'Qty Sold', 'Revenue'], $this->salesReport($from, $to)['topProducts']
                ->map(fn ($r) => [$r->description, $r->sku, $r->qty, $r->revenue])],
            'lead-sources' => [['Source', 'Leads', 'Won', 'Lost', 'Conversion %'], $this->leadReport($from, $to)['bySource']
                ->map(fn ($r) => [$r['label'], $r['total'], $r['won'], $r['lost'], $r['rate']])],
            'lead-users' => [['User', 'Assigned', 'Won', 'Lost', 'Conversion %'], $this->leadReport($from, $to)['byUser']
                ->map(fn ($r) => [$r['name'], $r['total'], $r['won'], $r['lost'], $r['rate']])],
            'technicians' => [['Technician', 'Installations', 'Avg Rating', 'Tickets Resolved'], $this->serviceReport($from, $to)['technicians']
                ->map(fn ($r) => [$r['name'], $r['installations'], $r['rating'], $r['tickets']])],
            'stock' => [['Product', 'SKU', 'Stock', 'Cost Value', 'Retail Value'], $this->inventoryReport($from, $to)['valuation']
                ->map(fn ($p) => [$p->name, $p->sku, $p->total_stock, $p->total_stock * $p->cost_price, $p->total_stock * $p->selling_price])],
            default => abort(404),
        };

        return Csv::download("report-$type-{$from->toDateString()}-{$to->toDateString()}.csv", $headings, $rows->all());
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function range(Request $request): array
    {
        $from = CarbonImmutable::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('to', today()->toDateString()))->endOfDay();

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    protected function salesReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = SalesOrder::where('status', '!=', 'cancelled')->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);
        $payments = Payment::whereBetween('paid_on', [$from->toDateString(), $to->toDateString()]);

        $bySalesperson = (clone $orders)->selectRaw('user_id, count(*) as orders, sum(total) as revenue, sum(paid_amount) as collected')
            ->groupBy('user_id')->get();
        $users = User::whereIn('id', $bySalesperson->pluck('user_id'))->pluck('name', 'id');

        return [
            'summary' => [
                'orders' => (clone $orders)->count(),
                'revenue' => (clone $orders)->sum('total'),
                'tax' => (clone $orders)->sum('tax_total'),
                'discount' => (clone $orders)->sum('discount'),
                'collected' => (clone $payments)->sum('amount'),
                'avg' => (clone $orders)->avg('total') ?? 0,
            ],
            'bySalesperson' => $bySalesperson->map(fn ($r) => [
                'name' => $users[$r->user_id] ?? 'Unassigned', 'orders' => $r->orders,
                'revenue' => (float) $r->revenue, 'collected' => (float) $r->collected,
            ])->sortByDesc('revenue')->values(),
            'byMethod' => (clone $payments)->selectRaw('method, sum(amount) as total')->groupBy('method')->pluck('total', 'method'),
            'topProducts' => SalesOrderItem::query()
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                ->leftJoin('products', 'products.id', '=', 'sales_order_items.product_id')
                ->where('sales_orders.status', '!=', 'cancelled')
                ->whereNull('sales_orders.deleted_at')
                ->whereBetween('sales_orders.order_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('sales_order_items.description, products.sku, sum(sales_order_items.quantity) as qty, sum(sales_order_items.total) as revenue')
                ->groupBy('sales_order_items.description', 'products.sku')
                ->orderByDesc('revenue')->limit(20)->get(),
            'byCategory' => SalesOrderItem::query()
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                ->join('products', 'products.id', '=', 'sales_order_items.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->where('sales_orders.status', '!=', 'cancelled')
                ->whereNull('sales_orders.deleted_at')
                ->whereBetween('sales_orders.order_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("coalesce(categories.name, 'Uncategorised') as category, sum(sales_order_items.total) as revenue")
                ->groupBy('categories.name')->orderByDesc('revenue')->pluck('revenue', 'category'),
            'receivables' => SalesOrder::with('customer')->where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')
                ->orderBy('order_date')->limit(25)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function leadReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leads = Lead::whereBetween('created_at', [$from, $to]);
        $summarise = fn (Collection $rows) => [
            'total' => $rows->count(),
            'won' => $rows->where('status', 'won')->count(),
            'lost' => $rows->where('status', 'lost')->count(),
            'rate' => $rows->count() ? round($rows->where('status', 'won')->count() / $rows->count() * 100, 1) : 0,
        ];
        $all = (clone $leads)->get(['id', 'source', 'status', 'assigned_to', 'lost_reason', 'created_at', 'converted_at', 'campaign']);
        $users = User::whereIn('id', $all->pluck('assigned_to')->filter())->pluck('name', 'id');

        return [
            'summary' => $summarise($all) + ['open' => $all->whereNotIn('status', ['won', 'lost'])->count()],
            'bySource' => $all->groupBy('source')->map(fn ($rows, $source) => ['label' => config("crm.lead_sources.$source", $source)] + $summarise($rows))->sortByDesc('total')->values(),
            'byUser' => $all->groupBy('assigned_to')->map(fn ($rows, $id) => ['name' => $users[$id] ?? 'Unassigned'] + $summarise($rows))->sortByDesc('won')->values(),
            'byCampaign' => $all->whereNotNull('campaign')->groupBy('campaign')->map(fn ($rows, $c) => ['name' => $c] + $summarise($rows))->sortByDesc('total')->take(15)->values(),
            'lostReasons' => $all->where('status', 'lost')->countBy('lost_reason')->mapWithKeys(fn ($v, $k) => [config("crm.lost_reasons.$k", 'Not specified') => $v]),
            'funnel' => collect(config('crm.lead_statuses'))->map(fn ($def, $status) => ['label' => $def['label'], 'count' => $all->where('status', $status)->count()]),
            'avgDaysToWin' => round((float) $all->where('status', 'won')->filter(fn ($l) => $l->converted_at)->avg(fn ($l) => $l->created_at->diffInHours($l->converted_at) / 24), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inventoryReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $valuation = Product::where('track_stock', true)->withSum('stocks', 'quantity')->orderBy('name')->get();
        $soldIds = SalesOrderItem::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled')->where('order_date', '>=', now()->subDays(90)))
            ->distinct()->pluck('product_id');

        return [
            'valuation' => $valuation,
            'totals' => [
                'cost' => $valuation->sum(fn ($p) => $p->total_stock * $p->cost_price),
                'retail' => $valuation->sum(fn ($p) => $p->total_stock * $p->selling_price),
                'units' => $valuation->sum('total_stock'),
                'low' => $valuation->filter(fn ($p) => $p->total_stock <= $p->reorder_level)->count(),
            ],
            'deadStock' => $valuation->filter(fn ($p) => $p->total_stock > 0 && ! $soldIds->contains($p->id))->sortByDesc(fn ($p) => $p->total_stock * $p->cost_price)->take(20),
            'movementSummary' => StockMovement::whereBetween('created_at', [$from, $to])
                ->selectRaw('type, sum(quantity) as qty, count(*) as entries')->groupBy('type')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tickets = ServiceTicket::whereBetween('created_at', [$from, $to])->get();
        $resolved = $tickets->whereNotNull('resolved_at');
        $installs = Installation::whereBetween('created_at', [$from, $to])->get();
        $techIds = $installs->pluck('technician_id')->merge($tickets->pluck('assigned_to'))->filter()->unique();

        return [
            'summary' => [
                'tickets' => $tickets->count(),
                'resolved' => $resolved->count(),
                'warranty' => $tickets->where('is_under_warranty', true)->count(),
                'avgHours' => round((float) $resolved->avg(fn ($t) => $t->created_at->diffInHours($t->resolved_at)), 1),
                'slaMet' => $resolved->count() ? round($resolved->filter(fn ($t) => $t->resolved_at <= $t->due_at)->count() / $resolved->count() * 100, 1) : 0,
                'rating' => round((float) $tickets->whereNotNull('rating')->avg('rating'), 1),
                'charges' => $tickets->sum('service_charge'),
                'installations' => $installs->count(),
                'installed' => $installs->where('status', 'completed')->count(),
                'installRating' => round((float) $installs->whereNotNull('customer_rating')->avg('customer_rating'), 1),
            ],
            'byType' => $tickets->countBy('type')->mapWithKeys(fn ($v, $k) => [config("crm.ticket_types.$k", $k) => $v]),
            'byStatus' => $tickets->countBy('status')->mapWithKeys(fn ($v, $k) => [config("crm.ticket_statuses.$k.label", $k) => $v]),
            'topProducts' => $tickets->whereNotNull('product_id')->countBy('product_id')->sortDesc()->take(10)
                ->mapWithKeys(fn ($v, $id) => [Product::withTrashed()->find($id)?->name ?? '#'.$id => $v]),
            'technicians' => User::whereIn('id', $techIds)->get()->map(fn ($u) => [
                'name' => $u->name,
                'installations' => $installs->where('technician_id', $u->id)->where('status', 'completed')->count(),
                'rating' => round((float) $installs->where('technician_id', $u->id)->whereNotNull('customer_rating')->avg('customer_rating'), 1),
                'tickets' => $resolved->where('assigned_to', $u->id)->count(),
            ])->sortByDesc('installations')->values(),
        ];
    }
}
