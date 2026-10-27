<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $key = 'login:'.Str::lower($credentials['email']).':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in one minute.']);
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'The email or password is incorrect.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        if ($request->user()->role === 'admin') {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'admin.login',
                'ip_address' => $request->ip(),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword(): View
    {
        return view('auth.change-password');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $user->update(['password' => $request->validated('password')]);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'user.password_changed',
                'subject_type' => $user::class,
                'subject_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);
        });
        $request->session()->regenerate();

        return redirect()->route('password.edit')->with('status', 'Your password has been changed.');
    }
}
