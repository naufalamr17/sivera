<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#18181b;line-height:1.6;max-width:640px;margin:32px auto">
<h1 style="font-size:24px">SIVERA daily summary</h1>
<p>{{ $summary['from'] }} · {{ $summary['timezone'] }} · Events received during this day.</p>
<p><strong>{{ $summary['events'] }}</strong> events · <strong>{{ $summary['open'] }}</strong> active issues · <strong>{{ $summary['critical'] }}</strong> critical issues</p>
<h2 style="font-size:18px">Applications with the most errors</h2>
@forelse ($summary['ranking'] as $application)
<p>{{ $loop->iteration }}. {{ $application->name }} — {{ $application->events }} errors / {{ $application->issues }} issues</p>
@empty <p>No ERROR or CRITICAL events were received.</p> @endforelse
<h2 style="font-size:18px">Most frequent errors</h2>
@foreach ($summary['top_errors'] as $issue)
<div style="border-top:1px solid #e4e4e7;padding:12px 0">
<strong>{{ $issue->application->name }} · {{ $issue->exception_class }}</strong>
<p>{{ $issue->message }}</p>
<p>{{ $issue->period_count }} events · {{ $issue->file }}:{{ $issue->line }} · {{ $issue->route }}</p>
<a href="{{ route('issues.show', $issue) }}">Inspect issue</a>
</div>
@endforeach
<p>Status and severity reflect their current values. Messages describe the reported error; a root cause still needs investigation.</p>
<a href="{{ route('dashboard', ['from' => $summary['from'], 'to' => $summary['to']]) }}">Open dashboard</a>
</body></html>
