@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('content')
<div class="page-head"><div><h2>Alert rules</h2><p>Send important alerts by email or Qontak WhatsApp, with a cooldown per issue.</p></div><a class="button primary" href="{{ route('notifications.create') }}">+ Add rule</a></div>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>RULE</th><th>TRIGGER</th><th>CHANNEL</th><th>TARGET</th><th>COOLDOWN</th><th>STATUS</th><th></th></tr></thead><tbody>
@forelse($rules as $rule)
<tr><td><strong>{{ $rule->name }}</strong><small>{{ $rule->severity ?: 'All severities' }}</small></td><td>{{ str_replace('_', ' ', strtoupper($rule->trigger)) }}</td><td>{{ strtoupper($rule->channel) }}</td><td>{{ $rule->target }}</td><td>{{ $rule->cooldown_minutes }} minutes</td><td><span @class(['badge', 'enabled' => $rule->is_active])>{{ $rule->is_active ? 'ACTIVE' : 'DISABLED' }}</span></td><td><a class="text-link" href="{{ route('notifications.edit', $rule) }}">Edit →</a></td></tr>
@empty <tr><td colspan="7" class="empty">No alert rules yet.</td></tr> @endforelse
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">DELIVERY</span><h2>Recent notifications</h2></div></div><div class="table-scroll"><table><thead><tr><th>TIME</th><th>ISSUE</th><th>RULE</th><th>CHANNEL</th><th>STATUS</th></tr></thead><tbody>
@forelse($logs as $log)
<tr><td>{{ $log->created_at }}</td><td><a class="text-link" href="{{ route('issues.show', $log->issue) }}">{{ $log->issue->exception_class }}</a></td><td>{{ $log->rule->name }}</td><td>{{ strtoupper($log->channel) }}</td><td><span @class(['badge', 'enabled' => $log->status === 'sent'])>{{ strtoupper($log->status) }}</span></td></tr>
@empty <tr><td colspan="5" class="empty">No alerts have been sent yet.</td></tr> @endforelse
</tbody></table></div></section>
@endsection
