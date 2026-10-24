<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body row g-3">
        <x-field name="name" label="Product name" :value="$product->name" required col="col-md-8" />
        <x-field name="sku" label="SKU / Code" :value="$product->sku" required col="col-md-4" />
        <x-field name="category_id" type="select" label="Category" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->full_name])" :value="$product->category_id" col="col-md-4" />
        <x-field name="brand" label="Brand / Collection" :value="$product->brand" col="col-md-4" />
        <x-field name="material" label="Material" :value="$product->material" col="col-md-4" placeholder="Sheesham wood, fabric, MDF…" />
        <x-field name="color" label="Colour" :value="$product->color" col="col-md-4" />
        <x-field name="finish" label="Finish" :value="$product->finish" col="col-md-4" placeholder="Matte, gloss, walnut polish…" />
        <x-field name="dimensions" label="Dimensions" :value="$product->dimensions" col="col-md-4" placeholder="L x W x H (in/cm)" />
        <x-field name="description" type="textarea" label="Description" :value="$product->description" col="col-12" />
    </div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body row g-3">
            <x-field name="cost_price" type="number" label="Cost price" :value="$product->cost_price" required col="col-6" step="0.01" min="0" />
            <x-field name="selling_price" type="number" label="Selling price" :value="$product->selling_price" required col="col-6" step="0.01" min="0" />
            <x-field name="mrp" type="number" label="MRP" :value="$product->mrp" col="col-6" step="0.01" min="0" />
            <x-field name="tax_rate" type="number" label="Tax / GST %" :value="$product->tax_rate" required col="col-6" step="0.01" min="0" />
            <x-field name="hsn_code" label="HSN code" :value="$product->hsn_code" col="col-6" />
            <x-field name="tally_name" label="Tally stock item name" :value="$product->tally_name" col="col-12" help="Only if it differs from the name or SKU above." />
            <x-field name="unit" type="select" label="Unit" :options="config('crm.units')" :value="$product->unit" col="col-6" :empty="false" />
            <x-field name="reorder_level" type="number" label="Reorder level" :value="$product->reorder_level ?? 0" required col="col-6" min="0" />
            <x-field name="warranty_months" type="number" label="Warranty (months)" :value="$product->warranty_months" required col="col-6" min="0" />
            <x-field name="track_stock" type="checkbox" label="Track stock" :value="$product->track_stock" col="col-12" />
            <x-field name="requires_installation" type="checkbox" label="Needs installation" :value="$product->requires_installation" col="col-12" />
            <x-field name="is_active" type="checkbox" label="Active (available for sale)" :value="$product->is_active" col="col-12" />
        </div></div>
        <div class="card mb-3"><div class="card-body row g-3">
            @if($product->image_url)<div class="col-12"><img src="{{ $product->image_url }}" class="img-fluid rounded" alt=""></div>@endif
            <x-field name="image" type="file" label="Photo" col="col-12" accept="image/*" />
            @if($warehouses->isNotEmpty())
                <x-field name="opening_stock" type="number" label="Opening stock" col="col-6" min="0" />
                <x-field name="opening_warehouse_id" type="select" label="At location" :options="$warehouses->pluck('name', 'id')" :value="$warehouses->firstWhere('is_default', true)?->id" col="col-6" :empty="false" />
            @endif
        </div></div>
    </div>
</div>
