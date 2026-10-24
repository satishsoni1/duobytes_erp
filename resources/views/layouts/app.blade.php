<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('crm.brand.product') }}</title>
    <meta name="theme-color" content="#0d4e98">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    @include('partials.styles')
    @stack('styles')
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="brand" aria-label="{{ config('crm.brand.product') }} home">
            <img src="{{ asset(config('crm.brand.mark')) }}" alt="">
            <span class="brand-word"><span class="duo">Duo</span><span class="bytes">Bytes</span><small>ERP</small></span>
        </a>
        <div class="tenant" title="Company">
            @if(setting('company_logo'))<img src="{{ Storage::disk('public')->url(setting('company_logo')) }}" alt="" height="18">@else<i class="bi bi-building"></i>@endif
            <span class="text-truncate">{{ setting('company_name', config('app.name')) }}</span>
        </div>
        @include('partials.nav')
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-link text-body d-lg-none p-0 me-2" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">
                <i class="bi bi-list fs-3"></i>
            </button>
            <form action="{{ route('search') }}" class="flex-grow-1 me-3" style="max-width:420px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Search leads, customers, orders, products…" value="{{ request('q') }}">
                </div>
            </form>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="{{ route('help.index') }}" class="btn btn-light btn-sm" title="Help & user guide" aria-label="Help"><i class="bi bi-question-circle"></i></a>
                @php($unread = auth()->user()->unreadNotifications()->limit(8)->get())
                @php($unreadCount = auth()->user()->unreadNotifications()->count())
                <div class="dropdown">
                    <button class="btn btn-light btn-sm position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        @if($unreadCount)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="width:340px">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong>Notifications</strong>
                            @if($unreadCount)
                                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-link btn-sm p-0">Mark all read</button></form>
                            @endif
                        </div>
                        @forelse($unread as $n)
                            <a href="{{ route('notifications.open', $n->id) }}" class="dropdown-item py-2 border-bottom text-wrap">
                                <div class="d-flex gap-2">
                                    <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} text-{{ $n->data['color'] ?? 'primary' }} mt-1"></i>
                                    <div><div class="fw-semibold small">{{ $n->data['title'] }}</div><div class="small text-muted">{{ $n->data['message'] }}</div><div class="text-muted" style="font-size:.7rem">{{ $n->created_at->diffForHumans() }}</div></div>
                                </div>
                            </a>
                        @empty
                            <div class="p-3 text-center text-muted small">You're all caught up.</div>
                        @endforelse
                        <a href="{{ route('notifications.index') }}" class="dropdown-item text-center small py-2">View all</a>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light btn-sm d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->role_label }}</span></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('follow-ups.index') }}"><i class="bi bi-calendar-check me-2"></i>My follow-ups</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
        <footer class="app-footer">
            <span>&copy; {{ now()->year }} {{ setting('company_name', config('app.name')) }}</span>
            <span>Powered by <img src="{{ asset(config('crm.brand.mark')) }}" alt=""> <strong>{{ config('crm.brand.vendor') }}</strong></span>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    window.CRM = { csrf: document.querySelector('meta[name=csrf-token]').content, currency: @json(config('crm.currency')), palette: @json(config('crm.brand.chart_palette')) };
    document.querySelectorAll('select.searchable').forEach(el => new TomSelect(el, { create: false, maxOptions: 500 }));
    document.querySelectorAll('select[data-remote]').forEach(el => new TomSelect(el, {
        valueField: 'value', labelField: 'text', searchField: 'text', preload: 'focus',
        load(query, cb) { fetch(el.dataset.remote + '?q=' + encodeURIComponent(query)).then(r => r.json()).then(cb).catch(() => cb()); },
    }));
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
@stack('scripts')
</body>
</html>
