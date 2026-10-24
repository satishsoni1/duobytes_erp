<x-field name="name" label="Name" :value="$warehouse->name" required col="col-md-8" placeholder="MG Road Showroom" />
<x-field name="code" label="Code" :value="$warehouse->code" required col="col-md-4" placeholder="SR1" />
<x-field name="type" type="select" label="Type" :options="['showroom' => 'Showroom', 'godown' => 'Godown / Warehouse', 'factory' => 'Factory / Workshop']" :value="$warehouse->type" col="col-md-6" :empty="false" />
<x-field name="is_default" type="checkbox" label="Default location" :value="$warehouse->is_default" col="col-md-6" />
<x-field name="address" type="textarea" label="Address" :value="$warehouse->address" col="col-12" rows="2" />
