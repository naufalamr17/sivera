@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('content')
<div class="page-head"><div><h1>Notifications</h1><p>Issue activity for everyone, plus a daily email summary.</p></div><a class="button" href="{{ route('users.index') }}">Manage users</a></div>
<div class="notice">Every active Admin and Viewer receives the previous day's summary at <strong>{{ config('sivera.digest_time') }} · {{ config('sivera.timezone') }}</strong>, including days with no events. The email also lists issues awaiting follow-up. RESOLVED and IGNORED issues still appear in activity summaries and status-change alerts, but receive no daily follow-up.</div>
<section class="panel"><div class="panel-head"><h2>Email recipients</h2><span class="panel-meta">All {{ $recipients->total() }} active users · managed under Users</span></div><div class="table-scroll"><table><thead><tr><th>User</th><th>Email address</th><th>Role</th></tr></thead><tbody>
@forelse ($recipients as $recipient)
<tr><td class="table-title">{{ $recipient->name }}</td><td>{{ $recipient->email }}</td><td>{{ ucfirst($recipient->role) }}</td></tr>
@empty <tr><td colspan="3" class="empty">No active users.</td></tr> @endforelse
</tbody></table></div></section>
<div class="pagination">{{ $recipients->links() }}</div>
<section class="panel"><div class="panel-head"><h2>Delivery history</h2><span class="panel-meta">{{ $logs->total() }} deliveries</span></div><div class="table-scroll"><table><thead><tr><th>Created</th><th>Summary date / issue</th><th>Recipient</th><th>Status</th></tr></thead><tbody>
@forelse ($logs as $log)
<tr><td>{{ $log->created_at }}</td><td>@if($log->digest_date){{ $log->digest_date }}@elseif($log->issue)<a class="text-link" href="{{ route('issues.show', $log->issue) }}">{{ $log->issue->exception_class }}</a>@else Historical alert @endif</td><td>{{ $log->digest_recipient ?? $log->user?->email ?? $log->rule?->name }}</td><td><span class="badge">{{ ucfirst($log->status) }}</span></td></tr>
@empty <tr><td colspan="4" class="empty">No deliveries yet.</td></tr> @endforelse
</tbody></table></div></section>
<div class="pagination">{{ $logs->links() }}</div>
@endsection
