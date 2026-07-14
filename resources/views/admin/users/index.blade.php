@extends('admin.layout')

@section('title', 'User Management')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 md:grid-cols-4">
            <div>
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                <input id="q" type="text" name="q" value="{{ request('q') }}" class="app-input" placeholder="Name, email, phone">
            </div>
            <div>
                <label for="role" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Role</label>
                <select id="role" name="role" class="app-select">
                    <option value="">All</option>
                    <option value="admin" @selected(request('role') === 'admin')>Admin</option>
                    <option value="user" @selected(request('role') === 'user')>User</option>
                </select>
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                <select id="status" name="status" class="app-select">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="blocked" @selected(request('status') === 'blocked')>Blocked</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="app-btn-primary">Apply</button>
                <a href="{{ route('admin.users.index') }}" class="app-btn-muted">Reset</a>
            </div>
        </form>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-display text-xl font-bold text-slate-900">Users & Push QA</h2>
                <p class="text-sm text-slate-500">Manage accounts, test device delivery, and send custom notifications.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.users.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    <i class="fas fa-user-plus"></i> Create User
                </a>
                <a href="{{ route('admin.users.export', request()->query()) }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="{{ route('admin.push-notifications.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create Custom Push</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">User</th>
                        <th class="px-3 py-2">Listings</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $user->listings_count }}</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $user->is_admin ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $user->is_admin ? 'Admin' : 'User' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $user->is_blocked ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $user->is_blocked ? 'Blocked' : 'Active' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if (! $user->is_admin && $user->listings_count > 0)
                                        <a href="{{ route('admin.sellers.show', $user) }}" class="rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">
                                            Seller Profile
                                        </a>
                                    @endif

                                    <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ $user->is_admin ? 'Remove Admin' : 'Make Admin' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.toggle-block', $user) }}">
                                        @csrf
                                        <button type="submit" class="rounded-xl {{ $user->is_blocked ? 'bg-emerald-600' : 'bg-rose-600' }} px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ $user->is_blocked ? 'Unblock' : 'Block' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.test-push', $user) }}">
                                        @csrf
                                        <button type="submit" title="Send a test push notification to this user's devices" class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                            Test Push
                                        </button>
                                    </form>

                                    <a href="{{ route('admin.push-notifications.create', ['user' => $user->id]) }}" class="rounded-xl bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                                        Custom Push
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-600">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </section>
@endsection
