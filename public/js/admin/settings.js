// public/js/admin/settings.js

window.adminSettingsPage = function(config) {
    const defaults = [
        {
            badge: 'Trending Deals',
            title: 'Cars and bikes this week',
            desc: 'Browse top listings from nearby sellers and compare prices fast.',
        },
        {
            badge: 'Smart Buying',
            title: 'Verified listings, faster chats',
            desc: 'Message sellers instantly and close deals with confidence.',
        },
        {
            badge: 'Post Instantly',
            title: 'Sell in your city today',
            desc: 'Upload photos, set price, and publish your ad in under two minutes.',
        },
    ];

    const normalizeImages = (images) => {
        const initial = Array.isArray(images) ? images.map(item => String(item ?? '').trim()) : [];
        return initial.length > 0 ? initial : [''];
    };

    const normalizePositions = (positions, images) => {
        const allowed = ['center', 'top', 'bottom', 'left', 'right'];
        const raw = Array.isArray(positions) ? positions : [];
        return images.map((_, i) => {
            const p = String(raw[i] ?? 'center').trim();
            return allowed.includes(p) ? p : 'center';
        });
    };

    const normalizeFits = (fits, images) => {
        const raw = Array.isArray(fits) ? fits : [];
        return images.map((_, i) => {
            const f = String(raw[i] ?? 'cover').trim();
            return f === 'contain' ? 'contain' : 'cover';
        });
    };

    const normalizeDisplaySeconds = (seconds) => {
        const value = Number(seconds);
        if (!Number.isFinite(value)) return 5;
        return Math.min(60, Math.max(1, Math.round(value)));
    };

    const normalizeAppBannerImages = (images) => {
        return Array.isArray(images)
            ? images.map(item => String(item ?? '').trim()).filter(item => item !== '')
            : [];
    };

    const normalizeSlides = (slides) => {
        const incoming = Array.isArray(slides) ? slides : [];
        return defaults.map((fallback, index) => {
            const slide = incoming[index] || {};
            return {
                badge: String(slide.badge ?? fallback.badge),
                title: String(slide.title ?? fallback.title),
                desc: String(slide.desc ?? fallback.desc),
            };
        });
    };

    const images = normalizeImages(config.bannerImages);
    
    return {
        tab: String(config.activeTab || 'general'),
        bannerMode: String(config.bannerMode || 'text') === 'image' ? 'image' : 'text',
        bannerImages: images,
        bannerPositions: normalizePositions(config.bannerPositions, images),
        bannerFits: normalizeFits(config.bannerFits, images),
        bannerDisplaySeconds: normalizeDisplaySeconds(config.bannerDisplaySeconds),
        appBannerImages: normalizeAppBannerImages(config.appBannerImages),
        bannerImageObjectUrls: images.map(() => ''),
        storageBaseUrl: String(config.storageBaseUrl || '/storage').replace(/\/+$/, ''),
        siteLogoUrl: String(config.siteLogoUrl || ''),
        siteFaviconUrl: String(config.siteFaviconUrl || ''),
        siteLogoPreview: '',
        siteFaviconPreview: '',
        textSlides: normalizeSlides(config.slides),
        textSlideDefaults: defaults,
        previewSlideIndex: 0,
        previewTimer: null,

        init() {
            this.startPreviewTimer();

            this.$watch('bannerMode', () => {
                this.previewSlideIndex = 0;
            });

            this.$watch('bannerDisplaySeconds', () => {
                this.startPreviewTimer();
            });

            window.addEventListener('beforeunload', () => {
                if (this.previewTimer) {
                    window.clearInterval(this.previewTimer);
                }
                this.bannerImageObjectUrls.forEach((_, index) => {
                    this.revokeObjectUrl(index);
                });
                this.revokeBrandingPreview('logo');
                this.revokeBrandingPreview('favicon');
            });
        },

        normalizedBannerDisplayMs() {
            const seconds = Number(this.bannerDisplaySeconds);
            if (!Number.isFinite(seconds)) return 5000;
            return Math.min(60, Math.max(1, Math.round(seconds))) * 1000;
        },

        startPreviewTimer() {
            if (this.previewTimer) window.clearInterval(this.previewTimer);
            this.previewTimer = window.setInterval(() => {
                this.advancePreviewSlide();
            }, this.normalizedBannerDisplayMs());
        },

        addBannerImage() {
            if (this.bannerImages.length >= 10) return;
            this.bannerImages.push('');
            this.bannerImageObjectUrls.push('');
            this.bannerPositions.push('center');
            this.bannerFits.push('cover');
        },

        removeBannerImage(index) {
            if (this.bannerImages.length <= 1) {
                this.bannerImages[0] = '';
                this.revokeObjectUrl(0);
                this.bannerPositions[0] = 'center';
                this.bannerFits[0] = 'cover';
                return;
            }

            this.revokeObjectUrl(index);
            this.bannerImages.splice(index, 1);
            this.bannerImageObjectUrls.splice(index, 1);
            this.bannerPositions.splice(index, 1);
            this.bannerFits.splice(index, 1);

            const maxIndex = this.currentPreviewCount() - 1;
            if (this.previewSlideIndex > maxIndex) this.previewSlideIndex = 0;
        },

        moveAppBannerUp(index) {
            if (index <= 0 || index >= this.appBannerImages.length) return;
            const temp = this.appBannerImages[index - 1];
            this.appBannerImages[index - 1] = this.appBannerImages[index];
            this.appBannerImages[index] = temp;
        },

        moveAppBannerDown(index) {
            if (index < 0 || index >= this.appBannerImages.length - 1) return;
            const temp = this.appBannerImages[index + 1];
            this.appBannerImages[index + 1] = this.appBannerImages[index];
            this.appBannerImages[index] = temp;
        },

        removeAppBanner(index) {
            if (index < 0 || index >= this.appBannerImages.length) return;
            this.appBannerImages.splice(index, 1);
        },

        onBannerFileChanged(event, index) {
            this.revokeObjectUrl(index);
            const file = event?.target?.files?.[0];
            if (!file) {
                this.bannerImageObjectUrls[index] = '';
                return;
            }
            this.bannerImageObjectUrls[index] = URL.createObjectURL(file);
        },

        revokeObjectUrl(index) {
            const objectUrl = this.bannerImageObjectUrls[index];
            if (typeof objectUrl === 'string' && objectUrl.startsWith('blob:')) {
                try { URL.revokeObjectURL(objectUrl); } catch (_) {}
            }
            this.bannerImageObjectUrls[index] = '';
        },

        resolveImagePath(path) {
            const source = String(path || '').trim();
            if (source === '') return '';
            if (source.startsWith('http://') || source.startsWith('https://') || source.startsWith('/')) return source;
            const base = this.storageBaseUrl || '/storage';
            return `${base}/${source.replace(/^\/+/, '')}`;
        },

        brandingPreview(path, preview) {
            const objectUrl = String(preview || '').trim();
            if (objectUrl !== '') return objectUrl;
            return this.resolveImagePath(path);
        },

        revokeBrandingPreview(type) {
            const key = type === 'favicon' ? 'siteFaviconPreview' : 'siteLogoPreview';
            const objectUrl = String(this[key] || '').trim();
            if (objectUrl.startsWith('blob:')) {
                try { URL.revokeObjectURL(objectUrl); } catch (_) {}
            }
            this[key] = '';
        },

        onBrandingFileChanged(event, type) {
            this.revokeBrandingPreview(type);
            const file = event?.target?.files?.[0];
            if (!file) return;
            const key = type === 'favicon' ? 'siteFaviconPreview' : 'siteLogoPreview';
            this[key] = URL.createObjectURL(file);
        },

        bannerImagePreview(index) {
            const objectUrl = String(this.bannerImageObjectUrls[index] || '').trim();
            if (objectUrl !== '') return objectUrl;
            return this.resolveImagePath(this.bannerImages[index] || '');
        },

        bannerPreviewImages() {
            return this.bannerImages
                .map((_, index) => this.bannerImagePreview(index))
                .filter(image => String(image).trim() !== '');
        },

        primaryBannerImage() {
            const firstImage = this.bannerImages
                .map(image => String(image || '').trim())
                .find(image => image !== '');
            return firstImage || '';
        },

        currentPreviewCount() {
            if (this.bannerMode === 'image') return this.bannerPreviewImages().length;
            return this.textSlides.length;
        },

        advancePreviewSlide() {
            const total = this.currentPreviewCount();
            if (total <= 1) {
                this.previewSlideIndex = 0;
                return;
            }
            this.previewSlideIndex = (this.previewSlideIndex + 1) % total;
        },

        setPreviewSlide(index) {
            this.previewSlideIndex = Number(index) || 0;
        },

        slideValue(index, field) {
            const safeIndex = Number(index) || 0;
            const slide = this.textSlides[safeIndex] || {};
            const fallback = this.textSlideDefaults[safeIndex] || this.textSlideDefaults[0];
            const value = String(slide[field] || '').trim();
            return value !== '' ? value : String(fallback[field] || '');
        },

        previewTextGradient() {
            if (this.previewSlideIndex === 1) return 'from-sky-500 via-cyan-500 to-teal-500';
            if (this.previewSlideIndex === 2) return 'from-violet-500 via-fuchsia-500 to-rose-500';
            return 'from-amber-400 via-orange-400 to-orange-500';
        },
    };
};