SIVERA ISSUE ACTIVITY
@if ($alert->type === 'status_changed')
Status changed from {{ $alert->from_status }} to {{ $alert->status }}.
@else
An error event was received.
@endif
{{ $alert->created_at->timezone(config('sivera.timezone'))->format('Y-m-d H:i:s') }} - {{ config('sivera.timezone') }}

{{ $alert->issue->application->name }} - {{ $alert->issue->exception_class }}
{{ mb_substr($alert->issue->message, 0, 200) }}
Severity: {{ $alert->issue->severity }}
Status: {{ $alert->type === 'status_changed' ? $alert->status : $alert->issue->status }}
{{ $alert->issue->file }}:{{ $alert->issue->line }} - {{ $alert->issue->route }}

Inspect issue: {{ route('issues.show', $alert->issue) }}

Issue details reflect their current values. Messages describe the reported error; a root cause still needs investigation.
