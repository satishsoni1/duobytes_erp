<div class="row g-3">
    <x-field name="name" label="Name" :value="$lead->name" required col="col-md-4" />
    <x-field name="phone" label="Mobile" :value="$lead->phone" col="col-md-4" placeholder="98765 43210" />
    <x-field name="whatsapp" label="WhatsApp (if different)" :value="$lead->exists && $lead->whatsapp !== $lead->phone ? $lead->whatsapp : null" col="col-md-4" />
    <x-field name="email" type="email" label="Email" :value="$lead->email" col="col-md-4" />
    <x-field name="city" label="City" :value="$lead->city" col="col-md-4" />
    <x-field name="source" type="select" label="Source" :options="config('crm.lead_sources')" :value="$lead->source" required col="col-md-4" :empty="false" />
    <x-field name="requirement" type="textarea" label="Requirement" :value="$lead->requirement" col="col-md-8" placeholder="e.g. 3-seater recliner sofa in grey fabric, 6-seater teak dining set" />
    <div class="col-md-4 row g-3 m-0 p-0">
        <x-field name="budget" type="number" label="Budget" :value="$lead->budget" col="col-12" step="1" min="0" />
        <x-field name="priority" type="select" label="Priority" :options="config('crm.lead_priorities')" :value="$lead->priority" col="col-12" :empty="false" />
    </div>
    <x-field name="campaign" label="Campaign / Reference" :value="$lead->campaign" col="col-md-4" />
    <x-field name="expected_close_date" type="date" label="Expected closing" :value="$lead->expected_close_date" col="col-md-4" />
    @if(auth()->user()->seesAllRecords())
        <x-field name="assigned_to" type="select" label="Assign to" :options="$users->pluck('name', 'id')" :value="$lead->assigned_to" col="col-md-4" empty="Auto (round-robin)" />
    @endif
    <x-field name="address" type="textarea" label="Address" :value="$lead->address" col="col-md-6" rows="2" />
    <x-field name="notes" type="textarea" label="Internal notes" :value="$lead->notes" col="col-md-6" rows="2" />
</div>
