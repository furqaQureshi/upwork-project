@props(['user', 'category' => null, 'size' => 'sm'])

@php
    $verification = $user ? $user->applicableSellerVerificationForCategory($category) : null;
    $package = $verification?->subscriptionPackage;
@endphp

@if($verification && ($package?->seller_tier ?? '') === 'car_verified')
    @php
        $sizeClasses = match($size) {
            'lg' => 'gap-1.5 px-3 py-1.5 text-sm',
            'md' => 'gap-1 px-2.5 py-1 text-xs',
            'sm' => 'gap-0.5 px-2 py-0.5 text-[10px]',
            'xs' => 'gap-0.5 px-1.5 py-0.5 text-[9px]',
            default => 'gap-0.5 px-2 py-0.5 text-[10px]'
        };

        $iconSize = match($size) {
            'lg' => 'h-5 w-5',
            'md' => 'h-4 w-4',
            'sm' => 'h-3.5 w-3.5',
            'xs' => 'h-3 w-3',
            default => 'h-3.5 w-3.5'
        };

        $tier = (string) ($package?->seller_tier ?? '');
        $badgeColors = match($tier) {
            'premium_verified' => 'bg-amber-100 text-amber-700',
            'car_verified' => 'bg-blue-100 text-blue-700',
            default => 'bg-emerald-100 text-emerald-700'
        };
        $badgeLabel = trim((string) ($package?->resolved_seller_badge_label ?? ''));
        if ($badgeLabel === '') {
            $badgeLabel = 'Verified';
        }
    @endphp

    <span class="inline-flex items-center font-bold uppercase tracking-wide rounded-full {{ $badgeColors }} {{ $sizeClasses }}" 
          title="Verified on {{ $verification?->verified_at?->format('M d, Y') }}"
          @if($size === 'lg') data-bs-toggle="tooltip" @endif>
        <x-heroicon name="check-badge" class="{{ $iconSize }}" />
        <span>{{ $badgeLabel }}</span>
    </span>
@endif
