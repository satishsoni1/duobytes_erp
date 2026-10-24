<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'finish' => ['nullable', 'string', 'max:100'],
            'dimensions' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', Rule::in(array_keys(config('crm.units')))],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'tally_name' => ['nullable', 'string', 'max:255'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'warranty_months' => ['required', 'integer', 'min:0', 'max:600'],
            'requires_installation' => ['boolean'],
            'track_stock' => ['boolean'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
            'opening_stock' => ['nullable', 'integer', 'min:0'],
            'opening_warehouse_id' => ['nullable', 'required_with:opening_stock', 'exists:warehouses,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_installation' => $this->boolean('requires_installation'),
            'track_stock' => $this->boolean('track_stock'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
