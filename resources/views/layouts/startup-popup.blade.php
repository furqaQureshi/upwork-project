@php
    $startupPopupEnabled = (bool) setting('startup_popup_enabled', false);
    $startupPopupTitle = trim((string) setting('startup_popup_title', ''));
    $startupPopupMessage = trim((string) setting('startup_popup_message', ''));
    $startupPopupButtonLabel = trim((string) setting('startup_popup_button_label', ''));
    $startupPopupLinkUrl = trim((string) setting('startup_popup_link_url', ''));
    $startupPopupOpenNewTab = (bool) setting('startup_popup_open_new_tab', false);

    if (
        $startupPopupLinkUrl !== ''
        && preg_match('/^https?:\/\//i', $startupPopupLinkUrl) !== 1
        && ! str_starts_with($startupPopupLinkUrl, '/')
    ) {
        $startupPopupLinkUrl = '/'.ltrim($startupPopupLinkUrl, '/');
    }

    $startupPopupImageRaw = trim((string) setting('startup_popup_image_url', ''));
    $startupPopupImageUrl = '';
    if ($startupPopupImageRaw !== '') {
        $startupPopupImageUrl = preg_match('/^https?:\/\//i', $startupPopupImageRaw) === 1
            ? $startupPopupImageRaw
            : \Illuminate\Support\Facades\Storage::url($startupPopupImageRaw);
    }

    $startupPopupStyle = strtolower(trim((string) setting('startup_popup_style', 'premium')));
    if (! in_array($startupPopupStyle, ['minimal', 'premium', 'festive'], true)) {
        $startupPopupStyle = 'premium';
    }

    $startupPopupOverlayClass = match ($startupPopupStyle) {
        'minimal' => 'fixed inset-0 z-[140] overflow-y-auto bg-slate-950/65 px-4 py-6 backdrop-blur-[2px] sm:px-6 sm:py-10',
        'festive' => 'fixed inset-0 z-[140] overflow-y-auto bg-rose-950/65 px-4 py-6 backdrop-blur-sm sm:px-6 sm:py-10',
        default => 'fixed inset-0 z-[140] overflow-y-auto bg-slate-950/70 px-4 py-6 backdrop-blur-sm sm:px-6 sm:py-10',
    };
    $startupPopupCardClass = match ($startupPopupStyle) {
        'minimal' => 'w-full max-w-lg overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-2xl shadow-slate-900/25',
        'festive' => 'w-full max-w-xl overflow-hidden rounded-[2rem] border border-rose-200 bg-white shadow-[0_30px_90px_-30px_rgba(190,24,93,0.55)]',
        default => 'w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/20 bg-white shadow-[0_30px_90px_-30px_rgba(15,23,42,0.85)]',
    };
    $startupPopupTopGlowClass = match ($startupPopupStyle) {
        'minimal' => 'hidden',
        'festive' => 'pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-r from-rose-500/30 via-amber-400/25 to-orange-500/30',
        default => 'pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-r from-orange-500/20 via-amber-400/20 to-rose-500/20',
    };
    $startupPopupCloseClass = match ($startupPopupStyle) {
        'minimal' => 'absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:text-slate-700',
        'festive' => 'absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-rose-200 bg-white/95 text-rose-500 shadow-lg shadow-rose-900/10 transition hover:bg-white hover:text-rose-700',
        default => 'absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/70 bg-white/90 text-slate-500 shadow-lg shadow-slate-900/10 transition hover:bg-white hover:text-slate-700',
    };
    $startupPopupImageClass = match ($startupPopupStyle) {
        'minimal' => 'h-56 w-full object-cover sm:h-64',
        'festive' => 'h-72 w-full object-cover sm:h-80',
        default => 'h-64 w-full object-cover sm:h-72',
    };
    $startupPopupImageHoverClass = $startupPopupStyle === 'minimal' ? '' : ' transition duration-500 group-hover:scale-[1.03]';
    $startupPopupImageOverlayClass = match ($startupPopupStyle) {
        'minimal' => 'hidden',
        'festive' => 'pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-rose-950/45 to-transparent',
        default => 'pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-slate-950/35 to-transparent',
    };
    $startupPopupFallbackStripClass = match ($startupPopupStyle) {
        'minimal' => 'h-14 bg-slate-100',
        'festive' => 'h-20 bg-gradient-to-r from-rose-500 via-orange-400 to-amber-300',
        default => 'h-20 bg-gradient-to-r from-orange-400 via-amber-300 to-rose-400',
    };
    $startupPopupContentClass = match ($startupPopupStyle) {
        'minimal' => 'relative space-y-4 px-5 pb-5 pt-5 sm:px-6 sm:pb-6',
        'festive' => 'relative space-y-5 px-5 pb-6 pt-5 sm:px-7 sm:pb-7',
        default => 'relative space-y-5 px-5 pb-6 pt-5 sm:px-7 sm:pb-7',
    };
    $startupPopupBadgeLabel = match ($startupPopupStyle) {
        'premium' => 'Featured campaign',
        'festive' => 'Festive spotlight',
        default => '',
    };
    $startupPopupBadgeClass = match ($startupPopupStyle) {
        'festive' => 'inline-flex items-center rounded-full bg-gradient-to-r from-rose-500 to-orange-400 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white',
        default => 'inline-flex items-center rounded-full bg-orange-100 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-orange-700',
    };
    $startupPopupTitleClass = match ($startupPopupStyle) {
        'minimal' => 'font-display text-2xl font-bold leading-tight text-slate-900',
        'festive' => 'font-display text-3xl font-extrabold leading-tight tracking-tight text-rose-900',
        default => 'font-display text-3xl font-bold leading-tight tracking-tight text-slate-900',
    };
    $startupPopupMessageClass = match ($startupPopupStyle) {
        'minimal' => 'text-sm leading-6 text-slate-600',
        'festive' => 'text-[15px] leading-7 text-rose-900/80',
        default => 'text-[15px] leading-7 text-slate-600',
    };
    $startupPopupCtaClass = match ($startupPopupStyle) {
        'minimal' => 'inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-slate-800',
        'festive' => 'inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-rose-500 via-orange-500 to-amber-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-orange-300/50 transition hover:from-rose-600 hover:via-orange-600 hover:to-amber-600',
        default => 'inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-orange-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-orange-300/50 transition hover:from-orange-600 hover:to-rose-600',
    };
    $startupPopupCloseButtonClass = match ($startupPopupStyle) {
        'minimal' => 'inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800',
        'festive' => 'inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-white px-5 py-3 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:text-rose-900',
        default => 'inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800',
    };
    $startupPopupCardTransitionEnter = match ($startupPopupStyle) {
        'minimal' => 'transform ease-out duration-250',
        'festive' => 'transform ease-out duration-500',
        default => 'transform ease-out duration-380',
    };
    $startupPopupCardTransitionEnterStart = match ($startupPopupStyle) {
        'minimal' => 'opacity-0 translate-y-2 scale-[0.99]',
        'festive' => 'opacity-0 translate-y-6 rotate-[1.5deg] scale-90',
        default => 'opacity-0 translate-y-4 scale-95',
    };
    $startupPopupCardTransitionEnterEnd = match ($startupPopupStyle) {
        'minimal' => 'opacity-100 translate-y-0 scale-100',
        'festive' => 'opacity-100 translate-y-0 rotate-0 scale-100',
        default => 'opacity-100 translate-y-0 scale-100',
    };
    $startupPopupCardTransitionLeave = match ($startupPopupStyle) {
        'minimal' => 'transform ease-in duration-180',
        'festive' => 'transform ease-in duration-260',
        default => 'transform ease-in duration-220',
    };
    $startupPopupCardTransitionLeaveStart = match ($startupPopupStyle) {
        'minimal' => 'opacity-100 translate-y-0 scale-100',
        'festive' => 'opacity-100 translate-y-0 rotate-0 scale-100',
        default => 'opacity-100 translate-y-0 scale-100',
    };
    $startupPopupCardTransitionLeaveEnd = match ($startupPopupStyle) {
        'minimal' => 'opacity-0 translate-y-1 scale-[0.99]',
        'festive' => 'opacity-0 translate-y-4 rotate-[1deg] scale-95',
        default => 'opacity-0 translate-y-2 scale-95',
    };

    $startupPopupHasContent = $startupPopupTitle !== '' || $startupPopupMessage !== '' || $startupPopupImageUrl !== '';
    $startupPopupFingerprint = sha1((string) json_encode([
        'title' => $startupPopupTitle,
        'message' => $startupPopupMessage,
        'image' => $startupPopupImageUrl,
        'link' => $startupPopupLinkUrl,
        'button' => $startupPopupButtonLabel,
        'style' => $startupPopupStyle,
        'new_tab' => $startupPopupOpenNewTab,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
@endphp

@if ($startupPopupEnabled && $startupPopupHasContent)
    <div
        data-startup-popup-root
        data-startup-popup-style="{{ $startupPopupStyle }}"
        x-data="startupPopupModal({
            enabled: @js($startupPopupEnabled),
            title: @js($startupPopupTitle),
            message: @js($startupPopupMessage),
            imageUrl: @js($startupPopupImageUrl),
            linkUrl: @js($startupPopupLinkUrl),
            buttonLabel: @js($startupPopupButtonLabel),
            style: @js($startupPopupStyle),
            openNewTab: @js($startupPopupOpenNewTab),
            fingerprint: @js($startupPopupFingerprint),
        })"
        x-init="init()"
    >
        <template x-teleport="body">
            <section
                x-show="open"
                x-cloak
                x-transition.opacity
                class="{{ $startupPopupOverlayClass }}"
            >
                <div class="flex min-h-full items-center justify-center">
                    <div
                        x-transition:enter="{{ $startupPopupCardTransitionEnter }}"
                        x-transition:enter-start="{{ $startupPopupCardTransitionEnterStart }}"
                        x-transition:enter-end="{{ $startupPopupCardTransitionEnterEnd }}"
                        x-transition:leave="{{ $startupPopupCardTransitionLeave }}"
                        x-transition:leave-start="{{ $startupPopupCardTransitionLeaveStart }}"
                        x-transition:leave-end="{{ $startupPopupCardTransitionLeaveEnd }}"
                        class="{{ $startupPopupCardClass }}"
                    >
                        <div class="relative">
                            <div class="{{ $startupPopupTopGlowClass }}"></div>

                            <button
                                type="button"
                                @click="dismiss()"
                                class="{{ $startupPopupCloseClass }}"
                            >
                                <span class="sr-only">Close popup</span>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>

                            @if ($startupPopupImageUrl !== '')
                                <div class="relative overflow-hidden">
                                    @if ($startupPopupLinkUrl !== '')
                                        <a
                                            href="{{ $startupPopupLinkUrl }}"
                                            x-bind:href="linkUrl || '#'"
                                            x-bind:target="openNewTab ? '_blank' : null"
                                            x-bind:rel="openNewTab ? 'noopener noreferrer' : null"
                                            @click="rememberSeen()"
                                            class="group block"
                                        >
                                            <img src="{{ $startupPopupImageUrl }}" x-bind:src="imageUrl" alt="Startup popup image" class="{{ $startupPopupImageClass }}{{ $startupPopupImageHoverClass }}">
                                        </a>
                                    @else
                                        <img src="{{ $startupPopupImageUrl }}" x-bind:src="imageUrl" alt="Startup popup image" class="{{ $startupPopupImageClass }}">
                                    @endif

                                    <div class="{{ $startupPopupImageOverlayClass }}"></div>
                                </div>
                            @else
                                <div class="{{ $startupPopupFallbackStripClass }}"></div>
                            @endif

                            <div class="{{ $startupPopupContentClass }}">
                                @if ($startupPopupBadgeLabel !== '')
                                    <span class="{{ $startupPopupBadgeClass }}">{{ $startupPopupBadgeLabel }}</span>
                                @endif

                                @if ($startupPopupTitle !== '')
                                    <h2 class="{{ $startupPopupTitleClass }}" x-text="title">{{ $startupPopupTitle }}</h2>
                                @endif

                                @if ($startupPopupMessage !== '')
                                    <p class="{{ $startupPopupMessageClass }}" x-text="message">{{ $startupPopupMessage }}</p>
                                @endif

                                <div class="flex flex-wrap gap-3 pt-1">
                                    @if ($startupPopupLinkUrl !== '')
                                        <a
                                            href="{{ $startupPopupLinkUrl }}"
                                            x-bind:href="linkUrl || '#'"
                                            x-bind:target="openNewTab ? '_blank' : null"
                                            x-bind:rel="openNewTab ? 'noopener noreferrer' : null"
                                            @click="rememberSeen()"
                                            class="{{ $startupPopupCtaClass }}"
                                        >{{ $startupPopupButtonLabel !== '' ? $startupPopupButtonLabel : 'Open offer' }}</a>
                                    @endif

                                    <button type="button" @click="dismiss()" class="{{ $startupPopupCloseButtonClass }}">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>

    <script>
        if (typeof window.startupPopupModal !== 'function') {
            window.startupPopupModal = function (config) {
                return {
                    open: false,
                    enabled: !!config.enabled,
                    title: String(config.title || ''),
                    message: String(config.message || ''),
                    imageUrl: String(config.imageUrl || ''),
                    linkUrl: String(config.linkUrl || ''),
                    buttonLabel: String(config.buttonLabel || ''),
                    style: String(config.style || 'premium'),
                    openNewTab: !!config.openNewTab,
                    fingerprint: String(config.fingerprint || ''),
                    storageKey: 'unsell_startup_popup_seen',

                    init() {
                        if (!this.shouldOpen()) {
                            return;
                        }

                        window.setTimeout(() => {
                            this.open = true;
                        }, 250);
                    },

                    shouldOpen() {
                        if (!this.enabled || this.fingerprint === '') {
                            return false;
                        }

                        if (this.title === '' && this.message === '' && this.imageUrl === '') {
                            return false;
                        }

                        try {
                            return window.localStorage.getItem(this.storageKey) !== this.fingerprint;
                        } catch (_) {
                            return true;
                        }
                    },

                    rememberSeen() {
                        try {
                            window.localStorage.setItem(this.storageKey, this.fingerprint);
                        } catch (_) {
                        }
                    },

                    dismiss() {
                        this.rememberSeen();
                        this.open = false;
                    },
                };
            };
        }
    </script>
@endif
