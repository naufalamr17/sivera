<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · SIVERA</title>
    <link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('sivera-icon.svg').'?v=2' }}">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
<main class="login-card">
    <div class="login-brand"><img class="brand-mark" src="{{ asset('sivera-icon.svg').'?v=2' }}" alt="" width="38" height="38"><span><strong>SIVERA</strong><small>APPLICATION MONITORING</small></span></div>
    <span class="eyebrow">SECURE ACCESS</span>
    <h1>Welcome back.</h1>
    <p>Sign in to monitor and resolve issues across internal applications.</p>
    @if ($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif
    <form action="{{ route('login.store') }}" method="POST" class="stack">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary" type="submit">Sign in to dashboard</button>
    </form>
    <div class="login-foot">Smart Integrated Visibility & Event Response Application</div>
</main>
</body>
</html>
