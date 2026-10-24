<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with('category')->withSum('stocks', 'quantity')
            ->search($request->query('q'))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('stock') === 'low', fn ($q) => $q->lowStock())
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product(['unit' => 'pcs', 'tax_rate' => setting('default_tax_rate', 18), 'warranty_months' => 12, 'track_stock' => true, 'is_active' => true]),
            'categories' => Category::with('parent')->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request, InventoryService $inventory): RedirectResponse
    {
        $data = collect($request->validated())->except(['image', 'opening_stock', 'opening_warehouse_id'])->all();
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        if ($request->integer('opening_stock') > 0) {
            $inventory->adjust($product, $request->integer('opening_warehouse_id'), $request->integer('opening_stock'), 'opening', null, 'Opening stock', (float) $product->cost_price);
        }

        return redirect()->route('products.show', $product)->with('success', 'Product created.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'stocks.warehouse']);

        return view('products.show', [
            'product' => $product,
            'movements' => $product->movements()->with(['warehouse', 'user'])->paginate(20),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => Category::with('parent')->orderBy('name')->get(),
            'warehouses' => collect(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = collect($request->validated())->except(['image', 'opening_stock', 'opening_warehouse_id'])->all();
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        $product->update($data);

        return redirect()->route('products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product archived.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Product::with('category')->withSum('stocks', 'quantity')->orderBy('name');

        return Csv::download('products-'.now()->format('Ymd').'.csv',
            ['SKU', 'Name', 'Category', 'Material', 'Color', 'Dimensions', 'Unit', 'Cost', 'Price', 'MRP', 'Tax %', 'Stock', 'Reorder Level', 'Stock Value'],
            (function () use ($query) {
                foreach ($query->lazy(500) as $p) {
                    yield [$p->sku, $p->name, $p->category?->name, $p->material, $p->color, $p->dimensions, $p->unit,
                        $p->cost_price, $p->selling_price, $p->mrp, $p->tax_rate, $p->total_stock, $p->reorder_level,
                        round($p->total_stock * (float) $p->cost_price, 2)];
                }
            })()
        );
    }
}
