@props(['message' => 'Nothing here yet.', 'icon' => 'inbox'])
<div class="text-center text-muted py-5">
    <i class="bi bi-{{ $icon }} fs-1 d-block mb-2 opacity-50"></i>
    {{ $message }}
    <div class="mt-2">{{ $slot }}</div>
</div>
