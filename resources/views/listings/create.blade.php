<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-100 text-orange-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </div>
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">Post a Free Ad</h1>
                <p class="text-sm text-slate-500">Reach thousands of buyers near you</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl pb-[calc(env(safe-area-inset-bottom)+6.5rem)] md:pb-0" x-data="postAdWizard()" x-init="init()">

        {{-- Step Progress Indicator --}}
        <div class="mb-6">
            <div class="flex items-center">
                <div class="flex flex-col items-center gap-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold ring-2 transition-all"
                         :class="step >= 1 ? 'bg-orange-500 text-white ring-orange-200' : 'bg-white text-slate-400 ring-slate-200'">1</div>
                    <span class="text-[11px] font-bold uppercase tracking-wide transition-colors"
                          :class="step === 1 ? 'text-orange-600' : 'text-slate-400'">Category</span>
                </div>
                <div class="mx-2 mb-5 h-0.5 flex-1 transition-colors" :class="step >= 2 ? 'bg-orange-400' : 'bg-slate-200'"></div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold ring-2 transition-all"
                         :class="step >= 2 ? 'bg-orange-500 text-white ring-orange-200' : 'bg-white text-slate-400 ring-slate-200'">2</div>
                    <span class="text-[11px] font-bold uppercase tracking-wide transition-colors"
                          :class="step === 2 ? 'text-orange-600' : 'text-slate-400'">Custom + Details</span>
                </div>
                <div class="mx-2 mb-5 h-0.5 flex-1 transition-colors" :class="step >= 3 ? 'bg-orange-400' : 'bg-slate-200'"></div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold ring-2 transition-all"
                         :class="step >= 3 ? 'bg-orange-500 text-white ring-orange-200' : 'bg-white text-slate-400 ring-slate-200'">3</div>
                    <span class="text-[11px] font-bold uppercase tracking-wide transition-colors"
                          :class="step === 3 ? 'text-orange-600' : 'text-slate-400'">Price + Photos</span>
                </div>
                <div class="mx-2 mb-5 h-0.5 flex-1 transition-colors" :class="step >= 4 ? 'bg-orange-400' : 'bg-slate-200'"></div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold ring-2 transition-all"
                         :class="step >= 4 ? 'bg-orange-500 text-white ring-orange-200' : 'bg-white text-slate-400 ring-slate-200'">4</div>
                    <span class="text-[11px] font-bold uppercase tracking-wide transition-colors"
                          :class="step === 4 ? 'text-orange-600' : 'text-slate-400'">Location + Post</span>
                </div>
            </div>
        </div>

        {{-- Server-side validation errors --}}
        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <p class="font-semibold text-rose-700">Please fix the errors below:</p>
                <ul class="mt-2 list-inside list-disc text-sm text-rose-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="post-ad-form" method="POST" action="{{ route('listings.store') }}"
              enctype="multipart/form-data"
              @submit.prevent="handleSubmit($event)">
            @csrf

            {{-- Hidden inputs managed by Alpine --}}
            <input type="hidden" id="category_id" name="category_id" :value="selectedCategoryId">
            <input type="hidden" name="price_type" :value="priceType">
            <input type="hidden" name="condition" :value="conditionEnabled ? condition : ''">

            {{-- Actual file input (hidden, managed by Alpine photo UI) --}}
            <input type="file" id="photo-file-input" name="images[]" multiple accept="image/*"
                     class="sr-only" @change="addPhotos($event)">

            {{-- =================== STEP 1: CATEGORY =================== --}}
            <div x-show="step === 1" class="space-y-4">

                <div class="app-card">
                    <h2 class="font-display text-xl font-bold text-slate-900">What are you selling?</h2>
                    <p class="mt-1 text-sm text-slate-500">Choose a category so buyers find your ad faster</p>

                    <div class="mt-5 grid grid-cols-3 gap-3 sm:grid-cols-4">
                        <template x-for="parent in categoryTree" :key="parent.id">
                            <button type="button"
                                    @click="selectParent(parent.id)"
                                    class="flex flex-col items-center gap-2 rounded-2xl border-2 p-3 text-center transition-all duration-150"
                                    :class="selectedParentId === parent.id
                                        ? 'border-orange-500 bg-orange-50 shadow-sm shadow-orange-100'
                                        : 'border-slate-200 bg-white hover:border-orange-300 hover:bg-orange-50/40'">
                                <template x-if="parent.icon_url">
                                    <img :src="parent.icon_url" :alt="parent.name"
                                         class="h-10 w-10 rounded-xl object-cover">
                                </template>
                                <template x-if="!parent.icon_url">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-2xl">
                                        🏷️
                                    </div>
                                </template>
                                <span class="text-xs font-semibold leading-tight text-slate-700"
                                      x-text="parent.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Subcategory Picker --}}
                <div x-show="selectedParentId && currentSubcategories.length > 0" x-cloak class="app-card">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Subcategory in
                        <span class="text-orange-500" x-text="selectedParentCategory ? selectedParentCategory.name : ''"></span>
                    </h2>
                    <p class="text-sm text-slate-500">Pick the most specific match</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <template x-for="child in currentSubcategories" :key="child.id">
                            <button type="button"
                                    @click="selectSubcategory(child.id)"
                                    class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-semibold transition-all duration-150"
                                    :class="selectedCategoryId === child.id
                                        ? 'border-orange-500 bg-orange-500 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-orange-400'">
                                <template x-if="child.icon_url">
                                    <img :src="child.icon_url" :alt="child.name"
                                         class="h-5 w-5 rounded-full object-cover">
                                </template>
                                <span x-text="child.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-500" x-show="selectedCategoryId">
                        Selected: <span class="font-semibold text-orange-600" x-text="selectedCategoryLabel"></span>
                    </p>
                    <div class="ml-auto">
                        <button type="button"
                                @click="goToStep2()"
                                :disabled="!selectedCategoryId"
                                class="app-btn-primary hidden md:inline-flex disabled:cursor-not-allowed disabled:opacity-40">
                            Continue
                            <svg class="ml-1.5 inline-block h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- =================== STEP 2: AD DETAILS =================== --}}
            <div x-show="step === 2" x-cloak class="space-y-4">

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="step = 1"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-orange-600 hover:underline">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                        </svg>
                        Change Category
                    </button>
                    <span x-show="selectedCategoryLabel"
                          class="inline-flex items-center rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700"
                          x-text="selectedCategoryLabel"></span>
                </div>

                <div class="app-card space-y-5">
                    <h2 class="font-display text-xl font-bold text-slate-900" x-text="customFieldsHeading"></h2>
                    <p class="text-sm text-slate-500">Fill category-specific details first so your ad looks complete.</p>

                    {{-- Dynamic custom fields for selected category --}}
                    @include('listings.partials.custom-fields', ['customFields' => $customFields])
                </div>

                <div class="app-card space-y-5">
                    <h2 class="font-display text-xl font-bold text-slate-900">Ad Details</h2>

                    {{-- Title --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label for="title" value="Ad Title *" />
                            <span class="text-xs font-semibold tabular-nums"
                                  :class="titleLen > 70 ? 'text-orange-500' : 'text-slate-400'">
                                <span x-text="titleLen"></span>/70
                            </span>
                        </div>
                        <x-text-input
                            id="title" name="title" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('title') }}"
                            required maxlength="140"
                            placeholder="e.g. Samsung Galaxy A54 128GB Blue, 6 months old"
                            @input="titleLen = $event.target.value.length"
                        />
                        <p class="mt-1 text-xs text-slate-400">Clear, specific titles get 3x more responses</p>
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    {{-- Description --}}
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-input-label for="description" value="Description *" />
                            <button type="button"
                                    @click="generateDescriptionFromCustomFields()"
                                    class="inline-flex items-center gap-1 rounded-xl border border-orange-300 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700 hover:bg-orange-100">
                                Generate From Custom Fields
                            </button>
                        </div>
                        <textarea id="description" name="description"
                                  class="app-textarea mt-1" rows="6" required
                                  placeholder="Describe condition, reason for selling, included accessories, warranty status...">{{ old('description') }}</textarea>
                        <p x-show="descriptionHelperMessage"
                           x-cloak
                           x-text="descriptionHelperMessage"
                           class="mt-1 text-xs font-semibold"
                           :class="descriptionHelperError ? 'text-rose-600' : 'text-emerald-700'"></p>
                        <p class="mt-1 text-xs text-slate-400">Be honest — buyers appreciate transparency</p>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>

                    @if ($aiListingAssistantEnabled)
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-sky-900">AI Listing Assistant</p>
                                    <p class="mt-0.5 text-xs text-sky-700">Auto-generates title, description, and attributes from your draft + photos. Typical time savings: 35-55%, quality uplift up to 37%.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="generateAiDraft('all')" :disabled="aiDraftLoading" class="inline-flex items-center rounded-xl bg-sky-700 px-3 py-2 text-xs font-semibold text-white disabled:opacity-60">
                                        <span x-show="!aiDraftLoading">Generate with AI</span>
                                        <span x-show="aiDraftLoading" x-cloak>Generating...</span>
                                    </button>
                                    <button type="button" @click="generateAiDraft('description')" :disabled="aiDraftLoading" class="inline-flex items-center rounded-xl border border-sky-300 bg-white px-3 py-2 text-xs font-semibold text-sky-800 disabled:opacity-60">
                                        <span x-show="!aiDraftLoading">Descriptron</span>
                                        <span x-show="aiDraftLoading" x-cloak>Writing...</span>
                                    </button>
                                    @if ($aiPriceRecommendationEnabled)
                                        <button type="button" @click="fetchAiPriceRecommendation()" :disabled="aiPriceLoading" class="inline-flex items-center rounded-xl border border-sky-300 bg-white px-3 py-2 text-xs font-semibold text-sky-800 disabled:opacity-60">
                                            <span x-show="!aiPriceLoading">Recommend Price</span>
                                            <span x-show="aiPriceLoading" x-cloak>Analyzing...</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <p x-show="aiDraftError" x-text="aiDraftError" x-cloak class="mt-2 text-xs font-semibold text-rose-600"></p>

                            <div x-show="aiDraft" x-cloak class="mt-3 space-y-2 rounded-xl border border-sky-200 bg-white p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-bold uppercase tracking-wide text-sky-700">AI Suggestions</p>
                                    <p class="text-[11px] font-semibold text-slate-500" x-show="aiDraft && aiDraft.provider">
                                        Provider: <span class="uppercase" x-text="aiDraft.provider"></span>
                                    </p>
                                </div>

                                <p class="text-[11px] text-slate-600" x-show="aiDraft && aiDraft.time_savings_percent">
                                    Estimated time saving: <span class="font-semibold" x-text="(aiDraft?.time_savings_percent?.min || 35) + '-' + (aiDraft?.time_savings_percent?.max || 55) + '%' "></span>
                                    | Quality uplift up to <span class="font-semibold" x-text="(aiDraft?.quality_improvement_percent || 37) + '%' "></span>
                                </p>

                                <div class="flex flex-wrap gap-2" x-show="aiDraft">
                                    <button type="button" @click="applyAiField('title')" class="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800">Use Title</button>
                                    <button type="button" @click="applyAiField('description')" class="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800">Use Description</button>
                                    <button type="button" @click="applyAiField('price')" class="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800">Use Price</button>
                                    <button type="button" @click="applyAiField('video_script')" class="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800">Use Video Script</button>
                                </div>

                                <div x-show="aiDraft && aiDraft.duplicate_risk" class="rounded-xl border px-3 py-2"
                                                                         :class="aiDraft?.duplicate_risk === 'high' ? 'border-rose-200 bg-rose-50' : (aiDraft?.duplicate_risk === 'medium' ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50')">
                                    <p class="text-xs font-bold uppercase tracking-wide"
                                                                             :class="aiDraft?.duplicate_risk === 'high' ? 'text-rose-700' : (aiDraft?.duplicate_risk === 'medium' ? 'text-amber-700' : 'text-emerald-700')">
                                                                                Duplicate risk: <span x-text="(aiDraft?.duplicate_risk || 'low').toUpperCase()"></span>
                                    </p>
                                    <div class="mt-2 space-y-1" x-show="aiDraft.duplicate_candidates && aiDraft.duplicate_candidates.length > 0">
                                        <template x-for="candidate in (aiDraft.duplicate_candidates || [])" :key="candidate.listing_id">
                                            <a :href="'/listings/' + candidate.slug" target="_blank" rel="noopener" class="block text-[11px] font-semibold text-slate-700 hover:text-orange-600">
                                                <span x-text="candidate.title"></span>
                                                <span class="text-slate-500"> • Similarity <span x-text="candidate.similarity_score"></span>%</span>
                                            </a>
                                        </template>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-600" x-show="aiDraft && aiDraft.price_recommendation && aiDraft.price_recommendation.suggested_price">
                                    Suggested price: <span class="font-semibold" x-text="formatMoney(aiDraft.price_recommendation.suggested_price)"></span>
                                </p>
                                <div x-show="aiDraft && aiDraft.attributes && aiDraft.attributes.length > 0" class="flex flex-wrap gap-1.5">
                                    <template x-for="attribute in (aiDraft.attributes || [])" :key="attribute.key + attribute.value">
                                        <button type="button" @click="applyAiAttribute(attribute)" class="rounded-full bg-sky-100 px-2 py-1 text-[11px] font-semibold text-sky-800 hover:bg-sky-200" x-text="attribute.key + ': ' + attribute.value"></button>
                                    </template>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2" x-show="aiDraft && ((aiDraft.pros && aiDraft.pros.length > 0) || (aiDraft.tradeoffs && aiDraft.tradeoffs.length > 0))">
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-2" x-show="aiDraft && aiDraft.pros && aiDraft.pros.length > 0">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Pros</p>
                                        <ul class="mt-1 list-disc space-y-1 pl-4 text-[11px] text-emerald-800">
                                            <template x-for="pro in (aiDraft.pros || [])" :key="pro">
                                                <li x-text="pro"></li>
                                            </template>
                                        </ul>
                                    </div>
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-2" x-show="aiDraft && aiDraft.tradeoffs && aiDraft.tradeoffs.length > 0">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Trade-offs</p>
                                        <ul class="mt-1 list-disc space-y-1 pl-4 text-[11px] text-amber-800">
                                            <template x-for="tradeoff in (aiDraft.tradeoffs || [])" :key="tradeoff">
                                                <li x-text="tradeoff"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>

                                <p class="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-[11px] font-semibold text-violet-700" x-show="aiDraft && aiDraft.video_script" x-text="'Video idea: ' + (aiDraft?.video_script || '')"></p>

                                <ul class="list-disc space-y-1 pl-4 text-[11px] text-slate-600" x-show="aiDraft && aiDraft.image_optimization_tips && aiDraft.image_optimization_tips.length > 0">
                                    <template x-for="tip in (aiDraft?.image_optimization_tips || [])" :key="tip">
                                        <li x-text="tip"></li>
                                    </template>
                                </ul>
                                <p class="text-[11px] font-semibold text-violet-700" x-show="aiDraft && aiDraft.ar_camera_hint" x-text="aiDraft?.ar_camera_hint || ''"></p>
                            </div>
                        </div>
                    @endif
                </div>

                <p id="step2-error" class="hidden text-sm font-semibold text-rose-600"></p>

                <div class="hidden items-center justify-between md:flex">
                    <button type="button" @click="step = 1" class="app-btn-muted">
                        <svg class="mr-1 inline-block h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                        </svg>
                        Back
                    </button>
                    <button type="button" @click="goToStep3()" class="app-btn-primary">
                        Continue
                        <svg class="ml-1.5 inline-block h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- =================== STEP 3: PRICE + PHOTOS =================== --}}
            <div x-show="step === 3" x-cloak class="space-y-4">

                <button type="button" @click="step = 2"
                        class="hidden items-center gap-1 text-sm font-semibold text-orange-600 hover:underline md:inline-flex">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                    </svg>
                    Edit Details
                </button>

                <div class="app-card space-y-5">
                    <h2 class="font-display text-xl font-bold text-slate-900">Price &amp; Condition</h2>

                    {{-- Condition (only shown when enabled for the selected category) --}}
                    <div x-show="conditionEnabled" x-cloak>
                        <x-input-label value="Condition *" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (['new' => 'New', 'used' => 'Used', 'refurbished' => 'Refurbished'] as $val => $label)
                                <button type="button"
                                        @click="condition = '{{ $val }}'"
                                        class="inline-flex items-center rounded-full border-2 px-5 py-2.5 text-sm font-semibold transition-all duration-150"
                                        :class="condition === '{{ $val }}'
                                            ? 'border-orange-500 bg-orange-50 text-orange-700'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-orange-300'">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('condition')" class="mt-1" />
                    </div>

                    {{-- Price --}}
                    <div>
                        <x-input-label value="Price" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (['fixed' => 'Fixed Price', 'negotiable' => 'Negotiable', 'free' => 'Free'] as $val => $label)
                                <button type="button"
                                        @click="priceType = '{{ $val }}'"
                                        class="inline-flex items-center rounded-full border-2 px-5 py-2.5 text-sm font-semibold transition-all duration-150"
                                        :class="priceType === '{{ $val }}'
                                            ? 'border-orange-500 bg-orange-50 text-orange-700'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-orange-300'">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <div x-show="priceType !== 'free'" class="mt-3">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-base font-bold text-slate-400">&#8377;</span>
                                <input id="price" name="price" type="number" min="0" step="1"
                                       class="app-input pl-8"
                                       value="{{ old('price') }}"
                                       placeholder="Enter your asking price"
                                       :disabled="priceType === 'free'"
                                       :required="priceType !== 'free'">
                            </div>
                            <p class="mt-1 text-xs text-slate-400"
                               x-show="priceType === 'negotiable'">Buyers can negotiate from your listed price</p>
                        </div>
                        <div x-show="priceType === 'free'" class="mt-3">
                            <input type="hidden" name="price" value="0" :disabled="priceType !== 'free'">
                            <div class="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <span class="text-sm font-semibold text-emerald-700">This item will be listed as FREE</span>
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                    </div>

                    <p id="step3-error" class="hidden text-sm font-semibold text-rose-600"></p>
                </div>

                {{-- Photos --}}
                <div class="app-card space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-bold text-slate-900">Photos</h2>
                            <p class="text-sm text-slate-500">
                                <span x-text="photos.length"></span>/<span x-text="maxImages"></span> photos added
                                <span class="font-semibold text-orange-500"> &middot; First photo = cover</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($aiListingAssistantEnabled)
                                <button type="button"
                                        @click="generateAiDraft()"
                                        :disabled="aiDraftLoading"
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-2xl border border-sky-300 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-60">
                                    AI from Photos
                                </button>
                            @endif
                            <button type="button"
                                @click="document.getElementById('photo-file-input').click()"
                                    x-show="photos.length < maxImages"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-2xl border border-orange-300 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 hover:bg-orange-100">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Photos
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        <template x-for="(photo, index) in photos" :key="photo.id">
                            <div class="group relative aspect-square overflow-hidden rounded-2xl border-2 transition-all"
                                 :class="index === 0 ? 'border-orange-400 shadow-md shadow-orange-100' : 'border-slate-200'">
                                <img :src="photo.url" :alt="photo.name" class="h-full w-full object-cover" loading="lazy">
                                <div x-show="index === 0"
                                     class="absolute inset-x-0 bottom-0 bg-orange-500/90 py-1 text-center text-[10px] font-bold uppercase tracking-wide text-white">
                                    Cover
                                </div>
                                <button type="button"
                                        @click.stop="removePhoto(photo.id)"
                                        class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900/75 text-white transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>

                        <button type="button"
                            @click="document.getElementById('photo-file-input').click()"
                                x-show="photos.length < maxImages"
                                class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400 transition-colors hover:border-orange-400 hover:bg-orange-50 hover:text-orange-500">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-xs font-semibold">Add</span>
                        </button>
                    </div>

                    <p class="text-xs text-slate-400">
                        JPG, PNG or WEBP &middot; Max 4MB each &middot; <span x-text="'Up to ' + maxImages + ' photos'"></span>
                    </p>
                    <p id="photos-error" class="hidden text-sm font-semibold text-rose-600"></p>
                    <x-input-error :messages="$errors->get('images')" class="mt-1" />
                    <x-input-error :messages="$errors->get('images.*')" class="mt-1" />
                </div>

                <div class="hidden items-center justify-between md:flex">
                    <button type="button" @click="step = 2" class="app-btn-muted">
                        <svg class="mr-1 inline-block h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                        </svg>
                        Back
                    </button>
                    <button type="button" @click="goToStep4()" class="app-btn-primary">
                        Continue
                        <svg class="ml-1.5 inline-block h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- =================== STEP 4: LOCATION + VIDEO + POST =================== --}}
            <div x-show="step === 4" x-cloak class="space-y-4">

                <button type="button" @click="step = 3"
                        class="hidden items-center gap-1 text-sm font-semibold text-orange-600 hover:underline md:inline-flex">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                    </svg>
                    Back to Price + Photos
                </button>

                {{-- Location --}}
                <div class="app-card space-y-4">
                    <div>
                        <h2 class="font-display text-xl font-bold text-slate-900">Location</h2>
                        <p class="text-sm text-slate-500">Where is this item located?</p>
                    </div>

                    @include('listings.partials.location-selector')
                </div>

                {{-- Video Link --}}
                <div class="app-card space-y-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            YouTube Video <span class="text-sm font-normal text-slate-400">(Optional)</span>
                        </h2>
                        <p class="text-sm text-slate-500">Add a video to show your item in action</p>
                    </div>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                            <svg class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M21.8 8s-.2-1.4-.8-2c-.8-.8-1.6-.8-2-.9C16.6 5 12 5 12 5s-4.6 0-7 .1c-.4.1-1.2.1-2 .9-.6.6-.8 2-.8 2S2 9.6 2 11.2v1.5c0 1.6.2 3.2.2 3.2s.2 1.4.8 2c.8.8 1.8.8 2.3.9C6.8 19 12 19 12 19s4.6 0 7-.1c.4-.1 1.2-.1 2-.9.6-.6.8-2 .8-2S22 14.4 22 14.8v-1.5C22 9.6 21.8 8 21.8 8zM9.7 15.5V8.5l6.3 3.5-6.3 3.5z"/>
                            </svg>
                        </span>
                        <input id="youtube_url" name="youtube_url" type="url"
                               class="app-input pl-11"
                               value="{{ old('youtube_url') }}"
                               placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    <x-input-error :messages="$errors->get('youtube_url')" class="mt-1" />
                </div>

                {{-- Submit --}}
                <div class="app-card space-y-3 bg-slate-50">
                    <p class="text-sm text-slate-500">
                        By posting, you agree to our
                        <a href="{{ route('legal.terms') }}" class="font-semibold text-orange-600 underline">Terms and Conditions</a>
                        and
                        <a href="{{ route('legal.privacy') }}" class="font-semibold text-orange-600 underline">Privacy Policy</a>.
                        @if ((bool) setting('listing_moderation_enabled', true))
                            <span class="font-semibold text-amber-600">Ads are reviewed before going live.</span>
                        @endif
                    </p>
                    <div class="hidden flex-col gap-3 sm:flex-row sm:items-center md:flex">
                        <button type="button" @click="step = 3" class="app-btn-muted">
                            <svg class="mr-1 inline-block h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                            </svg>
                            Back
                        </button>
                        <button type="submit"
                                class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-orange-500 px-6 py-4 text-base font-bold text-white shadow-lg shadow-orange-200 transition-all hover:bg-orange-600 active:scale-95">
                            Post Ad Now
                        </button>
                    </div>
                </div>

                @if ($hasListingPackageRequired)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-800">Active Package Required</p>
                        <p class="mt-1 text-xs text-amber-700">An active listing package is required before posting your ad.</p>

                        @if ($requiresSellerVerificationForPackage ?? false)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Seller verification is required for package-based posting. Upload your document in profile and wait for admin approval.</p>
                            <a href="{{ route('profile.edit') }}" class="mt-3 mr-2 inline-flex items-center rounded-xl border border-rose-300 bg-white px-4 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                Complete Verification
                            </a>
                        @endif

                        <a href="{{ route('subscriptions.index') }}" class="mt-3 inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700">
                            Get Package
                        </a>
                    </div>
                @elseif ($hasListingPackageOptionalAfterFreeLimit ?? false)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-800">Free Limits Active</p>
                        <p class="mt-1 text-xs text-amber-700">You can post free ads within configured limits. If your free limit is exhausted, buy a listing package to continue posting immediately.</p>

                        @if ($requiresSellerVerificationForPackage ?? false)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Seller verification is required only for package-based posting. Upload your document in profile and wait for admin approval.</p>
                            <a href="{{ route('profile.edit') }}" class="mt-3 mr-2 inline-flex items-center rounded-xl border border-rose-300 bg-white px-4 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                Complete Verification
                            </a>
                        @endif

                        <a href="{{ route('subscriptions.index') }}" class="mt-3 inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700">
                            View Packages
                        </a>
                    </div>
                @endif
            </div>

            {{-- Mobile Bottom Action Bar --}}
            <div class="md:hidden">
                <div class="fixed inset-x-0 bottom-0 z-[90] border-t border-slate-200 bg-white/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-3 shadow-[0_-16px_35px_-20px_rgba(15,23,42,0.55)] backdrop-blur">
                    <div class="mx-auto flex w-full max-w-2xl items-center gap-3" x-show="step === 1" x-cloak>
                        <button type="button"
                                @click="goToStep2()"
                                :disabled="!selectedCategoryId"
                                class="app-btn-primary flex-1 justify-center disabled:cursor-not-allowed disabled:opacity-40">
                            Continue
                        </button>
                    </div>

                    <div class="mx-auto flex w-full max-w-2xl items-center gap-3" x-show="step === 2" x-cloak>
                        <button type="button" @click="step = 1" class="app-btn-muted flex-1 justify-center">Back</button>
                        <button type="button" @click="goToStep3()" class="app-btn-primary flex-1 justify-center">Continue</button>
                    </div>

                    <div class="mx-auto flex w-full max-w-2xl items-center gap-3" x-show="step === 3" x-cloak>
                        <button type="button" @click="step = 2" class="app-btn-muted flex-1 justify-center">Back</button>
                        <button type="button" @click="goToStep4()" class="app-btn-primary flex-1 justify-center">Continue</button>
                    </div>

                    <div class="mx-auto flex w-full max-w-2xl items-center gap-3" x-show="step === 4" x-cloak>
                        <button type="button" @click="step = 3" class="app-btn-muted flex-1 justify-center">Back</button>
                        <button type="submit"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-orange-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-200 transition-all hover:bg-orange-600 active:scale-[0.99]">
                            Post Ad Now
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script>
    @include('listings.partials.location-selector-script')

    function postAdWizard() {
        @php
            $initStep = 1;
            if ($errors->any() && old('category_id')) {
                $hasStep3Errors = $errors->hasAny(['price', 'price_type', 'condition', 'images', 'images.*']);
                $hasStep4Errors = $errors->hasAny(['city', 'state', 'address', 'latitude', 'longitude', 'youtube_url']);

                if ($hasStep3Errors) {
                    $initStep = 3;
                } elseif ($hasStep4Errors) {
                    $initStep = 4;
                } else {
                    $initStep = 2;
                }
            }
        @endphp

        return {
            step: {{ $initStep }},
            categoryTree:  @json($categoryTree),
            allCategories: @json($allCategories),
            ...listingLocationSelector({
                initialCity: @js((string) old('city', auth()->user()->city ?? '')),
                initialState: @js((string) old('state', auth()->user()->state ?? '')),
                initialAddress: @js((string) old('address', '')),
                initialLatitude: @js((string) old('latitude', '')),
                initialLongitude: @js((string) old('longitude', '')),
                locationApi: @js([
                    'countries' => route('api.location.countries'),
                    'states' => route('api.location.states'),
                    'cities' => route('api.location.cities'),
                    'areas' => route('api.location.areas'),
                ]),
                defaultCountry: @js(strtoupper((string) setting('location_default_country', 'IN'))),
                mapsApiKey: @js(trim((string) setting('google_maps_api_key', ''))),
                locationStorageKey: 'unsell_location_state',
            }),
            selectedParentId:    null,
            selectedCategoryId:  {{ (int) old('category_id', 0) }},
            condition:  '{{ old('condition', 'used') }}',
            priceType:  '{{ old('price_type', 'fixed') }}',
            titleLen:   0,
            photos:     [],
            photoFiles: [],
            maxPhotosBase: {{ $maxImages }},
            locationStorageKey: 'unsell_location_state',
            aiListingAssistantEnabled: @js((bool) ($aiListingAssistantEnabled ?? false)),
            aiPriceRecommendationEnabled: @js((bool) ($aiPriceRecommendationEnabled ?? false)),
            aiDraftLoading: false,
            aiPriceLoading: false,
            aiDraft: null,
            aiDraftError: '',
            descriptionHelperMessage: '',
            descriptionHelperError: false,

            init() {
                this.initLocationSelector();

                const oldCatId = {{ (int) old('category_id', 0) }};
                if (oldCatId) {
                    this.selectedCategoryId = oldCatId;
                    for (const p of this.categoryTree) {
                        if (p.id === oldCatId || p.children.some(c => c.id === oldCatId)) {
                            this.selectedParentId = p.id;
                            break;
                        }
                    }
                }

                // Keep hidden category input and dependent custom-field UI in sync.
                this.$watch('selectedCategoryId', () => {
                    this.$nextTick(() => this._syncCategoryField());
                });

                this.$nextTick(() => {
                    const titleEl = document.getElementById('title');
                    if (titleEl) this.titleLen = titleEl.value.length;
                    if (this.selectedCategoryId) this._syncCategoryField();
                });
            },

            _syncCategoryField() {
                const el = document.getElementById('category_id');
                if (el) {
                    el.value = this.selectedCategoryId;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
            },

            buildMarketplaceLocationLabel() {
                const parts = [this.address, this.city, this.state]
                    .map((part) => String(part || '').trim())
                    .filter((part) => part !== '');

                return parts.length > 0 ? parts.join(', ') : 'Current location';
            },

            syncMarketplaceLocationState() {
                const label = this.buildMarketplaceLocationLabel();

                try {
                    window.localStorage.setItem(this.locationStorageKey, JSON.stringify({
                        label,
                        promptHandled: true,
                    }));
                    window.localStorage.removeItem('unsell_selected_location_label');
                    window.localStorage.removeItem('unsell_location_prompt_seen');
                } catch (_) {
                }

                window.dispatchEvent(new CustomEvent('unsell-location-updated', {
                    detail: { label },
                }));
            },

            get conditionEnabled() {
                const cat = this.allCategories.find(c => c.id === this.selectedCategoryId);
                return cat ? (cat.condition_enabled !== false) : true;
            },

            get selectedParentCategory() {
                return this.categoryTree.find(c => c.id === this.selectedParentId) || null;
            },

            get currentSubcategories() {
                return this.selectedParentCategory ? this.selectedParentCategory.children : [];
            },

            get selectedCategoryLabel() {
                const cat = this.allCategories.find(c => c.id === this.selectedCategoryId);
                if (!cat) return '';
                const par = cat.parent_id ? this.allCategories.find(p => p.id === cat.parent_id) : null;
                return par ? par.name + ' > ' + cat.name : cat.name;
            },

            get customFieldsHeading() {
                const cat = this.allCategories.find(c => c.id === this.selectedCategoryId);
                if (!cat || !cat.name) {
                    return 'Category Details';
                }

                return 'Details for ' + String(cat.name).trim();
            },

            get maxImages() {
                const cat = this.allCategories.find(c => c.id === this.selectedCategoryId);
                const par = cat && cat.parent_id ? this.allCategories.find(p => p.id === cat.parent_id) : null;
                const txt = [(cat ? cat.name : ''), (par ? par.name : '')].join(' ').toLowerCase();
                if (/car|vehicle|auto|bike|motor|scooter|truck/.test(txt)) return 20;
                if (/propert|real.?estate|flat|apartment|house|land|plot|commercial/.test(txt)) return 20;
                return this.maxPhotosBase;
            },

            selectParent(parentId) {
                this.selectedParentId = parentId;
                const p = this.categoryTree.find(c => c.id === parentId);
                if (!p || p.children.length === 0) {
                    this.selectedCategoryId = parentId;
                } else {
                    this.selectedCategoryId = 0;
                }
            },

            selectSubcategory(catId) {
                this.selectedCategoryId = catId;
            },

            goToStep2() {
                if (!this.selectedCategoryId) return;
                this.step = 2;
                this.$nextTick(() => this._syncCategoryField());
            },

            goToStep3() {
                const titleEl = document.getElementById('title');
                const descEl  = document.getElementById('description');
                const errEl   = document.getElementById('step2-error');

                if (!titleEl || !titleEl.value.trim()) {
                    if (errEl) { errEl.textContent = 'Please enter an ad title.'; errEl.classList.remove('hidden'); }
                    if (titleEl) titleEl.focus();
                    return;
                }
                if (!descEl || descEl.value.trim().length < 10) {
                    if (errEl) { errEl.textContent = 'Please write a description (at least 10 characters).'; errEl.classList.remove('hidden'); }
                    if (descEl) descEl.focus();
                    return;
                }
                if (errEl) errEl.classList.add('hidden');
                this.step = 3;
            },

            goToStep4() {
                const step3ErrEl = document.getElementById('step3-error');
                const photosErrEl = document.getElementById('photos-error');
                const priceEl = document.getElementById('price');

                if (step3ErrEl) {
                    step3ErrEl.classList.add('hidden');
                }
                if (photosErrEl) {
                    photosErrEl.classList.add('hidden');
                }

                if (this.conditionEnabled && !this.condition) {
                    if (step3ErrEl) {
                        step3ErrEl.textContent = 'Please select condition.';
                        step3ErrEl.classList.remove('hidden');
                    }
                    return;
                }

                if (this.priceType !== 'free') {
                    const rawPrice = priceEl ? String(priceEl.value || '').trim() : '';
                    const parsedPrice = Number(rawPrice);

                    if (rawPrice === '' || Number.isNaN(parsedPrice) || parsedPrice < 0) {
                        if (step3ErrEl) {
                            step3ErrEl.textContent = 'Please enter a valid price.';
                            step3ErrEl.classList.remove('hidden');
                        }
                        if (priceEl) {
                            priceEl.focus();
                        }
                        return;
                    }
                }

                if (this.photos.length < 1) {
                    if (photosErrEl) {
                        photosErrEl.textContent = 'Please upload at least one photo.';
                        photosErrEl.classList.remove('hidden');
                    }
                    return;
                }

                this.step = 4;
            },

            addPhotos(event) {
                const input = event.target;
                const files = Array.from((input && input.files) ? input.files : []);
                const toAdd = files.slice(0, this.maxImages - this.photos.length);
                toAdd.forEach(file => {
                    const id = Date.now() + Math.random();
                    this.photos.push({ id, url: URL.createObjectURL(file), name: file.name });
                    this.photoFiles.push({ id, file });
                });

                // Keep native file input synced when possible.
                // Avoid resetting input.value here: some mobile WebViews lose attached files.
                this._syncFileInput();
            },

            removePhoto(id) {
                const idx = this.photos.findIndex(p => p.id === id);
                if (idx > -1) {
                    URL.revokeObjectURL(this.photos[idx].url);
                    this.photos.splice(idx, 1);
                    this.photoFiles.splice(idx, 1);
                }
                this._syncFileInput();
            },

            _syncFileInput() {
                const input = document.getElementById('photo-file-input');
                if (!input) {
                    return false;
                }

                if (typeof DataTransfer === 'undefined') {
                    return false;
                }

                try {
                    const dt = new DataTransfer();
                    this.photoFiles.forEach(pf => dt.items.add(pf.file));
                    input.files = dt.files;

                    const assignedCount = input.files ? input.files.length : 0;

                    return assignedCount === this.photoFiles.length;
                } catch (_) {
                    return false;
                }
            },

            getAddressValue(obj, keys) {
                if (!obj || !Array.isArray(keys)) {
                    return '';
                }

                for (const key of keys) {
                    const value = obj[key];
                    if (value && String(value).trim() !== '') {
                        return String(value).trim();
                    }
                }

                return '';
            },

            async ensureGoogleMapsCoreScript() {
                if (!this.mapsApiKey) {
                    return false;
                }

                if (window.google && window.google.maps) {
                    return true;
                }

                if (this.mapsScriptPromise) {
                    return this.mapsScriptPromise;
                }

                this.mapsScriptPromise = new Promise((resolve) => {
                    const existing = document.querySelector('script[data-google-maps-core]');
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
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(this.mapsApiKey);
                    script.async = true;
                    script.defer = true;
                    script.dataset.googleMapsCore = '1';
                    script.onload = () => resolve(!!(window.google && window.google.maps));
                    script.onerror = () => resolve(false);
                    document.head.appendChild(script);
                });

                const loaded = await this.mapsScriptPromise;
                if (!loaded) {
                    this.mapsScriptPromise = null;
                }

                return loaded;
            },

            getGoogleAddressComponent(components, preferredTypes) {
                if (!Array.isArray(components) || !Array.isArray(preferredTypes)) {
                    return '';
                }

                for (const type of preferredTypes) {
                    const found = components.find((component) => Array.isArray(component.types) && component.types.includes(type));
                    if (found && found.long_name) {
                        return String(found.long_name).trim();
                    }
                }

                return '';
            },

            async reverseGeocodeWithGoogle(lat, lng) {
                const ready = await this.ensureGoogleMapsCoreScript();
                if (!ready || !window.google || !window.google.maps) {
                    return null;
                }

                if (!this.mapsGeocoder) {
                    this.mapsGeocoder = new window.google.maps.Geocoder();
                }

                const results = await new Promise((resolve, reject) => {
                    this.mapsGeocoder.geocode({ location: { lat: parseFloat(lat), lng: parseFloat(lng) } }, (geocodeResults, status) => {
                        if (status === 'OK' && Array.isArray(geocodeResults) && geocodeResults.length > 0) {
                            resolve(geocodeResults);
                            return;
                        }

                        reject(status);
                    });
                });

                const first = results[0];
                const components = Array.isArray(first.address_components) ? first.address_components : [];

                const areaParts = [
                    this.getGoogleAddressComponent(components, ['sublocality_level_1', 'sublocality', 'neighborhood']),
                    this.getGoogleAddressComponent(components, ['route']),
                ].filter((part) => part !== '');

                return {
                    city: this.getGoogleAddressComponent(components, ['locality', 'postal_town', 'administrative_area_level_2']),
                    state: this.getGoogleAddressComponent(components, ['administrative_area_level_1']),
                    address: areaParts.join(', '),
                };
            },

            async reverseGeocodeWithNominatim(lat, lng) {
                const endpoint = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
                const response = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'Accept-Language': 'en',
                    },
                });

                if (!response.ok) {
                    throw new Error('Reverse geocode failed');
                }

                const payload = await response.json();
                const addr = payload && payload.address ? payload.address : {};

                const areaParts = [
                    this.getAddressValue(addr, ['suburb', 'neighbourhood', 'quarter', 'hamlet']),
                    this.getAddressValue(addr, ['road']),
                ].filter((part) => part !== '');

                return {
                    city: this.getAddressValue(addr, ['city', 'town', 'village', 'municipality', 'county']),
                    state: this.getAddressValue(addr, ['state']),
                    address: areaParts.join(', '),
                };
            },

            async fillLocationDetails(lat, lng) {
                try {
                    const fromGoogle = await this.reverseGeocodeWithGoogle(lat, lng);
                    if (fromGoogle) {
                        if (fromGoogle.city) this.city = fromGoogle.city;
                        if (fromGoogle.state) this.state = fromGoogle.state;
                        if (fromGoogle.address) this.address = fromGoogle.address;
                        return true;
                    }
                } catch (_) {
                    // Fallback handled below.
                }

                try {
                    const fromNominatim = await this.reverseGeocodeWithNominatim(lat, lng);
                    if (fromNominatim) {
                        if (fromNominatim.city) this.city = fromNominatim.city;
                        if (fromNominatim.state) this.state = fromNominatim.state;
                        if (fromNominatim.address) this.address = fromNominatim.address;
                        return true;
                    }
                } catch (_) {
                    return false;
                }

                return false;
            },

            async detectLocation() {
                if (!navigator.geolocation) {
                    this.locationErrorMessage = 'Geolocation is not supported in this browser. Please enter location manually.';
                    return;
                }

                this.locationDetecting = true;
                this.locationErrorMessage = '';
                this.locationStatusMessage = 'Detecting your location...';

                navigator.geolocation.getCurrentPosition(
                    async (pos) => {
                        this.latitude  = pos.coords.latitude.toFixed(6);
                        this.longitude = pos.coords.longitude.toFixed(6);
                        this.locationStatusMessage = 'Coordinates captured. Fetching location details...';

                        const resolved = await this.fillLocationDetails(this.latitude, this.longitude);
                        if (resolved) {
                            this.locationStatusMessage = 'Location auto-filled successfully.';
                        } else {
                            this.locationStatusMessage = 'Coordinates captured. Please verify city/state manually.';
                        }

                        this.syncMarketplaceLocationState();

                        this.locationDetecting = false;
                    },
                    () => {
                        this.locationDetecting = false;
                        this.locationErrorMessage = 'Unable to detect location permission. Please enable location access or enter manually.';
                        this.locationStatusMessage = '';
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 0,
                    }
                );
            },

            setInputValue(id, value) {
                const element = document.getElementById(id);
                if (!element) {
                    return;
                }

                element.value = value;
                this.dispatchInputEvents(element);
            },

            dispatchInputEvents(element) {
                if (!element) {
                    return;
                }

                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            },

            normalizeText(value) {
                return String(value || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim();
            },

            scoreTextSimilarity(left, right) {
                if (!left || !right) {
                    return 0;
                }

                if (left === right) {
                    return 10;
                }

                if (left.includes(right) || right.includes(left)) {
                    return 6;
                }

                const leftTokens = left.split(' ').filter(Boolean);
                const rightTokens = right.split(' ').filter(Boolean);

                if (leftTokens.length === 0 || rightTokens.length === 0) {
                    return 0;
                }

                const leftSet = new Set(leftTokens);
                let overlap = 0;

                rightTokens.forEach((token) => {
                    if (leftSet.has(token)) {
                        overlap += 1;
                    }
                });

                return overlap;
            },

            appendToDescription(text, heading = null) {
                const cleanedText = String(text || '').trim();
                if (cleanedText === '') {
                    return;
                }

                const descriptionElement = document.getElementById('description');
                if (!descriptionElement) {
                    return;
                }

                const currentDescription = String(descriptionElement.value || '').trim();
                if (currentDescription.toLowerCase().includes(cleanedText.toLowerCase())) {
                    return;
                }

                const block = heading ? `${heading}\n${cleanedText}` : cleanedText;
                const nextDescription = currentDescription === ''
                    ? block
                    : `${currentDescription}\n\n${block}`;

                this.setInputValue('description', nextDescription);
            },

            appendAttributeToDescription(key, value) {
                const cleanKey = String(key || '').trim();
                const cleanValue = String(value || '').trim();

                if (cleanKey === '' || cleanValue === '') {
                    return;
                }

                const line = `- ${cleanKey}: ${cleanValue}`;
                const descriptionElement = document.getElementById('description');
                const currentDescription = String(descriptionElement?.value || '');

                if (currentDescription.toLowerCase().includes(line.toLowerCase())) {
                    return;
                }

                if (currentDescription.includes('AI Suggested Specs:')) {
                    this.setInputValue('description', `${currentDescription.trimEnd()}\n${line}`);
                    return;
                }

                this.appendToDescription(line, 'AI Suggested Specs:');
            },

            getVisibleCustomFieldWrappers() {
                return Array.from(document.querySelectorAll('[data-custom-field-wrapper]:not(.hidden)'));
            },

            readCustomFieldValue(wrapper) {
                if (!wrapper) {
                    return '';
                }

                const fileInput = wrapper.querySelector('input[type="file"]');
                if (fileInput) {
                    if (fileInput.files && fileInput.files.length > 0) {
                        return Array.from(fileInput.files).map((file) => file.name).join(', ');
                    }

                    return '';
                }

                const select = wrapper.querySelector('select');
                if (select && String(select.value || '').trim() !== '') {
                    return String(select.value).trim();
                }

                const textOrNumberInput = wrapper.querySelector('input[type="text"], input[type="number"]');
                if (textOrNumberInput && String(textOrNumberInput.value || '').trim() !== '') {
                    return String(textOrNumberInput.value).trim();
                }

                const checkedRadio = wrapper.querySelector('input[type="radio"]:checked');
                if (checkedRadio && String(checkedRadio.value || '').trim() !== '') {
                    return String(checkedRadio.value).trim();
                }

                const checkedCheckboxValues = Array.from(wrapper.querySelectorAll('input[type="checkbox"]:checked'))
                    .filter((checkbox) => {
                        const name = String(checkbox.name || '');
                        return !name.startsWith('custom_fields_remove[');
                    })
                    .map((checkbox) => String(checkbox.value || '').trim())
                    .filter((value) => value !== '');

                if (checkedCheckboxValues.length > 0) {
                    return checkedCheckboxValues.join(', ');
                }

                return '';
            },

            generateDescriptionFromCustomFields() {
                const descriptionElement = document.getElementById('description');
                if (!descriptionElement) {
                    return;
                }

                const wrappers = this.getVisibleCustomFieldWrappers();
                const lines = [];

                wrappers.forEach((wrapper) => {
                    const label = String(wrapper.querySelector('label span')?.textContent || '').trim();
                    const value = this.readCustomFieldValue(wrapper);

                    if (label !== '' && value !== '') {
                        lines.push(`- ${label}: ${value}`);
                    }
                });

                if (lines.length === 0) {
                    this.descriptionHelperError = true;
                    this.descriptionHelperMessage = 'Fill at least one custom field first, then generate description.';
                    return;
                }

                const sectionLines = ['Listing Specifications:'];
                if (this.selectedCategoryLabel) {
                    sectionLines.push(`- Category: ${this.selectedCategoryLabel}`);
                }
                sectionLines.push(...lines);

                const generatedSection = sectionLines.join('\n');
                const currentDescription = String(descriptionElement.value || '').trim();
                const sectionPattern = /(?:^|\n\n)Listing Specifications:\n(?:- .*\n?)+/m;

                const nextDescription = sectionPattern.test(currentDescription)
                    ? currentDescription.replace(sectionPattern, (matched) => {
                        return matched.startsWith('\n\n')
                            ? `\n\n${generatedSection}`
                            : generatedSection;
                    }).trim()
                    : (currentDescription === ''
                        ? generatedSection
                        : `${currentDescription}\n\n${generatedSection}`);

                this.setInputValue('description', nextDescription);
                this.descriptionHelperError = false;
                this.descriptionHelperMessage = 'Description updated from selected custom fields.';
            },

            findBestCustomFieldWrapper(fieldKey) {
                const normalizedKey = this.normalizeText(fieldKey);
                if (normalizedKey === '') {
                    return null;
                }

                const wrappers = Array.from(document.querySelectorAll('[data-custom-field-wrapper]:not(.hidden)'));

                let bestWrapper = null;
                let bestScore = 0;

                wrappers.forEach((wrapper) => {
                    const labelSpan = wrapper.querySelector('label span');
                    const labelText = this.normalizeText(labelSpan?.textContent || '');
                    const score = this.scoreTextSimilarity(normalizedKey, labelText);

                    if (score > bestScore) {
                        bestScore = score;
                        bestWrapper = wrapper;
                    }
                });

                return bestScore > 0 ? bestWrapper : null;
            },

            applyValueToWrapper(wrapper, rawValue) {
                if (!wrapper) {
                    return false;
                }

                const value = String(rawValue || '').trim();
                if (value === '') {
                    return false;
                }

                const select = wrapper.querySelector('select');
                if (select) {
                    const normalizedValue = this.normalizeText(value);
                    const options = Array.from(select.options || []).filter((option) => option.value !== '');

                    let matchedOption = options.find((option) => this.normalizeText(option.value) === normalizedValue);
                    if (!matchedOption) {
                        matchedOption = options.find((option) => {
                            const normalizedOption = this.normalizeText(option.value);
                            return normalizedOption.includes(normalizedValue) || normalizedValue.includes(normalizedOption);
                        });
                    }

                    if (matchedOption) {
                        select.value = matchedOption.value;
                        this.dispatchInputEvents(select);
                        return true;
                    }
                }

                const textOrNumberInput = wrapper.querySelector('input[type="text"], input[type="number"]');
                if (textOrNumberInput) {
                    const parsedNumber = Number.parseInt(value.replace(/[^\d.-]/g, ''), 10);
                    const nextValue = textOrNumberInput.type === 'number'
                        ? (Number.isFinite(parsedNumber) ? String(parsedNumber) : '')
                        : value;

                    if (nextValue !== '') {
                        textOrNumberInput.value = nextValue;
                        this.dispatchInputEvents(textOrNumberInput);
                        return true;
                    }
                }

                const radios = Array.from(wrapper.querySelectorAll('input[type="radio"]'));
                if (radios.length > 0) {
                    const normalizedValue = this.normalizeText(value);
                    const matchedRadio = radios.find((radio) => {
                        const normalizedRadio = this.normalizeText(radio.value);
                        return normalizedRadio === normalizedValue
                            || normalizedRadio.includes(normalizedValue)
                            || normalizedValue.includes(normalizedRadio);
                    });

                    if (matchedRadio) {
                        matchedRadio.checked = true;
                        this.dispatchInputEvents(matchedRadio);
                        return true;
                    }
                }

                const checkboxes = Array.from(wrapper.querySelectorAll('input[type="checkbox"]'));
                if (checkboxes.length > 0) {
                    const values = value.split(/[,;/|]/).map((part) => this.normalizeText(part)).filter(Boolean);
                    let matched = false;

                    checkboxes.forEach((checkbox) => {
                        const normalizedCheckbox = this.normalizeText(checkbox.value);
                        const shouldCheck = values.some((segment) => {
                            return normalizedCheckbox === segment
                                || normalizedCheckbox.includes(segment)
                                || segment.includes(normalizedCheckbox);
                        });

                        if (shouldCheck) {
                            checkbox.checked = true;
                            this.dispatchInputEvents(checkbox);
                            matched = true;
                        }
                    });

                    if (matched) {
                        return true;
                    }
                }

                return false;
            },

            applyAiAttribute(attribute) {
                if (!attribute || typeof attribute !== 'object') {
                    return;
                }

                const key = String(attribute.key || '').trim();
                const value = String(attribute.value || '').trim();

                if (key === '' || value === '') {
                    return;
                }

                const wrapper = this.findBestCustomFieldWrapper(key);
                const didPopulateField = this.applyValueToWrapper(wrapper, value);

                if (!didPopulateField) {
                    this.appendAttributeToDescription(key, value);
                }
            },

            applyAiField(field) {
                if (!this.aiDraft || typeof this.aiDraft !== 'object') {
                    return;
                }

                const key = String(field || '').toLowerCase();

                if (key === 'title') {
                    const title = String(this.aiDraft.title || '').trim();
                    if (title !== '') {
                        this.setInputValue('title', title);
                        this.titleLen = title.length;
                    }
                    return;
                }

                if (key === 'description') {
                    const description = String(this.aiDraft.description || '').trim();
                    if (description !== '') {
                        this.setInputValue('description', description);
                    }
                    return;
                }

                if (key === 'price') {
                    const suggestedPrice = Number(this.aiDraft?.price_recommendation?.suggested_price || 0);
                    if (suggestedPrice > 0) {
                        this.priceType = 'fixed';
                        this.$nextTick(() => {
                            this.setInputValue('price', String(Math.round(suggestedPrice)));
                        });
                    }
                    return;
                }

                if (key === 'video_script') {
                    const script = String(this.aiDraft.video_script || '').trim();
                    if (script !== '') {
                        this.appendToDescription(script, 'Video Script Suggestion:');
                    }
                }
            },

            async generateAiDraft(mode = 'all') {
                if (!this.aiListingAssistantEnabled || this.aiDraftLoading) {
                    return;
                }

                this.aiDraftLoading = true;
                this.aiDraftError = '';

                try {
                    const formData = new FormData();

                    const title = (document.getElementById('title')?.value || '').trim();
                    const description = (document.getElementById('description')?.value || '').trim();

                    if (title !== '') formData.append('title', title);
                    if (description !== '') formData.append('description', description);
                    if (this.selectedCategoryId) formData.append('category_id', String(this.selectedCategoryId));
                    if (this.condition) formData.append('condition', String(this.condition));
                    if (this.city) formData.append('city', String(this.city));
                    if (this.state) formData.append('state', String(this.state));
                    if (this.address) formData.append('address', String(this.address));
                    formData.append('mode', String(mode || 'all'));

                    this.photoFiles.slice(0, 6).forEach((photoItem) => {
                        if (photoItem && photoItem.file) {
                            formData.append('images[]', photoItem.file, photoItem.file.name || 'photo.jpg');
                        }
                    });

                    const response = await fetch('{{ route('ai.listings.generate') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload.ok) {
                        this.aiDraftError = payload.message || 'AI draft generation failed.';
                        return;
                    }

                    this.aiDraft = payload.data || null;
                    this.applyAiDraft(this.aiDraft, mode);
                } catch (_) {
                    this.aiDraftError = 'Unable to generate AI draft right now. Please try again.';
                } finally {
                    this.aiDraftLoading = false;
                }
            },

            applyAiDraft(draft, mode = 'all') {
                if (!draft || typeof draft !== 'object') {
                    return;
                }

                const normalizedMode = String(mode || 'all').toLowerCase();
                const applyTitle = normalizedMode === 'all' || normalizedMode === 'title';
                const applyDescription = normalizedMode === 'all' || normalizedMode === 'description';
                const applyPrice = normalizedMode === 'all' || normalizedMode === 'price';

                const title = String(draft.title || '').trim();
                const description = String(draft.description || '').trim();

                if (applyTitle && title !== '') {
                    this.setInputValue('title', title);
                    this.titleLen = title.length;
                }

                if (applyDescription && description !== '') {
                    this.setInputValue('description', description);
                }

                const suggestedPrice = draft.price_recommendation && draft.price_recommendation.suggested_price
                    ? Number(draft.price_recommendation.suggested_price)
                    : 0;

                if (applyPrice && suggestedPrice > 0) {
                    this.priceType = 'fixed';
                    this.$nextTick(() => {
                        this.setInputValue('price', String(Math.round(suggestedPrice)));
                    });
                }

                if (this.step === 3) {
                    this.step = 2;
                }
            },

            async fetchAiPriceRecommendation() {
                if (!this.aiPriceRecommendationEnabled || this.aiPriceLoading) {
                    return;
                }

                this.aiPriceLoading = true;
                this.aiDraftError = '';

                try {
                    const response = await fetch('{{ route('ai.listings.price-recommendation') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            category_id: this.selectedCategoryId || null,
                            condition: this.condition || null,
                            city: this.city || null,
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload.ok) {
                        this.aiDraftError = payload.message || 'AI price recommendation failed.';
                        return;
                    }

                    const priceData = payload.data || {};
                    const suggested = Number(priceData.suggested_price || 0);

                    this.aiDraft = this.aiDraft || {};
                    this.aiDraft.price_recommendation = priceData;

                    if (suggested > 0) {
                        this.priceType = 'fixed';
                        this.$nextTick(() => {
                            this.setInputValue('price', String(Math.round(suggested)));
                        });
                    }
                } catch (_) {
                    this.aiDraftError = 'Unable to fetch AI pricing right now.';
                } finally {
                    this.aiPriceLoading = false;
                }
            },

            formatMoney(value) {
                const number = Number(value || 0);

                return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(number);
            },

            handleSubmit() {
                const errEl = document.getElementById('photos-error');
                if (this.photos.length === 0) {
                    if (errEl) { errEl.textContent = 'Please add at least 1 photo.'; errEl.classList.remove('hidden'); }
                    return;
                }

                const input = document.getElementById('photo-file-input');
                let attachedCount = input && input.files ? input.files.length : 0;

                if (attachedCount === 0 && this.photoFiles.length > 0) {
                    this._syncFileInput();
                    attachedCount = input && input.files ? input.files.length : 0;
                }

                if (attachedCount === 0) {
                    if (errEl) {
                        errEl.textContent = 'Photos were not attached correctly. Please re-add your photos.';
                        errEl.classList.remove('hidden');
                    }
                    return;
                }

                if (errEl) errEl.classList.add('hidden');
                document.getElementById('post-ad-form').submit();
            },
        };
    }
    </script>
</x-app-layout>
