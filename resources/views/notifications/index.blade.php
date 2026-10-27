@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('content')
<div class="page-head"><div><h1>Notifications</h1><p>Live alerts in your workspace. One email summary each morning.</p></div><a class="button primary" href="{{ route('notifications.create') }}">Add recipient</a></div>
<div class="notice">Daily email: <strong>{{ config('sivera.digest_time') }} · {{ config('sivera.timezone') }}</strong>. Covers the previous calendar day, including days with no events.</div>
<section class="panel"><div class="panel-head"><h2>Email recipients</h2></div><div class="table-scroll"><table><thead><tr><th>Recipient</th><th>Email address</th><th>Status</th><th></th></tr></thead><tbody>
@forelse ($rules as $rule)
<tr><td class="table-title">{{ $rule->name }}</td><td>{{ $rule->target }}</td><td><span class="badge">{{ $rule->is_active ? 'Active' : 'Disabled' }}</span></td><td><a class="text-link" href="{{ route('notifications.edit', $rule) }}">Edit</a></td></tr>
@empty <tr><td colspan="4" class="empty">Add an email recipient to receive morning summaries.</td></tr> @endforelse
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><h2>Delivery history</h2><span class="panel-meta">Latest 20 deliveries</span></div><div class="table-scroll"><table><thead><tr><th>Created</th><th>Summary date / issue</th><th>Recipient</th><th>Status</th></tr></thead><tbody>
@forelse ($logs as $log)
<tr><td>{{ $log->created_at }}</td><td>@if($log->digest_date){{ $log->digest_date }}@elseif($log->issue)<a class="text-link" href="{{ route('issues.show', $log->issue) }}">{{ $log->issue->exception_class }}</a>@else Historical alert @endif</td><td>{{ $log->digest_recipient ?? $log->rule?->name }}</td><td><span class="badge">{{ ucfirst($log->status) }}</span></td></tr>
@empty <tr><td colspan="4" class="empty">No deliveries yet.</td></tr> @endforelse
</tbody></table></div></section>
@endsection
