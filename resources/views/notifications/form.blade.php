@extends('layouts.app')
@section('title', $rule->exists ? 'Edit notification rule' : 'Add notification rule')
@section('heading', $rule->exists ? 'Edit notification rule' : 'Add notification rule')
@section('content')
<div class="page-head"><div><h2>{{ $rule->exists ? $rule->name : 'New alert rule' }}</h2><p>Provider credentials are read from the server environment.</p></div><a class="text-link" href="{{ route('notifications.index') }}">← All rules</a></div>
<section class="panel form-panel">
<form class="stack" method="POST" action="{{ $rule->exists ? route('notifications.update', $rule) : route('notifications.store') }}">
    @csrf @if($rule->exists) @method('PUT') @endif
    <label>Rule name<input name="name" required value="{{ old('name', $rule->name) }}"></label>
    <div class="form-grid"><label>Trigger<select name="trigger">@foreach(['new_critical' => 'New critical', 'becomes_critical' => 'Becomes critical', 'reopened' => 'Reopened', 'high_frequency' => 'High frequency'] as $value => $label)<option value="{{ $value }}" @selected(old('trigger', $rule->trigger ?? 'new_critical') === $value)>{{ $label }}</option>@endforeach</select></label><label>Channel<select name="channel"><option value="email" @selected(old('channel', $rule->channel ?? 'email') === 'email')>Email</option><option value="qontak" @selected(old('channel', $rule->channel) === 'qontak')>Qontak WhatsApp</option></select></label></div>
    <div class="form-grid"><label>Target <small>(email address or WhatsApp number)</small><input name="target" required value="{{ old('target', $rule->target) }}"></label><label>Recipient name for Qontak<input name="target_name" value="{{ old('target_name', $rule->target_name) }}"></label></div>
    <div class="form-grid"><label>Severity<select name="severity"><option value="">All</option>@foreach(['INFO','WARNING','ERROR','CRITICAL'] as $value)<option value="{{ $value }}" @selected(old('severity', $rule->severity) === $value)>{{ $value }}</option>@endforeach</select></label><label>Status<select name="is_active"><option value="1" @selected(old('is_active', $rule->is_active ?? true))>Active</option><option value="0" @selected(!old('is_active', $rule->is_active ?? true))>Disabled</option></select></label></div>
    <div class="form-grid"><label>Frequency threshold<input type="number" name="frequency_threshold" min="1" max="100000" value="{{ old('frequency_threshold', $rule->frequency_threshold ?? 10) }}" required></label><label>Frequency window (minutes)<input type="number" name="frequency_window_minutes" min="1" max="1440" value="{{ old('frequency_window_minutes', $rule->frequency_window_minutes ?? 5) }}" required></label></div>
    <label>Cooldown (minutes)<input type="number" name="cooldown_minutes" min="1" max="1440" value="{{ old('cooldown_minutes', $rule->cooldown_minutes ?? 30) }}" required></label>
    <div><button class="button primary" type="submit">{{ $rule->exists ? 'Save changes' : 'Create rule' }}</button></div>
</form></section>
@endsection
