@props(['title', 'subtitle' => null, 'back' => null])
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        @if($back)<a href="{{ $back }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back</a>@endif
        <h1 class="h4 mb-0 fw-bold">{{ $title }}</h1>
        @if($subtitle)<div class="text-muted small">{!! $subtitle !!}</div>@endif
    </div>
    <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
</div>
