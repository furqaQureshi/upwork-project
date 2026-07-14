@props(['user', 'category' => null, 'class' => ''])

@php
    $verification = $user ? $user->applicableSellerVerificationForCategory($category) : null;
    $package = $verification?->subscriptionPackage;
    $isCarTier = ($package?->seller_tier ?? '') === 'car_verified';
    $badgeLabel = trim((string) ($package?->resolved_seller_badge_label ?? ''));
    if ($badgeLabel === '') {
        $badgeLabel = 'Verified';
    }
@endphp

@if($verification && $isCarTier)
    
    <div class="rounded-xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-green-50 p-4 {{ $class }}">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon name="check-circle" class="h-6 w-6 text-emerald-600" />
                    <h3 class="font-display text-lg font-bold text-emerald-900">Verified Car Seller</h3>
                </div>
                
                <p class="text-sm text-emerald-800 mb-3">
                    This seller has been verified and meets our car selling standards. 
                    You can buy with confidence!
                </p>
                
                <div class="space-y-2 text-xs text-emerald-700">
                    <div class="flex items-center gap-2">
                        <x-heroicon name="check-badge" class="h-4 w-4" />
                        <span>Documents verified by our team</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon name="shield-check" class="h-4 w-4" />
                        <span>{{ $badgeLabel }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon name="calendar" class="h-4 w-4" />
                        <span>Verified on {{ $verification?->verified_at?->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="flex-shrink-0">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-200">
                    <x-heroicon name="check-badge" class="h-8 w-8 text-emerald-700" />
                </div>
            </div>
        </div>
    </div>
@endif
