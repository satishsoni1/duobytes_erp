<div class="row g-3">
    <x-field name="name" label="Full name" :value="$customer->name" required col="col-md-4" />
    <x-field name="phone" label="Mobile" :value="$customer->phone" required col="col-md-4" />
    <x-field name="whatsapp" label="WhatsApp (if different)" :value="$customer->exists && $customer->whatsapp !== $customer->phone ? $customer->whatsapp : null" col="col-md-4" />
    <x-field name="email" type="email" label="Email" :value="$customer->email" col="col-md-4" />
    <x-field name="alt_phone" label="Alternate phone" :value="$customer->alt_phone" col="col-md-4" />
    <x-field name="type" type="select" label="Customer type" :options="config('crm.customer_types')" :value="$customer->type" required col="col-md-4" :empty="false" />
    <x-field name="company_name" label="Company / Firm" :value="$customer->company_name" col="col-md-4" />
    <x-field name="tax_number" label="GSTIN / Tax no." :value="$customer->tax_number" col="col-md-4" />
    <x-field name="source" type="select" label="Source" :options="config('crm.lead_sources')" :value="$customer->source" col="col-md-4" />
    <x-field name="address" type="textarea" label="Address" :value="$customer->address" col="col-md-12" rows="2" />
    <x-field name="city" label="City" :value="$customer->city" col="col-md-4" />
    <x-field name="state" label="State" :value="$customer->state" col="col-md-4" />
    <x-field name="pincode" label="PIN code" :value="$customer->pincode" col="col-md-4" />
    <x-field name="birthday" type="date" label="Birthday" :value="$customer->birthday" col="col-md-4" />
    <x-field name="anniversary" type="date" label="Anniversary" :value="$customer->anniversary" col="col-md-4" />
    <x-field name="assigned_to" type="select" label="Account manager" :options="$users->pluck('name', 'id')" :value="$customer->assigned_to" col="col-md-4" />
    <x-field name="notes" type="textarea" label="Notes" :value="$customer->notes" col="col-md-8" rows="2" />
    <x-field name="whatsapp_opt_in" type="checkbox" label="Agreed to receive WhatsApp updates & offers" :value="$customer->whatsapp_opt_in" col="col-md-4" />
</div>
