@extends('layouts.app')
@section('title', $application->exists ? 'Edit application' : 'Add application')
@section('heading', $application->exists ? 'Edit application' : 'Add application')
@section('content')
<div class="page-head"><div><h2>{{ $application->exists ? $application->name : 'New application' }}</h2><p>Each application has its own identity and credentials.</p></div><a class="text-link" href="{{ route('applications.index') }}">← Back</a></div>
<section class="panel form-panel">
<form method="POST" action="{{ $application->exists ? route('applications.update', $application) : route('applications.store') }}" class="stack">
    @csrf @if($application->exists) @method('PUT') @endif
    <div class="form-grid"><label>Application name<input name="name" value="{{ old('name', $application->name) }}" required maxlength="255"></label><label>App ID / slug<input name="slug" value="{{ old('slug', $application->slug) }}" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="100"></label></div>
    <label>Description<textarea name="description" rows="3">{{ old('description', $application->description) }}</textarea></label>
    <div class="form-grid"><label>Environment<input name="environment" value="{{ old('environment', $application->environment ?? 'production') }}" required maxlength="32"></label><label>Rate limit / minute<input type="number" name="rate_limit_per_minute" min="1" max="120" value="{{ old('rate_limit_per_minute', $application->rate_limit_per_minute ?? 60) }}" required></label></div>
    <label>Allowed IPs <small>(one IP per line; leave blank to allow all IPs)</small><textarea name="allowed_ips_text" rows="3" placeholder="192.0.2.10">{{ old('allowed_ips_text', implode("\n", $application->allowed_ips ?? [])) }}</textarea></label>
    <div><button type="submit" class="button primary">{{ $application->exists ? 'Save changes' : 'Create application' }}</button></div>
</form>
</section>
@endsection
