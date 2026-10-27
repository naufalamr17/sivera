SIVERA DAILY SUMMARY
{{ $summary['from'] }} · {{ $summary['timezone'] }}
Events received during this day.

Events: {{ $summary['events'] }}
Active issues: {{ $summary['open'] }}
Critical issues: {{ $summary['critical'] }}

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
{{ $issue->period_count }} events · {{ $issue->file }}:{{ $issue->line }} · {{ $issue->route }}
{{ route('issues.show', $issue) }}

@endforeach
Status and severity reflect their current values.
{{ route('dashboard', ['from' => $summary['from'], 'to' => $summary['to']]) }}
