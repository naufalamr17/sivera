@extends('layouts.app')
@section('title', 'Change password')
@section('heading', 'Change password')
@section('content')
<div class="page-head"><div><h1>Change password</h1><p>Update the password for your own account.</p></div></div>
<section class="panel form-panel">
    <form method="POST" action="{{ route('password.update') }}" class="stack">
        @csrf
        @method('PUT')
        <label>Current password<input type="password" name="current_password" autocomplete="current-password" required autofocus @error('current_password') aria-invalid="true" aria-describedby="current-password-error" @enderror>
            @error('current_password')<small id="current-password-error" role="alert">{{ $message }}</small>@enderror
        </label>
        <label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" required aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
            <small id="password-help">Use at least 12 characters and choose a different password from your current one.</small>
            @error('password')<small id="password-error" role="alert">{{ $message }}</small>@enderror
        </label>
        <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
        <div><button class="button primary" type="submit">Update password</button></div>
    </form>
</section>
@endsection
