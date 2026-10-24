<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('purchase-orders.index', [
            'orders' => PurchaseOrder::with(['supplier', 'warehouse'])
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('supplier_id'), fn ($q, $v) => $q->where('supplier_id', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where('number', 'like', "%$v%"))
                ->latest()->paginate(25)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $po = new PurchaseOrder(['order_date' => today(), 'warehouse_id' => Warehouse::default()?->id, 'status' => 'draft']);
        $lines = [];

        // "Reorder low stock" shortcut pre-fills every product at or below its reorder level.
        if ($request->boolean('low_stock')) {
            $lines = Product::active()->lowStock()->withSum('stocks', 'quantity')->get()->map(fn ($p) => [
                'product_id' => $p->id, 'label' => "{$p->name} ({$p->sku})",
                'quantity' => max(1, $p->reorder_level * 2 - $p->total_stock),
                'unit_cost' => $p->cost_price, 'tax_rate' => $p->tax_rate,
            ])->all();
        }

        return view('purchase-orders.form', $this->formData($po, $lines));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $po = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create(collect($data)->except('items')->all() + ['created_by' => $request->user()->id]);
            $po->syncItems($data['items']);

            return $po;
        });

        return redirect()->route('purchase-orders.show', $po)->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-orders.show', ['po' => $purchaseOrder->load(['supplier', 'warehouse', 'items.product', 'creator'])]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $purchaseOrder->load('items.product');
        if (! $purchaseOrder->isEditable()) {
            return back()->with('error', 'Only draft/ordered purchase orders without receipts can be edited.');
        }

        $lines = $purchaseOrder->items->map(fn ($i) => [
            'product_id' => $i->product_id, 'label' => "{$i->product->name} ({$i->product->sku})",
            'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost, 'tax_rate' => $i->tax_rate,
        ])->all();

        return view('purchase-orders.form', $this->formData($purchaseOrder, $lines));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->load('items');
        abort_unless($purchaseOrder->isEditable(), 422, 'Purchase order can no longer be edited.');
        $data = $this->validated($request);

        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update(collect($data)->except('items')->all());
            $purchaseOrder->syncItems($data['items']);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->load('items');
        if ($purchaseOrder->items->sum('received_quantity') > 0) {
            return back()->with('error', 'Received purchase orders cannot be deleted. Cancel it instead.');
        }
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order deleted.');
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,ordered,cancelled']]);
        if (in_array($purchaseOrder->status, ['received', 'partial'], true) && $data['status'] !== 'cancelled') {
            return back()->with('error', 'Status cannot be changed after goods are received.');
        }
        $purchaseOrder->update($data);

        return back()->with('success', 'Status updated.');
    }

    public function receiveForm(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-orders.receive', ['po' => $purchaseOrder->load(['items.product', 'supplier', 'warehouse'])]);
    }

    /**
     * Goods receipt (GRN): adds received quantities into the PO's warehouse.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder, InventoryService $inventory): RedirectResponse
    {
        abort_if(in_array($purchaseOrder->status, ['received', 'cancelled'], true), 422, 'Purchase order is closed.');
        $data = $request->validate([
            'received' => ['required', 'array'],
            'received.*' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $data, $inventory) {
            $purchaseOrder->load('items.product');
            foreach ($purchaseOrder->items as $item) {
                $qty = min((int) ($data['received'][$item->id] ?? 0), $item->pending_quantity);
                if ($qty <= 0) {
                    continue;
                }
                $inventory->adjust($item->product, $purchaseOrder->warehouse_id, $qty, 'purchase', $purchaseOrder, "GRN against {$purchaseOrder->number}", (float) $item->unit_cost);
                $item->increment('received_quantity', $qty);
                $item->product->update(['cost_price' => $item->unit_cost]);
            }
            $purchaseOrder->refresh()->load('items');
            $pending = $purchaseOrder->items->sum(fn ($i) => $i->pending_quantity);
            $purchaseOrder->update(['status' => $pending === 0 ? 'received' : 'partial']);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Goods received into stock.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'status' => ['required', 'in:draft,ordered'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    protected function formData(PurchaseOrder $po, array $lines): array
    {
        return [
            'po' => $po,
            'lines' => old('items', $lines),
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ];
    }
}
