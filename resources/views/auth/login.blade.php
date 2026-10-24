<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('crm.brand.product') }}</title>
    <meta name="theme-color" content="#0d4e98">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 16px;
            background:
                radial-gradient(circle at 85% 15%, rgba(253, 164, 23, .35) 0, transparent 35%),
                radial-gradient(circle at 10% 90%, rgba(22, 117, 207, .45) 0, transparent 40%),
                linear-gradient(135deg, #071f45 0%, #0d4e98 60%, #0b3d7a 100%);
        }
        .login { width: 100%; max-width: 420px; border-radius: 1rem; overflow: hidden; }
        .login::before { content: ''; display: block; height: 4px; background: linear-gradient(90deg, #1675cf, #0d4e98 45%, #f27a1a 75%, #fda417); }
        .login .logo { max-width: 240px; width: 100%; height: auto; }
        .btn-brand { background: #0d4e98; border-color: #0d4e98; color: #fff; font-weight: 600; }
        .btn-brand:hover, .btn-brand:focus { background: #083a7c; border-color: #083a7c; color: #fff; }
        .form-control:focus { border-color: #7fa9de; box-shadow: 0 0 0 .2rem rgba(13, 78, 152, .15); }
        .form-check-input:checked { background-color: #0d4e98; border-color: #0d4e98; }
        .tenant { color: #5d6b82; }
        .footer { color: rgba(255, 255, 255, .7); font-size: .75rem; }
    </style>
</head>
<body>
<div class="card login shadow-lg border-0">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <img src="{{ asset(config('crm.brand.logo')) }}" alt="{{ config('crm.brand.product') }}" class="logo">
            <div class="tenant small mt-3"><i class="bi bi-building"></i> {{ setting('company_name', config('app.name')) }}</div>
        </div>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-semibold" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold" for="password">Password</label>
                <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>
            <button class="btn btn-brand w-100 py-2">Sign in</button>
        </form>
    </div>
</div>
<div class="footer mt-3 text-center">Sales · Inventory · CRM · Service &nbsp;|&nbsp; &copy; {{ now()->year }} {{ config('crm.brand.vendor') }}</div>
</body>
</html>
