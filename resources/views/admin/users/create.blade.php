@extends('admin.layout')

@section('title', 'Create User')

@section('content')
    <section class="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-6">
            <h2 class="font-display text-2xl font-bold text-slate-900">Create New User</h2>
            <p class="mt-1 text-sm text-slate-600">Add a new user account to the system manually.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
                <h3 class="font-semibold text-rose-900">Please fix the following errors:</h3>
                <ul class="mt-2 list-inside space-y-1 text-sm text-rose-800">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <!-- Name -->
                <div>
                    <label for="name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        Full Name *
                    </label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        class="app-input"
                        placeholder="User's full name"
                        required
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        Email Address *
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="app-input"
                        placeholder="user@example.com"
                        required
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        Phone Number
                    </label>
                    <input
                        id="phone"
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        class="app-input"
                        placeholder="+91 9876543210"
                    >
                    @error('phone')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- City -->
                <div>
                    <label for="city" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        City
                    </label>
                    <input
                        id="city"
                        type="text"
                        name="city"
                        value="{{ old('city') }}"
                        class="app-input"
                        placeholder="Mumbai"
                    >
                    @error('city')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- State -->
                <div>
                    <label for="state" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        State
                    </label>
                    <input
                        id="state"
                        type="text"
                        name="state"
                        value="{{ old('state') }}"
                        class="app-input"
                        placeholder="Maharashtra"
                    >
                    @error('state')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        Password *
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="app-input"
                        placeholder="Minimum 8 characters"
                        required
                    >
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Confirmation -->
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">
                        Confirm Password *
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        class="app-input"
                        placeholder="Re-enter password"
                        required
                    >
                    @error('password_confirmation')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Admin Checkbox -->
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input
                            type="checkbox"
                            name="is_admin"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            {{ old('is_admin') ? 'checked' : '' }}
                        >
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Make this user an Administrator</p>
                            <p class="text-xs text-slate-500">Admins can moderate content, manage users, and access all admin features</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3 pt-4">
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 font-semibold text-white hover:bg-emerald-700">
                    <i class="fas fa-check"></i> Create User
                </button>
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 bg-white px-6 py-2.5 font-semibold text-slate-900 hover:bg-slate-50">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
