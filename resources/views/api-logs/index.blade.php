@extends('layouts.app')
@section('title', 'API logs')
@section('heading', 'API logs')
@section('content')
<div class="page-head"><div><h2>Ingestion requests</h2><p>Request security metadata without signatures, secrets, or payloads.</p></div></div>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>TIME</th><th>APPLICATION</th><th>CLIENT ID</th><th>SOURCE IP</th><th>STATUS</th><th>SIGNATURE</th><th>SIZE</th></tr></thead><tbody>
@forelse($logs as $log)
<tr><td>{{ $log->received_at }}</td><td>{{ $log->application?->name ?? 'Unknown' }}</td><td>{{ $log->client_id ?? '—' }}</td><td>{{ $log->source_ip ?? '—' }}</td><td><span @class(['badge', 'enabled' => $log->http_status === 202])>{{ $log->http_status }}</span></td><td>{{ $log->signature_valid ? 'VALID' : 'INVALID' }}</td><td>{{ number_format($log->request_size) }} B</td></tr>
@empty <tr><td colspan="7" class="empty">No API requests yet.</td></tr> @endforelse
</tbody></table></div></section><div class="pagination">{{ $logs->links() }}</div>
@endsection
