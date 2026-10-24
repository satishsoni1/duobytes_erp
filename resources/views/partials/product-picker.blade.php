<div class="{{ $col ?? 'col-12' }}">
    <label class="form-label small fw-semibold mb-1">Product <span class="text-danger">*</span></label>
    <select name="{{ $name ?? 'product_id' }}" data-remote="{{ route('lookup.products') }}" class="form-select" required placeholder="Search product…">
        @if($product)<option value="{{ $product->id }}" selected>{{ $product->name }} ({{ $product->sku }})</option>@endif
    </select>
</div>
