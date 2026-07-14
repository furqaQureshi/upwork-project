@php
    $galleryImages = $listing->images->pluck('url')->filter()->values();
    if ($galleryImages->isEmpty()) {
        $galleryImages = collect([$listing->main_image_url]);
    }

    $sellerPhone = trim((string) ($listing->user?->phone ?? ''));
    $sellerPhoneHref = preg_replace('/[^0-9\+]/', '', $sellerPhone);
    $sellerWhatsappNumber = preg_replace('/[^0-9]/', '', $sellerPhoneHref);
    $whatsappMessage = rawurlencode('Hi, I saw your listing "'.$listing->title.'". Is it still available?');
    $sellerWhatsappHref = $sellerWhatsappNumber !== '' ? 'https://wa.me/'.$sellerWhatsappNumber.'?text='.$whatsappMessage : '';

    $priceLabel = $listing->price_type === 'free' ? 'FREE' : '₹'.number_format((float) $listing->price);
    $locationLabel = collect([$listing->address, $listing->city, $listing->state])
        ->filter(fn ($value): bool => trim((string) $value) !== '')
        ->implode(', ');
    $locationLabel = $locationLabel !== '' ? $locationLabel : 'Location not specified';

    $postedLabel = $listing->published_at?->diffForHumans() ?? $listing->created_at->diffForHumans();
    $activeSince = ($listing->published_at ?? $listing->created_at)->format('d M Y');
    $isUrgent = $listing->published_at
        ? $listing->published_at->gt(now()->subDays(3))
        : $listing->created_at->gt(now()->subDays(3));

    $sellerAvatar = trim((string) ($listing->user?->avatar ?? ''));
    if ($sellerAvatar !== '' && ! str_starts_with($sellerAvatar, 'http://') && ! str_starts_with($sellerAvatar, 'https://')) {
        $sellerAvatar = \Illuminate\Support\Facades\Storage::url($sellerAvatar);
    }
    if ($sellerAvatar === '') {
        $sellerAvatar = 'https://ui-avatars.com/api/?name='.urlencode((string) $listing->user?->name).'&background=ea580c&color=ffffff';
    }

    $sellerMemberSince = $listing->user?->created_at?->format('Y') ?? 'N/A';
    $sellerLastSeen = $listing->user?->last_seen_at
        ? ($listing->user->last_seen_at->gt(now()->subMinutes(5)) ? 'Online now' : 'Online '.$listing->user->last_seen_at->diffForHumans())
        : 'Last seen not available';
    $sellerListingsCount = $listing->user?->listings()->count() ?? 0;
    $sellerResponseLabel = $sellerListingsCount >= 15 ? 'Very responsive' : ($sellerListingsCount >= 5 ? 'Responds quickly' : 'Response stats building');

    $offerMessage = 'Hi, my offer for "'.$listing->title.'" is '.($listing->price_type === 'free' ? 'FREE' : '₹'.number_format((float) $listing->price)).'. Is it negotiable?';

    $canReport = auth()->check() && ! $listing->isOwnedBy(auth()->user());
    $googleMapsApiKey = trim((string) setting('google_maps_api_key', ''));
    $mapQuery = $locationLabel !== '' ? $locationLabel : $listing->title;
    $mapIframeSrc = ($listing->latitude !== null && $listing->longitude !== null)
        ? 'https://www.google.com/maps?q='.urlencode($listing->latitude.','.$listing->longitude).'&z=15&output=embed'
        : 'https://www.google.com/maps?q='.urlencode($mapQuery).'&z=14&output=embed';
    $mapLink = ($listing->latitude !== null && $listing->longitude !== null)
        ? 'https://www.google.com/maps?q='.urlencode($listing->latitude.','.$listing->longitude)
        : 'https://www.google.com/maps?q='.urlencode($mapQuery);

    $canOpenGoogleMap = auth()->check()
        ? ($listing->isOwnedBy(auth()->user()) || $hasMapAccess)
        : false;

    $openMapUrl = auth()->check()
        ? ($canOpenGoogleMap ? route('listings.open-map', $listing) : route('subscriptions.index', ['feature' => 'call']))
        : route('login');

    $openMapLabel = auth()->check()
        ? ($canOpenGoogleMap ? 'Open in Google Maps' : 'Unlock Map Access')
        : 'Login to open map';

    preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_\-]{11})/', (string) $listing->youtube_url, $ytMatch);
    $ytId = $ytMatch[1] ?? null;
    $applicableVerification = $listing->user?->applicableSellerVerificationForCategory($listing->category);
    $applicablePackage = $applicableVerification?->subscriptionPackage;
