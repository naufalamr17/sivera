@extends('layouts.app')
@section('title', 'Audit logs')
@section('heading', 'Audit logs')
@section('content')
<div class="page-head"><div><h2>Activity trail</h2><p>Administrator actions are recorded without storing secrets.</p></div></div>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>TIME</th><th>USER NAME</th><th>ACTION</th><th>SUBJECT</th><th>IP</th></tr></thead><tbody>
@forelse($logs as $log)
<tr><td>@include('layouts.timestamp', ['value' => $log->created_at, 'databaseGenerated' => true])</td><td>{{ $log->user?->name ?? '—' }}</td><td><strong>{{ $log->action }}</strong></td><td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td><td>{{ $log->ip_address ?? '—' }}</td></tr>
@empty <tr><td colspan="5" class="empty">No activity yet.</td></tr> @endforelse
</tbody></table></div></section><div class="pagination">{{ $logs->links() }}</div>
@endsection
