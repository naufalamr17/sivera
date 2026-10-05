@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Overview')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">MONITORING</span><h1>Overview</h1><p>Activity and issues across all applications.</p></div>
    <span class="refresh-label"><span class="pulse-dot"></span> Live · {{ $pollSeconds }}s refresh</span>
</div>
<form class="explore-bar" method="GET" action="{{ route('issues.index') }}" role="search">
    <span class="explore-prefix">Explore issues</span>
    <label class="explore-search"><span aria-hidden="true">⌕</span><input type="search" name="search" placeholder="Search issues, errors, routes, and more" aria-label="Search issues"></label>
    <button class="button primary" type="submit">Search</button>
</form>
<div class="quick-filters" aria-label="Quick filters">
    <a href="{{ route('issues.index', ['severity' => 'CRITICAL']) }}">severity is <strong>critical</strong></a>
    <a href="{{ route('issues.index', ['status' => 'NEW']) }}">status is <strong>new</strong></a>
    <a href="{{ route('issues.index') }}">See full list →</a>
</div>
<div class="metrics">
    <div class="metric"><span>APPLICATIONS</span><strong id="count-applications">{{ $summary['applications'] }}</strong><small>Registered</small></div>
    <div class="metric"><span>OPEN ISSUES</span><strong id="count-open">{{ $summary['open'] }}</strong><small>Need attention</small></div>
    <div class="metric critical"><span>CRITICAL</span><strong id="count-critical">{{ $summary['critical'] }}</strong><small>Highest priority</small></div>
    <div class="metric"><span>EVENTS TODAY</span><strong id="count-today">{{ $summary['today'] }}</strong><small>Occurrences</small></div>
    <div class="metric"><span>RESOLVED</span><strong id="count-resolved">{{ $summary['resolved'] }}</strong><small>Completed</small></div>
</div>
<div id="critical-alert" class="notice error" hidden>A new critical issue was detected. <a href="{{ route('issues.index', ['severity' => 'CRITICAL']) }}">View issue</a></div>
<section class="panel activity-panel" aria-labelledby="activity-title">
    <div class="panel-head"><div><span class="eyebrow">EVENT VOLUME</span><h2 id="activity-title">Events over time</h2></div><span class="panel-meta">Last 24 hours · 1 hour interval</span></div>
    <div class="chart-wrap">
        <div class="chart-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <div class="activity-bars" role="img" aria-label="Hourly event counts over the last 24 hours">
            @foreach ($activity as $hour)
                <div class="activity-slot" title="{{ $hour['label'] }} · {{ $hour['count'] }} events"><span class="activity-bar" style="height: {{ $hour['height'] }}%"></span></div>
            @endforeach
        </div>
        <div class="chart-labels"><span>{{ $activity[0]['label'] }}</span><span>{{ $activity[6]['label'] }}</span><span>{{ $activity[12]['label'] }}</span><span>{{ $activity[18]['label'] }}</span><span>{{ $activity[23]['label'] }}</span></div>
    </div>
</section>
<section class="panel issues-panel">
    <div class="panel-head"><div><span class="eyebrow">ISSUE STREAM</span><h2>Latest issues</h2></div><div class="panel-head-actions"><span class="refresh-label">Auto-refresh</span><a class="button" href="{{ route('issues.index') }}">View all issues</a></div></div>
    <div class="table-scroll"><table><thead><tr><th>ISSUE / MESSAGE</th><th>SEVERITY</th><th>STATUS</th><th>EVENTS</th><th>LAST SEEN</th></tr></thead>
        <tbody id="latest-issues">
        @forelse ($summary['latest'] as $issue)
            <tr><td><a class="table-title" href="{{ route('issues.show', $issue) }}">{{ $issue->exception_class }}</a><small>{{ $issue->application->name }} · {{ \Illuminate\Support\Str::limit($issue->message, 75) }}</small></td><td><span class="badge severity-{{ strtolower($issue->severity) }}">{{ $issue->severity }}</span></td><td><span class="badge">{{ $issue->status }}</span></td><td class="tabular">{{ $issue->occurrence_count }}</td><td>{{ $issue->last_seen_at->diffForHumans() }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No issues yet. New events will appear here.</td></tr> @endforelse
        </tbody>
    </table></div>
</section>
<script>
(() => {
    const endpoint = @json(route('dashboard.live-summary'));
    const issueBase = @json(url('/issues'));
    let previousCritical = Number(document.getElementById('count-critical').textContent);
    const addCell = (row, value, className) => {
        const cell = row.insertCell();
        if (className) cell.className = className;
        cell.textContent = value ?? '';
        return cell;
    };
    const addBadge = (row, value, className) => {
        const badge = document.createElement('span');
        badge.className = 'badge' + (className ? ' ' + className : '');
        badge.textContent = value;
        row.insertCell().append(badge);
    };
    async function poll() {
        try {
            const response = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            for (const key of ['applications', 'open', 'critical', 'today', 'resolved']) {
                document.getElementById('count-' + key).textContent = data[key];
            }
            if (data.critical > previousCritical) document.getElementById('critical-alert').hidden = false;
            previousCritical = data.critical;
            const body = document.getElementById('latest-issues');
            body.replaceChildren();
            for (const issue of data.latest) {
                const row = body.insertRow();
                const first = row.insertCell();
                const link = document.createElement('a');
                link.className = 'table-title';
                link.href = issueBase + '/' + encodeURIComponent(issue.uuid);
                link.textContent = issue.exception_class;
                const small = document.createElement('small');
                small.textContent = issue.application.name + ' · ' + issue.message.slice(0, 75);
                first.append(link, small);
                addBadge(row, issue.severity, 'severity-' + issue.severity.toLowerCase());
                addBadge(row, issue.status);
                addCell(row, issue.occurrence_count, 'tabular');
                addCell(row, new Date(issue.last_seen_at).toLocaleString('en-US'));
            }
            if (!data.latest.length) {
                const row = body.insertRow();
                addCell(row, 'No issues yet.').colSpan = 5;
            }
        } catch (_) {
            // Keep existing data until the next poll.
        }
    }
    setInterval(poll, {{ $pollSeconds * 1000 }});
})();
</script>
@endsection
