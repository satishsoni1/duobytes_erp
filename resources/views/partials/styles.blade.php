<style>
    /* DuoBytes ERP theme – colours sampled from the DuoBytes logo. */
    :root {
        --sidebar-w: 248px;
        --brand: #0d4e98; --brand-dark: #083a7c; --brand-light: #1675cf;
        --accent: #f27a1a; --accent-dark: #d65518; --gold: #fda417;
        --ink: #0b2a55; --muted: #5d6b82; --line: #e3e9f2; --bg: #f3f6fb;
        --brand-gradient: linear-gradient(90deg, #1675cf 0%, #0d4e98 45%, #f27a1a 75%, #fda417 100%);
        --bs-primary: #0d4e98; --bs-primary-rgb: 13, 78, 152;
        --bs-link-color: #0d4e98; --bs-link-color-rgb: 13, 78, 152; --bs-link-hover-color: #083a7c; --bs-link-hover-color-rgb: 8, 58, 124;
        --bs-body-color: #1d2b40;
    }
    body { background: var(--bg); font-size: .9rem; }
    .app { display: flex; min-height: 100vh; }

    .sidebar { width: var(--sidebar-w); background: #fff; color: var(--ink); border-right: 1px solid var(--line); position: fixed; inset: 0 auto 0 0; overflow-y: auto; z-index: 1040; transition: transform .2s; }
    .sidebar .brand { display: flex; align-items: center; gap: .6rem; padding: .9rem 1.1rem; text-decoration: none; border-bottom: 1px solid var(--line); position: relative; }
    .sidebar .brand::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: var(--brand-gradient); }
    .sidebar .brand img { height: 34px; width: auto; }
    .brand-word { font-weight: 800; font-size: 1.15rem; line-height: 1; letter-spacing: -.01em; }
    .brand-word .duo { color: var(--brand-dark); } .brand-word .bytes { color: var(--accent); }
    .brand-word small { display: block; font-size: .6rem; font-weight: 700; letter-spacing: .28em; color: var(--brand-dark); margin-top: .2rem; }
    .sidebar .tenant { padding: .6rem 1.25rem .2rem; font-size: .75rem; color: var(--muted); display: flex; align-items: center; gap: .4rem; }
    .sidebar .nav-section { font-size: .66rem; text-transform: uppercase; letter-spacing: .1em; color: #8a97ab; font-weight: 600; padding: 1rem 1.25rem .35rem; }
    .sidebar .nav-link { color: #34445e; padding: .45rem 1.25rem; display: flex; align-items: center; gap: .65rem; border-left: 3px solid transparent; }
    .sidebar .nav-link i { color: #7a8aa3; }
    .sidebar .nav-link:hover { color: var(--brand); background: #f1f6fd; } .sidebar .nav-link:hover i { color: var(--brand); }
    .sidebar .nav-link.active { color: var(--brand-dark); background: #e8f0fb; border-left-color: var(--accent); font-weight: 600; }
    .sidebar .nav-link.active i { color: var(--accent); }
    .sidebar .nav-link .badge { margin-left: auto; }

    .main { margin-left: var(--sidebar-w); flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .topbar { background: #fff; border-bottom: 1px solid var(--line); padding: .6rem 1.25rem; display: flex; align-items: center; position: sticky; top: 0; z-index: 1030; }
    .content { padding: 1.25rem; flex: 1; }
    .app-footer { padding: .75rem 1.25rem 1rem; font-size: .75rem; color: var(--muted); display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; }
    .app-footer img { height: 16px; vertical-align: -3px; }
    .avatar { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, var(--brand-light), var(--brand-dark)); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 600; }

    .card { border: 1px solid var(--line); border-radius: .6rem; box-shadow: 0 1px 2px rgba(11, 42, 85, .04); }
    .card-header { background: #fff; font-weight: 600; color: var(--ink); border-bottom: 1px solid var(--line); }
    .stat { padding: 1rem 1.1rem; }
    .stat .label { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
    .stat .value { font-size: 1.45rem; font-weight: 700; color: var(--ink); }
    .stat .icon { width: 42px; height: 42px; border-radius: .5rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    h1, .h4 { color: var(--ink); }

    .btn-primary { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: var(--brand-dark); --bs-btn-hover-border-color: var(--brand-dark); --bs-btn-active-bg: var(--brand-dark); --bs-btn-active-border-color: var(--brand-dark); --bs-btn-disabled-bg: var(--brand); --bs-btn-disabled-border-color: var(--brand); }
    .btn-outline-primary { --bs-btn-color: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: var(--brand); --bs-btn-hover-border-color: var(--brand); --bs-btn-active-bg: var(--brand); --bs-btn-active-border-color: var(--brand); }
    .btn-accent { --bs-btn-color: #fff; --bs-btn-bg: var(--accent); --bs-btn-border-color: var(--accent); --bs-btn-hover-color: #fff; --bs-btn-hover-bg: var(--accent-dark); --bs-btn-hover-border-color: var(--accent-dark); --bs-btn-active-bg: var(--accent-dark); --bs-btn-active-color: #fff; }
    .btn-light { --bs-btn-bg: #f1f4f9; --bs-btn-border-color: #e3e9f2; --bs-btn-hover-bg: #e6edf7; --bs-btn-hover-border-color: #d5dfec; }
    a { color: var(--brand); } a:hover { color: var(--brand-dark); }
    .form-control:focus, .form-select:focus, .form-check-input:focus { border-color: #7fa9de; box-shadow: 0 0 0 .2rem rgba(13, 78, 152, .15); }
    .form-check-input:checked { background-color: var(--brand); border-color: var(--brand); }
    .nav-tabs .nav-link { color: var(--muted); } .nav-tabs .nav-link.active { color: var(--brand-dark); font-weight: 600; border-bottom: 2px solid var(--accent); }
    .pagination { --bs-pagination-color: var(--brand); --bs-pagination-active-bg: var(--brand); --bs-pagination-active-border-color: var(--brand); --bs-pagination-hover-color: var(--brand-dark); --bs-pagination-focus-box-shadow: 0 0 0 .2rem rgba(13, 78, 152, .15); }
    .progress { --bs-progress-bar-bg: var(--brand); }
    .list-group { --bs-list-group-active-bg: var(--brand); --bs-list-group-active-border-color: var(--brand); }
    .ts-wrapper.focus .ts-control { border-color: #7fa9de; box-shadow: 0 0 0 .2rem rgba(13, 78, 152, .15); }

    .table { --bs-table-bg: transparent; } .table > :not(caption) > * > * { padding: .6rem .75rem; }
    .table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; background: #f6f8fc; white-space: nowrap; }
    .table-hover > tbody > tr:hover > * { --bs-table-bg-state: #f4f8fe; }
    .badge { font-weight: 500; }
    @foreach(['primary' => '13,78,152', 'secondary' => '93,107,130', 'success' => '25,135,84', 'danger' => '220,53,69', 'warning' => '204,130,0', 'info' => '22,117,207', 'dark' => '11,42,85', 'purple' => '111,66,193', 'orange' => '242,122,26'] as $name => $rgb)
    .badge-soft-{{ $name }} { background: rgba({{ $rgb }}, .12); color: rgb({{ $rgb }}); }
    .text-{{ $name }}-soft { color: rgb({{ $rgb }}); } .bg-{{ $name }}-soft { background: rgba({{ $rgb }}, .12); color: rgb({{ $rgb }}); }
    @endforeach

    .timeline { list-style: none; padding: 0; margin: 0; }
    .timeline li { position: relative; padding: 0 0 1rem 2.2rem; }
    .timeline li::before { content: ''; position: absolute; left: .8rem; top: 1.6rem; bottom: 0; width: 1px; background: var(--line); }
    .timeline li:last-child::before { display: none; }
    .timeline .dot { position: absolute; left: 0; top: 0; width: 1.65rem; height: 1.65rem; border-radius: 50%; background: #e8f0fb; display: flex; align-items: center; justify-content: center; font-size: .8rem; color: var(--brand); }
    .kanban { display: flex; gap: .75rem; overflow-x: auto; padding-bottom: 1rem; }
    .kanban-col { flex: 0 0 270px; background: #e9eef6; border-radius: .6rem; padding: .6rem; max-height: calc(100vh - 210px); display: flex; flex-direction: column; }
    .kanban-col .cards { overflow-y: auto; flex: 1; min-height: 60px; }
    .kanban-card { background: #fff; border-radius: .45rem; padding: .6rem .7rem; margin-bottom: .5rem; cursor: grab; border: 1px solid var(--line); }
    .kanban-col.drag-over { outline: 2px dashed var(--accent); }
    .dl-grid { display: grid; grid-template-columns: 140px 1fr; gap: .35rem .75rem; margin: 0; }
    .dl-grid dt { color: var(--muted); font-weight: 500; } .dl-grid dd { margin: 0; word-break: break-word; }
    .chat { background: #efeae2; height: calc(100vh - 330px); min-height: 320px; overflow-y: auto; padding: 1rem; border-radius: .5rem; }
    .bubble { max-width: 75%; padding: .5rem .75rem; border-radius: .6rem; margin-bottom: .5rem; box-shadow: 0 1px 1px rgba(0,0,0,.06); white-space: pre-wrap; word-break: break-word; }
    .bubble.in { background: #fff; } .bubble.out { background: #d9fdd3; margin-left: auto; }
    .bubble .meta { font-size: .68rem; color: #667781; text-align: right; margin-top: .2rem; }
    .overdue { color: #dc3545; font-weight: 600; }
    @media (max-width: 991.98px) {
        .sidebar { transform: translateX(-100%); } .sidebar.open { transform: none; box-shadow: 0 0 40px rgba(11, 42, 85, .3); }
        .main { margin-left: 0; } .dl-grid { grid-template-columns: 110px 1fr; }
    }
    @media print { .sidebar, .topbar, .app-footer, .no-print { display: none !important; } .main { margin: 0; } body { background: #fff; } }
</style>
