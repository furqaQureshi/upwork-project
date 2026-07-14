<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="font-display text-2xl font-bold text-slate-900">Menu</h1>
            <p class="text-sm text-slate-600">Quick access to your account and app pages</p>
        </div>
    </x-slot>

    <div class="space-y-4">
        @auth
            <section class="app-card space-y-2">
                <a href="{{ route('profile.edit') }}" class="app-btn-muted w-full justify-start">Profile Details</a>
                <a href="{{ route('listings.index') }}" class="app-btn-muted w-full justify-start">My Listings</a>
                <a href="{{ route('subscriptions.index') }}" class="app-btn-muted w-full justify-start">Packages</a>
                <a href="{{ route('chat.index') }}" class="app-btn-muted w-full justify-start">
                    Messages
                    @if ($unreadNotifications > 0)
                        <span class="ml-2 rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-bold text-white">
                            {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                        </span>
                    @endif
                </a>
                @if ((bool) setting('ai_enabled', false) && (bool) setting('ai_compass_enabled', true))
                    <a href="{{ route('ai.compass') }}" class="app-btn-muted w-full justify-start">AI Assistant</a>
                @endif
                @if ((bool) setting('ai_enabled', false) && (bool) setting('ai_autoiq_enabled', true))
                    <a href="{{ route('ai.autoiq') }}" class="app-btn-muted w-full justify-start">AutoIQ Dashboard</a>
                @endif
                @if ((bool) setting('ai_enabled', false) && (bool) setting('ai_job_matching_enabled', true))
                    <a href="{{ route('ai.navigator') }}" class="app-btn-muted w-full justify-start">AI Navigator</a>
                @endif
                @if (auth()->user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}" class="app-btn-muted w-full justify-start">Admin Panel</a>
                @endif
            </section>

            <section class="app-card">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white">
                        Logout
                    </button>
                </form>
            </section>
        @else
            <section class="app-card space-y-2">
                <a href="{{ route('login') }}" class="app-btn-muted w-full">Log in</a>
                <a href="{{ route('register') }}" class="app-btn-primary w-full">Create Account</a>
            </section>
        @endauth

        <section class="app-card space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Legal</p>
            <a href="{{ route('legal.terms') }}" class="app-btn-muted w-full justify-start">Terms and Conditions</a>
            <a href="{{ route('legal.privacy') }}" class="app-btn-muted w-full justify-start">Privacy Policy</a>
            <a href="{{ route('legal.refund') }}" class="app-btn-muted w-full justify-start">Refund and Cancellation Policy</a>
            <a href="{{ route('legal.content-policy') }}" class="app-btn-muted w-full justify-start">Content Policy</a>
            <a href="{{ route('legal.data-deletion') }}" class="app-btn-muted w-full justify-start">Account and Data Deletion</a>
        </section>
    </div>
</x-app-layout>
