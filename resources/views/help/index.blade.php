@extends('layouts.app')
@section('title', 'Help & User Guide')
@push('styles')
<style>
    .help-toc { position: sticky; top: 70px; max-height: calc(100vh - 90px); overflow-y: auto; }
    .help-toc .list-group-item { border: 0; padding: .35rem .9rem; font-size: .85rem; }
    .help-toc .list-group-item.active { background: #e8f0fb; color: var(--brand-dark); border-left: 3px solid var(--accent); font-weight: 600; }
    .help-section { scroll-margin-top: 72px; }
    .help-section h2 { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
    .help-section h2 i { color: var(--brand); }
    .help-section h3 { font-size: .95rem; font-weight: 700; margin-top: 1.1rem; }
    .help-section li { margin-bottom: .25rem; }
    .flow { display: flex; flex-wrap: wrap; align-items: stretch; gap: .4rem; }
    .flow .step { flex: 1 1 120px; background: #f6f9fd; border: 1px solid var(--line); border-radius: .5rem; padding: .55rem .6rem; text-align: center; font-size: .8rem; }
    .flow .step i { display: block; font-size: 1.25rem; color: var(--brand); margin-bottom: .15rem; }
    .flow .arrow { display: flex; align-items: center; color: var(--accent); }
    .tip { background: #fff4ea; border-left: 3px solid var(--accent); padding: .55rem .8rem; border-radius: .35rem; font-size: .85rem; }
    kbd { background: #eee; color: #333; border: 1px solid #ccc; padding: 0 .3rem; font-size: .75rem; }
    @media (max-width: 991.98px) { .help-toc { position: static; max-height: none; } .flow .arrow { display: none; } }
</style>
@endpush
@section('content')
@php
    $sections = [
        'start' => ['Getting started', 'rocket-takeoff'],
        'roles' => ['Roles & access', 'person-badge'],
        'flow' => ['How the business flows', 'diagram-3'],
        'leads' => ['Leads & pipeline', 'funnel'],
        'followups' => ['Follow-ups & timeline', 'calendar-check'],
        'customers' => ['Customers', 'people'],
        'quotations' => ['Quotations', 'file-earmark-text'],
        'orders' => ['Sales orders & payments', 'bag-check'],
        'inventory' => ['Products & stock', 'box-seam'],
        'purchases' => ['Purchase orders & suppliers', 'truck'],
        'installations' => ['Installations', 'tools'],
        'service' => ['After-sales service', 'wrench-adjustable-circle'],
        'whatsapp' => ['WhatsApp', 'whatsapp'],
        'tally' => ['Tally sync', 'arrow-left-right'],
        'reports' => ['Reports', 'graph-up'],
        'admin' => ['Settings & integrations', 'gear'],
        'routine' => ['Daily routine by role', 'list-check'],
        'faq' => ['FAQ & troubleshooting', 'question-circle'],
    ];
    $company = setting('company_name', config('app.name'));
    $sla = collect(config('crm.sla_hours'))->map(fn ($h, $p) => ucfirst($p).': '.$h.' h')->implode(' · ');
@endphp
<x-page-header :title="config('crm.brand.product').' – help & user guide'" :subtitle="'Everything you need to run '.e($company).' on '.config('crm.brand.product').' – what each screen does and how work moves from enquiry to after-sales service.'" />

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card help-toc">
            <div class="card-body pb-2">
                <input type="search" id="helpSearch" class="form-control form-control-sm" placeholder="Search help…" aria-label="Search help">
            </div>
            <div class="list-group list-group-flush pb-2" id="helpToc">
                @foreach($sections as $id => [$label, $icon])
                    <a href="#{{ $id }}" class="list-group-item list-group-item-action" data-target="{{ $id }}"><i class="bi bi-{{ $icon }} me-2"></i>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-9" id="helpBody">
        <div class="alert alert-light border small d-none" id="helpNoResults">No help topics match your search.</div>

        {{-- Getting started --}}
        <section class="card mb-3 help-section" id="start"><div class="card-body">
            <h2><i class="bi bi-rocket-takeoff"></i> Getting started</h2>
            <p>This CRM keeps every customer conversation, quotation, order, delivery, installation and service request for the showroom in one place, so anyone in the team can see the full history of a customer.</p>
            <h3>The screen</h3>
            <ul>
                <li><strong>Left menu</strong> – shows only the modules your role can use. Red/green badges show overdue follow-ups and unread WhatsApp messages. On a phone, tap <i class="bi bi-list"></i> to open it.</li>
                <li><strong>Search bar</strong> (top) – finds leads, customers, orders, quotations and products by name, phone, code or number.</li>
                <li><strong><i class="bi bi-bell"></i> Bell</strong> – your alerts: new leads assigned to you, follow-ups due, SLA breaches, low stock and more. Click one to jump to the record.</li>
                <li><strong><i class="bi bi-question-circle"></i> Help</strong> – opens this guide from any page.</li>
                <li><strong>Your name</strong> (top right) – profile, password change, your follow-ups, logout.</li>
            </ul>
            <h3>The dashboard</h3>
            <p>Your home page shows the numbers that matter to your role: new and untouched leads, pipeline, sales this month, receivables, pending deliveries, open tickets, installations to schedule and low-stock items. Every tile is clickable and opens the matching filtered list. Below are <em>your follow-ups due today</em> – start your day there.</p>
            <div class="tip"><i class="bi bi-lightbulb"></i> Every record (lead, customer, order, ticket…) has a <strong>timeline</strong> on the right. Anything done in the system is written there automatically, so you never need to ask “what happened with this customer?”.</div>
        </div></section>

        {{-- Roles --}}
        <section class="card mb-3 help-section" id="roles"><div class="card-body">
            <h2><i class="bi bi-person-badge"></i> Roles & access</h2>
            <p>Each user has one role. The role decides which menus they see. Administrators manage users in <em>Admin → Users</em>.</p>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Role</th><th>Can use</th></tr></thead><tbody>
                @foreach(config('crm.roles') as $role => $label)
                    <tr><td class="fw-semibold text-nowrap">{{ $label }}</td>
                        <td class="small">{{ $role === 'admin' ? 'Everything, including users, settings and integrations' : collect(config('crm.permissions'))->filter(fn ($roles) => in_array($role, $roles, true))->keys()->map(fn ($m) => str($m)->replace('.view', ' (view only)')->replace('.', ' ')->title())->implode(', ') }}</td></tr>
                @endforeach
            </tbody></table></div>
            <ul class="small">
                <li><strong>Sales executives</strong> see only the leads and orders assigned to them. Admins, managers and support see all records.</li>
                <li><strong>Technicians</strong> can update only the service tickets assigned to them.</li>
                <li>Users are never deleted – they are <em>deactivated</em>, so their history stays intact.</li>
            </ul>
        </div></section>

        {{-- Flow --}}
        <section class="card mb-3 help-section" id="flow"><div class="card-body">
            <h2><i class="bi bi-diagram-3"></i> How the business flows</h2>
            <p>A typical customer journey, and where each step happens in the CRM:</p>
            <div class="flow mb-3">
                @foreach([
                    ['funnel', 'Lead', 'Enquiry arrives'],
                    ['telephone', 'Follow-up', 'Call, visit, WhatsApp'],
                    ['person-check', 'Customer', 'Lead converted'],
                    ['file-earmark-text', 'Quotation', 'Shared on WhatsApp'],
                    ['bag-check', 'Sales order', 'Stock reserved'],
                    ['cash-coin', 'Payment', 'Advance / balance'],
                    ['truck', 'Delivery', 'Dispatched → delivered'],
                    ['tools', 'Installation', 'Fitting & rating'],
                    ['wrench-adjustable', 'Service', 'Warranty & repairs'],
                ] as $i => [$icon, $title, $sub])
                    @if($i)<div class="arrow"><i class="bi bi-chevron-right"></i></div>@endif
                    <div class="step"><i class="bi bi-{{ $icon }}"></i><strong>{{ $title }}</strong><div class="text-muted">{{ $sub }}</div></div>
                @endforeach
            </div>
            <ol>
                <li><strong>Lead</strong> – arrives automatically (website, Facebook/Instagram ads, WhatsApp, Google Ads, IndiaMART, email) or is entered by staff for walk-ins and calls. It is assigned to a salesperson and a “first contact” follow-up is created.</li>
                <li><strong>Follow-up</strong> – the salesperson calls, chats or invites the customer to the showroom, logging each step and moving the lead along the pipeline.</li>
                <li><strong>Convert</strong> – when the customer is serious, <em>Convert</em> creates the customer record and opens a new quotation.</li>
                <li><strong>Quotation</strong> – priced with GST and discounts, then sent as a WhatsApp link the customer can open on their phone.</li>
                <li><strong>Sales order</strong> – an accepted quotation is converted into an order. Confirming the order deducts stock from the chosen showroom/godown.</li>
                <li><strong>Payment</strong> – advances and balances are recorded against the order; the customer gets a WhatsApp receipt.</li>
                <li><strong>Delivery</strong> – the order moves through production, ready, dispatched, delivered. Customers are informed at each key step.</li>
                <li><strong>Installation</strong> – for products that need fitting, an installation job is created automatically on delivery and scheduled with a technician.</li>
                <li><strong>After-sales service</strong> – complaints and repairs become tickets with an SLA; warranty is checked automatically.</li>
            </ol>
            <div class="tip"><i class="bi bi-arrow-left-right"></i> If invoices are made in <strong>Tally</strong>, those sales come into the CRM automatically as sales orders – see <a href="#tally">Tally sync</a>.</div>
        </div></section>

        {{-- Leads --}}
        <section class="card mb-3 help-section" id="leads"><div class="card-body">
            <h2><i class="bi bi-funnel"></i> Leads & pipeline</h2>
            <p>A lead is anyone who has shown interest but has not bought yet.</p>
            <h3>Where leads come from</h3>
            <ul>
                <li><strong>Automatically</strong>: website forms, Facebook &amp; Instagram lead ads, Google Ads lead forms, IndiaMART, inbound email and new WhatsApp chats (an administrator connects these in Settings).</li>
                <li><strong>Manually</strong>: <em>Leads → New Lead</em> for showroom walk-ins, phone calls, referrals, architects and exhibitions.</li>
                <li><strong>Bulk</strong>: <em>Leads → Import</em> a CSV file (for example an exhibition list).</li>
            </ul>
            <h3>What happens automatically</h3>
            <ul>
                <li><strong>Duplicates are merged</strong> – if the phone or email matches an open lead, no new lead is created; the enquiry is added to the existing lead's timeline, it is marked <em>Hot</em> and its owner is alerted.</li>
                <li><strong>Assignment</strong> – new leads go round-robin to users marked “Include in automatic lead rotation”, unless assignment is set to manual.</li>
                <li><strong>First contact task</strong> – the owner gets a follow-up due within the response time set in Settings (default 30 minutes).</li>
                <li><strong>Welcome message</strong> – if switched on in Settings, the lead receives a WhatsApp welcome.</li>
            </ul>
            <h3>Working a lead</h3>
            <ol>
                <li>Open the lead, call or WhatsApp the customer and log what was discussed (<em>Add note / call</em> on the timeline).</li>
                <li>Update the <strong>status</strong>:
                    @foreach(config('crm.lead_statuses') as $k => $d){!! status_badge('lead_statuses', $k) !!} @endforeach</li>
                <li>Schedule the next <strong>follow-up</strong> so it never goes cold.</li>
                <li>When ready to quote, press <strong>Convert</strong> – this creates the customer and opens a quotation.</li>
                <li>Mark <strong>Won</strong> or <strong>Lost</strong> (a lost reason is required – it feeds the Reports). Pending follow-ups close automatically.</li>
            </ol>
            <h3>Pipeline board</h3>
            <p><em>Leads → Pipeline</em> shows leads as cards in status columns. Drag a card to another column to change its status. Managers can reassign leads and use bulk actions on the list view.</p>
            <div class="tip"><i class="bi bi-lightbulb"></i> The dashboard flags leads still <em>New</em> after 2 hours – these are the enquiries most likely to be lost.</div>
        </div></section>

        {{-- Follow-ups --}}
        <section class="card mb-3 help-section" id="followups"><div class="card-body">
            <h2><i class="bi bi-calendar-check"></i> Follow-ups & timeline</h2>
            <ul>
                <li><strong>Follow-ups</strong> are reminders to call, WhatsApp, email, meet or visit, attached to a lead, customer or ticket. You get a bell alert 15 minutes before one is due; overdue ones show in red and in the left-menu badge.</li>
                <li>Open <em>Follow-ups</em> in the menu to see everything due today, overdue and upcoming. Mark each one done with the outcome.</li>
                <li>The <strong>timeline</strong> on every record shows notes, calls, status changes, WhatsApp messages and system events in date order. You can also <strong>attach files</strong> (site photos, floor plans, signed quotations).</li>
            </ul>
        </div></section>

        {{-- Customers --}}
        <section class="card mb-3 help-section" id="customers"><div class="card-body">
            <h2><i class="bi bi-people"></i> Customers</h2>
            <ul>
                <li>A customer is created when a lead is converted, when a quotation/order is made for a new buyer, or when a Tally invoice arrives for a new party.</li>
                <li>The customer page shows contact details, GSTIN, outstanding balance and every lead, quotation, order, installation and ticket for that customer.</li>
                <li>Record <strong>birthday</strong> and <strong>anniversary</strong> to send automatic WhatsApp greetings. Switch off <em>WhatsApp opt-in</em> for customers who do not want messages.</li>
                <li>Types (individual, business, architect, dealer, builder) let you target WhatsApp broadcasts.</li>
            </ul>
        </div></section>

        {{-- Quotations --}}
        <section class="card mb-3 help-section" id="quotations"><div class="card-body">
            <h2><i class="bi bi-file-earmark-text"></i> Quotations</h2>
            <ol>
                <li><em>Quotations → New</em> (or <em>Convert</em> from a lead). Choose the customer, add products – price and GST fill in from the product, and you can change quantity, price and discount % per line. Add an overall discount, notes and terms.</li>
                <li><strong>Print / PDF</strong> for the showroom, or <strong>Send on WhatsApp</strong> – the customer receives a secure link to view the quotation (the status changes to <em>Sent</em>).</li>
                <li>Update the status as the customer responds: Accepted or Rejected. Quotations past their validity date are marked <em>Expired</em> automatically every night.</li>
                <li><strong>Duplicate</strong> to make a revised version quickly. <strong>Convert to order</strong> once accepted – choose which showroom/godown will supply it.</li>
            </ol>
            <p class="small text-muted mb-0">Line total = quantity × price − discount %, plus GST on the discounted amount. Quotation total = lines − overall discount.</p>
        </div></section>

        {{-- Orders --}}
        <section class="card mb-3 help-section" id="orders"><div class="card-body">
            <h2><i class="bi bi-bag-check"></i> Sales orders & payments</h2>
            <h3>Order statuses</h3>
            <p>@foreach(config('crm.order_statuses') as $k => $d){!! status_badge('order_statuses', $k) !!} @endforeach</p>
            <ul>
                <li><strong>Stock is deducted</strong> from the order's showroom/godown when the order moves to <em>Confirmed</em> (or any later status). Cancelling an order returns the stock.</li>
                <li>Once stock is allocated, the order can no longer be edited – cancel it and create a new one if items change.</li>
                <li>The customer gets WhatsApp updates on <em>Confirmed</em>, <em>Dispatched</em> and <em>Delivered</em> (if these notifications are switched on).</li>
                <li>When an order containing products that need installation is marked <strong>Delivered</strong>, an installation job is created automatically. When all installations are completed the order becomes <strong>Completed</strong>.</li>
                <li><strong>Invoice</strong> prints a tax invoice; <strong>Invoice link</strong> copies a secure link you can share.</li>
            </ul>
            <h3>Payments</h3>
            <ul>
                <li>On the order page use <em>Record payment</em> for each advance or balance: amount, method (cash, UPI, card, bank transfer, cheque, EMI/finance), reference and date.</li>
                <li>Payment status updates itself: <span class="badge badge-soft-danger">Unpaid</span> → <span class="badge badge-soft-warning">Partial</span> → <span class="badge badge-soft-success">Paid</span>. The customer can receive a WhatsApp receipt with the balance due.</li>
                <li>Outstanding balances appear on the dashboard (<em>Receivables</em>) and in <em>Reports → Sales</em>.</li>
            </ul>
        </div></section>

        {{-- Inventory --}}
        <section class="card mb-3 help-section" id="inventory"><div class="card-body">
            <h2><i class="bi bi-box-seam"></i> Products & stock</h2>
            <ul>
                <li><strong>Products</strong> hold SKU, category, material, finish, dimensions, cost/selling price, GST %, HSN, warranty months, reorder level and a photo. Tick <em>Needs installation</em> for wardrobes, modular kitchens, beds etc. – this drives automatic installation jobs.</li>
                <li><strong>Showrooms &amp; godowns</strong> are your stock locations. One is the default for new orders.</li>
                <li><strong>Stock</strong> shows quantity by location. Use <em>Adjust</em> for counts, damage or opening stock and <em>Transfer</em> to move pieces between showroom and godown.</li>
                <li>Every change is written to the <strong>stock ledger</strong> (who, when, why, which order or purchase), so stock can always be explained.</li>
                <li>Items at or below their reorder level are flagged <em>low stock</em>; managers and the store team get a daily digest at 9:00 AM.</li>
            </ul>
        </div></section>

        {{-- Purchases --}}
        <section class="card mb-3 help-section" id="purchases"><div class="card-body">
            <h2><i class="bi bi-truck"></i> Purchase orders & suppliers</h2>
            <ol>
                <li>Add your manufacturers and vendors under <em>Suppliers</em>.</li>
                <li>Create a <em>Purchase order</em> with the products, quantities, cost and the location it will arrive at. Mark it <em>Ordered</em> when sent to the supplier.</li>
                <li>When goods arrive, press <strong>Receive</strong> and enter the quantities actually received (partial deliveries are fine). Stock goes up immediately and the product's cost price is updated.</li>
            </ol>
        </div></section>

        {{-- Installations --}}
        <section class="card mb-3 help-section" id="installations"><div class="card-body">
            <h2><i class="bi bi-tools"></i> Installations</h2>
            <ul>
                <li>Jobs are created automatically on delivery (or manually via <em>New</em>). Pending jobs show on the dashboard.</li>
                <li>Schedule a date, time slot and technician. The technician gets an alert and the customer a WhatsApp confirmation.</li>
                <li>The <strong>calendar</strong> view shows the team's bookings by day so you don't double-book.</li>
                <li>Technicians mark the job <em>In progress</em> on arrival and <em>Completed</em> with notes, the customer's rating (1–5) and feedback. Ratings appear in technician reports.</li>
            </ul>
        </div></section>

        {{-- Service --}}
        <section class="card mb-3 help-section" id="service"><div class="card-body">
            <h2><i class="bi bi-wrench-adjustable-circle"></i> After-sales service</h2>
            <ol>
                <li><em>After-sales Service → New Ticket</em>: pick the customer, their order and product, the issue type (repair, replacement, warranty claim, polishing, upholstery, termite treatment…), priority and how it was reported.</li>
                <li><strong>Warranty is checked automatically</strong> from the delivery date and the product's warranty period; covered tickets are marked <span class="badge badge-soft-success">Warranty</span> and charges are disabled.</li>
                <li>An <strong>SLA deadline</strong> is set from the priority ({{ $sla }}). Tickets that miss it are escalated to managers every hour.</li>
                <li>Assign a technician and visit slot; the customer is informed on WhatsApp.</li>
                <li>Move through Assigned → In progress → Awaiting parts → <strong>Resolved</strong> (resolution notes required) → Closed. Record any service charge and the customer's rating.</li>
            </ol>
        </div></section>

        {{-- WhatsApp --}}
        <section class="card mb-3 help-section" id="whatsapp"><div class="card-body">
            <h2><i class="bi bi-whatsapp"></i> WhatsApp</h2>
            <ul>
                <li>The <strong>Inbox</strong> shows conversations on your business number; unread chats are highlighted. Each chat shows whether the number belongs to a lead or customer, with a link to it, or a button to create a lead.</li>
                <li><strong>The 24-hour rule</strong> (set by WhatsApp): you can send free text and files only within 24 hours of the customer's last message. Outside that window, send an approved <strong>template</strong>.</li>
                <li>Ticks show delivery: <i class="bi bi-clock"></i> queued, <i class="bi bi-check2"></i> sent, <i class="bi bi-check2-all"></i> delivered, <i class="bi bi-check2-all text-primary"></i> read, <i class="bi bi-exclamation-circle text-danger"></i> failed (hover to see why).</li>
                <li><strong>Templates &amp; broadcast</strong>: templates are created in Meta Business Manager and synced here. Managers can broadcast a template to opted-in customers or to leads, filtered by type, status, source or city – ideal for new collections and festive offers.</li>
                <li><strong>Automatic notifications</strong> (welcome, quotation, order updates, payments, installations, service, birthdays) are switched on and mapped to templates by an administrator in Settings.</li>
            </ul>
        </div></section>

        {{-- Tally --}}
        <section class="card mb-3 help-section" id="tally"><div class="card-body">
            <h2><i class="bi bi-arrow-left-right"></i> Tally sync</h2>
            <p>If your invoices are made in <strong>Tally Prime or Tally.ERP 9</strong>, the CRM can take those sales in as sales orders, so customer history, receivables, stock, delivery and installation all stay complete. Set up under <em>Admin → Tally Sync</em> (admins and managers).</p>
            <h3>Choose how data arrives</h3>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Mode</th><th>Use when</th><th>How</th></tr></thead><tbody>
                <tr><td class="fw-semibold">Manual upload</td><td>Occasional imports, or trying it out</td><td>In Tally: <em>Day Book</em> → <kbd>F2</kbd> period → <em>Export</em> (<kbd>Ctrl</kbd>+<kbd>E</kbd>) → XML. Upload the file on the Tally Sync page.</td></tr>
                <tr><td class="fw-semibold">Automatic pull</td><td>The CRM server can reach the Tally PC (same office network or VPN)</td><td>Enter the Tally address, e.g. <code>http://192.168.1.20:9000</code>. The CRM checks every 15 minutes; <em>Sync now</em> runs it immediately.</td></tr>
                <tr><td class="fw-semibold">Automatic push</td><td>The CRM is hosted online and Tally is on an office PC</td><td>Copy the ready-made script from the Tally Sync page onto the Tally PC and schedule it every 15 minutes with the command shown there.</td></tr>
            </tbody></table></div>
            <p class="small">For pull and push, enable Tally's XML server on the Tally PC: <em>F1 Help → Settings → Connectivity → Client/Server configuration</em> → <em>Both</em>, port <em>9000</em>, and keep Tally open with the company loaded.</p>
            <h3>What gets imported</h3>
            <ul>
                <li>Vouchers of the types you list (default <em>Sales</em>; add e.g. <em>GST Sales</em> or <em>Sales Order</em> if you use those names). Receipts, purchases and journals are ignored.</li>
                <li><strong>Customer</strong> – matched by Tally ledger name, then GSTIN, then name; new parties are created automatically with GSTIN and address. For cash-ledger sales the buyer name on the invoice is used.</li>
                <li><strong>Items</strong> – matched to CRM products by the product's <em>Tally stock item name</em>, then SKU, then name. Unmatched items are listed on the Tally Sync page – link them once and every future import remembers.</li>
                <li><strong>Amounts</strong> – the CRM order total always equals the Tally invoice value. GST, freight and round-off are carried over; extra ledgers are listed in the order notes.</li>
                <li><strong>Stock</strong> – deducted from the godown named in Tally (or the default location), if “Deduct CRM stock” is on.</li>
                <li>Imported orders show a <span class="badge badge-soft-info">Tally</span> badge with the voucher number, and you can search orders by Tally voucher number.</li>
            </ul>
            <h3>Edits and cancellations</h3>
            <ul>
                <li>Importing the same data again never creates duplicates – each voucher is recognised by its unique Tally ID.</li>
                <li>A voucher <strong>edited</strong> in Tally updates the CRM order (lines, amounts and stock) on the next sync. Edits are picked up for the number of days set in “Re-check edits”.</li>
                <li>A voucher <strong>cancelled</strong> in Tally cancels the CRM order and returns its stock. Deleted vouchers are only detected from a file export, so cancel rather than delete in Tally where possible.</li>
                <li>Delivery, installation and payment updates you make in the CRM are kept when Tally re-syncs.</li>
            </ul>
            <div class="tip"><i class="bi bi-lightbulb"></i> Each import is logged under <em>Recent imports</em> with counts and any voucher that failed and why.</div>
        </div></section>

        {{-- Reports --}}
        <section class="card mb-3 help-section" id="reports"><div class="card-body">
            <h2><i class="bi bi-graph-up"></i> Reports</h2>
            <p>Pick a date range (or a preset such as <em>This month</em>) and a tab. Most tables have a <strong>CSV</strong> link for Excel.</p>
            <ul>
                <li><strong>Sales</strong> – revenue, collections, average order, tax/discount, sales by salesperson and category, top products, payment methods and outstanding receivables.</li>
                <li><strong>Leads &amp; conversion</strong> – leads by source and salesperson with win rates, funnel, campaigns, lost reasons and average days to win. Use it to decide where to spend on advertising.</li>
                <li><strong>Inventory</strong> – stock value at cost and retail, low stock, dead stock (not sold in 90 days) and stock movements.</li>
                <li><strong>Service &amp; installations</strong> – ticket volume, SLA performance, warranty share, products with most complaints and technician performance.</li>
            </ul>
        </div></section>

        {{-- Admin --}}
        <section class="card mb-3 help-section" id="admin"><div class="card-body">
            <h2><i class="bi bi-gear"></i> Settings & integrations <span class="badge badge-soft-secondary">Administrators</span></h2>
            <ul>
                <li><strong>Company &amp; branding</strong> – name, GSTIN, address, bank details and logo printed on quotations and invoices.</li>
                <li><strong>Sales documents</strong> – default GST rate, quotation validity, terms &amp; conditions.</li>
                <li><strong>Leads &amp; inventory</strong> – round-robin or manual assignment, first-response time, auto-creating leads from WhatsApp, allowing negative stock.</li>
                <li><strong>WhatsApp Cloud API</strong> – phone number ID, business account ID and access token from Meta; paste the callback URL shown into the Meta app.</li>
                <li><strong>Facebook / Instagram lead ads</strong> – page token and app secret; subscribe the page's <em>leadgen</em> field to the callback URL shown.</li>
                <li><strong>Lead webhooks</strong> – ready-made URLs for website forms, Zapier/Make, IndiaMART, email parsers and Google Ads, protected by a token. Regenerating the token breaks existing integrations until they are updated.</li>
                <li><strong>Customer notifications</strong> – switch each automatic WhatsApp message on/off and map it to an approved template.</li>
                <li><strong>Webhook logs</strong> – every inbound lead, WhatsApp event and Tally import, for troubleshooting.</li>
                <li><strong>Users</strong> – add staff, set roles, choose who receives leads, deactivate leavers.</li>
            </ul>
            <h3>Server requirements (for your IT person)</h3>
            <ul class="small">
                <li>Scheduler: <code>* * * * * php artisan schedule:run</code> – runs reminders, SLA checks, quotation expiry, low-stock digest, greetings and Tally pull sync.</li>
                <li>Queue worker: <code>php artisan queue:work</code> – sends WhatsApp messages. Without it, messages stay <em>queued</em>.</li>
            </ul>
        </div></section>

        {{-- Routine --}}
        <section class="card mb-3 help-section" id="routine"><div class="card-body">
            <h2><i class="bi bi-list-check"></i> Daily routine by role</h2>
            <div class="row g-3">
                @foreach([
                    'Sales executive' => ['Open the dashboard – clear follow-ups due today and overdue.', 'Call every new lead within the response time; log the call and set the next follow-up.', 'Reply to WhatsApp chats in the inbox.', 'Send quotations on WhatsApp and chase pending ones.', 'Record advances and balances as they are received.'],
                    'Manager' => ['Check untouched leads and reassign if needed.', 'Review the pipeline board and quotations awaiting response.', 'Watch receivables and pending deliveries.', 'Check SLA breaches and installations to schedule.', 'Weekly: Reports → lead sources, salesperson performance, dead stock.'],
                    'Store / inventory' => ['Check low-stock items and raise purchase orders.', 'Receive goods against purchase orders.', 'Move confirmed orders to Ready / Dispatched.', 'Transfer stock between godown and showroom; record damage.'],
                    'Technician' => ['Open your installations and tickets for today.', 'Mark In progress on arrival.', 'Complete with notes, photos and the customer\'s rating.'],
                    'Customer support' => ['Log complaints as service tickets with the right priority.', 'Assign technicians and visit slots.', 'Follow up on tickets close to their SLA.', 'Close resolved tickets after confirming with the customer.'],
                ] as $role => $tasks)
                    <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="fw-semibold mb-2">{{ $role }}</div>
                        <ul class="small mb-0 ps-3">@foreach($tasks as $task)<li>{{ $task }}</li>@endforeach</ul></div></div>
                @endforeach
            </div>
        </div></section>

        {{-- FAQ --}}
        <section class="card mb-3 help-section" id="faq"><div class="card-body">
            <h2><i class="bi bi-question-circle"></i> FAQ & troubleshooting</h2>
            <div class="accordion" id="faqAccordion">
                @foreach([
                    'I can\'t see a menu or a lead/order my colleague can see.' => 'Menus depend on your role, and sales executives only see records assigned to them. Ask a manager to reassign the record or an administrator to change your role.',
                    'WhatsApp messages stay on the clock icon (queued).' => 'The WhatsApp connection is not configured, or the queue worker is not running on the server. Ask your administrator to check Settings → WhatsApp Cloud API and the queue worker.',
                    'A WhatsApp message failed.' => 'Hover the red icon to see the reason. The most common is the 24-hour rule – send an approved template instead of free text.',
                    'I can\'t edit an order.' => 'Orders are locked once stock has been allocated (Confirmed or later) or after delivery. Cancel it (stock is returned) and create a new one.',
                    'Confirming an order says insufficient stock.' => 'The chosen location doesn\'t have enough pieces. Transfer stock from another location, receive the pending purchase order, or ask an administrator to allow negative stock.',
                    'The same customer appears twice.' => 'Leads are de-duplicated by phone and email. If a duplicate customer slipped in (e.g. different numbers), keep using one record and note the other number in it.',
                    'A Tally invoice did not come into the CRM.' => 'Check its voucher type is listed in Tally Sync settings and its date is inside the sync period, then look at Recent imports for errors. For pull/push, Tally must be open with the XML server enabled on port 9000.',
                    'Tally items show as “unmatched”.' => 'Link each Tally stock item to a CRM product on the Tally Sync page, or fill the “Tally stock item name” on the product. Future imports will match automatically.',
                    'A website or Facebook lead did not arrive.' => 'Open Settings → Webhook logs. If nothing was received, the form or Meta app is not pointing at the right URL/token. If it shows Failed, the error explains which field was missing.',
                    'I forgot my password.' => 'Ask an administrator to set a new password for you in Admin → Users, then change it from your profile.',
                ] as $question => $answer)
                    <div class="accordion-item">
                        <h3 class="accordion-header m-0"><button class="accordion-button collapsed py-2 small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $loop->index }}">{{ $question }}</button></h3>
                        <div id="faq{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#faqAccordion"><div class="accordion-body small">{{ $answer }}</div></div>
                    </div>
                @endforeach
            </div>
        </div></section>
    </div>
</div>
@endsection
@push('scripts')
<script>
    const sections = [...document.querySelectorAll('.help-section')];
    const tocLinks = [...document.querySelectorAll('#helpToc [data-target]')];

    document.getElementById('helpSearch').addEventListener('input', e => {
        const term = e.target.value.trim().toLowerCase();
        let shown = 0;
        sections.forEach(section => {
            const match = ! term || section.textContent.toLowerCase().includes(term);
            section.classList.toggle('d-none', ! match);
            tocLinks.find(a => a.dataset.target === section.id)?.classList.toggle('d-none', ! match);
            shown += match ? 1 : 0;
        });
        document.getElementById('helpNoResults').classList.toggle('d-none', shown > 0);
    });

    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) {
            tocLinks.forEach(a => a.classList.toggle('active', a.dataset.target === entry.target.id));
        }
    }), { rootMargin: '-80px 0px -70% 0px' });
    sections.forEach(section => observer.observe(section));
</script>
@endpush
