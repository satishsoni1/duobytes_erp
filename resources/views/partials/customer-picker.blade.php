<div class="{{ $col ?? 'col-md-6' }}">
    <label class="form-label small fw-semibold mb-1">Customer <span class="text-danger">*</span>
        <a href="{{ route('customers.create') }}" target="_blank" class="ms-2 fw-normal">+ new</a></label>
    <select name="customer_id" data-remote="{{ route('lookup.customers') }}" class="form-select" required placeholder="Search by name or phone…">
        @if($customer)<option value="{{ $customer->id }}" selected>{{ $customer->display_name }} ({{ $customer->phone }})</option>@endif
    </select>
</div>
