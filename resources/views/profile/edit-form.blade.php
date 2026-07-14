<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Edit Profile</h2>
                <p class="text-sm text-slate-600">Update your personal information, verification details, and security settings.</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="app-btn-muted">Back to Profile Details</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4 pb-[calc(env(safe-area-inset-bottom)+5.75rem)] md:pb-0">
        <section class="app-card">
            @include('profile.partials.update-profile-information-form')
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="app-card">
                @include('profile.partials.update-password-form')
            </section>

            <section class="app-card" x-data="soundPrefs()">
                <h3 class="font-display text-base font-semibold text-slate-900">Notification Preferences</h3>
                <p class="mt-1 text-sm text-slate-500">Control in-app alerts for this device.</p>

                <div class="mt-4 flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Message sound</p>
                        <p class="text-xs text-slate-500">Play a short chime when a new message arrives</p>
                    </div>
                    <button
                        type="button"
                        @click="toggle()"
                        :aria-checked="enabled ? 'true' : 'false'"
                        role="switch"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                        :class="enabled ? 'bg-orange-500' : 'bg-slate-300'"
                    >
                        <span
                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                        ></span>
                    </button>
                </div>

                <p x-show="saved" x-transition class="mt-2 text-xs font-semibold text-emerald-600">Preference saved.</p>
            </section>
        </div>

        <section class="app-card">
            @include('profile.partials.delete-user-form')
        </section>
    </div>

    <script>
        function soundPrefs() {
            return {
                enabled: localStorage.getItem('unsell_sound_enabled') !== 'false',
                saved: false,
                toggle() {
                    this.enabled = !this.enabled;
                    localStorage.setItem('unsell_sound_enabled', this.enabled ? 'true' : 'false');
                    if (typeof window.unisellSetSoundEnabled === 'function') {
                        window.unisellSetSoundEnabled(this.enabled);
                    }
                    this.saved = true;
                    setTimeout(() => { this.saved = false; }, 1800);
                },
            };
        }
    </script>
</x-app-layout>
