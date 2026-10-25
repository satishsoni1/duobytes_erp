<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation shared by quotations and sales orders (header + line items).
 */
class SalesDocumentRequest extends FormRequest
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
        $rules = [
            'customer_id' => ['required', 'exists:customers,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];

        if ($this->routeIs('quotations.*')) {
            return $rules + [
                'quote_date' => ['required', 'date'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
                'terms' => ['nullable', 'string', 'max:5000'],
            ];
        }

        return $rules + [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'requires_installation' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_installation' => $this->boolean('requires_installation'),
            'discount' => $this->input('discount') ?: 0,
            'delivery_charge' => $this->input('delivery_charge') ?: 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function documentAttributes(): array
    {
        return collect($this->validated())->except('items')->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return array_values($this->validated('items'));
    }
}
