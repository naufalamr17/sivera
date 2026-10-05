@extends('layouts.app')
@section('title', 'Issues')
@section('heading', 'Issues')
@section('content')
<div class="page-head"><div><span class="eyebrow">EXPLORE</span><h1>Issues</h1><p>Filter and explore grouped exceptions.</p></div><span class="result-count">{{ $issues->total() }} issues</span></div>
<form class="issue-filters" method="GET" action="{{ route('issues.index') }}">
    <div class="filter-primary">
        <select name="application" aria-label="Application"><option value="">All applications</option>@foreach($applications as $application)<option value="{{ $application->id }}" @selected(request('application') == $application->id)>{{ $application->name }}</option>@endforeach</select>
        <select name="severity" aria-label="Severity"><option value="">All severities</option>@foreach(['INFO','WARNING','ERROR','CRITICAL'] as $value)<option value="{{ $value }}" @selected(request('severity') === $value)>{{ $value }}</option>@endforeach</select>
        <select name="status" aria-label="Status"><option value="">All statuses</option>@foreach(['NEW','ACKNOWLEDGED','INVESTIGATING','RESOLVED','IGNORED','REOPENED'] as $value)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $value }}</option>@endforeach</select>
        <input type="search" name="search" placeholder="Search issues, messages, routes, IDs" aria-label="Search issues" value="{{ request('search') }}">
        <button class="button primary" type="submit">Search</button>
    </div>
    <details class="advanced-filters" @if(request()->filled('exception_class') || request()->filled('route') || request()->filled('from') || request()->filled('to')) open @endif>
        <summary>Advanced filters</summary>
        <div class="advanced-fields">
            <input name="exception_class" placeholder="Exception type" aria-label="Exception type" value="{{ request('exception_class') }}">
            <input name="route" placeholder="Route" aria-label="Route" value="{{ request('route') }}">
            <input type="date" name="from" aria-label="From date" value="{{ request('from') }}">
            <input type="date" name="to" aria-label="To date" value="{{ request('to') }}">
            <button class="button" type="submit">Apply filters</button>
        </div>
    </details>
</form>
<section class="panel issues-panel"><div class="panel-head"><div><span class="eyebrow">RESULTS</span><h2>Issue stream</h2></div><span class="panel-meta">Sorted by last seen</span></div><div class="table-scroll"><table><thead><tr><th>ISSUE / MESSAGE</th><th>APPLICATION</th><th>SEVERITY</th><th>STATUS</th><th>EVENTS</th><th>LAST SEEN</th></tr></thead><tbody>
@forelse($issues as $issue)
<tr><td><a class="table-title" href="{{ route('issues.show', $issue) }}">{{ $issue->exception_class }}</a><small>{{ \Illuminate\Support\Str::limit($issue->message, 85) }}</small></td><td>{{ $issue->application->name }}</td><td><span class="badge severity-{{ strtolower($issue->severity) }}">{{ $issue->severity }}</span></td><td><span class="badge">{{ $issue->status }}</span></td><td class="tabular">{{ $issue->occurrence_count }}</td><td>{{ $issue->last_seen_at->diffForHumans() }}</td></tr>
@empty <tr><td colspan="6" class="empty">No issues match these filters.</td></tr> @endforelse
</tbody></table></div></section><div class="pagination">{{ $issues->links() }}</div>
@endsection
