@extends('layouts.app')
@section('title', 'Import Leads')
@section('content')
<x-page-header title="Import leads from CSV" :back="route('leads.index')" />
<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('leads.import.store') }}" enctype="multipart/form-data" class="card"><div class="card-body row g-3">
            @csrf
            <x-field name="file" type="file" label="CSV file" col="col-12" required accept=".csv,text/csv" />
            <x-field name="source" type="select" label="Default source" :options="config('crm.lead_sources')" value="other" col="col-md-6" :empty="false" help="Used when a row has no valid 'source' column." />
            <x-field name="assigned_to" type="select" label="Assign to" :options="$users->pluck('name', 'id')" col="col-md-6" empty="Round-robin" />
        </div><div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-upload"></i> Import</button></div></form>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body small">
            <h6>Expected columns</h6>
            <p>First row must be headings. Recognised: <code>name</code>, <code>phone</code>, <code>email</code>, <code>city</code>, <code>source</code>, <code>campaign</code>, <code>requirement</code>, <code>budget</code>, <code>address</code>.</p>
            <p>Rows need a name and a phone or email. Leads matching an existing open lead (same phone/email) are logged on that lead instead of duplicated. Up to 5,000 rows per file.</p>
            <pre class="bg-light p-2 rounded mb-0">name,phone,email,city,source,requirement,budget
Anita Sharma,9876543210,anita@example.com,Pune,exhibition,L-shape sofa,85000</pre>
        </div></div>
    </div>
</div>
@endsection
