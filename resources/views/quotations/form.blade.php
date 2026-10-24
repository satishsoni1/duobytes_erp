@extends('layouts.app')
@section('title', $quotation->exists ? 'Edit '.$quotation->number : 'New Quotation')
@section('content')
<x-page-header :title="$quotation->exists ? 'Edit '.$quotation->number : 'New quotation'" :back="$quotation->exists ? route('quotations.show', $quotation) : route('quotations.index')"
    :subtitle="$lead ? 'For lead '.e($lead->code).' – '.e($lead->name) : null" />
<form method="POST" action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store') }}">
    @csrf @if($quotation->exists) @method('PUT') @endif
    <input type="hidden" name="lead_id" value="{{ old('lead_id', $quotation->lead_id) }}">
    <div class="card mb-3"><div class="card-body row g-3">
        @include('partials.customer-picker', ['col' => 'col-md-6'])
        <x-field name="quote_date" type="date" label="Quotation date" :value="$quotation->quote_date" required col="col-md-3" />
        <x-field name="valid_until" type="date" label="Valid until" :value="$quotation->valid_until" col="col-md-3" />
    </div></div>
    @include('partials.line-items', ['lines' => $lines, 'discount' => $quotation->discount])
    <div class="card mb-3"><div class="card-body row g-3">
        <x-field name="notes" type="textarea" label="Notes for customer" :value="$quotation->notes" col="col-md-6" />
        <x-field name="terms" type="textarea" label="Terms & conditions" :value="$quotation->terms" col="col-md-6" />
    </div></div>
    <div class="text-end"><button class="btn btn-primary">{{ $quotation->exists ? 'Save quotation' : 'Create quotation' }}</button></div>
</form>
@endsection
