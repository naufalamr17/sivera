<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · SIVERA</title>
<link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('sivera-icon.svg').'?v=2' }}">
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
$navigation = [
    ['label' => 'Overview', 'icon' => 'overview', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard*')],
    ['label' => 'Issues', 'icon' => 'issues', 'url' => route('issues.index'), 'active' => request()->routeIs('issues*')],
    ['label' => 'Applications', 'icon' => 'applications', 'url' => route('applications.index'), 'active' => request()->routeIs('applications*')],
];
if (auth()->user()->can('admin')) {
    $navigation = [...$navigation,
        ['label' => 'Notifications', 'icon' => 'notifications', 'url' => route('notifications.index'), 'active' => request()->routeIs('notifications.index', 'notifications.create', 'notifications.edit'), 'admin' => true],
        ['label' => 'Users', 'icon' => 'users', 'url' => route('users.index'), 'active' => request()->routeIs('users*')],
        ['label' => 'Audit logs', 'icon' => 'audit', 'url' => route('audit.index'), 'active' => request()->routeIs('audit*')],
        ['label' => 'API logs', 'icon' => 'api', 'url' => route('api-logs.index'), 'active' => request()->routeIs('api-logs*')],
    ];
}
$navigation[] = ['label' => 'Change password', 'icon' => 'password', 'url' => route('password.edit'), 'active' => request()->routeIs('password.*'), 'account' => true];
$workspace = [
    'navigation' => $navigation,
    'heading' => trim($__env->yieldContent('heading', 'Overview')),
    'user' => ['id' => auth()->id(), 'name' => auth()->user()->name, 'role' => auth()->user()->role],
    'logout' => route('logout'),
    'dashboard' => route('dashboard'),
    'feed' => route('notifications.feed'),
    'pollSeconds' => config('sivera.poll_seconds'),
    'icon' => asset('sivera-icon.svg').'?v=2',
];
@endphp
<script type="application/json" id="workspace-data">@json($workspace)</script>
<div class="shell">
<aside id="workspace-sidebar" class="sidebar" aria-label="Main navigation"></aside>
<main class="main">
<div id="workspace-header"></div>
<div class="main-content">
@if(session('status')) <div class="notice" role="status">{{ session('status') }}</div> @endif
@if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
@yield('content')
<footer class="retention-notice">Monitoring history older than {{ config('sivera.retention_years', 3) }} years is automatically deleted during daily cleanup. This includes open issues whose last event is older than that period.</footer>
<noscript><div class="notice error">Enable JavaScript for navigation, live alerts, and dashboard charts. <a href="{{ route('issues.index') }}">Open issues</a>.</div></noscript>
</div>
</main>
</div>
</body>
</html>
