@php
    $u = auth()->user();
    $is = fn (...$p) => request()->routeIs(...$p) ? 'active' : '';
    $myOverdue = \App\Models\FollowUp::where('user_id', $u->id)->overdue()->count();
    $waUnread = $u->canAccess('whatsapp') ? \App\Models\WhatsappMessage::where('direction', 'in')->where('is_read', false)->count() : 0;
@endphp
<nav class="nav flex-column pb-4">
    <a class="nav-link {{ $is('dashboard') }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link {{ $is('follow-ups.*') }}" href="{{ route('follow-ups.index') }}"><i class="bi bi-calendar-check"></i> Follow-ups
        @if($myOverdue)<span class="badge bg-danger">{{ $myOverdue }}</span>@endif</a>

    @if($u->canAccess('leads') || $u->canAccess('customers') || $u->canAccess('whatsapp'))
        <div class="nav-section">CRM</div>
        @if($u->canAccess('leads'))
            <a class="nav-link {{ $is('leads.index', 'leads.show', 'leads.create', 'leads.edit', 'leads.import') }}" href="{{ route('leads.index') }}"><i class="bi bi-funnel"></i> Leads</a>
            <a class="nav-link {{ $is('leads.board') }}" href="{{ route('leads.board') }}"><i class="bi bi-kanban"></i> Pipeline</a>
        @endif
        @if($u->canAccess('customers'))
            <a class="nav-link {{ $is('customers.*') }}" href="{{ route('customers.index') }}"><i class="bi bi-people"></i> Customers</a>
        @endif
        @if($u->canAccess('whatsapp'))
            <a class="nav-link {{ $is('whatsapp.*') }}" href="{{ route('whatsapp.index') }}"><i class="bi bi-whatsapp"></i> WhatsApp
                @if($waUnread)<span class="badge bg-success">{{ $waUnread }}</span>@endif</a>
        @endif
    @endif

    @if($u->canAccess('sales'))
        <div class="nav-section">Sales</div>
        <a class="nav-link {{ $is('quotations.*') }}" href="{{ route('quotations.index') }}"><i class="bi bi-file-earmark-text"></i> Quotations</a>
        <a class="nav-link {{ $is('orders.*') }}" href="{{ route('orders.index') }}"><i class="bi bi-bag-check"></i> Sales Orders</a>
    @endif

    @if($u->canAccess('installations') || $u->canAccess('service'))
        <div class="nav-section">Service</div>
        @if($u->canAccess('installations'))
            <a class="nav-link {{ $is('installations.*') }}" href="{{ route('installations.index') }}"><i class="bi bi-tools"></i> Installations</a>
        @endif
        @if($u->canAccess('service'))
            <a class="nav-link {{ $is('tickets.*') }}" href="{{ route('tickets.index') }}"><i class="bi bi-wrench-adjustable-circle"></i> After-sales Service</a>
        @endif
    @endif

    @if($u->canAccess('inventory.view'))
        <div class="nav-section">Inventory</div>
        <a class="nav-link {{ $is('products.*') }}" href="{{ route('products.index') }}"><i class="bi bi-box-seam"></i> Products</a>
        <a class="nav-link {{ $is('stock.*') }}" href="{{ route('stock.index') }}"><i class="bi bi-boxes"></i> Stock</a>
        @if($u->canAccess('purchases'))
            <a class="nav-link {{ $is('purchase-orders.*') }}" href="{{ route('purchase-orders.index') }}"><i class="bi bi-truck"></i> Purchase Orders</a>
            <a class="nav-link {{ $is('suppliers.*') }}" href="{{ route('suppliers.index') }}"><i class="bi bi-building"></i> Suppliers</a>
        @endif
        @if($u->canAccess('inventory'))
            <a class="nav-link {{ $is('categories.*') }}" href="{{ route('categories.index') }}"><i class="bi bi-tags"></i> Categories</a>
            <a class="nav-link {{ $is('warehouses.*') }}" href="{{ route('warehouses.index') }}"><i class="bi bi-shop"></i> Showrooms & Godowns</a>
        @endif
    @endif

    @if($u->canAccess('reports') || $u->canAccess('tally') || $u->canAccess('users') || $u->canAccess('settings'))
        <div class="nav-section">Admin</div>
        @if($u->canAccess('reports'))<a class="nav-link {{ $is('reports.*') }}" href="{{ route('reports.index') }}"><i class="bi bi-graph-up"></i> Reports</a>@endif
        @if($u->canAccess('tally'))<a class="nav-link {{ $is('tally.*') }}" href="{{ route('tally.index') }}"><i class="bi bi-arrow-left-right"></i> Tally Sync</a>@endif
        @if($u->canAccess('users'))<a class="nav-link {{ $is('users.*') }}" href="{{ route('users.index') }}"><i class="bi bi-person-gear"></i> Users</a>@endif
        @if($u->canAccess('settings'))<a class="nav-link {{ $is('settings.*') }}" href="{{ route('settings.edit') }}"><i class="bi bi-gear"></i> Settings & Integrations</a>@endif
    @endif

    <div class="nav-section">Support</div>
    <a class="nav-link {{ $is('help.*') }}" href="{{ route('help.index') }}"><i class="bi bi-question-circle"></i> Help & User Guide</a>
</nav>
