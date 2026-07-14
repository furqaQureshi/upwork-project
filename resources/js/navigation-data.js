export const navigationData = ({
    initialMobileLocationLabel = 'Select location',
    homeUrl = '/',
    mapsApiKey = '',
    notificationPromptAvailable = false,
} = {}) => ({
    open: false,
    notificationsOpen: false,
    mobileLocationLabel: initialMobileLocationLabel,
    locationSelectorOpen: false,
    locationSelectorMessage: '',
    locationDetecting: false,
    notificationPromptAvailable,
    notificationPermission: 'default',
    notificationRequired: false,
    notificationBusy: false,
    notificationMessage: '',
    notificationDeniedBannerDismissed: false,
    notificationDeniedBannerStorageKey: 'unsell_notification_denied_banner_dismissed',
    locationStorageKey: 'unsell_location_state',
    showManualLocationInput: false,
    manualLocationInput: '',
    locationRequired: false,
    homeUrl,
    mapsApiKey,
    mapsScriptPromise: null,
    locationAutocompleteService: null,
    locationGeocoder: null,
    locationSuggestions: [],
    locationSuggestionsOpen: false,
    locationSearchLoading: false,
    autocompleteDebounceTimer: null,
    init() {
        this.hydrateMobileLocationLabel();

        this.locationRequired = !this.hasLocationContext();
        if (this.notificationPromptAvailable && 'Notification' in window) {
            this.notificationPermission = Notification.permission;
            this.notificationRequired = Notification.permission !== 'granted';
        }

        this.hydrateNotificationDeniedBannerState();

        if (this.locationRequired || this.notificationRequired) {
            this.openLocationSelector(true);
        }

        window.addEventListener('unsell-location-updated', (event) => {
            const detail = event && event.detail ? event.detail : null;
            if (!detail || !Object.prototype.hasOwnProperty.call(detail, 'label')) {
                return;
            }

            const label = detail.label ? String(detail.label).trim() : '';

            if (label === '') {
                this.mobileLocationLabel = 'Select location';
                this.clearMobileLocationLabel();
                this.locationRequired = true;
                this.locationSelectorMessage = '';
                return;
            }

            this.mobileLocationLabel = label;
            this.persistMobileLocationLabel(label);
            this.locationRequired = false;
            this.locationSelectorOpen = false;
            this.locationSelectorMessage = '';
        });

        window.addEventListener('unsell-notification-permission-updated', (event) => {
            const detail = event && event.detail ? event.detail : null;
            if (!detail || detail.supported === false) {
                return;
            }

            this.notificationPermission = detail.permission || this.notificationPermission;
            this.notificationRequired = !detail.granted;

            if (this.notificationPermission === 'denied') {
                this.notificationDeniedBannerDismissed = false;
                this.persistNotificationDeniedBannerState();
            }

            if (detail.granted) {
                this.notificationDeniedBannerDismissed = false;
                this.persistNotificationDeniedBannerState();
                this.notificationMessage = 'Notifications enabled successfully.';

                if (!this.locationRequired) {
                    this.locationSelectorOpen = false;
                }
            }
        });
    },
    permissionPromptTitle() {
        if (this.locationRequired && this.notificationRequired) {
            return 'Enable notifications and location';
        }

        if (this.locationRequired) {
            return 'Location required for marketplace access';
        }

        if (this.notificationRequired) {
            return 'Enable browser notifications';
        }

        return 'Permissions setup';
    },
    permissionPromptSubtitle() {
        if (this.locationRequired && this.notificationRequired) {
            return 'Notifications keep chats and updates instant, and location helps us show nearby listings.';
        }

        if (this.locationRequired) {
            return 'Choose one option to continue.';
        }

        if (this.notificationRequired) {
            return 'Allow browser notifications so chat messages, approvals, and admin alerts reach you instantly.';
        }

        return '';
    },
    readStoredLocationState() {
        try {
            const rawState = window.localStorage.getItem(this.locationStorageKey);
            if (rawState) {
                const parsedState = JSON.parse(rawState);
                if (parsedState && typeof parsedState === 'object') {
                    return {
                        label: typeof parsedState.label === 'string' ? parsedState.label : '',
                        promptHandled: parsedState.promptHandled === true,
                    };
                }
            }
        } catch (_) {
        }

        try {
            const legacyLabel = window.localStorage.getItem('unsell_selected_location_label');
            const legacyPromptHandled = window.localStorage.getItem('unsell_location_prompt_seen') === '1';
            if ((legacyLabel && legacyLabel.trim() !== '') || legacyPromptHandled) {
                const migratedState = {
                    label: legacyLabel && legacyLabel.trim() !== '' ? legacyLabel.trim() : '',
                    promptHandled: legacyPromptHandled || !!(legacyLabel && legacyLabel.trim() !== ''),
                };
                this.writeStoredLocationState(migratedState);
                return migratedState;
            }
        } catch (_) {
        }

        return {
            label: '',
            promptHandled: false,
        };
    },
    writeStoredLocationState(state) {
        try {
            const normalizedState = {
                label: state && typeof state.label === 'string' ? state.label.trim() : '',
                promptHandled: !!(state && state.promptHandled),
            };

            window.localStorage.setItem(this.locationStorageKey, JSON.stringify(normalizedState));
            window.localStorage.removeItem('unsell_selected_location_label');
            window.localStorage.removeItem('unsell_location_prompt_seen');
        } catch (_) {
        }
    },
    hasLocationContext() {
        return this.hasLocationInUrl() || this.hasStoredLocationLabel();
    },
    hasLocationInUrl() {
        try {
            const currentUrl = new URL(window.location.href);
            return ['area', 'city', 'state', 'lat', 'lng'].some((key) => {
                const value = currentUrl.searchParams.get(key);
                return value && String(value).trim() !== '';
            });
        } catch (_) {
            return false;
        }
    },
    hasStoredLocationLabel() {
        try {
            const savedState = this.readStoredLocationState();
            return savedState.label !== '';
        } catch (_) {
            return false;
        }
    },
    getUrlLocationLabel() {
        try {
            const currentUrl = new URL(window.location.href);
            const queryParts = ['area', 'city', 'state']
                .map((key) => {
                    const value = currentUrl.searchParams.get(key);
                    return value ? String(value).trim() : '';
                })
                .filter((value) => value !== '');

            if (queryParts.length > 0) {
                return queryParts.join(', ');
            }

            const lat = currentUrl.searchParams.get('lat');
            const lng = currentUrl.searchParams.get('lng');
            if (lat && lng) {
                return 'Current location';
            }

            return '';
        } catch (_) {
            return '';
        }
    },
    hydrateMobileLocationLabel() {
        const urlLabel = this.getUrlLocationLabel();
        if (urlLabel !== '') {
            this.mobileLocationLabel = urlLabel;
            this.persistMobileLocationLabel(urlLabel);
            return;
        }

        try {
            const savedLabel = this.readStoredLocationState().label;
            if (savedLabel && savedLabel.trim() !== '') {
                this.mobileLocationLabel = savedLabel;
            }
        } catch (_) {
        }
    },
    persistMobileLocationLabel(label) {
        try {
            this.writeStoredLocationState({
                label,
                promptHandled: true,
            });
        } catch (_) {
        }
    },
    clearMobileLocationLabel() {
        try {
            this.writeStoredLocationState({
                label: '',
                promptHandled: false,
            });
        } catch (_) {
        }
    },
    resetLocationQueryParams(url) {
        url.searchParams.delete('area');
        url.searchParams.delete('state');
        url.searchParams.delete('city');
        url.searchParams.delete('lat');
        url.searchParams.delete('lng');
        url.searchParams.delete('page');
    },
    clearManualSearchState() {
        if (this.autocompleteDebounceTimer) {
            window.clearTimeout(this.autocompleteDebounceTimer);
            this.autocompleteDebounceTimer = null;
        }

        this.locationSuggestions = [];
        this.locationSuggestionsOpen = false;
        this.locationSearchLoading = false;
    },
    async ensurePlacesScript() {
        if (!this.mapsApiKey) {
            return false;
        }

        if (window.google && window.google.maps && window.google.maps.places) {
            return true;
        }

        if (this.mapsScriptPromise) {
            return this.mapsScriptPromise;
        }

        this.mapsScriptPromise = new Promise((resolve) => {
            const existing = document.querySelector('script[data-google-maps-places]');
            if (existing) {
                if (window.google && window.google.maps && window.google.maps.places) {
                    resolve(true);
                    return;
                }

                existing.addEventListener('load', () => {
                    resolve(!!(window.google && window.google.maps && window.google.maps.places));
                }, { once: true });
                existing.addEventListener('error', () => resolve(false), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(this.mapsApiKey)}&libraries=places`;
            script.async = true;
            script.defer = true;
            script.dataset.googleMapsPlaces = '1';
            script.onload = () => resolve(!!(window.google && window.google.maps && window.google.maps.places));
            script.onerror = () => resolve(false);
            document.head.appendChild(script);
        });

        const loaded = await this.mapsScriptPromise;
        if (!loaded) {
            this.mapsScriptPromise = null;
        }

        return loaded;
    },
    scheduleManualLocationLookup() {
        this.locationSelectorMessage = '';
        this.locationSuggestionsOpen = true;

        if (this.autocompleteDebounceTimer) {
            window.clearTimeout(this.autocompleteDebounceTimer);
        }

        const query = this.manualLocationInput.trim();
        if (query.length < 2) {
            this.locationSearchLoading = false;
            this.locationSuggestions = [];
            return;
        }

        this.autocompleteDebounceTimer = window.setTimeout(() => {
            this.fetchManualLocationSuggestions(query);
        }, 220);
    },
    async fetchManualLocationSuggestions(query) {
        this.locationSearchLoading = true;

        try {
            const placesReady = await this.ensurePlacesScript();
            if (!placesReady || !window.google || !window.google.maps || !window.google.maps.places) {
                this.locationSuggestions = [];
                this.locationSelectorMessage = this.mapsApiKey
                    ? 'Google Places is unavailable. Type location and tap Apply.'
                    : 'Google Maps API key is missing. Type location and tap Apply.';
                return;
            }

            if (!this.locationAutocompleteService) {
                this.locationAutocompleteService = new window.google.maps.places.AutocompleteService();
            }

            const statusEnum = window.google.maps.places.PlacesServiceStatus;
            const predictions = await new Promise((resolve, reject) => {
                this.locationAutocompleteService.getPlacePredictions({
                    input: query,
                    types: ['geocode'],
                }, (results, status) => {
                    if (status === statusEnum.OK || status === statusEnum.ZERO_RESULTS) {
                        resolve(results || []);
                        return;
                    }

                    reject(status);
                });
            });

            this.locationSuggestions = predictions.map((prediction) => ({
                label: prediction.description,
                placeId: prediction.place_id,
                primary: prediction.structured_formatting && prediction.structured_formatting.main_text
                    ? prediction.structured_formatting.main_text
                    : prediction.description,
                secondary: prediction.structured_formatting && prediction.structured_formatting.secondary_text
                    ? prediction.structured_formatting.secondary_text
                    : '',
            }));

            if (this.locationSuggestions.length === 0) {
                this.locationSelectorMessage = 'No locations found. You can still enter manually and tap Apply.';
            }
        } catch (_) {
            this.locationSuggestions = [];
            this.locationSelectorMessage = 'Unable to fetch locations from Google. Type location and tap Apply.';
        } finally {
            this.locationSearchLoading = false;
            this.locationSuggestionsOpen = true;
        }
    },
    getLocationPart(components, preferredTypes) {
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
    async resolveGoogleLocation(query, placeId = null) {
        const placesReady = await this.ensurePlacesScript();
        if (!placesReady || !window.google || !window.google.maps) {
            return null;
        }

        if (!this.locationGeocoder) {
            this.locationGeocoder = new window.google.maps.Geocoder();
        }

        const results = await new Promise((resolve, reject) => {
            const request = placeId ? { placeId } : { address: query };
            this.locationGeocoder.geocode(request, (geocodeResults, status) => {
                if (status === 'OK' && Array.isArray(geocodeResults) && geocodeResults.length > 0) {
                    resolve(geocodeResults);
                    return;
                }

                reject(status);
            });
        });

        const first = results[0];
        const components = Array.isArray(first.address_components) ? first.address_components : [];
        const point = first.geometry && first.geometry.location ? first.geometry.location : null;

        const area = this.getLocationPart(components, ['sublocality_level_1', 'sublocality', 'neighborhood']);
        const city = this.getLocationPart(components, ['locality', 'postal_town', 'administrative_area_level_2']);
        const state = this.getLocationPart(components, ['administrative_area_level_1']);

        return {
            label: first.formatted_address || query,
            area,
            city,
            state,
            lat: point ? point.lat().toFixed(6) : '',
            lng: point ? point.lng().toFixed(6) : '',
        };
    },
    applyLocationAndRedirect(selection) {
        const label = selection && selection.label ? String(selection.label).trim() : '';
        this.mobileLocationLabel = label !== '' ? label : 'Current location';
        this.persistMobileLocationLabel(this.mobileLocationLabel);

        const url = new URL(this.homeUrl, window.location.origin);
        this.resetLocationQueryParams(url);

        if (selection && selection.area) {
            url.searchParams.set('area', selection.area);
        }
        if (selection && selection.state) {
            url.searchParams.set('state', selection.state);
        }

        const cityValue = selection && selection.city ? selection.city : (label || this.manualLocationInput.trim());
        if (cityValue && String(cityValue).trim() !== '') {
            url.searchParams.set('city', String(cityValue).trim());
        }

        if (selection && selection.lat && selection.lng) {
            url.searchParams.set('lat', selection.lat);
            url.searchParams.set('lng', selection.lng);
        }

        window.location.assign(url.toString());
    },
    openLocationSelector(force = false) {
        if (this.locationDetecting && !force) {
            return;
        }

        this.locationSelectorOpen = true;
        this.locationSelectorMessage = '';
        this.showManualLocationInput = false;
        this.manualLocationInput = (this.mobileLocationLabel === 'Select location' || this.mobileLocationLabel === 'Current location') ? '' : this.mobileLocationLabel;
        this.clearManualSearchState();
    },
    closeLocationSelector() {
        if (this.locationRequired) {
            this.locationSelectorMessage = 'Location is required to continue.';
            return;
        }

        this.locationSelectorOpen = false;
        this.showManualLocationInput = false;
        this.locationSelectorMessage = '';
        this.clearManualSearchState();
    },
    chooseMobileLocation() {
        this.openLocationSelector(false);
    },
    hydrateNotificationDeniedBannerState() {
        try {
            this.notificationDeniedBannerDismissed = window.localStorage.getItem(this.notificationDeniedBannerStorageKey) === '1';
        } catch (_) {
            this.notificationDeniedBannerDismissed = false;
        }

        if (this.notificationPermission !== 'denied') {
            this.notificationDeniedBannerDismissed = false;
            this.persistNotificationDeniedBannerState();
        }
    },
    persistNotificationDeniedBannerState() {
        try {
            if (this.notificationDeniedBannerDismissed) {
                window.localStorage.setItem(this.notificationDeniedBannerStorageKey, '1');
                return;
            }

            window.localStorage.removeItem(this.notificationDeniedBannerStorageKey);
        } catch (_) {
            // Ignore storage errors.
        }
    },
    showNotificationDeniedBanner() {
        return this.notificationPromptAvailable
            && this.notificationPermission === 'denied'
            && !this.notificationDeniedBannerDismissed;
    },
    dismissNotificationDeniedBanner() {
        this.notificationDeniedBannerDismissed = true;
        this.persistNotificationDeniedBannerState();
    },
    showNotificationHelpFromBanner() {
        this.notificationDeniedBannerDismissed = false;
        this.persistNotificationDeniedBannerState();
        this.notificationMessage = 'Notifications are blocked in your browser settings. Allow notifications for this site and refresh.';
        this.openLocationSelector(true);
    },
    async enableNotificationsFromStartup() {
        if (this.notificationBusy) {
            return;
        }

        if (!(this.notificationPromptAvailable && 'Notification' in window)) {
            this.notificationMessage = 'Notifications are unavailable on this device.';
            return;
        }

        this.notificationBusy = true;
        this.notificationMessage = '';

        const result = typeof window.unisellRequestNotificationPermission === 'function'
            ? await window.unisellRequestNotificationPermission()
            : { supported: false, granted: false, permission: 'unsupported' };

        this.notificationBusy = false;
        this.notificationPermission = result.permission || this.notificationPermission;
        this.notificationRequired = !result.granted;

        if (!result.supported) {
            this.notificationMessage = 'Notifications are unavailable on this device.';
            return;
        }

        if (result.granted) {
            this.notificationDeniedBannerDismissed = false;
            this.persistNotificationDeniedBannerState();
            this.notificationMessage = 'Notifications enabled successfully.';

            if (!this.locationRequired) {
                this.locationSelectorOpen = false;
            }

            return;
        }

        if (this.notificationPermission === 'denied') {
            this.notificationDeniedBannerDismissed = false;
            this.persistNotificationDeniedBannerState();
            this.notificationMessage = 'Notifications are blocked. Enable them from browser settings.';
            return;
        }

        this.notificationMessage = 'Notification permission was not granted yet.';
    },
    askManualLocation() {
        this.showManualLocationInput = true;
        this.locationSelectorMessage = '';
        this.locationSuggestionsOpen = false;

        this.$nextTick(() => {
            if (this.$refs.manualLocationInput) {
                this.$refs.manualLocationInput.focus();
            }

            if (this.manualLocationInput.trim().length >= 2) {
                this.scheduleManualLocationLookup();
            }
        });
    },
    async chooseLocationSuggestion(item) {
        if (!item) {
            return;
        }

        this.manualLocationInput = item.label;
        this.locationSuggestionsOpen = false;
        this.locationSuggestions = [];
        this.locationSelectorMessage = '';
        this.locationSearchLoading = true;

        try {
            const resolved = await this.resolveGoogleLocation(item.label, item.placeId || null);
            if (resolved) {
                this.applyLocationAndRedirect(resolved);
                return;
            }
        } catch (_) {
            // Fall back to typed selection.
        } finally {
            this.locationSearchLoading = false;
        }

        this.applyLocationAndRedirect({
            label: item.label,
            city: item.label,
        });
    },
    async applyManualLocation() {
        const cleaned = this.manualLocationInput.trim();
        if (cleaned === '') {
            this.locationSelectorMessage = 'Location is required to continue.';
            return;
        }

        this.locationSearchLoading = true;
        this.locationSuggestionsOpen = false;
        this.locationSelectorMessage = '';

        try {
            const resolved = await this.resolveGoogleLocation(cleaned);
            if (resolved) {
                this.applyLocationAndRedirect(resolved);
                return;
            }
        } catch (_) {
            // Fall back to manual value.
        } finally {
            this.locationSearchLoading = false;
        }

        this.applyLocationAndRedirect({
            label: cleaned,
            city: cleaned,
        });
    },
    detectMobileLocation() {
        if (!('geolocation' in navigator)) {
            this.locationSelectorMessage = 'Geolocation is not supported. Search location manually.';
            this.askManualLocation();
            return;
        }

        this.locationDetecting = true;
        this.locationSelectorMessage = 'Detecting your location...';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude.toFixed(6);
                const lng = position.coords.longitude.toFixed(6);
                const label = 'Current location';

                this.locationDetecting = false;
                this.mobileLocationLabel = label;
                this.persistMobileLocationLabel(label);

                const url = new URL(this.homeUrl, window.location.origin);
                this.resetLocationQueryParams(url);
                url.searchParams.set('lat', lat);
                url.searchParams.set('lng', lng);
                window.location.assign(url.toString());
            },
            () => {
                this.locationDetecting = false;
                this.locationSelectorMessage = 'Location permission denied. Search location manually.';
                this.askManualLocation();
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0,
            }
        );
    },
});
