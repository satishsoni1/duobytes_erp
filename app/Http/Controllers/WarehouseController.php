<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        return view('warehouses.index', [
            'warehouses' => Warehouse::withSum('stocks', 'quantity')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save(new Warehouse, $this->validated($request));

        return back()->with('success', 'Location added.');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.edit', ['warehouse' => $warehouse]);
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->save($warehouse, $this->validated($request, $warehouse));

        return redirect()->route('warehouses.index')->with('success', 'Location updated.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->stocks()->where('quantity', '!=', 0)->exists()) {
            return back()->with('error', 'Location still holds stock.');
        }
        $warehouse->delete();

        return back()->with('success', 'Location deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses')->ignore($warehouse)],
            'type' => ['required', 'in:showroom,godown,factory'],
            'address' => ['nullable', 'string', 'max:500'],
        ]) + ['is_default' => $request->boolean('is_default')];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function save(Warehouse $warehouse, array $data): void
    {
        DB::transaction(function () use ($warehouse, $data) {
            if ($data['is_default']) {
                Warehouse::query()->update(['is_default' => false]);
            }
            $warehouse->fill($data)->save();
        });
    }
}
