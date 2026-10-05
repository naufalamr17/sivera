@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'Users')
@section('content')
<div class="page-head"><div><h2>Dashboard access</h2><p>Administrators can manage Admin and Viewer access.</p></div><a class="button primary" href="{{ route('users.create') }}">+ Add user</a></div>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>USER</th><th>ROLE</th><th>STATUS</th><th>LAST LOGIN</th><th></th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td><td><span class="badge">{{ strtoupper($user->role) }}</span></td><td><span @class(['badge', 'enabled' => $user->is_active])>{{ $user->is_active ? 'ACTIVE' : 'DISABLED' }}</span></td><td>{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</td><td><a class="text-link" href="{{ route('users.edit', $user) }}">Edit →</a></td></tr>
@empty <tr><td colspan="5" class="empty">No users yet.</td></tr> @endforelse
</tbody></table></div></section><div class="pagination">{{ $users->links() }}</div>
@endsection
