@extends('layouts.app')
@section('title', $application->name)
@section('heading', $application->name)
@section('content')
<div class="page-head"><div><span class="eyebrow">{{ $application->slug }} · {{ $application->environment }}</span><h2>Application settings</h2><p>{{ $application->description ?: 'No description.' }}</p></div><a class="text-link" href="{{ route('applications.index') }}">← All applications</a></div>
@php($newCredential = session()->pull('new_credential'))
@if($newCredential)
<div class="notice secret-notice"><strong>Save these credentials now. The secret is shown only once.</strong><div>Client ID: <code>{{ $newCredential['client_id'] }}</code></div><div>Secret: <code>{{ $newCredential['secret'] }}</code></div></div>
@endif
<div class="detail-grid">
<section class="panel"><div class="panel-head"><div><span class="eyebrow">CONFIGURATION</span><h2>Overview</h2></div><span @class(['badge', 'enabled' => $application->is_active])>{{ $application->is_active ? 'ACTIVE' : 'DISABLED' }}</span></div>
<dl class="details"><div><dt>App ID</dt><dd>{{ $application->slug }}</dd></div><div><dt>Environment</dt><dd>{{ $application->environment }}</dd></div><div><dt>Rate limit</dt><dd>{{ $application->rate_limit_per_minute }} / minute</dd></div><div><dt>IP allowlist</dt><dd>{{ $application->allowed_ips ? implode(', ', $application->allowed_ips) : 'All IPs' }}</dd></div></dl>
@can('admin')<div class="actions"><a class="button" href="{{ route('applications.edit', $application) }}">Edit application</a><form method="POST" action="{{ route('applications.toggle', $application) }}">@csrf<button class="button" type="submit">{{ $application->is_active ? 'Disable' : 'Enable' }}</button></form></div>@endcan
</section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">SECURITY</span><h2>Credentials</h2></div></div>
@can('admin')<div class="actions"><form method="POST" action="{{ route('credentials.store', $application) }}">@csrf<button class="button primary" type="submit">Generate credential</button></form><form method="POST" action="{{ route('credentials.rotate', $application) }}">@csrf<button class="button" type="submit">Rotate secret</button></form></div>@endcan
<div class="credential-list">
@forelse($application->credentials as $credential)
<div class="credential"><div><strong>{{ $credential->client_id }}</strong><small>{{ $credential->is_active ? ($credential->expires_at ? 'Expires '.$credential->expires_at->format('d M Y H:i') : 'Active') : 'Revoked' }}</small></div>@can('admin')@if($credential->is_active)<form method="POST" action="{{ route('credentials.revoke', [$application, $credential]) }}">@csrf<button class="text-button danger" type="submit">Revoke</button></form>@endif @endcan</div>
@empty <p class="empty">No credentials yet.</p> @endforelse
</div></section>
</div>
@endsection
