@extends('layouts.app')
@section('title', $rule->exists ? 'Edit email recipient' : 'Add email recipient')
@section('heading', 'Email recipients')
@section('content')
<div class="page-head"><div><h1>{{ $rule->exists ? 'Edit recipient' : 'Add recipient' }}</h1><p>One daily email at {{ config('sivera.digest_time') }} {{ config('sivera.timezone') }}, covering the previous day.</p></div><a class="button" href="{{ route('notifications.index') }}">All recipients</a></div>
<section class="panel form-panel">
<form class="stack" method="POST" action="{{ $rule->exists ? route('notifications.update', $rule) : route('notifications.store') }}">
@csrf @if($rule->exists) @method('PUT') @endif
<label>Recipient label<input name="name" required maxlength="255" value="{{ old('name', $rule->name) }}" placeholder="Operations team"></label>
<label>Email address<input type="email" name="target" required maxlength="255" value="{{ old('target', $rule->target) }}"></label>
<label>Status<select name="is_active"><option value="1" @selected(old('is_active', $rule->is_active ?? true))>Active</option><option value="0" @selected(!old('is_active', $rule->is_active ?? true))>Disabled</option></select></label>
<p class="muted">The summary includes event counts, application ranking, and the most frequent errors. Browser alerts are enabled separately using the bell in the header.</p>
<div><button class="button primary" type="submit">Save recipient</button></div>
</form></section>
@endsection
