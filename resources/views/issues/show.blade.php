@extends('layouts.app')
@section('title', $issue->exception_class)
@section('heading', 'Issue detail')
@section('content')
<div class="page-head"><div><span class="eyebrow">ISSUE {{ $issue->uuid }}</span><h2>{{ $issue->exception_class }}</h2><p>{{ $issue->message }}</p></div><a class="text-link" href="{{ route('issues.index') }}">← All issues</a></div>
<div class="issue-badges"><span class="badge severity-{{ strtolower($issue->severity) }}">{{ $issue->severity }}</span><span class="badge">{{ $issue->status }}</span><span class="badge">{{ $issue->application->name }}</span></div>
<div class="detail-grid">
<section class="panel"><div class="panel-head"><div><span class="eyebrow">CONTEXT</span><h2>Exception</h2></div></div>
<dl class="details"><div><dt>Application</dt><dd>{{ $issue->application->name }} / {{ $issue->application->environment }}</dd></div><div><dt>Location</dt><dd>{{ $issue->file ?? '—' }}{{ $issue->line ? ':'.$issue->line : '' }}</dd></div><div><dt>Route</dt><dd>{{ $issue->method }} {{ $issue->route ?? '—' }}</dd></div><div><dt>First seen</dt><dd>{{ $issue->first_seen_at->format('d M Y H:i:s') }}</dd></div><div><dt>Last seen</dt><dd>{{ $issue->last_seen_at->format('d M Y H:i:s') }}</dd></div><div><dt>Occurrences</dt><dd>{{ number_format($issue->occurrence_count) }}</dd></div></dl>
</section>
@can('admin')
<section class="panel"><div class="panel-head"><div><span class="eyebrow">RESPONSE</span><h2>Manage issue</h2></div></div>
<form method="POST" action="{{ route('issues.status', $issue) }}" class="inline-form">@csrf<label>Status<select name="status">@foreach(['ACKNOWLEDGED','INVESTIGATING','RESOLVED','IGNORED','REOPENED'] as $value)<option value="{{ $value }}" @selected($issue->status === $value)>{{ $value }}</option>@endforeach</select></label><button class="button" type="submit">Update status</button></form>
<form method="POST" action="{{ route('issues.severity', $issue) }}" class="inline-form">@csrf<label>Severity<select name="severity">@foreach(['INFO','WARNING','ERROR','CRITICAL'] as $value)<option value="{{ $value }}" @selected($issue->severity === $value)>{{ $value }}</option>@endforeach</select></label><button class="button" type="submit">Update severity</button></form>
<form method="POST" action="{{ route('issues.comments', $issue) }}" class="stack">@csrf<label>Comment<textarea name="comment" rows="3" required maxlength="5000" placeholder="Add investigation context..."></textarea></label><div><button class="button primary" type="submit">Add comment</button></div></form>
</section>
@endcan
</div>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">EVENT DATA</span><h2>Occurrences</h2></div><span class="muted">Details are sampled after 100 events</span></div>
@forelse($occurrences as $occurrence)
<details class="occurrence" @if($loop->first) open @endif><summary><strong>{{ $occurrence->occurred_at->format('d M Y H:i:s') }}</strong><span>{{ $occurrence->event_id }}</span></summary>
<div class="occurrence-body"><div class="meta-line"><span>User reference: {{ $occurrence->user_reference ?? '—' }}</span><span>Source IP: {{ $occurrence->source_ip ?? '—' }}</span><span>Path: {{ $occurrence->request_metadata_json['path'] ?? '—' }}</span></div>
<h3>Stack trace</h3><ol class="trace">@forelse($occurrence->stack_trace_json as $frame)<li><strong>{{ $frame['class'] ?? '' }}{{ isset($frame['class']) ? '::' : '' }}{{ $frame['function'] ?? 'unknown' }}</strong><span>{{ $frame['file'] ?? '—' }}{{ isset($frame['line']) ? ':'.$frame['line'] : '' }}</span></li>@empty<li>No frames supplied.</li>@endforelse</ol></div></details>
@empty <p class="empty">No occurrence details yet.</p> @endforelse
<div class="pagination">{{ $occurrences->links() }}</div></section>
<div class="detail-grid">
<section class="panel"><div class="panel-head"><div><span class="eyebrow">TIMELINE</span><h2>Status history</h2></div></div><ul class="timeline">@forelse($issue->histories->sortByDesc('created_at') as $history)<li><strong>{{ $history->from_status ?? 'NEW' }} → {{ $history->to_status }}</strong><small>{{ $history->created_at }}</small></li>@empty<li class="empty">No status changes yet.</li>@endforelse</ul></section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">COLLABORATION</span><h2>Comments</h2></div></div><ul class="timeline">@forelse($issue->comments->sortByDesc('created_at') as $comment)<li><strong>{{ $comment->user?->name ?? 'Inactive user' }}</strong><p>{{ $comment->comment }}</p><small>{{ $comment->created_at }}</small></li>@empty<li class="empty">No comments yet.</li>@endforelse</ul></section>
</div>
@endsection
