@props([
    'listing',
    'isFavorited' => false,
    'showFavoriteOverlay' => false,
])

@php
    $viewer = auth()->user();
    $ownsListing = $listing->isOwnedBy($viewer);
    $imageCount = $listing->relationLoaded('images') ? $listing->images->count() : $listing->images()->count();
    $applicableVerification = $listing->user?->applicableSellerVerificationForCategory($listing->category);
    $applicablePackage = $applicableVerification?->subscriptionPackage;
@endphp

<article class="listing-card relative flex h-full flex-col overflow-hidden">
    @if ($showFavoriteOverlay && ! $ownsListing)
        @auth
            <form method="POST" action="{{ route('favorites.toggle', $listing) }}" class="absolute right-3 top-3 z-10">
                @csrf
                <button
                    type="submit"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/70 shadow-lg backdrop-blur {{ $isFavorited ? 'bg-rose-500 text-white shadow-rose-500/30' : 'bg-white/90 text-slate-700 shadow-slate-900/10' }}"
                    aria-label="{{ $isFavorited ? 'Remove saved listing' : 'Save listing' }}"
                >
                    <x-heroicon name="heart" :variant="$isFavorited ? 'solid' : 'outline'" class="h-5 w-5" />
                </button>
            </form>
        @else
            <a
                href="{{ route('login') }}"
                class="absolute right-3 top-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/70 bg-white/90 text-slate-700 shadow-lg shadow-slate-900/10 backdrop-blur"
                aria-label="Log in to save listing"
            >
                <x-heroicon name="heart" class="h-5 w-5" />
            </a>
        @endauth
    @endif

    <a href="{{ route('listings.show', $listing) }}" class="block flex-1">
        <div class="relative h-36 overflow-hidden bg-slate-100 sm:h-44 xl:h-48">
            <img
                src="{{ $listing->main_image_url }}"
                alt="{{ $listing->title }}"
                class="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
                loading="lazy"
            >
            @if ($listing->is_featured && (! $listing->featured_until || $listing->featured_until->isFuture()))
                <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-orange-500 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                    <x-heroicon name="tag" class="h-3.5 w-3.5" />
                    Featured
                </span>
            @endif

            @if ($imageCount > 1)
                <span class="absolute bottom-3 right-3 inline-flex items-center gap-1 rounded-full bg-slate-900/75 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                    <x-heroicon name="photo" class="h-3.5 w-3.5" />
                    {{ $imageCount }} photos
                </span>
            @endif
        </div>

        <div class="space-y-2 p-2.5 sm:space-y-2.5 sm:p-3">
            <div class="flex flex-wrap items-center gap-1.5">
                @if (($listing->price_type ?? 'fixed') === 'free')
                    <p class="font-display text-xl font-bold text-emerald-600">FREE</p>
                @else
                    <p class="font-display text-xl font-bold text-orange-600">₹{{ number_format((float) $listing->price) }}</p>
                @endif

                @if (($listing->price_type ?? 'fixed') === 'negotiable')
                    <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700">Negotiable</span>
                @endif

                {{-- Show Car Seller Verification Badge --}}
                <x-verified-car-seller-badge :user="$listing->user" :category="$listing->category" size="sm" />

                {{-- Fallback Generic Badge --}}
                @if ($applicableVerification && ($applicablePackage?->seller_tier ?? '') !== 'car_verified')
                    @php
                        $genericBadgeLabel = trim((string) ($applicablePackage?->resolved_seller_badge_label ?? ''));
                        if ($genericBadgeLabel === '') {
                            $genericBadgeLabel = 'Verified';
                        }
                    @endphp
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                        <x-heroicon name="check-badge" class="h-3.5 w-3.5" />
                        {{ $genericBadgeLabel }}
                    </span>
                @endif
            </div>

            <h3 class="line-clamp-2 min-h-[2.5rem] font-display text-[14px] font-bold leading-snug text-slate-900 sm:text-[15px]">
                {{ $listing->title }}
            </h3>

            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $listing->condition }}</p>

            <div class="flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500 sm:text-xs">
                <span class="inline-flex min-w-0 items-center gap-1 truncate">
                    <x-heroicon name="map-pin" class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">{{ $listing->city }}{{ $listing->state ? ', '.$listing->state : '' }}</span>
                </span>
                <span class="shrink-0">{{ $listing->published_at?->diffForHumans() ?? $listing->created_at->diffForHumans() }}</span>
            </div>
        </div>
    </a>

    @isset($actions)
        <div class="border-t border-slate-100 p-3">
            {{ $actions }}
        </div>
    @endisset
</article>
