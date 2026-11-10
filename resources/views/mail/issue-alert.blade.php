<!DOCTYPE html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#18181b;line-height:1.6;max-width:640px;margin:32px auto">
<h1 style="font-size:24px">SIVERA issue activity</h1>
@if ($alert->type === 'status_changed')
<p>Status changed from <strong>{{ $alert->from_status }}</strong> to <strong>{{ $alert->status }}</strong>.</p>
@else
<p>An error event was received.</p>
@endif
<p>{{ $alert->created_at->timezone(config('sivera.timezone'))->format('Y-m-d H:i:s') }} &middot; {{ config('sivera.timezone') }}</p>
<h2 style="font-size:18px">{{ $alert->issue->application->name }} &middot; {{ $alert->issue->exception_class }}</h2>
<p>{{ mb_substr($alert->issue->message, 0, 200) }}</p>
<p>Severity: {{ $alert->issue->severity }} &middot; Status: {{ $alert->type === 'status_changed' ? $alert->status : $alert->issue->status }}</p>
<p>{{ $alert->issue->file }}:{{ $alert->issue->line }} &middot; {{ $alert->issue->route }}</p>
<a href="{{ route('issues.show', $alert->issue) }}">Inspect issue</a>
<p>Issue details reflect their current values. Messages describe the reported error; a root cause still needs investigation.</p>
</body></html>
