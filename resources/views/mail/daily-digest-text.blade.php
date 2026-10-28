SIVERA DAILY SUMMARY
{{ $summary['from'] }} · {{ $summary['timezone'] }}
Events received during this day.

Events: {{ $summary['events'] }}
Active issues: {{ $summary['open'] }}
Critical issues: {{ $summary['critical'] }}
Resolved issues with events: {{ $summary['resolved'] }}
Ignored issues with events: {{ $summary['ignored'] }}

APPLICATION RANKING
@forelse ($summary['ranking'] as $application)
{{ $loop->iteration }}. {{ $application->name }}: {{ $application->events }} errors / {{ $application->issues }} issues
@empty
No ERROR or CRITICAL events were received.
@endforelse

MOST FREQUENT ERRORS
@foreach ($summary['top_errors'] as $issue)
{{ $issue->application->name }} · {{ $issue->exception_class }}
{{ $issue->message }}
{{ $issue->period_count }} events · {{ $issue->status }} · {{ $issue->file }}:{{ $issue->line }} · {{ $issue->route }}
{{ route('issues.show', $issue) }}

@endforeach
STATUS CHANGES DURING THIS DAY
{{ $summary['status_change_total'] }} status changes, including RESOLVED and IGNORED.
@foreach ($summary['status_changes'] as $alert)
{{ $alert->issue->application->name }} · {{ $alert->issue->exception_class }}: {{ $alert->from_status }} to {{ $alert->status }}
{{ route('issues.show', $alert->issue) }}
@endforeach
@if($summary['status_change_total'] > 20)
Showing the latest 20 status changes.
@endif
DAILY FOLLOW-UP
{{ $summary['follow_up_total'] }} issues still need attention, including issues without events yesterday.
RESOLVED and IGNORED issues are excluded from follow-up.
@forelse ($summary['follow_up_issues'] as $issue)
{{ $issue->application->name }} · {{ $issue->exception_class }} · {{ $issue->severity }} · {{ $issue->status }}
{{ route('issues.show', $issue) }}
@empty
No issues need follow-up.
@endforelse
@if ($summary['follow_up_total'] > 20)
Showing the first 20 issues. {{ route('issues.index', ['status' => 'ACTIVE']) }}
@endif

Status and severity reflect their current values.
{{ route('dashboard', ['from' => $summary['from'], 'to' => $summary['to']]) }}
