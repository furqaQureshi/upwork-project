<div class="space-y-4">
    <input type="hidden" name="city" :value="city">
    <input type="hidden" name="state" :value="state">
    <input type="hidden" name="address" :value="address">
    <input type="hidden" name="latitude" :value="latitude">
    <input type="hidden" name="longitude" :value="longitude">

    <div class="flex flex-wrap gap-3">
        <button type="button"
                @click="requestGeoLocation()"
                :disabled="locationDetecting"
                class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60">
            <svg class="h-4 w-4" :class="locationDetecting ? 'animate-spin' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3" />
                <path stroke-linecap="round" d="M12 2v2m0 16v2M2 12h2m16 0h2" />
            </svg>
            <span x-text="locationDetecting ? 'Detecting location...' : 'Detect my location'"></span>
        </button>

        <button type="button"
                @click="openLocationEditor()"
                class="inline-flex items-center gap-2 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-2.5 text-sm font-semibold text-orange-700 transition-colors hover:bg-orange-100">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
            </svg>
            Manually search location
        </button>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Selected location</p>
        <p class="mt-1 text-sm font-semibold text-slate-900" x-text="locationDisplayLabel()"></p>
        <p class="mt-1 text-xs text-slate-500">Use GPS detection for your current location or search by state, city, and area.</p>

        <p x-show="latitude && longitude"
           x-cloak
           class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
            Coordinates captured
            (<span x-text="parseFloat(latitude).toFixed(4)"></span>,
             <span x-text="parseFloat(longitude).toFixed(4)"></span>)
        </p>

        <p x-show="locationStatusMessage" x-cloak x-text="locationStatusMessage" class="mt-3 text-xs font-semibold text-slate-600"></p>
        <p x-show="locationErrorMessage" x-cloak x-text="locationErrorMessage" class="mt-2 text-xs font-semibold text-rose-600"></p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="settings-label" for="listing_location_city_display">City *</label>
            <input id="listing_location_city_display"
                   type="text"
                   class="app-input mt-1"
                   x-model="city"
                   readonly
                   placeholder="Detect or search location">
            <x-input-error :messages="$errors->get('city')" class="mt-1" />
        </div>
        <div>
            <label class="settings-label" for="listing_location_state_display">State</label>
            <input id="listing_location_state_display"
                   type="text"
                   class="app-input mt-1"
                   x-model="state"
                   readonly
                   placeholder="State will appear here">
            <x-input-error :messages="$errors->get('state')" class="mt-1" />
        </div>
    </div>

    <div>
        <label class="settings-label" for="listing_location_address_display">Locality / Area</label>
        <input id="listing_location_address_display"
               type="text"
               class="app-input mt-1"
               x-model="address"
               readonly
               placeholder="Area will appear here">
        <p class="mt-1 text-xs text-slate-400">A more specific area helps nearby buyers find the listing faster.</p>
        <x-input-error :messages="$errors->get('address')" class="mt-1" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="settings-label" for="listing_location_lat_display">Latitude</label>
            <input id="listing_location_lat_display" type="text" class="app-input mt-1" x-model="latitude" readonly placeholder="Optional GPS coordinate">
            <x-input-error :messages="$errors->get('latitude')" class="mt-1" />
        </div>
        <div>
            <label class="settings-label" for="listing_location_lng_display">Longitude</label>
            <input id="listing_location_lng_display" type="text" class="app-input mt-1" x-model="longitude" readonly placeholder="Optional GPS coordinate">
            <x-input-error :messages="$errors->get('longitude')" class="mt-1" />
        </div>
    </div>

    <section x-cloak
             x-show="isLocationEditorOpen"
             x-transition.opacity
             @keydown.escape.window="closeLocationEditor()"
             class="fixed inset-0 z-[80] flex items-end bg-slate-950/60 p-0 sm:items-center sm:justify-center sm:p-6">
        <div class="w-full max-h-[88vh] overflow-y-auto rounded-t-3xl bg-white p-4 shadow-2xl sm:max-w-2xl sm:rounded-3xl sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-display text-xl font-bold text-slate-900">Search listing location</h3>
                    <p class="mt-1 text-sm text-slate-600">Pick location details manually or pin the exact spot on the map.</p>
                </div>
                <button type="button" @click="closeLocationEditor()" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">Close</button>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button"
                        @click="requestGeoLocation()"
                        :disabled="locationDetecting"
                        class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">
                    <svg class="h-4 w-4" :class="locationDetecting ? 'animate-spin' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3" />
                        <path stroke-linecap="round" d="M12 2v2m0 16v2M2 12h2m16 0h2" />
                    </svg>
                    <span x-text="locationDetecting ? 'Detecting location...' : 'Use current location'"></span>
                </button>
            </div>

            <p x-show="locationStatusMessage" x-cloak x-text="locationStatusMessage" class="mt-3 text-xs font-semibold text-slate-600"></p>
            <p x-show="locationErrorMessage" x-cloak x-text="locationErrorMessage" class="mt-2 text-xs font-semibold text-rose-600"></p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="settings-label" for="listing_location_country">Country</label>
                    <select id="listing_location_country" class="app-select mt-1" x-model="selectedCountry" @change="onCountryChanged()">
                        <template x-if="countryOptions.length === 0">
                            <option :value="selectedCountry" x-text="selectedCountry"></option>
                        </template>
                        <template x-for="country in countryOptions" :key="country.code">
                            <option :value="country.code" x-text="country.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="settings-label" for="listing_location_state">State</label>
                    <select id="listing_location_state" class="app-select mt-1" x-model="selectedState" @change="onStateChanged()">
                        <option value="">Select state</option>
                        <template x-for="stateOption in stateOptions" :key="stateOption.name">
                            <option :value="stateOption.name" x-text="stateOption.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="settings-label" for="listing_location_city">City</label>
                    <select id="listing_location_city" class="app-select mt-1" x-model="selectedCity" @change="onCityChanged()">
                        <option value="">Select city</option>
                        <template x-for="cityOption in cityOptions" :key="cityOption.name">
                            <option :value="cityOption.name" x-text="cityOption.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="settings-label" for="listing_location_area">Area / Locality</label>
                    <input id="listing_location_area"
                           type="text"
                           list="listing-location-area-suggestions"
                           class="app-input mt-1"
                           x-model="selectedArea"
                           @change="onAreaChanged()"
                           placeholder="Search or type area name">
                    <datalist id="listing-location-area-suggestions">
                        <template x-for="areaOption in areaOptions" :key="areaOption.name">
                            <option :value="areaOption.name"></option>
                        </template>
                    </datalist>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-2">
                <template x-if="mapsApiKey">
                    <div>
                        <div id="listing-location-selector-map" class="h-56 w-full rounded-xl"></div>
                        <p class="mt-2 text-xs text-slate-500">Tap the map to pin the exact listing location.</p>
                    </div>
                </template>
                <template x-if="!mapsApiKey">
                    <div class="flex h-32 items-center justify-center rounded-xl bg-slate-50 px-4 text-center text-xs font-semibold text-slate-500">
                        Map preview is unavailable because Google Maps is not configured. You can still search location manually.
                    </div>
                </template>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="settings-label" for="listing_location_modal_lat">Latitude</label>
                    <input id="listing_location_modal_lat" class="app-input mt-1" x-model="selectedLat" readonly>
                </div>
                <div>
                    <label class="settings-label" for="listing_location_modal_lng">Longitude</label>
                    <input id="listing_location_modal_lng" class="app-input mt-1" x-model="selectedLng" readonly>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <button type="button" @click="closeLocationEditor()" class="app-btn-muted">Cancel</button>
                <button type="button" @click="applySelectedLocation()" class="app-btn-primary">Use this location</button>
            </div>
        </div>
    </section>
</div>