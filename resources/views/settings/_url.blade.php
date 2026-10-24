<div class="col-12">
    <label class="form-label small fw-semibold mb-1">{{ $label }}</label>
    <div class="input-group input-group-sm">
        <input class="form-control font-monospace" value="{{ $url }}" readonly aria-label="{{ $label }}">
        <button type="button" class="btn btn-light" data-copy="{{ $url }}" aria-label="Copy"><i class="bi bi-clipboard"></i></button>
    </div>
</div>
