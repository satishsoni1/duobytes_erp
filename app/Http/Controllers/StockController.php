<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $products = Product::active()->where('track_stock', true)
            ->with(['category', 'stocks'])->withSum('stocks', 'quantity')
            ->search($request->query('q'))
            ->when($request->query('stock') === 'low', fn ($q) => $q->lowStock())
            ->when($request->query('stock') === 'out', fn ($q) => $q->whereRaw('(select coalesce(sum(quantity),0) from product_stocks where product_id = products.id) <= 0'))
            ->orderBy('name')->paginate(30)->withQueryString();

        $valuation = Product::where('track_stock', true)
            ->join('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->selectRaw('coalesce(sum(product_stocks.quantity * products.cost_price),0) as cost, coalesce(sum(product_stocks.quantity * products.selling_price),0) as retail')
            ->first();

        return view('stock.index', compact('warehouses', 'products', 'valuation'));
    }

    public function movements(Request $request): View
    {
        $movements = StockMovement::with(['product', 'warehouse', 'user', 'reference'])
            ->when($request->query('product_id'), fn ($q, $v) => $q->where('product_id', $v))
            ->when($request->query('warehouse_id'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()->paginate(40)->withQueryString();

        return view('stock.movements', ['movements' => $movements, 'warehouses' => Warehouse::orderBy('name')->get()]);
    }

    public function adjustForm(Request $request): View
    {
        return view('stock.adjust', [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'product' => $request->query('product_id') ? Product::find($request->query('product_id')) : null,
        ]);
    }

    public function adjust(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'direction' => ['required', 'in:add,remove,set'],
            'quantity' => ['required', 'integer', 'min:0'],
            'type' => ['required', 'in:adjustment,damage,opening,sale_return'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $qty = match ($data['direction']) {
            'add' => $data['quantity'],
            'remove' => -$data['quantity'],
            'set' => $data['quantity'] - $product->stockIn($data['warehouse_id']),
        };

        $inventory->adjust($product, (int) $data['warehouse_id'], $qty, $data['type'], null, $data['note'], (float) $product->cost_price);

        return redirect()->route('products.show', $product)->with('success', 'Stock adjusted.');
    }

    public function transferForm(Request $request): View
    {
        return view('stock.transfer', [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'product' => $request->query('product_id') ? Product::find($request->query('product_id')) : null,
        ]);
    }

    public function transfer(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $inventory->transfer($product, (int) $data['from_warehouse_id'], (int) $data['to_warehouse_id'], (int) $data['quantity'], $data['note'] ?? 'Stock transfer');

        return redirect()->route('products.show', $product)->with('success', 'Stock transferred.');
    }
}
