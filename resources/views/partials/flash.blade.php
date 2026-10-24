@foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $class)
    @if(session($key))
        <div class="alert alert-{{ $class }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
