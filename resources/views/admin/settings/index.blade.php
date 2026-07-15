@extends('admin.layout')

@section('title', 'Settings')

@section('content')
<div x-data="adminSettingsPage({
    activeTab: @js($activeTab),
    bannerMode: @js($field('home_banner_mode', 'text')),
    bannerImages: @js($homeBannerImages),
    bannerPositions: @js($homeBannerPositions),
    bannerFits: @js($homeBannerFits),
    bannerDisplaySeconds: @js($homeBannerDisplaySeconds),
    appBannerImages: @js($appBannerImages),
    storageBaseUrl: @js($bannerPreviewStorageBaseUrl),
    siteLogoUrl: @js($siteLogoValue),
    siteFaviconUrl: @js($siteFaviconValue),
    slides: @js($homeBannerSlides),
})" x-init="init()">

    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-sm font-semibold text-rose-700">Please fix the highlighted settings and try again.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="scrollbar-hide flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'bg-orange-500 text-white shadow' : 'text-slate-600 hover:bg-slate-100'"
                class="whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition"
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- GENERAL SECTION --}}
    <section x-show="tab === 'general'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="general">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">General</h2>
                <p class="mt-0.5 text-sm text-slate-500">Core site identity and availability.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="settings-label" for="site_name">Site name</label>
                        <input id="site_name" type="text" name="site_name" value="{{ $field('site_name', 'Unsell') }}" class="app-input mt-1" maxlength="120">
                    </div>
                    <div>
                        <label class="settings-label" for="site_tagline">Tagline</label>
                        <input id="site_tagline" type="text" name="site_tagline" value="{{ $field('site_tagline', 'Buy & Sell Anything') }}" class="app-input mt-1" maxlength="200">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="app_url">App URL</label>
                        <input id="app_url" type="url" name="app_url" value="{{ $appUrlFieldValue }}" class="app-input mt-1" maxlength="500">
                        <x-input-error :messages="$errors->get('app_url')" class="mt-2" />
                        <p class="mt-1 text-xs text-slate-500">Primary public URL used for TWA/Bubblewrap output and runtime endpoint links.</p>
                    </div>
                    <div>
                        <label class="settings-label" for="site_logo_size_px">Logo size (px)</label>
                        <input id="site_logo_size_px" type="number" name="site_logo_size_px" value="{{ $logoSizePxValue }}" class="app-input mt-1" min="24" max="128" step="1">
                        <x-input-error :messages="$errors->get('site_logo_size_px')" class="mt-2" />
                    </div>

                    {{-- Brand Colors --}}
                    <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Two-part brand text color</p>
                        <p class="mt-1 text-xs text-slate-500">Set two text parts and colors for the main brand title.</p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="settings-label" for="site_brand_text_part_1">Brand text part 1</label>
                                <input id="site_brand_text_part_1" type="text" name="site_brand_text_part_1" value="{{ $brandTextPartOneValue }}" class="app-input mt-1" maxlength="80">
                            </div>
                            <div>
                                <label class="settings-label" for="site_brand_text_part_2">Brand text part 2</label>
                                <input id="site_brand_text_part_2" type="text" name="site_brand_text_part_2" value="{{ $brandTextPartTwoValue }}" class="app-input mt-1" maxlength="80">
                            </div>
                            <div>
                                <label class="settings-label" for="site_brand_text_spacing">Spacing</label>
                                <select id="site_brand_text_spacing" name="site_brand_text_spacing" class="app-select mt-1">
                                    <option value="none" @selected($brandTextSpacingValue === 'none')>No space</option>
                                    <option value="space" @selected($brandTextSpacingValue === 'space')>Single space</option>
                                </select>
                            </div>
                            <div>
                                <label class="settings-label" for="site_brand_text_color_1">Part 1 color</label>
                                <div class="mt-1 flex items-center gap-3">
                                    <input id="site_brand_text_color_1" type="color" name="site_brand_text_color_1" value="{{ $brandTextColorOneValue }}" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                                    <input type="text" value="{{ $brandTextColorOneValue }}" class="app-input" maxlength="7" readonly>
                                </div>
                            </div>
                            <div>
                                <label class="settings-label" for="site_brand_text_color_2">Part 2 color</label>
                                <div class="mt-1 flex items-center gap-3">
                                    <input id="site_brand_text_color_2" type="color" name="site_brand_text_color_2" value="{{ $brandTextColorTwoValue }}" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                                    <input type="text" value="{{ $brandTextColorTwoValue }}" class="app-input" maxlength="7" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preview</p>
                            <p class="mt-1 font-display text-xl font-bold leading-none">
                                <span style="color: {{ $brandTextColorOneValue }};">{{ $brandPreviewPartOne }}</span>
                                @if ($brandPreviewPartTwo !== '')
                                    <span style="color: {{ $brandTextColorTwoValue }};">{{ $brandPreviewSpacing }}{{ $brandPreviewPartTwo }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Site Logo --}}
                    <div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <label class="settings-label" for="site_logo">Site logo</label>
                                    <p class="mt-1 text-xs text-slate-500">Upload PNG, JPG, WEBP, or SVG.</p>
                                </div>
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                                    <template x-if="siteLogoPreview !== ''">
                                        <img :src="siteLogoPreview" alt="Site logo preview" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="siteLogoPreview === ''">
                                        @if ($siteLogoPreviewUrl)
                                            <img src="{{ $siteLogoPreviewUrl }}" alt="Site logo preview" class="h-full w-full object-contain">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-slate-200"></div>
                                        @endif
                                    </template>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="settings-label" for="site_logo">Logo URL / path</label>
                                <input id="site_logo" type="text" name="site_logo" value="{{ $siteLogoValue }}" x-model="siteLogoUrl" class="app-input mt-1" maxlength="500">
                            </div>
                            <div class="mt-4">
                                <label class="settings-label" for="site_logo_file">Upload logo</label>
                                <input id="site_logo_file" type="file" name="site_logo_file" class="app-input mt-1" accept=".png,.jpg,.jpeg,.webp,.svg" @change="onBrandingFileChanged($event, 'logo')">
                            </div>
                            @if ($siteLogoPreviewUrl)
                                <a href="{{ $siteLogoPreviewUrl }}" download class="mt-3 inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Download logo</a>
                            @endif
                        </div>
                    </div>

                    {{-- Site Favicon --}}
                    <div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <label class="settings-label" for="site_favicon">Browser icon</label>
                                    <p class="mt-1 text-xs text-slate-500">Upload ICO, PNG, SVG, or WEBP.</p>
                                </div>
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                                    <template x-if="siteFaviconPreview !== ''">
                                        <img :src="siteFaviconPreview" alt="Favicon preview" class="h-full w-full rounded-xl object-contain">
                                    </template>
                                    <template x-if="siteFaviconPreview === ''">
                                        @if ($siteFaviconPreviewUrl)
                                            <img src="{{ $siteFaviconPreviewUrl }}" alt="Favicon preview" class="h-full w-full rounded-xl object-contain">
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-[11px] font-semibold text-slate-500">ICO</div>
                                        @endif
                                    </template>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="settings-label" for="site_favicon">Icon URL / path</label>
                                <input id="site_favicon" type="text" name="site_favicon" value="{{ $siteFaviconValue }}" x-model="siteFaviconUrl" class="app-input mt-1" maxlength="500">
                            </div>
                            <div class="mt-4">
                                <label class="settings-label" for="site_favicon_file">Upload icon</label>
                                <input id="site_favicon_file" type="file" name="site_favicon_file" class="app-input mt-1" accept=".ico,.png,.svg,.webp" @change="onBrandingFileChanged($event, 'favicon')">
                            </div>
                        </div>
                    </div>

                    {{-- Other General Fields --}}
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="site_address">Business address</label>
                        <input id="site_address" type="text" name="site_address" value="{{ $field('site_address') }}" class="app-input mt-1" maxlength="300">
                    </div>

                    <div>
                        <label class="settings-label" for="site_currency">Currency code</label>
                        <select id="site_currency" name="site_currency" class="app-select mt-1">
                            @foreach (['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'] as $currency)
                                <option value="{{ $currency }}" @selected($field('site_currency', 'INR') === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="settings-label" for="site_currency_symbol">Currency symbol</label>
                        <input id="site_currency_symbol" type="text" name="site_currency_symbol" value="{{ $field('site_currency_symbol', 'Rs') }}" class="app-input mt-1" maxlength="10">
                    </div>

                    <div>
                        <label class="settings-label" for="social_facebook">Facebook URL</label>
                        <input id="social_facebook" type="url" name="social_facebook" value="{{ $field('social_facebook') }}" class="app-input mt-1" maxlength="500">
                    </div>

                    <div>
                        <label class="settings-label" for="social_instagram">Instagram URL</label>
                        <input id="social_instagram" type="url" name="social_instagram" value="{{ $field('social_instagram') }}" class="app-input mt-1" maxlength="500">
                    </div>

                    <div>
                        <label class="settings-label" for="social_twitter">Twitter / X URL</label>
                        <input id="social_twitter" type="url" name="social_twitter" value="{{ $field('social_twitter') }}" class="app-input mt-1" maxlength="500">
                    </div>

                    <div>
                        <label class="settings-label" for="social_whatsapp">WhatsApp number</label>
                        <input id="social_whatsapp" type="text" name="social_whatsapp" value="{{ $field('social_whatsapp') }}" class="app-input mt-1" maxlength="20">
                    </div>

                    <div>
                        <label class="settings-label" for="social_youtube">YouTube URL</label>
                        <input id="social_youtube" type="url" name="social_youtube" value="{{ $field('social_youtube') }}" class="app-input mt-1" maxlength="500">
                    </div>

                    <div>
                        <label class="settings-label" for="app_google_play_url">Google Play URL</label>
                        <input id="app_google_play_url" type="url" name="app_google_play_url" value="{{ $field('app_google_play_url') }}" class="app-input mt-1" maxlength="500">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="settings-label" for="app_store_url">Apple App Store URL</label>
                        <input id="app_store_url" type="url" name="app_store_url" value="{{ $field('app_store_url') }}" class="app-input mt-1" maxlength="500">
                    </div>

                    <div>
                        <label class="settings-label" for="maintenance_title">Maintenance title</label>
                        <input id="maintenance_title" type="text" name="maintenance_title" value="{{ $field('maintenance_title', 'We\'ll be back soon') }}" class="app-input mt-1" maxlength="160">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="settings-label" for="maintenance_message">Maintenance message</label>
                        <textarea id="maintenance_message" name="maintenance_message" class="app-textarea mt-1" maxlength="600">{{ $field('maintenance_message', 'The marketplace is temporarily under maintenance. Please check back shortly.') }}</textarea>
                    </div>

                    {{-- Home Banner Mode --}}
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="home_banner_mode">Home banner mode</label>
                        <select id="home_banner_mode" name="home_banner_mode" class="app-select mt-1" x-model="bannerMode">
                            <option value="text">Text slider</option>
                            <option value="image">Banner images</option>
                        </select>
                        <div class="mt-4 max-w-xs">
                            <label class="settings-label" for="home_banner_display_seconds">Banner display time (seconds)</label>
                            <input id="home_banner_display_seconds" type="number" name="home_banner_display_seconds" x-model.number="bannerDisplaySeconds" class="app-input mt-1" min="1" max="60" step="1">
                        </div>
                        <input type="hidden" name="home_banner_image_url" :value="primaryBannerImage()">
                    </div>

                    {{-- Banner Images Management --}}
                    <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Banner images</p>
                                <p class="mt-0.5 text-xs text-slate-500">Add and manage banner images.</p>
                            </div>
                            <button type="button" @click="addBannerImage()" :disabled="bannerImages.length >= 10" class="rounded-xl border border-orange-300 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700 hover:bg-orange-100 disabled:cursor-not-allowed disabled:opacity-50">
                                Add banner image
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <template x-for="(image, index) in bannerImages" :key="`banner-row-${index}`">
                                <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-600" x-text="`Banner ${index + 1}`"></p>
                                        <button type="button" @click="removeBannerImage(index)" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-100">Remove</button>
                                    </div>
                                    <div class="mt-3 space-y-3">
                                        <div>
                                            <label class="settings-label" :for="`home_banner_images_${index}`">Image URL / path</label>
                                            <input :id="`home_banner_images_${index}`" type="text" name="home_banner_images[]" x-model="bannerImages[index]" class="app-input mt-1" maxlength="500">
                                        </div>
                                        <div>
                                            <label class="settings-label" :for="`home_banner_image_files_${index}`">Upload image</label>
                                            <input :id="`home_banner_image_files_${index}`" type="file" name="home_banner_image_files[]" class="app-input mt-1" accept="image/png,image/jpeg,image/jpg,image/webp" @change="onBannerFileChanged($event, index)">
                                        </div>
                                        <div>
                                            <label class="settings-label" :for="`home_banner_image_positions_${index}`">Image position</label>
                                            <select :id="`home_banner_image_positions_${index}`" name="home_banner_image_positions[]" x-model="bannerPositions[index]" class="app-select mt-1">
                                                <option value="center">Center (default)</option>
                                                <option value="top">Top</option>
                                                <option value="bottom">Bottom</option>
                                                <option value="left">Left</option>
                                                <option value="right">Right</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="settings-label" :for="`home_banner_image_fits_${index}`">Image fit</label>
                                            <select :id="`home_banner_image_fits_${index}`" name="home_banner_image_fits[]" x-model="bannerFits[index]" class="app-select mt-1">
                                                <option value="cover">Cover – fill & crop (default)</option>
                                                <option value="contain">Contain – show full image</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                        <template x-if="bannerImagePreview(index) !== ''">
                                            <img :src="bannerImagePreview(index)" :alt="`Banner preview ${index + 1}`" :style="`object-position: ${bannerPositions[index] || 'center'}`" :class="`h-28 w-full ${bannerFits[index] === 'contain' ? 'object-contain' : 'object-cover'}`">
                                        </template>
                                        <template x-if="bannerImagePreview(index) === ''">
                                            <div class="flex h-28 items-center justify-center text-xs font-semibold text-slate-500">Preview appears after URL/path or image selection.</div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Live Banner Preview --}}
                    <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Live banner preview</p>
                                <p class="mt-0.5 text-xs text-slate-500">Preview updates as you edit.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-600" x-text="bannerMode === 'image' ? 'Image mode' : 'Text mode'"></span>
                        </div>

                        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200">
                            <template x-if="bannerMode === 'image'">
                                <div class="relative h-36 bg-slate-900">
                                    <template x-if="bannerPreviewImages().length > 0">
                                        <img :src="bannerPreviewImages()[previewSlideIndex] || bannerPreviewImages()[0]" :style="`object-position: ${bannerPositions[previewSlideIndex] || 'center'}`" :class="`h-full w-full ${bannerFits[previewSlideIndex] === 'contain' ? 'object-contain' : 'object-cover'}`">
                                    </template>
                                    <template x-if="bannerPreviewImages().length === 0">
                                        <div class="flex h-full items-center justify-center bg-gradient-to-r from-slate-700 to-slate-900 px-4 text-center text-xs font-semibold text-slate-200">Add at least one banner image</div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="bannerMode !== 'image'">
                                <div :class="`h-36 bg-gradient-to-r p-4 text-white ${previewTextGradient()}`">
                                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-white/85" x-text="slideValue(previewSlideIndex, 'badge')"></p>
                                    <h3 class="mt-1 font-display text-xl font-bold leading-tight" x-text="slideValue(previewSlideIndex, 'title')"></h3>
                                    <p class="mt-1 max-w-md text-xs text-white/90" x-text="slideValue(previewSlideIndex, 'desc')"></p>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex items-center gap-1.5" x-show="bannerMode === 'image' && bannerPreviewImages().length > 1" x-cloak>
                            <template x-for="(previewImage, imageIndex) in bannerPreviewImages()" :key="`preview-image-dot-${imageIndex}`">
                                <button type="button" @click="setPreviewSlide(imageIndex)" class="h-2.5 rounded-full transition-all" :class="previewSlideIndex === imageIndex ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                            </template>
                        </div>
                        <div class="mt-3 flex items-center gap-1.5" x-show="bannerMode !== 'image'" x-cloak>
                            <button type="button" @click="setPreviewSlide(0)" class="h-2.5 rounded-full transition-all" :class="previewSlideIndex === 0 ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                            <button type="button" @click="setPreviewSlide(1)" class="h-2.5 rounded-full transition-all" :class="previewSlideIndex === 1 ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                            <button type="button" @click="setPreviewSlide(2)" class="h-2.5 rounded-full transition-all" :class="previewSlideIndex === 2 ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                        </div>
                    </div>

                    {{-- App Banner Images --}}
                    <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm font-semibold text-slate-900">App banner images (Mobile App)</p>
                        <p class="mt-0.5 text-xs text-slate-500">Used by mobile API response as banner_images.</p>

                        <div class="mt-3 space-y-2" x-show="appBannerImages.length > 0" x-cloak>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current app banners</p>
                            <template x-for="(image, index) in appBannerImages" :key="`app-banner-${index}-${image}`">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <input type="hidden" name="app_banner_existing_images[]" :value="image">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-600" x-text="`Banner ${index + 1}`"></p>
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="moveAppBannerUp(index)" :disabled="index === 0" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 disabled:opacity-40">Up</button>
                                            <button type="button" @click="moveAppBannerDown(index)" :disabled="index === appBannerImages.length - 1" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 disabled:opacity-40">Down</button>
                                            <button type="button" @click="removeAppBanner(index)" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-100">Remove</button>
                                        </div>
                                    </div>
                                    <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                        <img :src="resolveImagePath(image)" :alt="`App banner ${index + 1}`" class="h-28 w-full object-contain">
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600" x-show="appBannerImages.length === 0" x-cloak>No app banners currently saved.</div>

                        <div class="mt-3">
                            <label class="settings-label" for="app_banner_image_files">Upload app banner images</label>
                            <input id="app_banner_image_files" type="file" name="app_banner_image_files[]" class="app-input mt-1" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                        </div>
                        <div class="mt-3 max-w-xs">
                            <label class="settings-label" for="app_banner_display_seconds">App banner display time (seconds)</label>
                            <input id="app_banner_display_seconds" type="number" name="app_banner_display_seconds" value="{{ $appBannerDisplaySeconds }}" class="app-input mt-1" min="1" max="60" step="1">
                        </div>
                    </div>

                    {{-- Text Slider Content --}}
                    <div class="sm:col-span-2">
                        <p class="settings-label">Text slider content</p>
                        <p class="mt-1 text-xs text-slate-500">Used when Home banner mode is set to Text slider.</p>
                    </div>

                    <div>
                        <label class="settings-label" for="home_banner_slide_1_badge">Slide 1 badge</label>
                        <input id="home_banner_slide_1_badge" type="text" name="home_banner_slide_1_badge" x-model="textSlides[0].badge" class="app-input mt-1" maxlength="80">
                    </div>
                    <div>
                        <label class="settings-label" for="home_banner_slide_1_title">Slide 1 title</label>
                        <input id="home_banner_slide_1_title" type="text" name="home_banner_slide_1_title" x-model="textSlides[0].title" class="app-input mt-1" maxlength="160">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="home_banner_slide_1_desc">Slide 1 description</label>
                        <textarea id="home_banner_slide_1_desc" name="home_banner_slide_1_desc" x-model="textSlides[0].desc" class="app-textarea mt-1" maxlength="260"></textarea>
                    </div>

                    <div>
                        <label class="settings-label" for="home_banner_slide_2_badge">Slide 2 badge</label>
                        <input id="home_banner_slide_2_badge" type="text" name="home_banner_slide_2_badge" x-model="textSlides[1].badge" class="app-input mt-1" maxlength="80">
                    </div>
                    <div>
                        <label class="settings-label" for="home_banner_slide_2_title">Slide 2 title</label>
                        <input id="home_banner_slide_2_title" type="text" name="home_banner_slide_2_title" x-model="textSlides[1].title" class="app-input mt-1" maxlength="160">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="home_banner_slide_2_desc">Slide 2 description</label>
                        <textarea id="home_banner_slide_2_desc" name="home_banner_slide_2_desc" x-model="textSlides[1].desc" class="app-textarea mt-1" maxlength="260"></textarea>
                    </div>

                    <div>
                        <label class="settings-label" for="home_banner_slide_3_badge">Slide 3 badge</label>
                        <input id="home_banner_slide_3_badge" type="text" name="home_banner_slide_3_badge" x-model="textSlides[2].badge" class="app-input mt-1" maxlength="80">
                    </div>
                    <div>
                        <label class="settings-label" for="home_banner_slide_3_title">Slide 3 title</label>
                        <input id="home_banner_slide_3_title" type="text" name="home_banner_slide_3_title" x-model="textSlides[2].title" class="app-input mt-1" maxlength="160">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="home_banner_slide_3_desc">Slide 3 description</label>
                        <textarea id="home_banner_slide_3_desc" name="home_banner_slide_3_desc" x-model="textSlides[2].desc" class="app-textarea mt-1" maxlength="260"></textarea>
                    </div>
                </div>

                {{-- Maintenance Mode --}}
                <div class="mt-5 flex items-start justify-between rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-rose-800">Maintenance mode</p>
                        <p class="mt-0.5 text-xs text-rose-600">Visitors see the offline page. Admins are unaffected.</p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="maintenance_mode" value="1" class="peer sr-only" @checked($oldBool('maintenance_mode'))>
                            <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-rose-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save General</button>
            </div>
        </form>
    </section>

    {{-- SUPPORT SECTION --}}
    <section x-show="tab === 'support'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="support">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Support</h2>
                <p class="mt-0.5 text-sm text-slate-500">Manage support contact details and app Help FAQs.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="settings-label" for="contact_email">Contact / support email</label>
                        <input id="contact_email" type="email" name="contact_email" value="{{ $field('contact_email') }}" class="app-input mt-1" maxlength="200">
                    </div>
                    <div>
                        <label class="settings-label" for="support_phone">Support phone</label>
                        <input id="support_phone" type="text" name="support_phone" value="{{ $field('support_phone') }}" class="app-input mt-1" maxlength="30">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="support_faqs_text">App FAQs (Help & Support)</label>
                        <textarea id="support_faqs_text" name="support_faqs_text" rows="10" class="app-input mt-1" placeholder="How do I post a listing? || Tap Sell, fill details, and submit.">{{ $supportFaqsText }}</textarea>
                        <x-input-error :messages="$errors->get('support_faqs_text')" class="mt-2" />
                        <p class="mt-1 text-xs text-slate-500">One FAQ per line using: Question || Answer</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Support</button>
            </div>
        </form>
    </section>

    {{-- LISTINGS SECTION --}}
    <section x-show="tab === 'listings'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="listings">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Listings</h2>
                <p class="mt-0.5 text-sm text-slate-500">Control how listings are submitted and managed.</p>

                <div class="mt-5 flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Require admin approval</p>
                        <p class="mt-0.5 text-xs text-slate-500">New listings stay pending until reviewed.</p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <input type="hidden" name="listing_moderation_enabled" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="listing_moderation_enabled" value="1" class="peer sr-only" @checked($oldBool('listing_moderation_enabled'))>
                            <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-3 flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Allow guest browsing</p>
                        <p class="mt-0.5 text-xs text-slate-500">Guests can browse listings without login.</p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <input type="hidden" name="listing_allow_guest_view" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="listing_allow_guest_view" value="1" class="peer sr-only" @checked($oldBool('listing_allow_guest_view'))>
                            <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-4 grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="settings-label" for="listing_expiry_days">Listing expiry (days)</label>
                        <input id="listing_expiry_days" type="number" name="listing_expiry_days" value="{{ $field('listing_expiry_days', 60) }}" min="1" max="3650" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="listing_max_images">Max images per listing</label>
                        <input id="listing_max_images" type="number" name="listing_max_images" value="{{ $field('listing_max_images', 8) }}" min="1" max="30" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="listing_max_per_user">Max active listings / user</label>
                        <input id="listing_max_per_user" type="number" name="listing_max_per_user" value="{{ $field('listing_max_per_user', 20) }}" min="1" max="9999" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="listing_description_min">Min description length</label>
                        <input id="listing_description_min" type="number" name="listing_description_min" value="{{ $field('listing_description_min', 20) }}" min="0" max="2000" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="location_nearby_radius_km">Nearby ads radius (km)</label>
                        <input id="location_nearby_radius_km" type="number" name="location_nearby_radius_km" value="{{ $field('location_nearby_radius_km', 30) }}" min="1" max="500" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="location_default_country">Default country code</label>
                        <input id="location_default_country" type="text" name="location_default_country" value="{{ strtoupper((string) $field('location_default_country', 'IN')) }}" class="app-input mt-1 uppercase" maxlength="2">
                    </div>
                    <div>
                        <label class="settings-label" for="free_call_access_limit">Free call access limit</label>
                        <input id="free_call_access_limit" type="number" name="free_call_access_limit" value="{{ $field('free_call_access_limit', 0) }}" min="0" max="100000" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="free_map_access_limit">Free map access limit</label>
                        <input id="free_map_access_limit" type="number" name="free_map_access_limit" value="{{ $field('free_map_access_limit', 0) }}" min="0" max="100000" class="app-input mt-1">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="settings-label" for="google_maps_api_key">Google Maps API key</label>
                        <input id="google_maps_api_key" type="password" name="google_maps_api_key" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing key">
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="listing_price_required" value="0">
                        <input type="checkbox" name="listing_price_required" value="1" class="rounded accent-orange-500" @checked($oldBool('listing_price_required'))>
                        Require price on each listing
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="listing_location_required" value="0">
                        <input type="checkbox" name="listing_location_required" value="1" class="rounded accent-orange-500" @checked($oldBool('listing_location_required'))>
                        Require location on each listing
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="listing_allow_negotiation" value="0">
                        <input type="checkbox" name="listing_allow_negotiation" value="1" class="rounded accent-orange-500" @checked($oldBool('listing_allow_negotiation'))>
                        Allow negotiable pricing
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="listing_auto_renew" value="0">
                        <input type="checkbox" name="listing_auto_renew" value="1" class="rounded accent-orange-500" @checked($oldBool('listing_auto_renew'))>
                        Auto renew near expiry
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 sm:col-span-2">
                        <input type="hidden" name="listing_allow_bump" value="0">
                        <input type="checkbox" name="listing_allow_bump" value="1" class="rounded accent-orange-500" @checked($oldBool('listing_allow_bump'))>
                        Allow bump / boost listing action
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-orange-900">Category-wise Free Post Limits</p>
                    <p class="mt-0.5 text-xs text-orange-700">Restrict how many free ads a user can post per category.</p>
                </div>
                <a href="{{ route('admin.free-post-limits.index') }}" class="ml-4 shrink-0 rounded-xl bg-orange-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-orange-600">Manage Rules →</a>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Listings</button>
            </div>
        </form>
    </section>

    {{-- FEATURED ADS SECTION --}}
    <section x-show="tab === 'featured'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="featured">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Featured Ads</h2>
                <p class="mt-0.5 text-sm text-slate-500">Pricing, durations, and payment gateway credentials.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="settings-label" for="featured_daily_rate">Daily rate</label>
                        <input id="featured_daily_rate" type="number" name="featured_daily_rate" value="{{ $field('featured_daily_rate', 49) }}" min="0" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="payment_gateway">Active payment gateway</label>
                        <select id="payment_gateway" name="payment_gateway" class="app-select mt-1">
                            @foreach (['razorpay' => 'Razorpay', 'phonepe' => 'PhonePe', 'paytm' => 'Paytm', 'mock' => 'Mock (testing)'] as $gw => $label)
                                <option value="{{ $gw }}" @selected($field('payment_gateway', 'mock') === $gw)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="payment_checkout_mode">Checkout flow mode</label>
                        <select id="payment_checkout_mode" name="payment_checkout_mode" class="app-select mt-1">
                            <option value="inapp_only" @selected($field('payment_checkout_mode', 'inapp_only') === 'inapp_only')>In-app only</option>
                            <option value="gateway_redirect" @selected($field('payment_checkout_mode', 'inapp_only') === 'gateway_redirect')>Allow gateway redirect</option>
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="payment_gateway_selection_mode">Gateway selection mode</label>
                        <select id="payment_gateway_selection_mode" name="payment_gateway_selection_mode" class="app-select mt-1">
                            <option value="single" @selected($field('payment_gateway_selection_mode', 'single') === 'single')>Single gateway</option>
                            <option value="multiple" @selected($field('payment_gateway_selection_mode', 'single') === 'multiple')>Multiple gateways</option>
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="payment_currency">Payment currency</label>
                        <select id="payment_currency" name="payment_currency" class="app-select mt-1">
                            @foreach (['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'] as $currency)
                                <option value="{{ $currency }}" @selected($field('payment_currency', 'INR') === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="payment_test_mode">Gateway mode</label>
                        <div class="mt-1 rounded-xl border border-slate-200 px-3 py-2">
                            <input type="hidden" name="payment_test_mode" value="0">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input type="checkbox" name="payment_test_mode" value="1" class="rounded accent-orange-500" @checked($oldBool('payment_test_mode'))>
                                Enable test mode
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="settings-label mb-2">Allowed feature durations (days)</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ([3, 7, 14, 15, 21, 30, 45, 60, 90] as $days)
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition {{ in_array($days, $allowedDays) ? 'border-orange-400 bg-orange-50 text-orange-700' : 'border-slate-200 bg-white text-slate-600' }}">
                                <input type="checkbox" name="featured_allowed_days[]" value="{{ $days }}" class="rounded accent-orange-500" @checked(in_array($days, $allowedDays))>
                                {{ $days }}d
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Razorpay Credentials --}}
                <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">Razorpay Credentials</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="settings-label" for="razorpay_key_id">Razorpay Key ID</label>
                            <input id="razorpay_key_id" type="text" name="razorpay_key_id" value="{{ $field('razorpay_key_id') }}" class="app-input mt-1" maxlength="100">
                        </div>
                        <div>
                            <label class="settings-label" for="razorpay_key_secret">Razorpay Key Secret</label>
                            <input id="razorpay_key_secret" type="password" name="razorpay_key_secret" value="" class="app-input mt-1" maxlength="100" placeholder="Leave blank to keep existing">
                        </div>
                        <div>
                            <label class="settings-label" for="razorpay_base_url">Razorpay Base URL</label>
                            <input id="razorpay_base_url" type="url" name="razorpay_base_url" value="{{ $field('razorpay_base_url', 'https://api.razorpay.com') }}" class="app-input mt-1" maxlength="255">
                        </div>
                    </div>
                </div>

                {{-- PhonePe Credentials --}}
                <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">PhonePe Credentials</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="settings-label" for="phonepe_merchant_id">PhonePe Merchant ID</label>
                            <input id="phonepe_merchant_id" type="text" name="phonepe_merchant_id" value="{{ $field('phonepe_merchant_id') }}" class="app-input mt-1" maxlength="100">
                        </div>
                        <div>
                            <label class="settings-label" for="phonepe_salt_key">PhonePe Salt Key</label>
                            <input id="phonepe_salt_key" type="password" name="phonepe_salt_key" value="" class="app-input mt-1" maxlength="120" placeholder="Leave blank to keep existing">
                        </div>
                        <div>
                            <label class="settings-label" for="phonepe_salt_index">PhonePe Salt Index</label>
                            <input id="phonepe_salt_index" type="text" name="phonepe_salt_index" value="{{ $field('phonepe_salt_index', '1') }}" class="app-input mt-1" maxlength="10">
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="settings-label" for="phonepe_base_url">PhonePe Base URL</label>
                            <input id="phonepe_base_url" type="url" name="phonepe_base_url" value="{{ $field('phonepe_base_url', 'https://api-preprod.phonepe.com/apis/pg-sandbox') }}" class="app-input mt-1" maxlength="255">
                        </div>
                    </div>
                </div>

                {{-- Paytm Credentials --}}
                <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">Paytm Credentials</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="settings-label" for="paytm_mid">Paytm MID</label>
                            <input id="paytm_mid" type="text" name="paytm_mid" value="{{ $field('paytm_mid') }}" class="app-input mt-1" maxlength="80">
                        </div>
                        <div>
                            <label class="settings-label" for="paytm_merchant_key">Paytm Merchant Key</label>
                            <input id="paytm_merchant_key" type="password" name="paytm_merchant_key" value="" class="app-input mt-1" maxlength="120" placeholder="Leave blank to keep existing">
                        </div>
                        <div>
                            <label class="settings-label" for="paytm_website">Paytm Website</label>
                            <input id="paytm_website" type="text" name="paytm_website" value="{{ $field('paytm_website', 'WEBSTAGING') }}" class="app-input mt-1" maxlength="50">
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="settings-label" for="paytm_base_url">Paytm Base URL</label>
                            <input id="paytm_base_url" type="url" name="paytm_base_url" value="{{ $field('paytm_base_url', 'https://securegw-stage.paytm.in') }}" class="app-input mt-1" maxlength="255">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Featured Ads</button>
            </div>
        </form>
    </section>

    {{-- REGISTRATION SECTION --}}
    <section x-show="tab === 'registration'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="registration">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Registration</h2>

                <div class="mt-5 space-y-3">
                    <div class="flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                        <div><p class="text-sm font-semibold text-slate-800">Allow new registrations</p></div>
                        <div class="ml-4 flex-shrink-0">
                            <input type="hidden" name="registration_enabled" value="0">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" name="registration_enabled" value="1" class="peer sr-only" @checked($oldBool('registration_enabled'))>
                                <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                        <div><p class="text-sm font-semibold text-slate-800">Require email verification</p></div>
                        <div class="ml-4 flex-shrink-0">
                            <input type="hidden" name="email_verification_required" value="0">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" name="email_verification_required" value="1" class="peer sr-only" @checked($oldBool('email_verification_required'))>
                                <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                        <div><p class="text-sm font-semibold text-slate-800">Require phone verification</p></div>
                        <div class="ml-4 flex-shrink-0">
                            <input type="hidden" name="phone_verification_required" value="0">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" name="phone_verification_required" value="1" class="peer sr-only" @checked($oldBool('phone_verification_required'))>
                                <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-800">Authentication channels</p>
                        <p class="mt-1 text-xs text-slate-500">Enable login and registration methods.</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="auth_login_email_enabled" value="0">
                                <input type="checkbox" name="auth_login_email_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('auth_login_email_enabled'))>
                                Enable email login
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="auth_login_mobile_enabled" value="0">
                                <input type="checkbox" name="auth_login_mobile_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('auth_login_mobile_enabled'))>
                                Enable mobile OTP login
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="auth_register_email_enabled" value="0">
                                <input type="checkbox" name="auth_register_email_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('auth_register_email_enabled'))>
                                Enable email registration
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="auth_register_mobile_enabled" value="0">
                                <input type="checkbox" name="auth_register_mobile_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('auth_register_mobile_enabled'))>
                                Enable mobile OTP registration
                            </label>
                        </div>

                        <div class="mt-4">
                            <label class="settings-label" for="firebase_auth_domain">Firebase Auth domain</label>
                            <input id="firebase_auth_domain" type="text" name="firebase_auth_domain" value="{{ $field('firebase_auth_domain') }}" class="app-input mt-1" maxlength="255">
                        </div>
                    </div>
                </div>

                {{-- Google OAuth --}}
                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-800">Google OAuth login</p></div>
                        <div>
                            <input type="hidden" name="google_oauth_enabled" value="0">
                            <input type="checkbox" name="google_oauth_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('google_oauth_enabled'))>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="google_oauth_client_id">Google Client ID</label>
                            <input id="google_oauth_client_id" type="text" name="google_oauth_client_id" value="{{ $field('google_oauth_client_id') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="google_oauth_client_secret">Google Client Secret</label>
                            <input id="google_oauth_client_secret" type="password" name="google_oauth_client_secret" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing">
                        </div>
                    </div>
                </div>

                {{-- Facebook OAuth --}}
                <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-800">Facebook OAuth login</p></div>
                        <div>
                            <input type="hidden" name="facebook_oauth_enabled" value="0">
                            <input type="checkbox" name="facebook_oauth_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('facebook_oauth_enabled'))>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="facebook_oauth_app_id">Facebook App ID</label>
                            <input id="facebook_oauth_app_id" type="text" name="facebook_oauth_app_id" value="{{ $field('facebook_oauth_app_id') }}" class="app-input mt-1" maxlength="100">
                        </div>
                        <div>
                            <label class="settings-label" for="facebook_oauth_app_secret">Facebook App Secret</label>
                            <input id="facebook_oauth_app_secret" type="password" name="facebook_oauth_app_secret" value="" class="app-input mt-1" maxlength="100" placeholder="Leave blank to keep existing">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Registration</button>
            </div>
        </form>
    </section>

    {{-- NOTIFICATIONS SECTION --}}
    <section x-show="tab === 'notifications'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="notifications">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Notifications</h2>

                <div class="mt-5 max-w-xs">
                    <label class="settings-label" for="notification_poll_seconds">Poll interval (seconds)</label>
                    <input id="notification_poll_seconds" type="number" name="notification_poll_seconds" value="{{ $field('notification_poll_seconds', 20) }}" min="5" max="300" class="app-input mt-1">
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="notification_email_enabled" value="0">
                        <input type="checkbox" name="notification_email_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('notification_email_enabled'))>
                        Enable email notifications
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="notification_push_enabled" value="0">
                        <input type="checkbox" name="notification_push_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('notification_push_enabled'))>
                        Enable push notifications
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="notification_new_message" value="0">
                        <input type="checkbox" name="notification_new_message" value="1" class="rounded accent-orange-500" @checked($oldBool('notification_new_message'))>
                        Notify for new messages
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="notification_listing_approved" value="0">
                        <input type="checkbox" name="notification_listing_approved" value="1" class="rounded accent-orange-500" @checked($oldBool('notification_listing_approved'))>
                        Notify when listing approved
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 sm:col-span-2">
                        <input type="hidden" name="notification_listing_expired" value="0">
                        <input type="checkbox" name="notification_listing_expired" value="1" class="rounded accent-orange-500" @checked($oldBool('notification_listing_expired'))>
                        Notify when listing expired
                    </label>
                </div>

                {{-- FCM Settings --}}
                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">Firebase Cloud Messaging (FCM)</p>
                    <p class="mt-1 text-xs text-slate-500">These values override env credentials when provided.</p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2 rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                            <label class="settings-label" for="fcm_service_account_json_file">Upload service account JSON</label>
                            <input id="fcm_service_account_json_file" type="file" name="fcm_service_account_json_file" class="app-input mt-1" accept=".json,application/json,text/plain">
                            <p class="mt-1 text-xs text-indigo-700">Upload Firebase service-account key file to auto-fill credentials.</p>
                        </div>
                        <div>
                            <label class="settings-label" for="fcm_project_id">Project ID</label>
                            <input id="fcm_project_id" type="text" name="fcm_project_id" value="{{ $field('fcm_project_id') }}" class="app-input mt-1" maxlength="120">
                        </div>
                        <div>
                            <label class="settings-label" for="fcm_messaging_sender_id">Messaging Sender ID</label>
                            <input id="fcm_messaging_sender_id" type="text" name="fcm_messaging_sender_id" value="{{ $field('fcm_messaging_sender_id') }}" class="app-input mt-1" maxlength="64">
                        </div>
                        <div>
                            <label class="settings-label" for="fcm_app_id">App ID</label>
                            <input id="fcm_app_id" type="text" name="fcm_app_id" value="{{ $field('fcm_app_id') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="fcm_vapid_key">VAPID Key</label>
                            <input id="fcm_vapid_key" type="text" name="fcm_vapid_key" value="{{ $field('fcm_vapid_key') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="fcm_service_account_email">Service Account Email</label>
                            <input id="fcm_service_account_email" type="email" name="fcm_service_account_email" value="{{ $field('fcm_service_account_email') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="fcm_api_key">Web API Key</label>
                            <input id="fcm_api_key" type="password" name="fcm_api_key" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="settings-label" for="fcm_service_account_private_key">Service Account Private Key</label>
                            <textarea id="fcm_service_account_private_key" name="fcm_service_account_private_key" rows="4" class="app-textarea mt-1" maxlength="10000" placeholder="Leave blank to keep existing"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="settings-label" for="fcm_server_key">Legacy FCM Server Key</label>
                            <input id="fcm_server_key" type="password" name="fcm_server_key" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing">
                        </div>

                        {{-- Notification Sound --}}
                        <div class="sm:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-sm font-semibold text-slate-800">Notification sound</p>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="settings-label" for="notification_sound_url">Sound URL / path</label>
                                    <input id="notification_sound_url" type="text" name="notification_sound_url" value="{{ $notificationSoundValue }}" class="app-input mt-1" maxlength="500">
                                </div>
                                <div>
                                    <label class="settings-label" for="notification_sound_file">Upload sound file</label>
                                    <input id="notification_sound_file" type="file" name="notification_sound_file" class="app-input mt-1" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4,.mp3,.wav,.ogg,.m4a">
                                </div>
                            </div>
                            @if ($notificationSoundPreviewUrl)
                                <div class="mt-3 rounded-xl border border-emerald-100 bg-white p-3">
                                    <p class="text-xs font-semibold uppercase text-emerald-700">Current sound preview</p>
                                    <audio controls preload="none" class="mt-2 w-full">
                                        <source src="{{ $notificationSoundPreviewUrl }}">
                                    </audio>
                                </div>
                            @endif
                        </div>

                        {{-- Startup Popup --}}
                        <div class="sm:col-span-2 rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Startup popup</p>
                                    <p class="mt-0.5 text-xs text-slate-600">Show custom offer when app starts.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex items-center gap-2 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                                        <input type="hidden" name="startup_popup_enabled" value="0">
                                        <input type="checkbox" name="startup_popup_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('startup_popup_enabled'))>
                                        Enable popup
                                    </label>
                                    <label class="inline-flex items-center gap-2 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                                        <input type="hidden" name="startup_popup_open_new_tab" value="0">
                                        <input type="checkbox" name="startup_popup_open_new_tab" value="1" class="rounded accent-orange-500" @checked($oldBool('startup_popup_open_new_tab'))>
                                        Open link in new tab
                                    </label>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="settings-label" for="startup_popup_title">Popup title</label>
                                    <input id="startup_popup_title" type="text" name="startup_popup_title" value="{{ $field('startup_popup_title') }}" class="app-input mt-1" maxlength="160">
                                </div>
                                <div>
                                    <label class="settings-label" for="startup_popup_button_label">Button label</label>
                                    <input id="startup_popup_button_label" type="text" name="startup_popup_button_label" value="{{ $field('startup_popup_button_label') }}" class="app-input mt-1" maxlength="80">
                                </div>
                                <div>
                                    <label class="settings-label" for="startup_popup_style">Popup style</label>
                                    <select id="startup_popup_style" name="startup_popup_style" class="app-select mt-1">
                                        <option value="minimal" @selected($startupPopupStyleValue === 'minimal')>Minimal</option>
                                        <option value="premium" @selected($startupPopupStyleValue === 'premium')>Premium</option>
                                        <option value="festive" @selected($startupPopupStyleValue === 'festive')>Festive</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="settings-label" for="startup_popup_message">Popup message</label>
                                    <textarea id="startup_popup_message" name="startup_popup_message" class="app-textarea mt-1" maxlength="600">{{ $field('startup_popup_message') }}</textarea>
                                </div>
                                <div>
                                    <label class="settings-label" for="startup_popup_image_url">Image URL / path</label>
                                    <input id="startup_popup_image_url" type="text" name="startup_popup_image_url" value="{{ $startupPopupImageValue }}" class="app-input mt-1" maxlength="500">
                                </div>
                                <div>
                                    <label class="settings-label" for="startup_popup_image_file">Upload image</label>
                                    <input id="startup_popup_image_file" type="file" name="startup_popup_image_file" class="app-input mt-1" accept="image/png,image/jpeg,image/jpg,image/webp">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="settings-label" for="startup_popup_link_url">Offer link</label>
                                    <input id="startup_popup_link_url" type="text" name="startup_popup_link_url" value="{{ $startupPopupLinkValue }}" class="app-input mt-1" maxlength="500">
                                </div>
                            </div>

                            @if ($startupPopupPreviewUrl || $field('startup_popup_title') || $field('startup_popup_message'))
                                <div class="{{ $startupPopupPreviewCardClasses }}">
                                    <p class="border-b border-orange-100 px-4 py-3 text-xs font-bold uppercase text-orange-600">Current popup preview • {{ ucfirst($startupPopupStyleValue) }} style</p>
                                    @if ($startupPopupPreviewUrl)
                                        <img src="{{ $startupPopupPreviewUrl }}" alt="Popup preview" class="h-48 w-full object-cover">
                                    @endif
                                    <div class="space-y-3 p-5">
                                        <div>
                                            <p class="font-display text-xl font-bold text-slate-900">{{ $field('startup_popup_title', 'Special offer') }}</p>
                                            <p class="mt-2 text-sm text-slate-600">{{ $field('startup_popup_message', 'Show a custom offer or alert to users when they start the app.') }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-3">
                                            @if ($startupPopupLinkValue)
                                                <a href="{{ $startupPopupLinkValue }}" class="{{ $startupPopupPreviewButtonClasses }}">
                                                    {{ $field('startup_popup_button_label', 'Open offer') ?: 'Open offer' }}
                                                </a>
                                            @endif
                                            <span class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-500">Close</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Notifications</button>
            </div>
        </form>
    </section>

    {{-- TWA SECTION --}}
    <section x-show="tab === 'twa'" class="mt-5" x-cloak
        data-manifest-url="{{ $manifestRuntimeUrl }}"
        data-assetlinks-url="{{ $assetlinksRuntimeUrl }}"
        data-app-url="{{ $savedAppUrl }}"
        data-storage-url="{{ rtrim(str_replace('/__media_path__', '', route('media.public', ['path' => '__media_path__'], false)), '/') }}"
        x-data="initTwaSettings({
            twaEnabled: @js((bool) $oldBool('twa_enabled')),
            name: @js((string) $field('twa_name', $field('site_name', 'Unsell'))),
            shortName: @js((string) $field('twa_short_name', 'Unsell')),
            description: @js((string) $field('twa_description', 'Buy and sell in your city with fast local discovery.')),
            startUrl: @js((string) $field('twa_start_url', '/')),
            scope: @js((string) $field('twa_scope', '/')),
            display: @js((string) $field('twa_display', 'standalone')),
            orientation: @js((string) $field('twa_orientation', 'any')),
            themeColor: @js((string) $field('twa_theme_color', '#f97316')),
            backgroundColor: @js((string) $field('twa_background_color', '#ffffff')),
            packageName: @js((string) $field('twa_package_name', '')),
            fingerprintsText: @js($twaFingerprintsText),
            iconUrl: @js((string) $field('twa_icon_url', '')),
            iconMaskableUrl: @js((string) $field('twa_icon_maskable_url', '')),
            navigationColor: @js((string) $field('twa_navigation_color', '#000000')),
            splashFadeDuration: @js((int) $field('twa_splash_fade_duration', 300)),
            appVersionName: @js((string) $field('twa_app_version_name', '1.0.0')),
            appVersionCode: @js((int) $field('twa_app_version_code', 1)),
            minSdkVersion: @js((int) $field('twa_min_sdk_version', 19)),
            signingKeyAlias: @js((string) $field('twa_signing_key_alias', 'android')),
            keystoreStoreType: @js((string) $field('twa_keystore_store_type', 'PKCS12')),
            keyFullName: @js((string) $field('twa_key_full_name', '')),
            keyOrg: @js((string) $field('twa_key_org', '')),
            keyOrgUnit: @js((string) $field('twa_key_org_unit', '')),
            keyCountry: @js(strtoupper((string) $field('twa_key_country', ''))),
            keyState: @js((string) $field('twa_key_state', '')),
            keyCity: @js((string) $field('twa_key_city', '')),
            manifestUrl: @js($manifestRuntimeUrl),
            assetlinksUrl: @js($assetlinksRuntimeUrl),
            appUrl: @js($savedAppUrl),
            storageUrl: @js(rtrim(Storage::url(''), '/'))
        })" x-init="initTwaOptimizations()">

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="twa">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Trusted Web Activity (TWA)</h2>
                <p class="mt-0.5 text-sm text-slate-500">Manage Android package verification and manifest fields.</p>

                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="twa_enabled" value="0">
                        <input type="checkbox" name="twa_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('twa_enabled')) @change="twaEnabled = $event.target.checked">
                        Enable TWA asset links output
                    </label>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="settings-label" for="twa_name">Manifest name</label>
                        <input id="twa_name" type="text" name="twa_name" x-model="name" class="app-input mt-1" maxlength="120">
                    </div>
                    <div>
                        <label class="settings-label" for="twa_short_name">Manifest short name</label>
                        <input id="twa_short_name" type="text" name="twa_short_name" x-model="shortName" class="app-input mt-1" maxlength="40">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="twa_description">Manifest description</label>
                        <textarea id="twa_description" name="twa_description" x-model="description" class="app-textarea mt-1" maxlength="320"></textarea>
                    </div>
                    <div>
                        <label class="settings-label" for="twa_start_url">Start URL</label>
                        <input id="twa_start_url" type="text" name="twa_start_url" x-model="startUrl" class="app-input mt-1" maxlength="500">
                    </div>
                    <div>
                        <label class="settings-label" for="twa_scope">Scope</label>
                        <input id="twa_scope" type="text" name="twa_scope" x-model="scope" class="app-input mt-1" maxlength="500">
                    </div>
                    <div>
                        <label class="settings-label" for="twa_display">Display</label>
                        <select id="twa_display" name="twa_display" x-model="display" class="app-select mt-1">
                            @foreach (['standalone', 'fullscreen', 'minimal-ui', 'browser'] as $mode)
                                <option value="{{ $mode }}">{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="twa_orientation">Orientation</label>
                        <select id="twa_orientation" name="twa_orientation" x-model="orientation" class="app-select mt-1">
                            @foreach (['any', 'natural', 'portrait', 'portrait-primary', 'portrait-secondary', 'landscape', 'landscape-primary', 'landscape-secondary'] as $orient)
                                <option value="{{ $orient }}">{{ $orient }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="twa_theme_color">Theme color</label>
                        <input id="twa_theme_color" type="text" name="twa_theme_color" x-model="themeColor" class="app-input mt-1" maxlength="7">
                    </div>
                    <div>
                        <label class="settings-label" for="twa_background_color">Background color</label>
                        <input id="twa_background_color" type="text" name="twa_background_color" x-model="backgroundColor" class="app-input mt-1" maxlength="7">
                    </div>
                </div>

                {{-- App Icons --}}
                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">App icons</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-bold uppercase text-slate-600">Standard icon</p>
                            <div class="mt-3 space-y-2">
                                <div>
                                    <label class="settings-label" for="twa_icon_url">Icon URL / path</label>
                                    <input id="twa_icon_url" type="text" name="twa_icon_url" x-model="iconUrl" class="app-input mt-1" maxlength="500">
                                </div>
                                <div>
                                    <label class="settings-label" for="twa_icon_file">Upload PNG</label>
                                    <input id="twa_icon_file" type="file" name="twa_icon_file" class="app-input mt-1" accept="image/png" @change="onIconFileChanged($event, 'icon')">
                                </div>
                            </div>
                            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                <template x-if="iconSrc(iconUrl, iconPreview) !== ''">
                                    <img :src="iconSrc(iconUrl, iconPreview)" alt="Icon preview" class="h-24 w-full object-contain">
                                </template>
                                <template x-if="iconSrc(iconUrl, iconPreview) === ''">
                                    <div class="flex h-24 items-center justify-center text-xs text-slate-400">No icon set</div>
                                </template>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-bold uppercase text-slate-600">Maskable icon</p>
                            <div class="mt-3 space-y-2">
                                <div>
                                    <label class="settings-label" for="twa_icon_maskable_url">Maskable URL</label>
                                    <input id="twa_icon_maskable_url" type="text" name="twa_icon_maskable_url" x-model="iconMaskableUrl" class="app-input mt-1" maxlength="500">
                                </div>
                                <div>
                                    <label class="settings-label" for="twa_icon_maskable_file">Upload PNG</label>
                                    <input id="twa_icon_maskable_file" type="file" name="twa_icon_maskable_file" class="app-input mt-1" accept="image/png" @change="onIconFileChanged($event, 'maskable')">
                                </div>
                            </div>
                            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                <template x-if="iconSrc(iconMaskableUrl, maskablePreview) !== ''">
                                    <img :src="iconSrc(iconMaskableUrl, maskablePreview)" alt="Maskable preview" class="h-24 w-full object-contain">
                                </template>
                                <template x-if="iconSrc(iconMaskableUrl, maskablePreview) === ''">
                                    <div class="flex h-24 items-center justify-center text-xs text-slate-400">No maskable icon set</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Android Verification --}}
                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">Android verification</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="settings-label" for="twa_package_name">Android package name</label>
                            <input id="twa_package_name" type="text" name="twa_package_name" x-model="packageName" class="app-input mt-1" maxlength="200">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="settings-label" for="twa_sha256_fingerprints_text">SHA-256 certificate fingerprints</label>
                            <textarea id="twa_sha256_fingerprints_text" name="twa_sha256_fingerprints_text" x-model="fingerprintsText" rows="6" class="app-textarea mt-1 font-mono text-xs"></textarea>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs text-slate-500">Enter one fingerprint per line. Comma-separated values are also supported.</p>
                                <button type="button" @click="normalizeFingerprints()" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">Normalize &amp; dedupe</button>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700" x-text="`Total: ${fingerprintStats.total}`"></span>
                                <span class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700" x-text="`Valid: ${fingerprintStats.valid}`"></span>
                                <span class="rounded bg-rose-100 px-2 py-0.5 font-semibold text-rose-700" x-text="`Invalid: ${fingerprintStats.invalid}`"></span>
                            </div>
                            <p x-show="fingerprintStats.invalid > 0" x-cloak class="mt-1 break-all text-[11px] text-rose-600" x-text="`First invalid entry: ${fingerprintStats.firstInvalid}`"></p>
                        </div>
                    </div>
                </div>

                {{-- Splash Screen & Build Config --}}
                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">Splash screen & Android build config</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="settings-label" for="twa_navigation_color">Navigation bar color</label>
                            <input id="twa_navigation_color" type="text" name="twa_navigation_color" x-model="navigationColor" class="app-input mt-1" maxlength="7">
                        </div>
                        <div>
                            <label class="settings-label" for="twa_splash_fade_duration">Splash fade-out (ms)</label>
                            <input id="twa_splash_fade_duration" type="number" name="twa_splash_fade_duration" x-model="splashFadeDuration" class="app-input mt-1" min="0" max="3000" step="50">
                        </div>
                        <div>
                            <label class="settings-label" for="twa_app_version_name">Version name</label>
                            <input id="twa_app_version_name" type="text" name="twa_app_version_name" x-model="appVersionName" class="app-input mt-1" maxlength="40">
                        </div>
                        <div>
                            <label class="settings-label" for="twa_app_version_code">Version code</label>
                            <input id="twa_app_version_code" type="number" name="twa_app_version_code" x-model="appVersionCode" class="app-input mt-1" min="1">
                        </div>
                        <div>
                            <label class="settings-label" for="twa_min_sdk_version">Min Android SDK</label>
                            <select id="twa_min_sdk_version" name="twa_min_sdk_version" x-model="minSdkVersion" class="app-select mt-1">
                                @foreach ([19 => 'API 19 (Android 4.4)', 21 => 'API 21 (Android 5.0)', 23 => 'API 23 (Android 6.0)', 24 => 'API 24 (Android 7.0)', 26 => 'API 26 (Android 8.0)', 28 => 'API 28 (Android 9)', 29 => 'API 29 (Android 10)', 30 => 'API 30 (Android 11)', 31 => 'API 31 (Android 12)', 33 => 'API 33 (Android 13)', 34 => 'API 34 (Android 14)'] as $api => $label)
                                    <option value="{{ $api }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <div class="flex h-28 flex-col items-center justify-center gap-2 p-4" :style="`background-color: ${backgroundColor}`">
                            <template x-if="iconSrc(iconUrl, iconPreview) !== ''">
                                <img :src="iconSrc(iconUrl, iconPreview)" alt="Splash icon" class="h-16 w-16 rounded-2xl object-contain shadow-md">
                            </template>
                            <template x-if="iconSrc(iconUrl, iconPreview) === ''">
                                <div class="h-16 w-16 rounded-2xl flex items-center justify-center text-3xl">📱</div>
                            </template>
                        </div>
                        <div class="h-5" :style="`background-color: ${navigationColor}`"></div>
                    </div>
                </div>

                {{-- App Signing Credentials --}}
                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">App signing credentials</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="twa_signing_key_alias">Keystore alias</label>
                            <input id="twa_signing_key_alias" type="text" name="twa_signing_key_alias" x-model="signingKeyAlias" class="app-input mt-1" maxlength="80">
                        </div>
                        <div>
                            <label class="settings-label" for="twa_keystore_store_type">Store type</label>
                            <select id="twa_keystore_store_type" name="twa_keystore_store_type" x-model="keystoreStoreType" class="app-select mt-1">
                                <option value="PKCS12">PKCS12 (recommended)</option>
                                <option value="JKS">JKS (legacy)</option>
                            </select>
                        </div>
                        <div>
                            <label class="settings-label" for="twa_keystore_password">Keystore password</label>
                            <div class="relative mt-1">
                                <input id="twa_keystore_password" type="password" name="twa_keystore_password" value="" class="app-input pr-14" maxlength="200" placeholder="Leave blank to keep existing">
                                @if ($keystorePasswordSet)
                                    <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">SET</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="settings-label" for="twa_key_password">Key password</label>
                            <div class="relative mt-1">
                                <input id="twa_key_password" type="password" name="twa_key_password" value="" class="app-input pr-14" maxlength="200" placeholder="Leave blank to keep existing">
                                @if ($keyPasswordSet)
                                    <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">SET</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Certificate DN --}}
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <p class="text-sm font-semibold text-slate-800">Certificate distinguished name</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="settings-label" for="twa_key_full_name">Full name (CN)</label>
                                <input id="twa_key_full_name" type="text" name="twa_key_full_name" x-model="keyFullName" class="app-input mt-1" maxlength="255">
                            </div>
                            <div>
                                <label class="settings-label" for="twa_key_org">Organization (O)</label>
                                <input id="twa_key_org" type="text" name="twa_key_org" x-model="keyOrg" class="app-input mt-1" maxlength="255">
                            </div>
                            <div>
                                <label class="settings-label" for="twa_key_org_unit">Org unit (OU)</label>
                                <input id="twa_key_org_unit" type="text" name="twa_key_org_unit" x-model="keyOrgUnit" class="app-input mt-1" maxlength="255">
                            </div>
                            <div>
                                <label class="settings-label" for="twa_key_country">Country code (C)</label>
                                <input id="twa_key_country" type="text" name="twa_key_country" x-model="keyCountry" @input="keyCountry = ($event.target.value.toUpperCase()).slice(0,2)" class="app-input mt-1" maxlength="2">
                            </div>
                            <div>
                                <label class="settings-label" for="twa_key_state">State / Province (ST)</label>
                                <input id="twa_key_state" type="text" name="twa_key_state" x-model="keyState" class="app-input mt-1" maxlength="255">
                            </div>
                            <div>
                                <label class="settings-label" for="twa_key_city">City / Locality (L)</label>
                                <input id="twa_key_city" type="text" name="twa_key_city" x-model="keyCity" class="app-input mt-1" maxlength="255">
                            </div>
                        </div>
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] font-semibold text-slate-700">Generated keytool command</p>
                                <button type="button" @click="copyKeytool()" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100" :class="keytoolCopied ? 'border-emerald-300 text-emerald-700' : ''">
                                    <span x-text="keytoolCopied ? 'Copied!' : 'Copy'"></span>
                                </button>
                            </div>
                            <pre class="select-all whitespace-pre-wrap break-all p-3 text-[11px] leading-relaxed text-slate-700" x-ref="keytoolPre" x-text="keytoolCmd()"></pre>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-xs text-indigo-800">
                    <p class="font-semibold">Runtime endpoints</p>
                    <p class="mt-1">Manifest URL: <a href="{{ $manifestRuntimeUrl }}" target="_blank" class="font-semibold underline">{{ $manifestRuntimeUrl }}</a></p>
                    <p class="mt-1">Asset links URL: <a href="{{ $assetlinksRuntimeUrl }}" target="_blank" class="font-semibold underline">{{ $assetlinksRuntimeUrl }}</a></p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save TWA Settings</button>
            </div>
        </form>

        {{-- Validate Configuration --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-display text-base font-bold text-slate-900">Validate configuration</p>
                    <p class="mt-0.5 text-xs text-slate-500">Checks form-field formats and fetches live endpoint responses.</p>
                </div>
                <button type="button" @click="validate()" :disabled="validating" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60">
                    <svg x-show="!validating" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    <svg x-show="validating" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 animate-spin"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0V5.36l.31.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd"/></svg>
                    <span x-text="validating ? 'Checking...' : 'Run checks'"></span>
                </button>
            </div>
            <div x-show="validationResults !== null" x-cloak class="mt-5 space-y-2">
                <template x-for="check in (validationResults || {}).checks || []" :key="check.label">
                    <div class="flex items-start gap-3 rounded-xl border px-3 py-2.5" :class="check.pass ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
                        <span class="mt-px shrink-0 text-base font-black leading-none" :class="check.pass ? 'text-emerald-500' : 'text-rose-500'" x-text="check.pass ? '✓' : '✗'"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold" :class="check.pass ? 'text-emerald-800' : 'text-rose-800'" x-text="check.label"></p>
                            <p class="mt-0.5 break-all text-xs" :class="check.pass ? 'text-emerald-600' : 'text-rose-700'" x-text="check.detail"></p>
                        </div>
                    </div>
                </template>
                <div x-show="(validationResults || {}).allPassed" class="mt-3 flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    All checks passed — configuration looks good.
                </div>
                <div x-show="!(validationResults || {}).allPassed" class="mt-3 flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    Some checks need attention — review the items above.
                </div>
            </div>
        </div>

        {{-- JSON Preview Panels --}}
        <div class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between rounded-t-3xl border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900">Manifest JSON preview</p>
                        <p class="text-xs text-slate-500">Updates live as you edit fields</p>
                    </div>
                    <button type="button" @click="copyManifest()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" :class="manifestCopied ? 'border-emerald-300 text-emerald-700' : ''">
                        <span x-text="manifestCopied ? 'Copied!' : 'Copy JSON'"></span>
                    </button>
                </div>
                <div class="max-h-[28rem] overflow-auto p-4">
                    <pre class="select-all text-[11px] leading-relaxed text-slate-700" x-ref="manifestPre" x-text="manifestPreviewText"></pre>
                </div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between rounded-t-3xl border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900">Asset links JSON preview</p>
                        <p class="text-xs text-slate-500">Requires TWA enabled + package + valid fingerprints</p>
                    </div>
                    <button type="button" @click="copyAsset()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" :class="assetCopied ? 'border-emerald-300 text-emerald-700' : ''">
                        <span x-text="assetCopied ? 'Copied!' : 'Copy JSON'"></span>
                    </button>
                </div>
                <div class="max-h-[28rem] overflow-auto p-4">
                    <div x-show="assetLinksPreview.length === 0" class="flex min-h-[6rem] items-center justify-center text-center text-xs text-slate-400">
                        Enable TWA, enter a valid package name, and add at least one fingerprint.
                    </div>
                    <pre x-show="assetLinksPreview.length > 0" class="select-all text-[11px] leading-relaxed text-slate-700" x-ref="assetPre" x-text="assetPreviewText"></pre>
                </div>
            </div>
        </div>

        {{-- Bubblewrap Config --}}
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 rounded-t-3xl border-b border-slate-200 bg-slate-50 px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-slate-900">Bubblewrap twa-manifest.json</p>
                    <p class="text-xs text-slate-500">Copy into your Bubblewrap project root and run bubblewrap build</p>
                </div>
                <button type="button" @click="copyBubblewrap()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" :class="bubblewrapCopied ? 'border-emerald-300 text-emerald-700' : ''">
                    <span x-text="bubblewrapCopied ? 'Copied!' : 'Copy JSON'"></span>
                </button>
            </div>
            <div class="max-h-[36rem] overflow-auto p-4">
                <pre class="select-all text-[11px] leading-relaxed text-slate-700" x-ref="bubblewrapPre" x-text="bubblewrapPreviewText"></pre>
            </div>
            <div class="rounded-b-3xl border-t border-slate-200 bg-amber-50 px-4 py-3">
                <p class="text-[11px] font-semibold text-amber-800">Generate AAB & APK with Bubblewrap CLI</p>
                <ol class="mt-1.5 list-decimal space-y-1 pl-4 text-[11px] text-amber-700">
                    <li>Install CLI: <code class="font-mono">npm i -g @bubblewrap/cli</code></li>
                    <li>Save JSON as <code class="font-mono">twa-manifest.json</code> in a new empty folder</li>
                    <li>Run: <code class="font-mono">bubblewrap build</code></li>
                    <li>Upload the <code class="font-mono">.aab</code> to Google Play Console</li>
                </ol>
            </div>
        </div>
    </section>

    {{-- EMAIL / SMTP SECTION --}}
    <section x-show="tab === 'email'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="email">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Email / SMTP</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="settings-label" for="mail_driver">Mail driver</label>
                        <select id="mail_driver" name="mail_driver" class="app-select mt-1">
                            @foreach (['log', 'smtp', 'mailgun', 'ses', 'sendmail'] as $driver)
                                <option value="{{ $driver }}" @selected($field('mail_driver', 'log') === $driver)>{{ strtoupper($driver) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="mail_from_name">From name</label>
                        <input id="mail_from_name" type="text" name="mail_from_name" value="{{ $field('mail_from_name', 'Unsell') }}" class="app-input mt-1" maxlength="100">
                    </div>
                    <div>
                        <label class="settings-label" for="mail_from_address">From address</label>
                        <input id="mail_from_address" type="email" name="mail_from_address" value="{{ $field('mail_from_address', 'no-reply@unsell.test') }}" class="app-input mt-1" maxlength="200">
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">SMTP settings</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="settings-label" for="mail_smtp_host">SMTP host</label>
                            <input id="mail_smtp_host" type="text" name="mail_smtp_host" value="{{ $field('mail_smtp_host') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="mail_smtp_port">SMTP port</label>
                            <input id="mail_smtp_port" type="number" name="mail_smtp_port" value="{{ $field('mail_smtp_port', 587) }}" min="1" max="65535" class="app-input mt-1">
                        </div>
                        <div>
                            <label class="settings-label" for="mail_smtp_username">SMTP username</label>
                            <input id="mail_smtp_username" type="text" name="mail_smtp_username" value="{{ $field('mail_smtp_username') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="mail_smtp_encryption">SMTP encryption</label>
                            <select id="mail_smtp_encryption" name="mail_smtp_encryption" class="app-select mt-1">
                                @foreach (['tls', 'ssl', 'starttls', 'none'] as $encryption)
                                    <option value="{{ $encryption }}" @selected($field('mail_smtp_encryption', 'tls') === $encryption)>{{ strtoupper($encryption) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-4">
                            <label class="settings-label" for="mail_smtp_password">SMTP password</label>
                            <input id="mail_smtp_password" type="password" name="mail_smtp_password" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing">
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                    <p class="text-sm font-semibold text-slate-800">Mailgun settings</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="mail_mailgun_domain">Mailgun domain</label>
                            <input id="mail_mailgun_domain" type="text" name="mail_mailgun_domain" value="{{ $field('mail_mailgun_domain') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="mail_mailgun_secret">Mailgun API key</label>
                            <input id="mail_mailgun_secret" type="password" name="mail_mailgun_secret" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Email Settings</button>
            </div>
        </form>
    </section>

    {{-- SECURITY SECTION --}}
    <section x-show="tab === 'security'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="security">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Security</h2>

                <div class="mt-5 flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div><p class="text-sm font-semibold text-slate-800">Enable reCAPTCHA</p></div>
                    <div class="ml-4 flex-shrink-0">
                        <input type="hidden" name="recaptcha_enabled" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="recaptcha_enabled" value="1" class="peer sr-only" @checked($oldBool('recaptcha_enabled'))>
                            <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="settings-label" for="recaptcha_version">reCAPTCHA version</label>
                        <select id="recaptcha_version" name="recaptcha_version" class="app-select mt-1">
                            @foreach (['v2', 'v3'] as $version)
                                <option value="{{ $version }}" @selected($field('recaptcha_version', 'v3') === $version)>{{ strtoupper($version) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="max_login_attempts">Max login attempts</label>
                        <input id="max_login_attempts" type="number" name="max_login_attempts" value="{{ $field('max_login_attempts', 5) }}" min="1" max="100" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="login_lockout_minutes">Lockout minutes</label>
                        <input id="login_lockout_minutes" type="number" name="login_lockout_minutes" value="{{ $field('login_lockout_minutes', 15) }}" min="1" max="1440" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="recaptcha_site_key">reCAPTCHA site key</label>
                        <input id="recaptcha_site_key" type="text" name="recaptcha_site_key" value="{{ $field('recaptcha_site_key') }}" class="app-input mt-1" maxlength="100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="recaptcha_secret_key">reCAPTCHA secret key</label>
                        <input id="recaptcha_secret_key" type="password" name="recaptcha_secret_key" value="" class="app-input mt-1" maxlength="100" placeholder="Leave blank to keep existing">
                    </div>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Security</button>
            </div>
        </form>
    </section>

    {{-- MARKETING & SEO SECTION --}}
    <section x-show="tab === 'marketing'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="marketing">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">SEO & Analytics</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="seo_meta_description">Default meta description</label>
                        <textarea id="seo_meta_description" name="seo_meta_description" class="app-textarea mt-1" maxlength="320">{{ $field('seo_meta_description') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="seo_meta_keywords">Keywords (comma separated)</label>
                        <input id="seo_meta_keywords" type="text" name="seo_meta_keywords" value="{{ $field('seo_meta_keywords') }}" class="app-input mt-1" maxlength="500">
                    </div>
                    <div>
                        <label class="settings-label" for="seo_robots">Robots</label>
                        <select id="seo_robots" name="seo_robots" class="app-select mt-1">
                            @foreach (['index,follow', 'noindex,follow', 'noindex,nofollow'] as $robots)
                                <option value="{{ $robots }}" @selected($field('seo_robots', 'index,follow') === $robots)>{{ $robots }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="seo_google_analytics_id">Google Analytics ID (GA4)</label>
                        <input id="seo_google_analytics_id" type="text" name="seo_google_analytics_id" value="{{ $field('seo_google_analytics_id') }}" class="app-input mt-1" maxlength="40">
                        <p class="mt-1 text-xs {{ $activeGoogleAnalyticsId !== '' ? 'text-emerald-600' : 'text-amber-600' }}">Active GA ID: <span class="font-semibold">{{ $activeGoogleAnalyticsId ?: 'Not set' }}</span></p>
                    </div>
                    <div>
                        <label class="settings-label" for="seo_google_site_verification">Google verification token</label>
                        <input id="seo_google_site_verification" type="text" name="seo_google_site_verification" value="{{ $field('seo_google_site_verification') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="seo_bing_site_verification">Bing verification token</label>
                        <input id="seo_bing_site_verification" type="text" name="seo_bing_site_verification" value="{{ $field('seo_bing_site_verification') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="facebook_pixel_id">Facebook Pixel ID</label>
                        <input id="facebook_pixel_id" type="text" name="facebook_pixel_id" value="{{ $field('facebook_pixel_id') }}" class="app-input mt-1" maxlength="50">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="og_image_url">Default OG image URL</label>
                        <input id="og_image_url" type="url" name="og_image_url" value="{{ $field('og_image_url') }}" class="app-input mt-1" maxlength="500">
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">Google Ads</h2>

                <div class="mt-5 flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div><p class="text-sm font-semibold text-slate-800">Enable Google Ads</p></div>
                    <div class="ml-4 flex-shrink-0">
                        <input type="hidden" name="adsense_enabled" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="adsense_enabled" value="1" class="peer sr-only" @checked($bool('adsense_enabled'))>
                            <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="settings-label" for="adsense_client_id">Google publisher ID</label>
                        <input id="adsense_client_id" type="text" name="adsense_client_id" value="{{ $field('adsense_client_id') }}" class="app-input mt-1" maxlength="80">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_slot_id">Fallback ad unit / slot ID</label>
                        <input id="adsense_slot_id" type="text" name="adsense_slot_id" value="{{ $field('adsense_slot_id') }}" class="app-input mt-1" maxlength="255">
                    </div>
                </div>

                <div class="mt-5">
                    <p class="settings-label mb-2">Banner ad locations</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach (['display' => 'Display', 'feed' => 'In-feed', 'article' => 'In-article', 'guest' => 'Guest/Auth', 'top' => 'Top', 'bottom' => 'Bottom'] as $key => $label)
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition {{ in_array($key, $adLocations, true) ? 'border-orange-400 bg-orange-50 text-orange-700' : 'border-slate-200 bg-white text-slate-600' }}">
                                <input type="checkbox" name="adsense_locations[]" value="{{ $key }}" class="rounded accent-orange-500" @checked(in_array($key, $adLocations, true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="settings-label" for="adsense_banner_slot_top">Top banner slot ID</label>
                        <input id="adsense_banner_slot_top" type="text" name="adsense_banner_slot_top" value="{{ $field('adsense_banner_slot_top') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_banner_slot_bottom">Bottom banner slot ID</label>
                        <input id="adsense_banner_slot_bottom" type="text" name="adsense_banner_slot_bottom" value="{{ $field('adsense_banner_slot_bottom') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_banner_slot_guest">Guest/Auth slot ID</label>
                        <input id="adsense_banner_slot_guest" type="text" name="adsense_banner_slot_guest" value="{{ $field('adsense_banner_slot_guest') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_native_slot_id">In-feed slot ID</label>
                        <input id="adsense_native_slot_id" type="text" name="adsense_native_slot_id" value="{{ $field('adsense_native_slot_id') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_article_slot_id">In-article slot ID</label>
                        <input id="adsense_article_slot_id" type="text" name="adsense_article_slot_id" value="{{ $field('adsense_article_slot_id') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_display_slot_id">Display slot ID</label>
                        <input id="adsense_display_slot_id" type="text" name="adsense_display_slot_id" value="{{ $field('adsense_display_slot_id') }}" class="app-input mt-1" maxlength="255">
                    </div>
                    <div>
                        <label class="settings-label" for="adsense_feed_rows_interval">In-feed banner every N rows</label>
                        <input id="adsense_feed_rows_interval" type="number" name="adsense_feed_rows_interval" value="{{ $field('adsense_feed_rows_interval', 2) }}" min="1" max="10" class="app-input mt-1">
                    </div>
                </div>

                {{-- Interstitial Ads --}}
                <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-800">Enable interstitial ads</p></div>
                        <div>
                            <input type="hidden" name="adsense_interstitial_enabled" value="0">
                            <input type="checkbox" name="adsense_interstitial_enabled" value="1" class="rounded accent-orange-500" @checked($bool('adsense_interstitial_enabled'))>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="adsense_interstitial_slot_id">Interstitial slot ID</label>
                            <input id="adsense_interstitial_slot_id" type="text" name="adsense_interstitial_slot_id" value="{{ $field('adsense_interstitial_slot_id') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="adsense_interstitial_clicks">Show after clicks</label>
                            <input id="adsense_interstitial_clicks" type="number" name="adsense_interstitial_clicks" value="{{ $field('adsense_interstitial_clicks', 6) }}" min="1" max="1000" class="app-input mt-1">
                        </div>
                    </div>
                </div>

                {{-- Reward Ads --}}
                <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-800">Enable reward ads</p></div>
                        <div>
                            <input type="hidden" name="adsense_reward_enabled" value="0">
                            <input type="checkbox" name="adsense_reward_enabled" value="1" class="rounded accent-orange-500" @checked($bool('adsense_reward_enabled'))>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="adsense_reward_slot_id">Reward slot ID</label>
                            <input id="adsense_reward_slot_id" type="text" name="adsense_reward_slot_id" value="{{ $field('adsense_reward_slot_id') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="adsense_reward_slot_id_secondary">Reward slot ID (secondary)</label>
                            <input id="adsense_reward_slot_id_secondary" type="text" name="adsense_reward_slot_id_secondary" value="{{ $field('adsense_reward_slot_id_secondary') }}" class="app-input mt-1" maxlength="255">
                        </div>
                        <div>
                            <label class="settings-label" for="adsense_reward_clicks">Show after clicks</label>
                            <input id="adsense_reward_clicks" type="number" name="adsense_reward_clicks" value="{{ $field('adsense_reward_clicks', 10) }}" min="1" max="1000" class="app-input mt-1">
                        </div>
                    </div>
                </div>

                {{-- App-open Ad --}}
                <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">App-open style ad slot</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="settings-label" for="adsense_app_open_slot_id">App-open slot ID</label>
                                <input id="adsense_app_open_slot_id" type="text" name="adsense_app_open_slot_id" value="{{ $field('adsense_app_open_slot_id') }}" class="app-input mt-1" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Test Controls --}}
                <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-800">QA: Test Ad Triggers</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" data-ad-test-trigger="interstitial" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Show Interstitial Now</button>
                        <button type="button" data-ad-test-prime="interstitial" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Prime Interstitial</button>
                        <button type="button" data-ad-test-trigger="reward" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Show Reward Now</button>
                        <button type="button" data-ad-test-trigger="rewardSecondary" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Show Reward 2 Now</button>
                        <button type="button" data-ad-test-prime="reward" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Prime Reward</button>
                        <button type="button" data-ad-test-trigger="appOpen" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Show App-open Now</button>
                        <button type="button" data-ad-test-reset class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">Reset Counter</button>
                    </div>
                    <p data-ad-test-feedback class="mt-3 text-xs font-semibold text-emerald-600"></p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save Marketing & SEO</button>
            </div>
        </form>
    </section>

    {{-- AI SUITE SECTION --}}
    <section x-show="tab === 'ai'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="ai">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">AI Platform</h2>

                <div class="mt-5 flex items-start justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Enable AI Suite</p>
                        <p class="mt-0.5 text-xs text-slate-500">Master switch for all AI capabilities.</p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <input type="hidden" name="ai_enabled" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="ai_enabled" value="1" class="peer sr-only" @checked($oldBool('ai_enabled'))>
                            <div class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="settings-label" for="ai_provider">AI provider</label>
                        <select id="ai_provider" name="ai_provider" class="app-select mt-1">
                            <option value="gemini" @selected($field('ai_provider', 'gemini') === 'gemini')>Gemini (real provider)</option>
                            <option value="mock" @selected($field('ai_provider', 'gemini') === 'mock')>Mock (local heuristics)</option>
                        </select>
                    </div>
                    <div>
                        <label class="settings-label" for="ai_gemini_model">Gemini text model</label>
                        <input id="ai_gemini_model" type="text" name="ai_gemini_model" value="{{ $field('ai_gemini_model', 'gemini-2.5-pro') }}" class="app-input mt-1" maxlength="80">
                    </div>
                    <div>
                        <label class="settings-label" for="ai_gemini_vision_model">Gemini vision model</label>
                        <input id="ai_gemini_vision_model" type="text" name="ai_gemini_vision_model" value="{{ $field('ai_gemini_vision_model', 'gemini-2.5-pro') }}" class="app-input mt-1" maxlength="80">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="settings-label" for="ai_gemini_api_key">Gemini API key</label>
                        <input id="ai_gemini_api_key" type="password" name="ai_gemini_api_key" value="" class="app-input mt-1" maxlength="255" placeholder="Leave blank to keep existing">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3 rounded-2xl border border-slate-200 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Force real provider when key exists</p>
                                <p class="mt-0.5 text-xs text-slate-500">Use Gemini whenever a key is available.</p>
                            </div>
                            <div>
                                <input type="hidden" name="ai_force_real_provider" value="0">
                                <input type="checkbox" name="ai_force_real_provider" value="1" class="rounded accent-orange-500" @checked(filter_var((string) $field('ai_force_real_provider', '1'), FILTER_VALIDATE_BOOLEAN))>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">AI Products & Features</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_listing_assistant_enabled" value="0">
                        <input type="checkbox" name="ai_listing_assistant_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('ai_listing_assistant_enabled'))>
                        Listing Assistant
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_compass_enabled" value="0">
                        <input type="checkbox" name="ai_compass_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('ai_compass_enabled'))>
                        CompassGPT
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_autoiq_enabled" value="0">
                        <input type="checkbox" name="ai_autoiq_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('ai_autoiq_enabled'))>
                        AutoIQ
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_fraud_detection_enabled" value="0">
                        <input type="checkbox" name="ai_fraud_detection_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('ai_fraud_detection_enabled'))>
                        Fraud Detection
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_personalization_enabled" value="0">
                        <input type="checkbox" name="ai_personalization_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('ai_personalization_enabled'))>
                        Personalized feed
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_job_matching_enabled" value="0">
                        <input type="checkbox" name="ai_job_matching_enabled" value="1" class="rounded accent-orange-500" @checked($oldBool('ai_job_matching_enabled'))>
                        Job matching
                    </label>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="settings-label" for="ai_compass_max_results">Compass max results</label>
                        <input id="ai_compass_max_results" type="number" name="ai_compass_max_results" value="{{ $field('ai_compass_max_results', 5) }}" min="1" max="20" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="ai_confidence_threshold">Fraud block threshold</label>
                        <input id="ai_confidence_threshold" type="number" name="ai_confidence_threshold" value="{{ $field('ai_confidence_threshold', 70) }}" min="1" max="100" class="app-input mt-1">
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">AI SEO Rank Engine</h2>
                        <p class="mt-1 text-sm text-slate-500">Continuously audits listing data and refreshes SEO metadata.</p>
                    </div>
                    <button type="submit" formaction="{{ route('admin.settings.ai-seo.run') }}" formmethod="POST" class="rounded-xl border border-orange-300 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700 hover:bg-orange-100">
                        Run AI SEO Audit Now
                    </button>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_seo_optimizer_enabled" value="0">
                        <input type="checkbox" name="ai_seo_optimizer_enabled" value="1" class="rounded accent-orange-500" @checked(filter_var((string) $field('ai_seo_optimizer_enabled', '1'), FILTER_VALIDATE_BOOLEAN))>
                        Enable AI SEO optimizer
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_seo_auto_apply_enabled" value="0">
                        <input type="checkbox" name="ai_seo_auto_apply_enabled" value="1" class="rounded accent-orange-500" @checked(filter_var((string) $field('ai_seo_auto_apply_enabled', '1'), FILTER_VALIDATE_BOOLEAN))>
                        Auto-apply updates
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="ai_seo_generate_sitemap" value="0">
                        <input type="checkbox" name="ai_seo_generate_sitemap" value="1" class="rounded accent-orange-500" @checked(filter_var((string) $field('ai_seo_generate_sitemap', '1'), FILTER_VALIDATE_BOOLEAN))>
                        Keep sitemap automation
                    </label>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="settings-label" for="ai_seo_audit_interval_minutes">Audit interval (minutes)</label>
                        <input id="ai_seo_audit_interval_minutes" type="number" name="ai_seo_audit_interval_minutes" value="{{ $field('ai_seo_audit_interval_minutes', 60) }}" min="5" max="1440" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="ai_seo_lookback_days">Data lookback (days)</label>
                        <input id="ai_seo_lookback_days" type="number" name="ai_seo_lookback_days" value="{{ $field('ai_seo_lookback_days', 30) }}" min="7" max="180" class="app-input mt-1">
                    </div>
                    <div>
                        <label class="settings-label" for="ai_seo_max_keywords">Max SEO keywords</label>
                        <input id="ai_seo_max_keywords" type="number" name="ai_seo_max_keywords" value="{{ $field('ai_seo_max_keywords', 14) }}" min="5" max="40" class="app-input mt-1">
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase text-slate-500">Last Run</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $field('ai_seo_last_run_at', 'Not yet') ?: 'Not yet' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase text-slate-500">Provider</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ strtoupper((string) $field('ai_seo_last_provider', 'n/a')) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase text-slate-500">SEO Score</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ (int) $field('ai_seo_last_score', 0) }}/100</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-[11px] font-semibold uppercase text-slate-500">Latest Summary</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $field('ai_seo_last_summary', 'No audit summary available yet.') }}</p>
                    </div>
                    @if ($aiSeoLastKeywords)
                        <div class="mt-4">
                            <p class="text-[11px] font-semibold uppercase text-slate-500">Latest Keywords</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($aiSeoLastKeywords as $keyword)
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ $keyword }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($aiSeoLastActions)
                        <div class="mt-4">
                            <p class="text-[11px] font-semibold uppercase text-slate-500">Latest Action Plan</p>
                            <ol class="mt-2 list-decimal space-y-1 pl-4 text-sm text-slate-700">
                                @foreach ($aiSeoLastActions as $action)
                                    <li>{{ $action }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save AI Settings</button>
            </div>
        </form>
    </section>

    {{-- APP UPDATE SECTION --}}
    <section x-show="tab === 'app'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="settings_section" value="app">

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">App Force Update</h2>
                <p class="mt-1 text-sm text-slate-500">Control which app versions are forced to update before use.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="settings-label" for="app_latest_version">Latest Version</label>
                        <input id="app_latest_version" type="text" name="app_latest_version" value="{{ $field('app_latest_version', '1.0.0') }}" class="app-input mt-1" maxlength="20" pattern="^\d+\.\d+\.\d+$">
                    </div>
                    <div>
                        <label class="settings-label" for="app_min_version">Min Version</label>
                        <input id="app_min_version" type="text" name="app_min_version" value="{{ $field('app_min_version', '1.0.0') }}" class="app-input mt-1" maxlength="20" pattern="^\d+\.\d+\.\d+$">
                    </div>
                </div>

                <div class="mt-5">
                    <label class="settings-label" for="app_play_store_url">Play Store URL</label>
                    <input id="app_play_store_url" type="url" name="app_play_store_url" value="{{ $field('app_play_store_url', 'https://play.google.com/store/apps/details?id=') }}" class="app-input mt-1" maxlength="500">
                </div>

                <div class="mt-5">
                    <label class="settings-label" for="app_force_update_msg">Force Update Message</label>
                    <textarea id="app_force_update_msg" name="app_force_update_msg" rows="3" class="app-input mt-1" maxlength="300">{{ $field('app_force_update_msg', 'A required update is available. Please update the app to continue.') }}</textarea>
                </div>

                <div class="mt-6 rounded-2xl border border-dashed border-orange-300 bg-orange-50 p-4">
                    <p class="text-xs font-semibold uppercase text-orange-600">Dialog Preview</p>
                    <div class="mt-3 max-w-xs rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 3v13m-4-4l4 4 4-4"/></svg>
                            <span class="text-sm font-bold text-slate-800">Update Required</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-600">{{ $field('app_force_update_msg', 'A required update is available. Please update the app to continue.') }}</p>
                        <div class="mt-3 flex items-center justify-end gap-1 rounded-lg bg-orange-500 px-3 py-2">
                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            <span class="text-xs font-semibold text-white">Update Now</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Save App Update Settings</button>
            </div>
        </form>
    </section>

    {{-- LICENSE SECTION --}}
    <section x-show="tab === 'license'" class="mt-5" x-cloak>
        <form method="POST" action="{{ route('admin.settings.license.verify') }}" class="space-y-5">
            @csrf

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-lg font-bold text-slate-900">CodeCanyon License Verification</h2>
                <p class="mt-1 text-sm text-slate-500">Verify your CodeCanyon purchase code to activate premium features.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="settings-label" for="codecanyon_purchase_code">Purchase Code</label>
                        <input id="codecanyon_purchase_code" type="text" name="codecanyon_purchase_code" value="{{ old('codecanyon_purchase_code') }}" class="app-input mt-1" maxlength="100" required>
                        <x-input-error :messages="$errors->get('codecanyon_purchase_code')" class="mt-2" />
                    </div>
                    <div>
                        <label class="settings-label" for="codecanyon_buyer_username">Buyer Username</label>
                        <input id="codecanyon_buyer_username" type="text" name="codecanyon_buyer_username" value="{{ old('codecanyon_buyer_username', $field('codecanyon_buyer_username', '')) }}" class="app-input mt-1" maxlength="50" required>
                        <x-input-error :messages="$errors->get('codecanyon_buyer_username')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-5">
                    <label class="settings-label" for="codecanyon_personal_token">Personal Token</label>
                    <input id="codecanyon_personal_token" type="password" name="codecanyon_personal_token" value="{{ $field('codecanyon_personal_token', '') }}" class="app-input mt-1" maxlength="100">
                    <x-input-error :messages="$errors->get('codecanyon_personal_token')" class="mt-2" />
                </div>

                @if(\App\Models\AppSetting::getValue('license_verified', false))
                    <div class="mt-5 rounded-xl border border-green-200 bg-green-50 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span class="text-sm font-semibold text-green-800">License Verified</span>
                        </div>
                        <p class="mt-1 text-xs text-green-700">
                            Last verified: {{ \App\Models\AppSetting::getValue('license_last_verified', 'Never') }}
                            @if(\App\Models\AppSetting::getValue('codecanyon_buyer_username'))
                                | Buyer: {{ \App\Models\AppSetting::getValue('codecanyon_buyer_username') }}
                            @endif
                        </p>
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            <span class="text-sm font-semibold text-amber-800">License Not Verified</span>
                        </div>
                        <p class="mt-1 text-xs text-amber-700">Please verify your purchase code to activate all features.</p>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <button type="submit" class="app-btn-primary">Verify License</button>
            </div>
        </form>
    </section>

    @push('scripts')
    <script src="{{ asset('js/admin/settings.js') }}"></script>
    <script src="{{ asset('js/admin/twa.js') }}"></script>
    @endpush
</div>
@endsection