@endphp

<x-app-layout>
    <div
        x-data="listingDetailPage({
            images: @js($galleryImages->values()),
            listingUrl: @js(request()->fullUrl()),
            mapLat: @js($listing->latitude !== null ? (float) $listing->latitude : null),
            mapLng: @js($listing->longitude !== null ? (float) $listing->longitude : null),
            mapApiKey: @js($googleMapsApiKey),
            homeUrl: @js(route('home')),
        })"
        x-init="init()"
        class="mx-auto max-w-6xl space-y-4 pb-28 md:pb-8"
    >
        <section class="relative overflow-hidden rounded-[1.35rem] border border-slate-200 bg-white shadow-md shadow-slate-200/60">
            <div class="relative bg-slate-950">
                <div class="absolute inset-x-0 top-0 z-20 flex items-center justify-between p-2.5 sm:p-3">
                    <button
                        type="button"
                        @click="goBack()"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm backdrop-blur"
                        aria-label="Back"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-2">
                        @auth
                            @if (! $listing->isOwnedBy(auth()->user()))
                                <form method="POST" action="{{ route('favorites.toggle', $listing) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm backdrop-blur" aria-label="Save ad">
                                        <x-heroicon name="heart" :variant="$isFavorited ? 'solid' : 'outline'" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm backdrop-blur" aria-label="Save ad">
                                <x-heroicon name="heart" class="h-4 w-4" />
                            </a>
                        @endauth

                        <button
                            type="button"
                            @click="shareListing()"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm backdrop-blur"
                            aria-label="Share"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12a3.75 3.75 0 1 0 3.75-3.75 3.75 3.75 0 0 0-3.75 3.75Zm0 0 6.6-4.95M8.25 12l6.6 4.95" />
                            </svg>
                        </button>

                        <div class="relative" x-data="{ open: false }">
                            <button
                                type="button"
                                @click="open = !open"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm backdrop-blur"
                                aria-label="More options"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01" />
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                @click.outside="open = false"
                                class="absolute right-0 top-11 z-30 w-40 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl"
                            >
                                <button type="button" @click="shareListing(); open = false" class="w-full rounded-xl px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100">Share listing</button>
                                @if ($canReport)
                                    <a href="#report-ad-section" @click="open = false" class="block w-full rounded-xl px-3 py-2 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50">Report ad</a>
                                @endif
                                @auth
                                    @if ($listing->isOwnedBy(auth()->user()))
                                        <a href="{{ route('listings.edit', $listing) }}" @click="open = false" class="block w-full rounded-xl px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100">Edit listing</a>
                                    @endif
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>

                <div class="absolute left-3 top-14 z-20 flex flex-wrap gap-2 sm:left-4 sm:top-16">
                    @if ($listing->is_featured)
                        <span class="inline-flex items-center rounded-full bg-amber-400 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-slate-900">Premium</span>
                    @endif
                    @if ($isUrgent)
                        <span class="inline-flex items-center rounded-full bg-rose-500 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-white">Urgent</span>
                    @endif
                </div>

                <div
                    class="relative h-[48vh] min-h-[260px] max-h-[520px] w-full"
                    @touchstart="onTouchStart($event)"
                    @touchend="onTouchEnd($event)"
                >
                    <img
                        :src="images[currentImageIndex]"
                        alt="{{ $listing->title }}"
                        class="h-full w-full object-cover"
                        @click="openLightbox(currentImageIndex)"
                    >

                    @if ($galleryImages->count() > 1)
                        <button type="button" @click="prevImage()" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-slate-950/55 p-1.5 text-white backdrop-blur" aria-label="Previous image">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6" />
                            </svg>
                        </button>
                        <button type="button" @click="nextImage()" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-slate-950/55 p-1.5 text-white backdrop-blur" aria-label="Next image">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                            </svg>
                        </button>
                    @endif

                    <div class="absolute bottom-2 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1 rounded-full bg-slate-900/45 px-2.5 py-1 backdrop-blur">
                        @foreach ($galleryImages as $image)
                            <button
                                type="button"
                                @click="setImage({{ $loop->index }})"
                                class="h-2 rounded-full transition-all"
                                :class="currentImageIndex === {{ $loop->index }} ? 'w-6 bg-white' : 'w-2 bg-white/60'"
                                aria-label="Image {{ $loop->iteration }}"
                            ></button>
                        @endforeach
                    </div>
                </div>
            </div>
            @if ($galleryImages->count() > 1)
                <div class="border-t border-slate-200/80 bg-white p-2.5">
                    <div class="flex gap-1.5 overflow-x-auto pb-1">
                        @foreach ($galleryImages as $image)
                            <button
                                type="button"
                                @click="setImage({{ $loop->index }})"
                                class="overflow-hidden rounded-lg border-2"
                                :class="currentImageIndex === {{ $loop->index }} ? 'border-orange-500' : 'border-transparent'"
                            >
                                <img src="{{ $image }}" alt="{{ $listing->title }}" class="h-12 w-16 object-cover sm:h-16 sm:w-20">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <article class="app-card relative overflow-hidden">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-orange-100/70 to-transparent"></div>
                    <div class="relative space-y-3.5">
                        <div class="flex flex-wrap items-start gap-2.5">
                            <h1 class="max-w-3xl font-display text-xl font-bold leading-tight text-slate-900 sm:text-2xl">{{ $listing->title }}</h1>
                        </div>

                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="space-y-1">
                                <p class="font-display text-3xl font-black leading-none {{ $listing->price_type === 'free' ? 'text-emerald-600' : 'text-orange-600' }}">{{ $priceLabel }}</p>
                                @if ($listing->price_type === 'negotiable')
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-blue-700">Negotiable</span>
                                @elseif ($listing->price_type === 'fixed')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">Fixed price</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-1.5 text-[10px] font-bold uppercase tracking-wide">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ ucfirst($listing->condition) }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $listing->category->display_name ?? $listing->category->name }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $listing->views }} views</span>
                                {{-- Car Seller Verification Badge --}}
                                <x-verified-car-seller-badge :user="$listing->user" :category="$listing->category" size="md" />
                                {{-- Fallback Generic Badge --}}
                                @if ($applicableVerification && ($applicablePackage?->seller_tier ?? '') !== 'car_verified')
                                    @php
                                        $genericBadgeLabel = trim((string) ($applicablePackage?->resolved_seller_badge_label ?? ''));
                                        if ($genericBadgeLabel === '') {
                                            $genericBadgeLabel = 'Verified';
                                        }
                                    @endphp
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-700">
                                        <x-heroicon name="check-badge" class="h-3.5 w-3.5" />
                                        {{ $genericBadgeLabel }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white/85 px-3 py-2.5">
                            <div class="grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                                <p class="inline-flex items-center gap-1.5">
                                    <x-heroicon name="map-pin" class="h-3.5 w-3.5 text-orange-500" />
                                    <span class="line-clamp-1">{{ $locationLabel }}</span>
                                </p>
                                <p>Posted {{ $postedLabel }}</p>
                                <p>Active since {{ $activeSince }}</p>
                            </div>
                        </div>
                    </div>
                </article>

                <section class="app-card space-y-2.5">
                    <h2 class="font-display text-lg font-bold text-slate-900">Description</h2>
                    <p class="whitespace-pre-line text-[13px] leading-6 text-slate-700">{{ $listing->description }}</p>
                </section>

                <section>
                    <x-google-ad location="article" />
                </section>

                <section class="app-card space-y-3.5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-bold text-slate-900">Details</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">Quick facts</span>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white/90">
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</dt>
                                <dd class="text-right font-semibold text-slate-800">{{ $listing->category->display_name ?? $listing->category->name }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Condition</dt>
                                <dd class="text-right font-semibold text-slate-800">{{ ucfirst($listing->condition) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">City</dt>
                                <dd class="text-right font-semibold text-slate-800">{{ $listing->city ?: 'Not specified' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">State</dt>
                                <dd class="text-right font-semibold text-slate-800">{{ $listing->state ?: 'Not specified' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Listing ID</dt>
                                <dd class="text-right font-semibold text-slate-800">#{{ $listing->id }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if ($customFieldValues->isNotEmpty())
                        <div class="space-y-2">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Category specifications</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($customFieldValues as $customValue)
                                    @php
                                        $customField = $customValue->customField;
                                        $displayValue = $customValue->value_text;
                                        if ($customField?->field_type === 'checkbox' && is_array($customValue->value_json)) {
                                            $displayValue = implode(', ', $customValue->value_json);
                                        }
                                    @endphp

                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-2.5">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $customField?->name }}</p>
                                        @if ($customField?->field_type === 'file' && $customValue->value_text)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::url($customValue->value_text) }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-xs font-semibold text-orange-600 underline">View file</a>
                                        @else
                                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $displayValue }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <section class="app-card space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-bold text-slate-900">Location</h2>
                        <a
                            href="{{ $openMapUrl }}"
                            @if ($canOpenGoogleMap)
                                target="_blank"
                                rel="noopener"
                            @endif
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide {{ $canOpenGoogleMap ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }}"
                        >
                            @if (! $canOpenGoogleMap)
                                <x-heroicon name="lock-closed" class="h-3.5 w-3.5" />
                            @endif
                            {{ $openMapLabel }}
                        </a>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        @if (! $canOpenGoogleMap)
                            <iframe src="{{ $mapIframeSrc }}" class="h-56 w-full border-0 pointer-events-none select-none" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            <div class="pointer-events-none absolute inset-0 bg-slate-900/10"></div>
                            <div class="pointer-events-none absolute left-0 top-0 h-16 w-56 bg-slate-50/95"></div>
                        @elseif ($listing->latitude !== null && $listing->longitude !== null && $googleMapsApiKey !== '')
                            <div x-ref="listingMap" class="h-56 w-full"></div>
                        @else
                            <iframe src="{{ $mapIframeSrc }}" class="h-56 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        @endif
                    </div>

                    <p class="text-xs text-slate-600">{{ $locationLabel }}</p>
                </section>
                    <section>
                        <x-google-ad location="article" />
                    </section>

                    @if ($ytId)
                    <section class="app-card space-y-2.5">
                        <h2 class="font-display text-lg font-bold text-slate-900">Video</h2>
                        <div class="overflow-hidden rounded-2xl border border-slate-200">
                            <div class="aspect-video">
                                <iframe
                                    src="https://www.youtube.com/embed/{{ $ytId }}"
                                    class="h-full w-full"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                    loading="lazy"
                                ></iframe>
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-3">
                <section class="app-card space-y-3.5 lg:sticky lg:top-20">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ $sellerAvatar }}" alt="{{ $listing->user?->name }}" class="h-12 w-12 rounded-2xl border border-white object-cover shadow">
                        <div class="min-w-0">
                            <p class="truncate font-display text-base font-bold text-slate-900">{{ $listing->user?->name }}</p>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Member since {{ $sellerMemberSince }}</p>
                        </div>
                    </div>

                    <div class="grid gap-1.5 rounded-2xl border border-slate-200 bg-slate-50 p-2.5 text-[11px] font-semibold text-slate-600">
                        <p>{{ $sellerLastSeen }}</p>
                        <p>{{ $sellerResponseLabel }}</p>
                        <p>{{ $sellerListingsCount }} active ads by seller</p>
                    </div>

                    @auth
                        @if (! $listing->isOwnedBy(auth()->user()))
                            <div class="space-y-2">
                                @if ($conversation)
                                    <a href="{{ route('chat.show', $conversation) }}" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 text-xs font-bold uppercase tracking-wide text-white shadow-md shadow-orange-200">Chat Now</a>
                                @else
                                    <form method="POST" action="{{ route('chat.from-listing', $listing) }}">
                                        @csrf
                                        <input type="hidden" name="message" value="Hi, I am interested in this listing.">
                                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 text-xs font-bold uppercase tracking-wide text-white shadow-md shadow-orange-200">Chat Now</button>
                                    </form>
                                @endif

                                <div class="grid grid-cols-2 gap-2">
                                    @if ($sellerPhoneHref !== '' && $hasCallAccess)
                                        <a href="{{ route('listings.start-call', $listing) }}" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-emerald-600 px-3 text-xs font-bold uppercase tracking-wide text-white">Call</a>
                                    @elseif ($sellerPhoneHref !== '')
                                        <a href="{{ route('subscriptions.index', ['feature' => 'call']) }}" class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-orange-200 bg-orange-50 px-3 text-xs font-bold uppercase tracking-wide text-orange-700">Unlock Call</a>
                                    @else
                                        <span class="inline-flex h-10 w-full cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-bold uppercase tracking-wide text-slate-400">No Phone</span>
                                    @endif

                                    @if ($sellerWhatsappHref !== '')
                                        <a href="{{ $sellerWhatsappHref }}" target="_blank" rel="noopener" class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 text-xs font-bold uppercase tracking-wide text-emerald-700">WhatsApp</a>
                                    @else
                                        <button type="button" @click="shareListing()" class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold uppercase tracking-wide text-slate-700">Share</button>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <form method="POST" action="{{ route('chat.from-listing', $listing) }}">
                                        @csrf
                                        <input type="hidden" name="message" value="{{ $offerMessage }}">
                                        <button type="submit" class="app-btn-muted inline-flex h-10 w-full items-center justify-center px-3 text-xs">Make Offer</button>
                                    </form>

                                    <form method="POST" action="{{ route('favorites.toggle', $listing) }}">
                                        @csrf
                                        <button type="submit" class="app-btn-muted inline-flex h-10 w-full items-center justify-center px-3 text-xs">{{ $isFavorited ? 'Saved' : 'Save ad' }}</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="space-y-2">
                                <a href="{{ route('listings.edit', $listing) }}" class="app-btn-primary inline-flex h-10 w-full items-center justify-center gap-2 px-3 text-xs">Edit Listing</a>
                                @if ($listing->status === 'approved')
                                    <a href="{{ route('payments.checkout', $listing) }}" class="app-btn-muted inline-flex h-10 w-full items-center justify-center px-3 text-xs">
                                        {{ $listing->is_featured && (! $listing->featured_until || $listing->featured_until->isFuture()) ? 'Extend Featured Plan' : 'Boost as Featured' }}
                                    </a>
                                @endif
                                @if ($listing->status !== 'sold')
                                    <form method="POST" action="{{ route('listings.mark-sold', $listing) }}">
                                        @csrf
                                        <button type="submit" class="app-btn-muted h-10 w-full px-3 text-xs">Mark as Sold</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @else
                        <div class="space-y-2">
                            <a href="{{ route('login') }}" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-orange-500 px-3 text-xs font-bold uppercase tracking-wide text-white shadow-md shadow-orange-200">Chat Now</a>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ route('login') }}" class="app-btn-muted inline-flex h-10 w-full items-center justify-center px-3 text-xs">Call</a>
                                <a href="{{ route('login') }}" class="app-btn-muted inline-flex h-10 w-full items-center justify-center px-3 text-xs">Save ad</a>
                            </div>
                        </div>
                    @endauth
                </section>
            </aside>
        </section>

        <section class="rounded-2xl border border-amber-200/70 bg-amber-50/80 p-3.5">
            <h3 class="font-display text-base font-bold text-amber-800">Safety tips</h3>
            <ul class="mt-2 space-y-1 text-xs text-amber-900">
                <li>Meet sellers in a public place.</li>
                <li>Verify product condition before payment.</li>
                <li>Avoid advance payment for unknown sellers.</li>
            </ul>
            @if ($canReport)
                <a href="#report-ad-section" class="mt-2.5 inline-flex text-[11px] font-bold uppercase tracking-wide text-rose-600">Report this ad</a>
            @endif
        </section>

        @auth
            @if ($canReport)
                <section id="report-ad-section" class="app-card space-y-2.5">
                    <h3 class="font-display text-base font-bold text-slate-900">Report this ad</h3>
                    <form method="POST" action="{{ route('listings.report', $listing) }}" class="space-y-2.5">
                        @csrf
                        <select name="reason" class="app-select" required>
                            <option value="">Select reason</option>
                            <option value="spam">Spam</option>
                            <option value="fake">Fake listing</option>
                            <option value="abusive">Abusive content</option>
                            <option value="wrong-category">Wrong category</option>
                            <option value="sold-elsewhere">Already sold</option>
                            <option value="other">Other</option>
                        </select>
                        <textarea name="details" class="app-textarea" placeholder="Optional details"></textarea>
                        <button type="submit" class="app-btn-muted h-10 w-full text-xs">Submit Report</button>
                    </form>
                </section>
            @endif
        @endauth

        <section>
            <x-google-ad location="display" />
        </section>

        @if ($similarListings->isNotEmpty())
            <section class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-bold text-slate-900">Similar / Recommended ads</h2>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Nearby picks</p>
                </div>

                <div class="flex gap-2.5 overflow-x-auto pb-1">
                    @foreach ($similarListings as $similar)
                        <div class="min-w-[210px] max-w-[210px] sm:min-w-[250px] sm:max-w-[250px] lg:min-w-[270px] lg:max-w-[270px]">
                            <x-listing-card :listing="$similar" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="fixed inset-x-0 z-40 px-3 md:hidden" style="bottom: calc(env(safe-area-inset-bottom) + 5.25rem);">
            <div class="mx-auto max-w-md rounded-2xl border border-slate-200/90 bg-white/95 p-1.5 shadow-[0_14px_34px_-20px_rgba(15,23,42,0.8)] backdrop-blur">
                @auth
                    @if (! $listing->isOwnedBy(auth()->user()))
                        <div class="grid grid-cols-4 gap-2">
                            @if ($conversation)
                                <a href="{{ route('chat.show', $conversation) }}" class="col-span-2 inline-flex h-10 items-center justify-center rounded-xl bg-orange-500 px-3 text-xs font-bold uppercase tracking-wide text-white">Chat Now</a>
                            @else
                                <form method="POST" action="{{ route('chat.from-listing', $listing) }}" class="col-span-2">
                                    @csrf
                                    <input type="hidden" name="message" value="Hi, I am interested in this listing.">
                                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-orange-500 px-3 text-xs font-bold uppercase tracking-wide text-white">Chat Now</button>
                                </form>
                            @endif

                            @if ($sellerPhoneHref !== '' && $hasCallAccess)
                                <a href="{{ route('listings.start-call', $listing) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-[11px] font-bold uppercase tracking-wide text-emerald-700">Call</a>
                            @elseif ($sellerPhoneHref !== '')
                                <a href="{{ route('subscriptions.index', ['feature' => 'call']) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-orange-200 bg-orange-50 text-[11px] font-bold uppercase tracking-wide text-orange-700">Unlock</a>
                            @else
                                <button type="button" @click="shareListing()" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-700">Share</button>
                            @endif

                            @if ($sellerWhatsappHref !== '')
                                <a href="{{ $sellerWhatsappHref }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 text-[11px] font-bold uppercase tracking-wide text-emerald-700">WhatsApp</a>
                            @else
                                <form method="POST" action="{{ route('favorites.toggle', $listing) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-700">Save</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <a href="{{ route('listings.edit', $listing) }}" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-orange-500 px-3 text-xs font-bold uppercase tracking-wide text-white">Edit Listing</a>
                    @endif
                @else
                    <div class="grid grid-cols-3 gap-2">
                        <a href="{{ route('login') }}" class="col-span-2 inline-flex h-10 items-center justify-center rounded-xl bg-orange-500 px-3 text-xs font-bold uppercase tracking-wide text-white">Chat Now</a>
                        <a href="{{ route('login') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-700">Call</a>
                    </div>
                @endauth
            </div>
        </div>

        <template x-teleport="body">
            <section x-show="lightboxOpen" x-cloak class="fixed inset-0 z-[130] bg-slate-950/95 p-3 sm:p-6" @keydown.escape.window="closeLightbox()">
                <div class="flex h-full flex-col gap-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-white/85">Image <span x-text="currentImageIndex + 1"></span> / <span x-text="images.length"></span></p>
                        <button type="button" @click="closeLightbox()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white" aria-label="Close">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="relative flex-1 overflow-hidden rounded-2xl">
                        <img :src="images[currentImageIndex]" alt="{{ $listing->title }}" class="h-full w-full object-contain">
                        <button type="button" @click="prevImage()" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-slate-900/60 p-2 text-white"> 
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6" /></svg>
                        </button>
                        <button type="button" @click="nextImage()" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-slate-900/60 p-2 text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" /></svg>
                        </button>
                    </div>
                </div>
            </section>
        </template>
    </div>

    <script>
        function listingDetailPage(config) {
            return {
                images: Array.isArray(config && config.images) ? config.images : [],
                listingUrl: (config && config.listingUrl) ? config.listingUrl : window.location.href,
                homeUrl: (config && config.homeUrl) ? config.homeUrl : '/',
                mapLat: (config && config.mapLat !== undefined) ? config.mapLat : null,
                mapLng: (config && config.mapLng !== undefined) ? config.mapLng : null,
                mapApiKey: (config && config.mapApiKey) ? config.mapApiKey : '',
                currentImageIndex: 0,
                lightboxOpen: false,
                touchStartX: null,
                mapScriptPromise: null,
                mapInstance: null,
                mapMarker: null,

                init() {
                    this.initMap();
                },

                goBack() {
                    if (window.history.length > 1) {
                        window.history.back();
                        return;
                    }

                    window.location.assign(this.homeUrl);
                },

                setImage(index) {
                    if (!Array.isArray(this.images) || this.images.length === 0) {
                        this.currentImageIndex = 0;
                        return;
                    }

                    const max = this.images.length - 1;
                    this.currentImageIndex = Math.max(0, Math.min(index, max));
                },

                nextImage() {
                    if (!Array.isArray(this.images) || this.images.length <= 1) {
                        return;
                    }

                    this.currentImageIndex = (this.currentImageIndex + 1) % this.images.length;
                },

                prevImage() {
                    if (!Array.isArray(this.images) || this.images.length <= 1) {
                        return;
                    }

                    this.currentImageIndex = (this.currentImageIndex - 1 + this.images.length) % this.images.length;
                },

                openLightbox(index) {
                    this.setImage(index);
                    this.lightboxOpen = true;
                },

                closeLightbox() {
                    this.lightboxOpen = false;
                },

                onTouchStart(event) {
                    if (!event.touches || event.touches.length === 0) {
                        this.touchStartX = null;
                        return;
                    }

                    this.touchStartX = event.touches[0].clientX;
                },

                onTouchEnd(event) {
                    if (this.touchStartX === null || !event.changedTouches || event.changedTouches.length === 0) {
                        this.touchStartX = null;
                        return;
                    }

                    const diff = event.changedTouches[0].clientX - this.touchStartX;
                    this.touchStartX = null;

                    if (Math.abs(diff) < 40) {
                        return;
                    }

                    if (diff < 0) {
                        this.nextImage();
                    } else {
                        this.prevImage();
                    }
                },

                async shareListing() {
                    const payload = {
                        title: document.title,
                        text: 'Check this listing',
                        url: this.listingUrl,
                    };

                    if (navigator.share) {
                        try {
                            await navigator.share(payload);
                            return;
                        } catch (_) {
                            // Fall back to clipboard.
                        }
                    }

                    try {
                        await navigator.clipboard.writeText(this.listingUrl);
                        window.alert('Listing link copied to clipboard.');
                    } catch (_) {
                        window.prompt('Copy this listing link', this.listingUrl);
                    }
                },

                async ensureMapScript() {
                    if (!this.mapApiKey) {
                        return false;
                    }

                    if (window.google && window.google.maps) {
                        return true;
                    }

                    if (this.mapScriptPromise) {
                        return this.mapScriptPromise;
                    }

                    this.mapScriptPromise = new Promise((resolve) => {
                        const existing = document.querySelector('script[data-listing-map-core]');
                        if (existing) {
                            if (window.google && window.google.maps) {
                                resolve(true);
                                return;
                            }

                            existing.addEventListener('load', () => resolve(!!(window.google && window.google.maps)), { once: true });
                            existing.addEventListener('error', () => resolve(false), { once: true });
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(this.mapApiKey);
                        script.async = true;
                        script.defer = true;
                        script.dataset.listingMapCore = '1';
                        script.onload = () => resolve(!!(window.google && window.google.maps));
                        script.onerror = () => resolve(false);
                        document.head.appendChild(script);
                    });

                    const loaded = await this.mapScriptPromise;
                    if (!loaded) {
                        this.mapScriptPromise = null;
                    }

                    return loaded;
                },

                async initMap() {
                    if (!this.$refs.listingMap) {
                        return;
                    }

                    if (this.mapLat === null || this.mapLng === null) {
                        return;
                    }

                    const ready = await this.ensureMapScript();
                    if (!ready || !window.google || !window.google.maps) {
                        return;
                    }

                    const center = {
                        lat: Number(this.mapLat),
                        lng: Number(this.mapLng),
                    };

                    if (!Number.isFinite(center.lat) || !Number.isFinite(center.lng)) {
                        return;
                    }

                    this.mapInstance = new window.google.maps.Map(this.$refs.listingMap, {
                        center,
                        zoom: 14,
                        mapTypeControl: false,
                        streetViewControl: false,
                    });

                    this.mapMarker = new window.google.maps.Marker({
                        map: this.mapInstance,
                        position: center,
                    });
                },
            };
        }
    </script>
</x-app-layout>
