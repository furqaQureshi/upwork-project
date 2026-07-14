// public/js/admin/twa.js

window.initTwaSettings = function(config) {
    return {
        twaEnabled: config.twaEnabled,
        name: config.name,
        shortName: config.shortName,
        description: config.description,
        startUrl: config.startUrl,
        scope: config.scope,
        display: config.display,
        orientation: config.orientation,
        themeColor: config.themeColor,
        backgroundColor: config.backgroundColor,
        packageName: config.packageName,
        fingerprintsText: config.fingerprintsText,
        iconUrl: config.iconUrl,
        iconMaskableUrl: config.iconMaskableUrl,
        navigationColor: config.navigationColor,
        splashFadeDuration: config.splashFadeDuration,
        appVersionName: config.appVersionName,
        appVersionCode: config.appVersionCode,
        minSdkVersion: config.minSdkVersion,
        signingKeyAlias: config.signingKeyAlias,
        keystoreStoreType: config.keystoreStoreType,
        keyFullName: config.keyFullName,
        keyOrg: config.keyOrg,
        keyOrgUnit: config.keyOrgUnit,
        keyCountry: config.keyCountry,
        keyState: config.keyState,
        keyCity: config.keyCity,
        iconPreview: null,
        maskablePreview: null,
        bubblewrapCopied: false,
        keytoolCopied: false,
        validating: false,
        validationResults: null,
        manifestCopied: false,
        assetCopied: false,
        manifestPreviewText: '',
        assetPreviewText: '[]',
        bubblewrapPreviewText: '',
        assetLinksPreview: [],
        fingerprintStats: { total: 0, valid: 0, invalid: 0, firstInvalid: '' },
        previewRefreshTimer: null,
        manifestUrl: config.manifestUrl,
        assetlinksUrl: config.assetlinksUrl,
        appUrl: config.appUrl,
        storageUrl: config.storageUrl,

        parsedFingerprints() {
            return (this.fingerprintsText || '').split(/[\r\n,]+/)
                .map(s => s.trim().toUpperCase())
                .filter(s => s !== '');
        },

        fingerprintRegex() {
            return /^[A-F0-9]{2}(?::[A-F0-9]{2}){31}$/;
        },

        initTwaOptimizations() {
            this.refreshTwaDerivedState();
            const watchKeys = [
                'twaEnabled', 'name', 'shortName', 'description', 'startUrl', 'scope',
                'display', 'orientation', 'themeColor', 'backgroundColor', 'packageName',
                'fingerprintsText', 'iconUrl', 'iconMaskableUrl', 'iconPreview', 'maskablePreview',
                'navigationColor', 'splashFadeDuration', 'appVersionName', 'appVersionCode',
                'minSdkVersion', 'signingKeyAlias', 'keystoreStoreType'
            ];
            watchKeys.forEach((key) => {
                this.$watch(key, () => this.scheduleTwaDerivedRefresh());
            });
        },

        scheduleTwaDerivedRefresh() {
            clearTimeout(this.previewRefreshTimer);
            this.previewRefreshTimer = setTimeout(() => this.refreshTwaDerivedState(), 80);
        },

        refreshTwaDerivedState() {
            const all = this.parsedFingerprints();
            const matcher = this.fingerprintRegex();
            const valid = all.filter(f => matcher.test(f));
            const invalid = all.filter(f => !matcher.test(f));

            this.fingerprintStats = {
                total: all.length,
                valid: valid.length,
                invalid: invalid.length,
                firstInvalid: invalid[0] || ''
            };

            this.assetLinksPreview = this.buildAssetLinksJson(valid);
            this.manifestPreviewText = JSON.stringify(this.manifestJson(), null, 2);
            this.assetPreviewText = JSON.stringify(this.assetLinksPreview, null, 2);
            this.bubblewrapPreviewText = JSON.stringify(this.bubblewrapJson(valid), null, 2);
        },

        normalizeFingerprints() {
            const unique = Array.from(new Set(this.parsedFingerprints()));
            this.fingerprintsText = unique.join('\n');
            this.refreshTwaDerivedState();
        },

        normalizePath(p) {
            p = (p || '').trim();
            if (!p || p === '/') return '/';
            if (/^https?:\/\//i.test(p)) return p;
            return '/' + p.replace(/^\/+/, '');
        },

        iconSrc(url, preview) {
            if (preview) return preview;
            if (!url || url === '') return '';
            if (/^https?:\/\//i.test(url)) return url;

            const normalized = String(url).replace(/^\/+/, '');
            if (normalized.startsWith('media-files/')) {
                return '/' + normalized;
            }
            if (normalized.startsWith('storage/')) {
                return '/media-files/' + normalized.replace(/^storage\//, '');
            }

            const base = this.storageUrl || '/media-files';
            return base.replace(/\/+$/, '') + '/' + normalized;
        },

        onIconFileChanged(event, type) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                if (type === 'icon') this.iconPreview = e.target.result;
                else this.maskablePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        keytoolDN() {
            const parts = [];
            if ((this.keyFullName || '').trim()) parts.push('CN=' + this.keyFullName.trim());
            if ((this.keyOrgUnit || '').trim()) parts.push('OU=' + this.keyOrgUnit.trim());
            if ((this.keyOrg || '').trim()) parts.push('O=' + this.keyOrg.trim());
            if ((this.keyCity || '').trim()) parts.push('L=' + this.keyCity.trim());
            if ((this.keyState || '').trim()) parts.push('ST=' + this.keyState.trim());
            if ((this.keyCountry || '').trim()) parts.push('C=' + this.keyCountry.trim().toUpperCase());
            return parts.join(', ');
        },

        keytoolCmd() {
            const alias = (this.signingKeyAlias || 'android').trim();
            const storeType = (this.keystoreStoreType || 'PKCS12').toUpperCase();
            const ext = storeType === 'PKCS12' ? '.p12' : '.jks';
            const dn = this.keytoolDN();
            return `keytool -genkey -v -keystore android${ext} -alias ${alias} -keyalg RSA -keysize 2048 -validity 10000 -storetype ${storeType}` +
                (dn ? `\n    -dname '${dn}'` : '');
        },

        async copyKeytool() {
            const txt = this.keytoolCmd();
            try {
                await navigator.clipboard.writeText(txt);
            } catch(e) {
                const r = document.createRange();
                r.selectNodeContents(this.$refs.keytoolPre);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(r);
                document.execCommand('copy');
            }
            this.keytoolCopied = true;
            setTimeout(() => { this.keytoolCopied = false; }, 2000);
        },

        bubblewrapJson(validFingerprints = null) {
            const appUrl = this.appUrl || window.location.origin || '';
            let host = '';
            try { host = new URL(appUrl).host; } catch(e) { host = appUrl; }
            const matcher = this.fingerprintRegex();
            const fp = Array.isArray(validFingerprints)
                ? validFingerprints
                : this.parsedFingerprints().filter(f => matcher.test(f));
            const pkg = (this.packageName || '').trim();
            const resolveUrl = url => {
                if (!url || url === '') return '';
                if (/^https?:\/\//i.test(url)) return url;
                return appUrl.replace(/\/+$/, '') + '/' + url.replace(/^\/+/, '');
            };
            return {
                packageId: pkg || 'com.example.app',
                host: host,
                name: this.name || 'Unsell',
                launcherName: this.shortName || 'Unsell',
                display: this.display || 'standalone',
                orientation: this.orientation || 'any',
                themeColor: this.themeColor || '#f97316',
                navigationColor: this.navigationColor || '#000000',
                backgroundColor: this.backgroundColor || '#ffffff',
                startUrl: this.normalizePath(this.startUrl),
                iconUrl: resolveUrl(this.iconUrl),
                maskableIconUrl: resolveUrl(this.iconMaskableUrl),
                monochromeIconUrl: null,
                shortcuts: [],
                signingKey: { path: (this.keystoreStoreType || 'PKCS12').toUpperCase() === 'PKCS12' ? './android.p12' : './android.jks', alias: this.signingKeyAlias || 'android' },
                splashScreenFadeOutDuration: parseInt(this.splashFadeDuration) || 300,
                enableNotifications: true,
                features: {},
                alphaDependencies: { enabled: false },
                enableSiteSettingsShortcut: true,
                isChromeOSOnly: false,
                isMetaQuest: false,
                fingerprints: fp.map(f => ({ name: 'auto', value: f })),
                useBrowserOnChromeOS: true,
                minSdkVersion: parseInt(this.minSdkVersion) || 19,
                appVersionName: this.appVersionName || '1.0.0',
                appVersionCode: parseInt(this.appVersionCode) || 1,
                generatedAppVersionCode: parseInt(this.appVersionCode) || 1
            };
        },

        manifestJson() {
            return {
                name: this.name || 'Unsell',
                short_name: this.shortName || 'Unsell',
                description: this.description || '',
                start_url: this.normalizePath(this.startUrl),
                scope: this.normalizePath(this.scope),
                display: this.display || 'standalone',
                orientation: this.orientation || 'any',
                theme_color: this.themeColor || '#f97316',
                background_color: this.backgroundColor || '#ffffff',
                categories: ['shopping', 'lifestyle', 'business'],
                lang: 'en-IN',
                icons: [
                    (this.iconUrl ? { src: this.iconSrc(this.iconUrl, this.iconPreview), sizes: 'any', type: 'image/png', purpose: 'any' } : { src: '/branding/unsell-icon-512.png', sizes: 'any', type: 'image/svg+xml', purpose: 'any' }),
                    (this.iconMaskableUrl ? { src: this.iconSrc(this.iconMaskableUrl, this.maskablePreview), sizes: 'any', type: 'image/png', purpose: 'maskable' } : { src: '/branding/unsell-maskable-512.png', sizes: 'any', type: 'image/svg+xml', purpose: 'maskable' })
                ]
            };
        },

        buildAssetLinksJson(validFingerprints = null) {
            const matcher = this.fingerprintRegex();
            const fp = Array.isArray(validFingerprints)
                ? validFingerprints
                : this.parsedFingerprints().filter(f => matcher.test(f));
            const pkg = (this.packageName || '').trim();
            if (!this.twaEnabled || !pkg || fp.length === 0) return [];
            return [{
                relation: ['delegate_permission/common.handle_all_urls'],
                target: { namespace: 'android_app', package_name: pkg, sha256_cert_fingerprints: fp }
            }];
        },

        assetLinksJson() {
            return this.assetLinksPreview;
        },

        async copyManifest() {
            const txt = this.manifestPreviewText || JSON.stringify(this.manifestJson(), null, 2);
            try {
                await navigator.clipboard.writeText(txt);
            } catch(e) {
                const r = document.createRange();
                r.selectNodeContents(this.$refs.manifestPre);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(r);
                document.execCommand('copy');
            }
            this.manifestCopied = true;
            setTimeout(() => { this.manifestCopied = false; }, 2000);
        },

        async copyAsset() {
            const txt = this.assetPreviewText || JSON.stringify(this.assetLinksJson(), null, 2);
            try {
                await navigator.clipboard.writeText(txt);
            } catch(e) {
                const r = document.createRange();
                r.selectNodeContents(this.$refs.assetPre);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(r);
                document.execCommand('copy');
            }
            this.assetCopied = true;
            setTimeout(() => { this.assetCopied = false; }, 2000);
        },

        async copyBubblewrap() {
            const txt = this.bubblewrapPreviewText || JSON.stringify(this.bubblewrapJson(), null, 2);
            try {
                await navigator.clipboard.writeText(txt);
            } catch(e) {
                const r = document.createRange();
                r.selectNodeContents(this.$refs.bubblewrapPre);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(r);
                document.execCommand('copy');
            }
            this.bubblewrapCopied = true;
            setTimeout(() => { this.bubblewrapCopied = false; }, 2000);
        },

        async validate() {
            this.validating = true;
            this.validationResults = null;
            const checks = [];
            const pkg = (this.packageName || '').trim();
            const pkgOk = /^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/.test(pkg);
            checks.push({
                label: 'Android package name format',
                pass: pkgOk,
                detail: pkgOk ? pkg : (pkg === '' ? 'Package name is empty' : 'Invalid — expected com.example.app style')
            });

            const matcher = this.fingerprintRegex();
            const allFp = this.parsedFingerprints();
            const validFp = allFp.filter(f => matcher.test(f));
            const badFp = allFp.filter(f => !matcher.test(f));
            checks.push({
                label: 'SHA-256 fingerprint format',
                pass: allFp.length > 0 && badFp.length === 0,
                detail: allFp.length === 0 ? 'No fingerprints entered' : badFp.length > 0 ? (badFp.length + ' invalid — first: ' + badFp[0]) : (validFp.length + ' fingerprint(s) valid')
            });

            const themeOk = /^#[0-9A-Fa-f]{6}$/.test(this.themeColor);
            const bgOk = /^#[0-9A-Fa-f]{6}$/.test(this.backgroundColor);
            checks.push({
                label: 'Color values (#RRGGBB format)',
                pass: themeOk && bgOk,
                detail: (!themeOk ? ('Invalid theme: ' + this.themeColor + ' ') : '') + (!bgOk ? ('Invalid background: ' + this.backgroundColor) : '') || 'Both colors valid'
            });

            checks.push({
                label: 'TWA output enabled',
                pass: !!this.twaEnabled,
                detail: this.twaEnabled ? 'Asset links will be served when settings are saved' : 'Toggle is off — save with toggle on to serve asset links'
            });

            const iconPresent = (this.iconUrl || '').trim() !== '';
            checks.push({
                label: 'App icon configured',
                pass: iconPresent,
                detail: iconPresent ? 'Icon URL: ' + this.iconSrc(this.iconUrl, null) : 'No icon set — upload one or enter a URL for best PWA/TWA compatibility'
            });

            const manifestUrl = this.manifestUrl || '/manifest.webmanifest';
            const assetlinksUrl = this.assetlinksUrl || '/.well-known/assetlinks.json';

            try {
                const r = await fetch(manifestUrl, { signal: AbortSignal.timeout(8000) });
                if (r.ok) {
                    const j = await r.json();
                    checks.push({
                        label: 'Manifest endpoint (live)',
                        pass: !!j.name,
                        detail: j.name ? ('name: ' + j.name + ' · start_url: ' + j.start_url) : 'Missing required fields in response'
                    });
                } else {
                    checks.push({ label: 'Manifest endpoint (live)', pass: false, detail: 'HTTP ' + r.status });
                }
            } catch(e) {
                checks.push({ label: 'Manifest endpoint (live)', pass: false, detail: 'Unreachable: ' + e.message });
            }

            try {
                const r = await fetch(assetlinksUrl, { signal: AbortSignal.timeout(8000) });
                if (r.ok) {
                    const j = await r.json();
                    const hasRel = Array.isArray(j) && j.length > 0 && j[0].relation;
                    checks.push({
                        label: 'Asset links endpoint (live)',
                        pass: r.ok,
                        detail: hasRel ? ('Package: ' + j[0].target.package_name + ' · ' + j[0].target.sha256_cert_fingerprints.length + ' fingerprint(s)') : 'Endpoint OK but returning [] — save settings with toggle on'
                    });
                } else {
                    checks.push({ label: 'Asset links endpoint (live)', pass: false, detail: 'HTTP ' + r.status });
                }
            } catch(e) {
                checks.push({ label: 'Asset links endpoint (live)', pass: false, detail: 'Unreachable: ' + e.message });
            }

            this.validationResults = { checks, allPassed: checks.every(c => c.pass) };
            this.validating = false;
        }
    };
};