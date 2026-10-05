@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'Add user')
@section('heading', $user->exists ? 'Edit user' : 'Add user')
@section('content')
<div class="page-head"><div><h2>{{ $user->exists ? $user->name : 'New user' }}</h2><p>Viewers have read-only access to the dashboard and issue details.</p></div><a class="text-link" href="{{ route('users.index') }}">← All users</a></div>
<section class="panel form-panel">
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="stack">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="form-grid"><label>Name<input name="name" value="{{ old('name', $user->name) }}" required maxlength="255"></label><label>Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label></div>
    <div class="form-grid"><label>Role<select name="role"><option value="viewer" @selected(old('role', $user->role ?? 'viewer') === 'viewer')>Viewer</option><option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option></select></label><label>Status<select name="is_active"><option value="1" @selected(old('is_active', $user->is_active ?? true))>Active</option><option value="0" @selected(!old('is_active', $user->is_active ?? true))>Disabled</option></select></label></div>
    <div class="form-grid"><label>Password {{ $user->exists ? '(leave blank to keep current password)' : '' }}<input type="password" name="password" autocomplete="new-password" @if(!$user->exists) required @endif minlength="12"></label><label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" @if(!$user->exists) required @endif></label></div>
    <div><button class="button primary" type="submit">{{ $user->exists ? 'Save changes' : 'Create user' }}</button></div>
</form></section>
@endsection
