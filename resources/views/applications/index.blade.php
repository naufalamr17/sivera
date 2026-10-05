@extends('layouts.app')
@section('title', 'Applications')
@section('heading', 'Applications')
@section('content')
<div class="page-head"><div><h2>Connected applications</h2><p>Manage applications and credentials used to report exceptions.</p></div>@can('admin')<a class="button primary" href="{{ route('applications.create') }}">+ Add application</a>@endcan</div>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>APPLICATION</th><th>ENVIRONMENT</th><th>ISSUES</th><th>STATUS</th><th></th></tr></thead><tbody>
@forelse ($applications as $application)
<tr><td><a class="table-title" href="{{ route('applications.show', $application) }}">{{ $application->name }}</a><small>{{ $application->slug }}</small></td><td>{{ $application->environment }}</td><td>{{ $application->issues_count }}</td><td><span @class(['badge', 'enabled' => $application->is_active])>{{ $application->is_active ? 'ACTIVE' : 'DISABLED' }}</span></td><td><a class="text-link" href="{{ route('applications.show', $application) }}">Details →</a></td></tr>
@empty <tr><td colspan="5" class="empty">No applications yet.</td></tr> @endforelse
</tbody></table></div></section>
<div class="pagination">{{ $applications->links() }}</div>
@endsection
