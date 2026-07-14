function listingLocationSelector(config = {}) {
    const normalizeText = (value) => String(value ?? '').trim();
    const normalizeCoordinate = (value) => {
        const text = String(value ?? '').trim();
        return text === '' ? '' : text;
    };

    const defaultCountry = normalizeText(config.defaultCountry || 'IN').toUpperCase() || 'IN';

    return {
        locationApi: (config && config.locationApi) ? config.locationApi : {},
        defaultCountry,
        city: normalizeText(config.initialCity || ''),
        state: normalizeText(config.initialState || ''),
        address: normalizeText(config.initialAddress || ''),
        latitude: normalizeCoordinate(config.initialLatitude || ''),
        longitude: normalizeCoordinate(config.initialLongitude || ''),
        selectedCountry: defaultCountry,
        selectedState: normalizeText(config.initialState || ''),
        selectedCity: normalizeText(config.initialCity || ''),
        selectedArea: normalizeText(config.initialAddress || ''),
        selectedLat: normalizeCoordinate(config.initialLatitude || ''),
        selectedLng: normalizeCoordinate(config.initialLongitude || ''),
        countryOptions: [],
        stateOptions: [],
        cityOptions: [],
        areaOptions: [],
        isLocationEditorOpen: false,
        mapInstance: null,
        mapMarker: null,
        locationDetecting: false,
        locationStatusMessage: '',
        locationErrorMessage: '',
        mapsApiKey: normalizeText(config.mapsApiKey || ''),
        mapsScriptPromise: null,
        mapsGeocoder: null,
        locationStorageKey: normalizeText(config.locationStorageKey || 'unsell_location_state'),

        initLocationSelector() {
            this.syncManualLocationFromFields();
        },

        locationDisplayLabel() {
            const parts = [this.address, this.city, this.state]
                .map((part) => normalizeText(part))
                .filter((part) => part !== '');

            return parts.length > 0 ? parts.join(', ') : 'No location selected yet.';
        },

        syncManualLocationFromFields() {
            this.selectedCountry = normalizeText(this.selectedCountry || this.defaultCountry || 'IN').toUpperCase() || 'IN';
            this.selectedState = normalizeText(this.state || this.selectedState || '');
            this.selectedCity = normalizeText(this.city || this.selectedCity || '');
            this.selectedArea = normalizeText(this.address || this.selectedArea || '');
            this.selectedLat = normalizeCoordinate(this.latitude || this.selectedLat || '');
            this.selectedLng = normalizeCoordinate(this.longitude || this.selectedLng || '');

            this.city = this.selectedCity;
            this.state = this.selectedState;
            this.address = this.selectedArea;
            this.latitude = this.selectedLat;
            this.longitude = this.selectedLng;
        },

        async waitForLocationDom() {
            await new Promise((resolve) => {
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => resolve());
                    return;
                }

                window.requestAnimationFrame(() => resolve());
            });
        },

        async openLocationEditor() {
            this.syncManualLocationFromFields();
            this.locationErrorMessage = '';
            await this.prepareLocationEditorOptions();
            this.isLocationEditorOpen = true;
            await this.waitForLocationDom();
            await this.initializeLocationMapPicker();
        },

        closeLocationEditor() {
            this.isLocationEditorOpen = false;
        },

        async prepareLocationEditorOptions() {
            await this.loadCountries();

            if (this.selectedState) {
                await this.loadStates();
            }

            if (this.selectedCity) {
                await this.loadCities();
                await this.loadAreas();
            }
        },

        async requestGeoLocation() {
            if (!('geolocation' in navigator)) {
                this.locationErrorMessage = 'Location detection is not supported in this browser. Please search manually.';
                return;
            }

            this.locationDetecting = true;
            this.locationErrorMessage = '';
            this.locationStatusMessage = 'Detecting your location...';

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    this.latitude = position.coords.latitude.toFixed(6);
                    this.longitude = position.coords.longitude.toFixed(6);
                    this.selectedLat = this.latitude;
                    this.selectedLng = this.longitude;
                    this.locationStatusMessage = 'Coordinates captured. Fetching location details...';

                    const resolved = await this.fillLocationDetails(this.latitude, this.longitude);
                    this.syncManualLocationFromFields();

                    if (resolved) {
                        this.locationStatusMessage = 'Location detected successfully.';
                    } else {
                        this.locationStatusMessage = 'Coordinates captured. Please confirm the searched location.';
                    }

                    if (this.isLocationEditorOpen) {
                        await this.prepareLocationEditorOptions();
                        await this.waitForLocationDom();
                        await this.initializeLocationMapPicker();
                    }

                    if (typeof this.syncMarketplaceLocationState === 'function') {
                        this.syncMarketplaceLocationState();
                    }

                    this.locationDetecting = false;
                },
                () => {
                    this.locationDetecting = false;
                    this.locationStatusMessage = '';
                    this.locationErrorMessage = 'Unable to access your device location. Please search manually.';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0,
                }
            );
        },

        detectLocation() {
            return this.requestGeoLocation();
        },

        async loadCountries() {
            const endpoint = this.locationApi.countries;
            if (!endpoint) {
                return;
            }

            try {
                const response = await fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                this.countryOptions = Array.isArray(payload.items) ? payload.items : [];
            } catch (_) {
                this.countryOptions = [];
            }
        },

        async onCountryChanged() {
            this.selectedState = '';
            this.selectedCity = '';
            this.selectedArea = '';
            this.selectedLat = '';
            this.selectedLng = '';
            this.stateOptions = [];
            this.cityOptions = [];
            this.areaOptions = [];
            await this.loadStates();
        },

        async onStateChanged() {
            this.selectedCity = '';
            this.selectedArea = '';
            this.selectedLat = '';
            this.selectedLng = '';
            this.cityOptions = [];
            this.areaOptions = [];
            await this.loadCities();
        },

        async onCityChanged() {
            this.selectedArea = '';
            this.selectedLat = '';
            this.selectedLng = '';
            this.areaOptions = [];
            await this.loadAreas();
        },

        onAreaChanged() {
            const normalizedArea = normalizeText(this.selectedArea);
            const selectedArea = this.areaOptions.find((item) => normalizeText(item.name) === normalizedArea);

            if (selectedArea && selectedArea.latitude && selectedArea.longitude) {
                this.selectedLat = String(selectedArea.latitude);
                this.selectedLng = String(selectedArea.longitude);
                this.placeLocationSelectorMarker(parseFloat(this.selectedLat), parseFloat(this.selectedLng));
            }
        },

        async loadStates() {
            const endpoint = this.locationApi.states;
            if (!endpoint || !this.selectedCountry) {
                return;
            }

            try {
                const response = await fetch(`${endpoint}?country=${encodeURIComponent(this.selectedCountry)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                this.stateOptions = Array.isArray(payload.items) ? payload.items : [];
            } catch (_) {
                this.stateOptions = [];
            }
        },

        async loadCities() {
            const endpoint = this.locationApi.cities;
            if (!endpoint || !this.selectedCountry || !this.selectedState) {
                return;
            }

            const params = new URLSearchParams({
                country: this.selectedCountry,
                state: this.selectedState,
            });

            try {
                const response = await fetch(`${endpoint}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                this.cityOptions = Array.isArray(payload.items) ? payload.items : [];
            } catch (_) {
                this.cityOptions = [];
            }
        },

        async loadAreas() {
            const endpoint = this.locationApi.areas;
            if (!endpoint || !this.selectedCountry || !this.selectedState || !this.selectedCity) {
                return;
            }

            const params = new URLSearchParams({
                country: this.selectedCountry,
                state: this.selectedState,
                city: this.selectedCity,
            });

            try {
                const response = await fetch(`${endpoint}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                this.areaOptions = Array.isArray(payload.items) ? payload.items : [];
            } catch (_) {
                this.areaOptions = [];
            }
        },

        applySelectedLocation() {
            this.city = normalizeText(this.selectedCity);
            this.state = normalizeText(this.selectedState);
            this.address = normalizeText(this.selectedArea);
            this.latitude = normalizeCoordinate(this.selectedLat);
            this.longitude = normalizeCoordinate(this.selectedLng);
            this.locationErrorMessage = '';
            this.locationStatusMessage = this.city !== '' ? 'Location selected successfully.' : '';
            this.isLocationEditorOpen = false;

            if (typeof this.syncMarketplaceLocationState === 'function') {
                this.syncMarketplaceLocationState();
            }
        },

        async initializeLocationMapPicker() {
            if (!this.mapsApiKey) {
                return;
            }

            const ready = await this.ensureGoogleMapsCoreScript();
            if (!ready || !window.google || !window.google.maps) {
                return;
            }

            const mapContainer = document.getElementById('listing-location-selector-map');
            if (!mapContainer) {
                return;
            }

            const defaultLat = parseFloat(this.selectedLat || this.latitude || '20.5937');
            const defaultLng = parseFloat(this.selectedLng || this.longitude || '78.9629');

            if (!this.mapInstance) {
                this.mapInstance = new window.google.maps.Map(mapContainer, {
                    center: { lat: defaultLat, lng: defaultLng },
                    zoom: 11,
                    mapTypeControl: false,
                    streetViewControl: false,
                });

                this.mapInstance.addListener('click', (event) => {
                    const lat = event.latLng.lat();
                    const lng = event.latLng.lng();
                    this.selectedLat = lat.toFixed(6);
                    this.selectedLng = lng.toFixed(6);
                    this.placeLocationSelectorMarker(lat, lng);
                });
            } else {
                this.mapInstance.setCenter({ lat: defaultLat, lng: defaultLng });
            }

            this.placeLocationSelectorMarker(defaultLat, defaultLng);
        },

        placeLocationSelectorMarker(lat, lng) {
            if (!window.google || !window.google.maps || !this.mapInstance) {
                return;
            }

            if (!this.mapMarker) {
                this.mapMarker = new window.google.maps.Marker({
                    map: this.mapInstance,
                    position: { lat, lng },
                });
            } else {
                this.mapMarker.setPosition({ lat, lng });
            }

            this.mapInstance.panTo({ lat, lng });
        },

        getAddressValue(obj, keys) {
            if (!obj || !Array.isArray(keys)) {
                return '';
            }

            for (const key of keys) {
                const value = obj[key];
                if (value && normalizeText(value) !== '') {
                    return normalizeText(value);
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
                    return normalizeText(found.long_name);
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
                    Accept: 'application/json',
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
    };
}