@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Overview')
@section('content')
@php
$dashboardData = [
    'summary' => $summary,
    'endpoint' => route('dashboard.live-summary'),
    'dashboard' => route('dashboard'),
    'issues' => route('issues.index'),
    'issueBase' => url('/issues'),
    'pollSeconds' => config('sivera.poll_seconds'),
];
@endphp
<script type="application/json" id="dashboard-data">@json($dashboardData)</script>
<div id="dashboard-root"><div class="page-head"><div><h1>Overview</h1><p>Loading your workspace…</p></div></div></div>
@endsection
