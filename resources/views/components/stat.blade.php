@props(['label', 'value', 'icon' => 'graph-up', 'color' => 'primary', 'href' => null, 'sub' => null])
<div class="card h-100">
    <div class="stat d-flex justify-content-between align-items-start">
        <div>
            <div class="label">{{ $label }}</div>
            <div class="value">@if($href)<a href="{{ $href }}" class="text-reset text-decoration-none">{{ $value }}</a>@else{{ $value }}@endif</div>
            @if($sub)<div class="small text-muted">{!! $sub !!}</div>@endif
        </div>
        <div class="icon bg-{{ $color }}-soft"><i class="bi bi-{{ $icon }}"></i></div>
    </div>
</div>
