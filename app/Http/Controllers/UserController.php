<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::query()->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());
        $this->audit($request, 'user.created', $user);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', compact('user'));
    }

    public function update(StoreUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()->is($user) && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            throw ValidationException::withMessages(['role' => 'You cannot remove your own Admin access.']);
        }

        if ($user->role === 'admin' && $user->is_active
            && ($data['role'] !== 'admin' || ! $data['is_active'])
            && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'At least one active Admin is required.']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $this->audit($request, 'user.updated', $user);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    private function audit(Request $request, string $action, User $user): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
