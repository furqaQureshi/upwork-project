@extends('admin.layout')

@section('title', 'Custom Push Notification')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Compose Custom Push</h2>
                <p class="mt-1 text-sm text-slate-600">Send a custom notification to marketplace users, sellers, admins, or one selected user.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="app-btn-muted">Back to Users</a>
        </div>

        <form method="POST" action="{{ route('admin.push-notifications.store') }}" class="mt-6 grid gap-4 lg:grid-cols-2">
            @csrf

            <div>
                <label for="audience" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Audience</label>
                <select id="audience" name="audience" class="app-select" onchange="document.getElementById('specific-user-wrap').style.display = this.value === 'specific_user' ? 'block' : 'none';">
                    <option value="all_users" @selected(old('audience', request('user') ? 'specific_user' : 'all_users') === 'all_users')>All Users</option>
                    <option value="sellers" @selected(old('audience') === 'sellers')>All Sellers</option>
                    <option value="admins" @selected(old('audience') === 'admins')>All Admins</option>
                    <option value="specific_user" @selected(old('audience', request('user') ? 'specific_user' : '') === 'specific_user')>Specific User</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('audience')" />
            </div>

            <div id="specific-user-wrap" style="display: {{ old('audience', request('user') ? 'specific_user' : '') === 'specific_user' ? 'block' : 'none' }};">
                <label for="user_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Select User</label>
                <select id="user_id" name="user_id" class="app-select">
                    <option value="">Choose a user</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('user_id', request('user')) === $user->id)>
                            {{ $user->name }} • {{ $user->email }} • {{ $user->active_push_devices_count }} push device(s)
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
            </div>

            <div class="lg:col-span-2">
                <label for="title" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Title</label>
                <input id="title" name="title" type="text" class="app-input" value="{{ old('title') }}" maxlength="80" placeholder="e.g. Weekend deal alert" required>
                <x-input-error class="mt-2" :messages="$errors->get('title')" />
            </div>

            <div class="lg:col-span-2">
                <label for="body" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Message</label>
                <textarea id="body" name="body" class="app-textarea" rows="5" maxlength="240" placeholder="Write the notification users should receive." required>{{ old('body') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('body')" />
            </div>

            <div class="lg:col-span-2">
                <label for="url" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Open URL (Optional)</label>
                <input id="url" name="url" type="text" class="app-input" value="{{ old('url', '/notifications') }}" maxlength="500" placeholder="/notifications or https://example.com/page">
                <p class="mt-1 text-xs text-slate-500">Relative paths open inside the app. External URLs are allowed.</p>
                <x-input-error class="mt-2" :messages="$errors->get('url')" />
            </div>

            <div class="lg:col-span-2 flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Send Custom Push</button>
                <a href="{{ route('admin.users.index') }}" class="app-btn-muted">Cancel</a>
            </div>
        </form>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recent Users Loaded</p>
            <p class="mt-1 font-display text-3xl font-bold text-slate-900">{{ $users->count() }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Users With Push</p>
            <p class="mt-1 font-display text-3xl font-bold text-indigo-600">{{ $users->where('active_push_devices_count', '>', 0)->count() }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sellers In List</p>
            <p class="mt-1 font-display text-3xl font-bold text-orange-600">{{ $users->where('listings_count', '>', 0)->count() }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Admins In List</p>
            <p class="mt-1 font-display text-3xl font-bold text-slate-800">{{ $users->where('is_admin', true)->count() }}</p>
        </article>
    </section>
@endsection
