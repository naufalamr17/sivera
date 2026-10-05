<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · SIVERA</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('sivera-icon.svg') }}">
    <script>try { if (localStorage.getItem('sivera.sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (_) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell">
    <aside class="sidebar" id="site-sidebar" aria-label="SIVERA navigation">
        <a class="brand" href="{{ route('dashboard') }}" title="SIVERA"><img src="{{ asset('sivera-icon.svg') }}" alt="" width="36" height="36"><span class="brand-copy"><strong>SIVERA</strong><small>INCIDENT RESPONSE</small></span></a>
        <nav class="nav" aria-label="Main menu">
            <span class="nav-label">WORKSPACE</span>
            <a href="{{ route('dashboard') }}" title="Overview" @class(['active' => request()->routeIs('dashboard*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span class="nav-text">Overview</span></a>
            <a href="{{ route('issues.index') }}" title="Issues" @class(['active' => request()->routeIs('issues*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-3 6-4 1 1-4 6-3Z"/></svg><span class="nav-text">Issues</span></a>
            <a href="{{ route('applications.index') }}" title="Applications" @class(['active' => request()->routeIs('applications*', 'credentials*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h3m-3 3h6"/></svg><span class="nav-text">Applications</span></a>
            @can('admin')
                <span class="nav-section">ADMINISTRATION</span>
                <a href="{{ route('users.index') }}" title="Users" @class(['active' => request()->routeIs('users*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20v-2a7 7 0 0 1 14 0v2H5Z"/></svg><span class="nav-text">Users</span></a>
                <a href="{{ route('notifications.index') }}" title="Notifications" @class(['active' => request()->routeIs('notifications*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 18h14l-2-3V9a5 5 0 0 0-10 0v6l-2 3Zm5 3h4"/></svg><span class="nav-text">Notifications</span></a>
                <a href="{{ route('audit.index') }}" title="Audit logs" @class(['active' => request()->routeIs('audit*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 3h10l4 4v14H5V3Zm10 0v5h4M8 12h8m-8 4h8"/></svg><span class="nav-text">Audit logs</span></a>
                <a href="{{ route('api-logs.index') }}" title="API logs" @class(['active' => request()->routeIs('api-logs*')])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 7-5 5 5 5m8-10 5 5-5 5M14 4l-4 16"/></svg><span class="nav-text">API logs</span></a>
            @endcan
        </nav>
        <div class="sidebar-bottom">
            <div class="user-card"><span class="user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="user-details"><span class="role-pill">{{ strtoupper(auth()->user()->role) }}</span><strong>{{ auth()->user()->name }}</strong></span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="sidebar-logout" title="Log out" aria-label="Log out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 4H5v16h5m4-4 4-4-4-4m-7 4h11"/></svg><span class="nav-text">Log out</span></button></form>
        </div>
    </aside>
    <button class="sidebar-backdrop" type="button" aria-label="Close navigation"></button>
    <main class="main">
        <header class="topbar">
            <div class="topbar-start"><button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-controls="site-sidebar" aria-expanded="true" aria-label="Collapse sidebar" title="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><a class="topbar-brand" href="{{ route('dashboard') }}"><img src="{{ asset('sivera-icon.svg') }}" alt="" width="23" height="23"><span>SIVERA</span></a><span class="breadcrumb-separator">/</span><strong class="topbar-heading">@yield('heading', 'Overview')</strong></div>
            <span class="live-indicator"><i></i> System active</span>
        </header>
        <div class="main-content">
            @if (session('status')) <div class="notice">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
