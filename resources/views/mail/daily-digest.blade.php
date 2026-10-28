<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#18181b;line-height:1.6;max-width:640px;margin:32px auto">
<h1 style="font-size:24px">SIVERA daily summary</h1>
<p>{{ $summary['from'] }} · {{ $summary['timezone'] }} · Events received during this day.</p>
<p><strong>{{ $summary['events'] }}</strong> events · <strong>{{ $summary['open'] }}</strong> active issues · <strong>{{ $summary['critical'] }}</strong> critical issues</p>
<p>{{ $summary['resolved'] }} resolved issues · {{ $summary['ignored'] }} ignored issues with events during this day.</p>
<h2 style="font-size:18px">Applications with the most errors</h2>
@forelse ($summary['ranking'] as $application)
<p>{{ $loop->iteration }}. {{ $application->name }} — {{ $application->events }} errors / {{ $application->issues }} issues</p>
@empty <p>No ERROR or CRITICAL events were received.</p> @endforelse
<h2 style="font-size:18px">Most frequent errors</h2>
@foreach ($summary['top_errors'] as $issue)
<div style="border-top:1px solid #e4e4e7;padding:12px 0">
<strong>{{ $issue->application->name }} · {{ $issue->exception_class }}</strong>
<p>{{ $issue->message }}</p>
<p>{{ $issue->period_count }} events · {{ $issue->status }} · {{ $issue->file }}:{{ $issue->line }} · {{ $issue->route }}</p>
<a href="{{ route('issues.show', $issue) }}">Inspect issue</a>
</div>
@endforeach
<h2 style="font-size:18px">Status changes during this day</h2>
<p>{{ $summary['status_change_total'] }} status changes, including RESOLVED and IGNORED.</p>
@foreach ($summary['status_changes'] as $alert)
<p><a href="{{ route('issues.show', $alert->issue) }}">{{ $alert->issue->application->name }} · {{ $alert->issue->exception_class }}</a>: {{ $alert->from_status }} to <strong>{{ $alert->status }}</strong></p>
@endforeach
@if($summary['status_change_total'] > 20)<p>Showing the latest 20 status changes.</p>@endif
<h2 style="font-size:18px">Daily follow-up</h2>
<p>{{ $summary['follow_up_total'] }} issues still need attention, including issues without events yesterday. RESOLVED and IGNORED issues are excluded from follow-up.</p>
@forelse ($summary['follow_up_issues'] as $issue)
<div style="border-top:1px solid #e4e4e7;padding:12px 0"><strong>{{ $issue->application->name }} · {{ $issue->exception_class }}</strong><p>{{ $issue->severity }} · {{ $issue->status }} · Last event: {{ $issue->last_seen_at->timezone($summary['timezone'])->format('Y-m-d H:i') }}</p><a href="{{ route('issues.show', $issue) }}">Follow up issue</a></div>
@empty <p>No issues need follow-up.</p> @endforelse
@if ($summary['follow_up_total'] > 20)<p>Showing the first 20 issues. <a href="{{ route('issues.index', ['status' => 'ACTIVE']) }}">View all issues awaiting follow-up</a></p>@endif
<p>Status and severity reflect their current values. Messages describe the reported error; a root cause still needs investigation.</p>
<a href="{{ route('dashboard', ['from' => $summary['from'], 'to' => $summary['to']]) }}">Open dashboard</a>
</body></html>
