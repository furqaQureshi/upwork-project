import './bootstrap';
import { navigationData } from './navigation-data';

import Alpine from 'alpinejs';
import { getApps, initializeApp as initializeFirebaseApp } from 'firebase/app';
import {
	deleteToken as deleteFcmToken,
	getMessaging,
	getToken as getFcmToken,
	isSupported as isFcmMessagingSupported,
	onMessage as onFcmMessage,
} from 'firebase/messaging';

window.Alpine = Alpine;
window.navigationData = navigationData;

Alpine.start();

let serviceWorkerRegistrationPromise = null;
let fcmServiceWorkerRegistrationPromise = null;
let firebaseMessagingPromise = null;
let fcmForegroundHandlerBound = false;

const registerServiceWorker = async () => {
	if (!('serviceWorker' in navigator)) {
		return null;
	}

	try {
		if (!serviceWorkerRegistrationPromise) {
			serviceWorkerRegistrationPromise = navigator.serviceWorker.register('/sw.js').catch(() => null);
		}

		const registration = await serviceWorkerRegistrationPromise;

		if (registration) {
			return registration;
		}

		return await navigator.serviceWorker.ready;
	} catch {
		return null;
	}
};

const getFcmConfig = () => ({
	apiKey: metaContent('fcm-api-key'),
	projectId: metaContent('fcm-project-id'),
	messagingSenderId: metaContent('fcm-messaging-sender-id'),
	appId: metaContent('fcm-app-id'),
	vapidKey: metaContent('fcm-vapid-key'),
});

const hasUsableFcmConfig = (config) => Boolean(
	config.apiKey && config.projectId && config.messagingSenderId && config.appId && config.vapidKey
);

const buildFcmServiceWorkerUrl = (config) => {
	const url = new URL('/firebase-messaging-sw.js', window.location.origin);
	url.searchParams.set('apiKey', config.apiKey);
	url.searchParams.set('projectId', config.projectId);
	url.searchParams.set('messagingSenderId', config.messagingSenderId);
	url.searchParams.set('appId', config.appId);

	return url.toString();
};

const registerFcmServiceWorker = async (config) => {
	if (!('serviceWorker' in navigator) || !hasUsableFcmConfig(config)) {
		return null;
	}

	try {
		if (!fcmServiceWorkerRegistrationPromise) {
			fcmServiceWorkerRegistrationPromise = navigator.serviceWorker.register(buildFcmServiceWorkerUrl(config), {
				scope: '/firebase-cloud-messaging-push-scope',
			}).catch(() => null);
		}

		return await fcmServiceWorkerRegistrationPromise;
	} catch {
		return null;
	}
};

const bootstrapFirebaseMessaging = async () => {
	if (firebaseMessagingPromise) {
		return firebaseMessagingPromise;
	}

	firebaseMessagingPromise = (async () => {
		const config = getFcmConfig();

		if (!hasUsableFcmConfig(config)) {
			return null;
		}

		try {
			const supported = await isFcmMessagingSupported();

			if (!supported) {
				return null;
			}

			const firebaseApp = getApps().find((app) => app.name === 'unsell-web-fcm')
				?? initializeFirebaseApp({
					apiKey: config.apiKey,
					projectId: config.projectId,
					messagingSenderId: config.messagingSenderId,
					appId: config.appId,
				}, 'unsell-web-fcm');

			return {
				config,
				messaging: getMessaging(firebaseApp),
			};
		} catch {
			return null;
		}
	})();

	return firebaseMessagingPromise;
};

const readStoredFcmToken = () => {
	try {
		return localStorage.getItem('unsell_fcm_token') ?? '';
	} catch {
		return '';
	}
};

const writeStoredFcmToken = (token) => {
	try {
		if (token) {
			localStorage.setItem('unsell_fcm_token', token);
			return;
		}

		localStorage.removeItem('unsell_fcm_token');
	} catch {
		// Ignore storage errors.
	}
};

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		registerServiceWorker();
	});
}

let deferredInstallPrompt = null;

const metaContent = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';

const bindInstallButtons = () => {
	document.querySelectorAll('[data-pwa-install]').forEach((button) => {
		if (button.dataset.pwaBound === 'true') {
			return;
		}

		button.dataset.pwaBound = 'true';

		button.addEventListener('click', async (e) => {
			e.preventDefault();
			
			if (!deferredInstallPrompt) {
				console.warn('PWA install prompt not available');
				return;
			}

			try {
				deferredInstallPrompt.prompt();
				const { outcome } = await deferredInstallPrompt.userChoice;
				console.log(`User response to the install prompt: ${outcome}`);
				deferredInstallPrompt = null;
				bindInstallButtons();
			} catch (error) {
				console.error('Error showing install prompt:', error);
				deferredInstallPrompt = null;
				bindInstallButtons();
			}
		});
	});

	// Update button visibility based on whether prompt is available
	document.querySelectorAll('[data-pwa-install]').forEach((button) => {
		const shouldShow = deferredInstallPrompt !== null;
		if (shouldShow) {
			button.classList.remove('hidden');
		} else {
			button.classList.add('hidden');
		}
	});
};

window.addEventListener('beforeinstallprompt', (event) => {
	event.preventDefault();
	deferredInstallPrompt = event;
	
	// Ensure buttons are updated when prompt event fires
	bindInstallButtons();
	
	console.log('PWA install prompt available');
});

const initializeGoogleAdsRuntime = () => {
	const runtimeEnabled = metaContent('ads-runtime-enabled') === '1';

	console.log('[GoogleAds] Runtime enabled:', runtimeEnabled);

	if (!runtimeEnabled) {
		console.log('[GoogleAds] Ads runtime disabled - skipping initialization');
		return;
	}

	const adClientId = metaContent('adsense-client-id');
	console.log('[GoogleAds] Client ID:', adClientId || '(empty)');

	const toPositiveInt = (raw, fallback) => {
		const parsed = Number.parseInt(raw, 10);
		return Number.isFinite(parsed) ? Math.max(1, parsed) : fallback;
	};

	const adConfig = {
		interstitial: {
			enabled: metaContent('ads-interstitial-enabled') === '1',
			slot: metaContent('ads-interstitial-slot'),
			clicks: toPositiveInt(metaContent('ads-interstitial-clicks') || '6', 6),
		},
		reward: {
			enabled: metaContent('ads-reward-enabled') === '1',
			slot: metaContent('ads-reward-slot'),
			clicks: toPositiveInt(metaContent('ads-reward-clicks') || '10', 10),
		},
		rewardSecondary: {
			enabled: metaContent('ads-reward-enabled') === '1',
			slot: metaContent('ads-reward-slot-secondary'),
			clicks: toPositiveInt(metaContent('ads-reward-clicks') || '10', 10),
		},
		appOpen: {
			enabled: true,
			slot: metaContent('ads-app-open-slot'),
			clicks: 1,
		},
	};

	console.log('[GoogleAds] Config:', {
		interstitial: adConfig.interstitial.slot ? 'configured' : 'empty',
		reward: adConfig.reward.slot ? 'configured' : 'empty',
		rewardSecondary: adConfig.rewardSecondary.slot ? 'configured' : 'empty',
		appOpen: adConfig.appOpen.slot ? 'configured' : 'empty',
	});

	const hasAnyUsableConfig = Object.values(adConfig).some((cfg) => cfg.slot);

	if (!hasAnyUsableConfig) {
		console.log('[GoogleAds] No ad slots configured - skipping ads runtime');
		return;
	}

	console.log('[GoogleAds] Initializing ads runtime with', Object.values(adConfig).filter(c => c.slot).length, 'configured slots');

	const counterStorageKey = 'unsell_ads_click_counter';
	const initialCount = Number.parseInt(localStorage.getItem(counterStorageKey) || '0', 10);
	let clickCount = Number.isFinite(initialCount) ? Math.max(0, initialCount) : 0;

	const persistClickCount = () => {
		localStorage.setItem(counterStorageKey, `${clickCount}`);
	};

	const showAdModal = ({ slotId }) => {
		const existing = document.getElementById('unsell-ad-modal');

		if (existing) {
			existing.remove();
		}

		const wrapper = document.createElement('div');
		wrapper.id = 'unsell-ad-modal';
		wrapper.className = 'fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/70 p-4';

		wrapper.innerHTML = `
			<div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl">
				<div class="mb-3 flex items-center justify-end">
					<button type="button" data-ad-close class="rounded-xl border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">Close</button>
				</div>
				<div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
					<ins
						class="adsbygoogle"
						style="display:block"
						data-ad-slot="${slotId}"
						data-ad-format="auto"
						data-full-width-responsive="true"
					></ins>
				</div>
			</div>
		`;

		document.body.appendChild(wrapper);

		wrapper.querySelector('[data-ad-close]')?.addEventListener('click', () => {
			wrapper.remove();
		});

		wrapper.addEventListener('click', (event) => {
			if (event.target === wrapper) {
				wrapper.remove();
			}
		});

		const adNode = wrapper.querySelector('.adsbygoogle');

		if (adNode) {
			adNode.setAttribute('data-ad-client', adClientId || adNode.getAttribute('data-ad-client') || '');
			try {
				(window.adsbygoogle = window.adsbygoogle || []).push({});
			} catch {
				// Keep modal visible even if ad provider fails.
			}
		}
	};

	const tryShowAd = (type, { force = false, slotOverride = '' } = {}) => {
		const config = adConfig[type];
		const slotId = slotOverride || config?.slot || '';

		if (!config || !slotId) {
			return false;
		}

		if (!force && !config.enabled) {
			return false;
		}

		showAdModal({ slotId });

		return true;
	};

	const maybeTriggerAds = () => {
		if (
			adConfig.interstitial.enabled &&
			adConfig.interstitial.slot &&
			clickCount % adConfig.interstitial.clicks === 0
		) {
			tryShowAd('interstitial');
			return;
		}

		const rewardSlots = [];
		if (adConfig.reward.enabled && adConfig.reward.slot) {
			rewardSlots.push(adConfig.reward.slot);
		}
		if (adConfig.rewardSecondary.enabled && adConfig.rewardSecondary.slot) {
			rewardSlots.push(adConfig.rewardSecondary.slot);
		}

		if (rewardSlots.length > 0 && clickCount % adConfig.reward.clicks === 0) {
			const triggerIndex = Math.max(0, Math.floor(clickCount / adConfig.reward.clicks) - 1);
			const rewardPosition = triggerIndex % rewardSlots.length;
			const rewardSlot = rewardSlots[rewardPosition];

			tryShowAd('reward', {
				slotOverride: rewardSlot,
			});
		}
	};

	const maybeShowAppOpenAd = () => {
		if (!adConfig.appOpen.slot) {
			return;
		}

		const appOpenSeenKey = 'unsell_ads_app_open_seen';
		if (sessionStorage.getItem(appOpenSeenKey) === '1') {
			return;
		}

		sessionStorage.setItem(appOpenSeenKey, '1');

		window.setTimeout(() => {
			tryShowAd('appOpen', {
				force: true,
			});
		}, 800);
	};

	const bindQaControls = () => {
		const feedbackNode = document.querySelector('[data-ad-test-feedback]');

		const setFeedback = (message) => {
			if (feedbackNode) {
				feedbackNode.textContent = message;
			}
		};

		document.querySelectorAll('[data-ad-test-trigger]').forEach((button) => {
			if (button.dataset.qaBound === 'true') {
				return;
			}

			button.dataset.qaBound = 'true';
			button.addEventListener('click', () => {
				const type = button.getAttribute('data-ad-test-trigger');
				const shown = type ? tryShowAd(type, { force: true }) : false;
				setFeedback(shown ? `${type} ad preview opened.` : `Cannot preview ${type} ad. Configure slot first.`);
			});
		});

		document.querySelectorAll('[data-ad-test-prime]').forEach((button) => {
			if (button.dataset.qaBound === 'true') {
				return;
			}

			button.dataset.qaBound = 'true';
			button.addEventListener('click', () => {
				const type = button.getAttribute('data-ad-test-prime');
				const config = type ? adConfig[type] : null;

				if (!config || !config.slot) {
					setFeedback(`Cannot prime ${type} ad. Configure slot first.`);
					return;
				}

				clickCount = Math.max(0, config.clicks - 1);
				persistClickCount();
				setFeedback(`${type} ad primed. Next tracked click will trigger it.`);
			});
		});

		document.querySelectorAll('[data-ad-test-reset]').forEach((button) => {
			if (button.dataset.qaBound === 'true') {
				return;
			}

			button.dataset.qaBound = 'true';
			button.addEventListener('click', () => {
				clickCount = 0;
				persistClickCount();
				setFeedback('Ad click counter reset to 0.');
			});
		});
	};

	bindQaControls();
	maybeShowAppOpenAd();

	document.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;

		if (!target) {
			return;
		}

		if (
			target.closest('#unsell-ad-modal') ||
			target.closest('[data-ad-test-control]') ||
			target.closest('a') === null && target.closest('button') === null
		) {
			return;
		}

		clickCount += 1;
		persistClickCount();
		maybeTriggerAds();
	});
};

const syncFcmSubscription = async ({ csrfToken, subscribeUrl }) => {
	if (!csrfToken || !subscribeUrl || !('Notification' in window) || Notification.permission !== 'granted') {
		return false;
	}

	const runtime = await bootstrapFirebaseMessaging();

	if (!runtime) {
		return false;
	}

	const registration = await registerFcmServiceWorker(runtime.config);

	if (!registration) {
		return false;
	}

	try {
		const token = await getFcmToken(runtime.messaging, {
			vapidKey: runtime.config.vapidKey,
			serviceWorkerRegistration: registration,
		});

		if (!token) {
			return false;
		}

		const response = await fetch(subscribeUrl, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': csrfToken,
				'Content-Type': 'application/json',
				Accept: 'application/json',
			},
			body: JSON.stringify({
				provider: 'fcm',
				token,
				permission: Notification.permission,
			}),
		});

		if (!response.ok) {
			return false;
		}

		writeStoredFcmToken(token);

		return true;
	} catch {
		return false;
	}
};

const deactivateFcmSubscription = async ({ csrfToken, unsubscribeUrl }) => {
	const storedToken = readStoredFcmToken();
	const runtime = await bootstrapFirebaseMessaging();

	if (runtime) {
		try {
			await deleteFcmToken(runtime.messaging);
		} catch {
			// Ignore local token cleanup failures.
		}
	}

	if (!csrfToken || !unsubscribeUrl || !storedToken) {
		writeStoredFcmToken('');
		return;
	}

	await fetch(unsubscribeUrl, {
		method: 'DELETE',
		headers: {
			'X-CSRF-TOKEN': csrfToken,
			'Content-Type': 'application/json',
			Accept: 'application/json',
		},
		body: JSON.stringify({
			token: storedToken,
		}),
	}).catch(() => undefined);

	writeStoredFcmToken('');
};

const initializeNotificationCenter = () => {
	const notificationsUrl = metaContent('notifications-index-url');
	const readUrlTemplate = metaContent('notifications-read-url-template');
	const readAllUrl = metaContent('notifications-read-all-url');
	const pushSubscribeUrl = metaContent('push-subscribe-url');
	const pushUnsubscribeUrl = metaContent('push-unsubscribe-url');
	const csrfToken = metaContent('csrf-token');
	const fcmConfig = getFcmConfig();
	const notificationSoundUrl = metaContent('notification-sound-url');

	if (!notificationsUrl || !csrfToken) {
		return;
	}

	const listNode = document.querySelector('[data-notification-list]');
	const emptyNode = document.querySelector('[data-notification-empty]');
	const enableNode = document.querySelector('[data-notification-enable]');
	const markAllNode = document.querySelector('[data-notification-mark-all]');
	const triggerNode = document.querySelector('[data-notification-trigger]');
	const badgeNodes = document.querySelectorAll(
		'[data-notification-badge], [data-notification-badge-mobile], [data-notification-badge-inline]'
	);

	if (!listNode || !emptyNode || badgeNodes.length === 0) {
		return;
	}

	const pollSeconds = Number.parseInt(metaContent('notifications-poll-seconds') || '20', 10);
	const pollIntervalMs = Number.isFinite(pollSeconds) ? Math.max(5000, pollSeconds * 1000) : 20000;
	const hasPushSetup = Boolean(pushSubscribeUrl && hasUsableFcmConfig(fcmConfig));

	let initialized = false;
	let knownNotificationIds = new Set();
	let pushReady = false;
	let audioContext = null;
	let notificationAudio = null;
	let canPlaySound = false;
	let soundEnabled = localStorage.getItem('unsell_sound_enabled') !== 'false';

	window.unisellSetSoundEnabled = (value) => {
		soundEnabled = Boolean(value);
	};

	const armSound = () => {
		canPlaySound = true;
		const AudioContextClass = window.AudioContext || window.webkitAudioContext;

		if (!AudioContextClass) {
			return;
		}

		if (!audioContext) {
			audioContext = new AudioContextClass();
		}

		if (audioContext.state === 'suspended') {
			audioContext.resume().catch(() => undefined);
		}
	};

	window.addEventListener('pointerdown', armSound, { once: true });
	window.addEventListener('keydown', armSound, { once: true });

	const updateBadges = (unreadCount) => {
		const label = unreadCount > 99 ? '99+' : `${unreadCount}`;

		badgeNodes.forEach((badgeNode) => {
			badgeNode.textContent = label;
			badgeNode.classList.toggle('hidden', unreadCount <= 0);
		});
	};

	const enableAlertButtonIfNeeded = () => {
		if (!enableNode) {
			return;
		}

		if (!('Notification' in window) || !hasPushSetup) {
			enableNode.classList.add('hidden');
			return;
		}

		enableNode.disabled = Notification.permission === 'denied';
		enableNode.textContent =
			Notification.permission === 'denied' ? 'Browser alerts blocked in settings' : 'Enable browser alerts';
		enableNode.classList.toggle('hidden', Notification.permission === 'granted');
	};

	const pulseTrigger = () => {
		if (!triggerNode) {
			return;
		}

		triggerNode.classList.remove('notification-ring-pop');
		void triggerNode.offsetWidth;
		triggerNode.classList.add('notification-ring-pop');
	};

	const playToneCue = async () => {
		if (!canPlaySound || !soundEnabled) {
			return;
		}

		const AudioContextClass = window.AudioContext || window.webkitAudioContext;

		if (!AudioContextClass) {
			return;
		}

		if (!audioContext) {
			audioContext = new AudioContextClass();
		}

		if (audioContext.state === 'suspended') {
			await audioContext.resume().catch(() => undefined);
		}

		const now = audioContext.currentTime;
		const oscillator = audioContext.createOscillator();
		const gainNode = audioContext.createGain();

		oscillator.type = 'triangle';
		oscillator.frequency.setValueAtTime(880, now);
		oscillator.frequency.exponentialRampToValueAtTime(660, now + 0.14);

		gainNode.gain.setValueAtTime(0.0001, now);
		gainNode.gain.exponentialRampToValueAtTime(0.08, now + 0.02);
		gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);

		oscillator.connect(gainNode);
		gainNode.connect(audioContext.destination);

		oscillator.start(now);
		oscillator.stop(now + 0.23);
	};

	const playNotificationCue = async (preferredSoundUrl = '') => {
		if (!canPlaySound || !soundEnabled) {
			return;
		}

		const cleanedSoundUrl = typeof preferredSoundUrl === 'string' ? preferredSoundUrl.trim() : '';

		if (cleanedSoundUrl !== '') {
			try {
				if (!notificationAudio || notificationAudio.src !== cleanedSoundUrl) {
					notificationAudio = new Audio(cleanedSoundUrl);
					notificationAudio.preload = 'auto';
				}

				notificationAudio.currentTime = 0;
				await notificationAudio.play();
				return;
			} catch {
				// Fall back to generated tone.
			}
		}

		await playToneCue();
	};

	const markRead = async (notificationId) => {
		if (!readUrlTemplate) {
			return;
		}

		const endpoint = readUrlTemplate.replace('__id__', encodeURIComponent(notificationId));

		await fetch(endpoint, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': csrfToken,
				Accept: 'application/json',
			},
		});
	};

	const showBrowserNotification = async (item) => {
		if (!('Notification' in window) || Notification.permission !== 'granted') {
			return;
		}

		const title = item.title || 'Notification';
		const soundUrl = typeof item.sound === 'string' && item.sound.trim() !== ''
			? item.sound.trim()
			: notificationSoundUrl;
		const options = {
			body: item.body || '',
			icon: item.icon || '/branding/unsell-icon-512.png',
			data: {
				url: item.url || '/',
			},
		};

		if (soundUrl) {
			options.sound = soundUrl;
		}

		if ('serviceWorker' in navigator) {
			try {
				const registration = await navigator.serviceWorker.ready;
				await registration.showNotification(title, options);
				return;
			} catch {
				// Fall through to window Notification API.
			}
		}

		const notification = new Notification(title, options);
		notification.onclick = () => {
			window.focus();
			if (item.url) {
				window.location.href = item.url;
			}
		};
	};

	const bindForegroundFcmMessages = async () => {
		if (fcmForegroundHandlerBound) {
			return;
		}

		const runtime = await bootstrapFirebaseMessaging();

		if (!runtime) {
			return;
		}

		fcmForegroundHandlerBound = true;

		onFcmMessage(runtime.messaging, async (payload) => {
			await pollNotifications();
			pulseTrigger();

			if (document.visibilityState === 'visible') {
				await playNotificationCue(payload?.data?.sound || notificationSoundUrl);
				return;
			}

			await showBrowserNotification({
				title: payload.notification?.title || payload.data?.title || 'Notification',
				body: payload.notification?.body || payload.data?.body || '',
				icon: payload.data?.icon || payload.notification?.image || '/branding/unsell-icon-512.png',
				url: payload.data?.url || '/',
			});
		});
	};

	const createNotificationRow = (item) => {
		const row = document.createElement('button');
		row.type = 'button';
		row.className = `notification-drawer-item w-full rounded-2xl border p-3 text-left transition ${
			item.is_read ? 'border-slate-100 bg-slate-50' : 'border-orange-200 bg-orange-50'
		}`;

		const title = document.createElement('p');
		title.className = 'text-sm font-semibold text-slate-900';
		title.textContent = item.title || 'Notification';

		const body = document.createElement('p');
		body.className = 'mt-1 text-xs text-slate-600';
		body.textContent = item.body || '';

		const time = document.createElement('p');
		time.className = 'mt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500';
		time.textContent = item.created_human || '';

		row.appendChild(title);
		row.appendChild(body);
		row.appendChild(time);

		row.addEventListener('click', async () => {
			try {
				await markRead(item.id);
			} catch {
				// Navigation can proceed even when mark-read fails.
			}

			if (item.url) {
				window.location.href = item.url;
			}
		});

		return row;
	};

	const renderNotifications = (items) => {
		listNode.innerHTML = '';

		if (items.length === 0) {
			emptyNode.classList.remove('hidden');
			return;
		}

		emptyNode.classList.add('hidden');

		items.forEach((item) => {
			listNode.appendChild(createNotificationRow(item));
		});
	};

	const pollNotifications = async () => {
		let payload;

		try {
			const response = await fetch(notificationsUrl, {
				headers: {
					Accept: 'application/json',
				},
			});

			if (!response.ok) {
				return;
			}

			payload = await response.json();
		} catch {
			return;
		}

		const items = Array.isArray(payload.items) ? payload.items : [];
		const unreadCountValue = Number.parseInt(`${payload.unread_count ?? 0}`, 10);
		const unreadCount = Number.isNaN(unreadCountValue) ? 0 : unreadCountValue;

		renderNotifications(items);
		updateBadges(unreadCount);

		const previousIds = new Set(knownNotificationIds);
		knownNotificationIds = new Set(items.map((item) => item.id));

		if (initialized) {
			const newlyArrived = items.filter((item) => !item.is_read && !previousIds.has(item.id));

			if (newlyArrived.length > 0) {
				pulseTrigger();
				if (document.visibilityState === 'visible') {
					const latestSound = newlyArrived.find((item) => typeof item.sound === 'string' && item.sound.trim() !== '')?.sound || notificationSoundUrl;
					await playNotificationCue(latestSound);
				}
			}

			if (document.visibilityState === 'hidden' && !pushReady) {
				for (const item of newlyArrived) {
					await showBrowserNotification(item);
				}
			}
		}

		initialized = true;
	};

	const syncPushIfAllowed = async () => {
		if (!hasPushSetup || !('Notification' in window)) {
			return;
		}

		if (Notification.permission === 'granted') {
			pushReady = await syncFcmSubscription({
				csrfToken,
				subscribeUrl: pushSubscribeUrl,
			});

			if (pushReady) {
				await bindForegroundFcmMessages();
			}
			return;
		}

		if (Notification.permission === 'denied') {
			await deactivateFcmSubscription({
				csrfToken,
				unsubscribeUrl: pushUnsubscribeUrl,
			});
		}
	};

	window.unisellRequestNotificationPermission = async () => {
		if (!('Notification' in window)) {
			return {
				supported: false,
				permission: 'unsupported',
				granted: false,
			};
		}

		let permission = Notification.permission;

		if (permission !== 'granted') {
			try {
				permission = await Notification.requestPermission();
			} catch {
				permission = Notification.permission;
			}
		}

		await syncPushIfAllowed();
		enableAlertButtonIfNeeded();

		const detail = {
			supported: true,
			permission,
			granted: permission === 'granted',
		};

		window.dispatchEvent(new CustomEvent('unsell-notification-permission-updated', { detail }));

		return detail;
	};

	if (markAllNode && readAllUrl) {
		markAllNode.addEventListener('click', async () => {
			try {
				await fetch(readAllUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': csrfToken,
						Accept: 'application/json',
					},
				});
			} catch {
				// Ignore transport errors and attempt refresh.
			}

			await pollNotifications();
		});
	}

	if (enableNode && 'Notification' in window) {
		enableNode.addEventListener('click', async () => {
			await window.unisellRequestNotificationPermission();
		});
	}

	syncPushIfAllowed();
	enableAlertButtonIfNeeded();
	pollNotifications();
	setInterval(pollNotifications, pollIntervalMs);
};

window.addEventListener('DOMContentLoaded', () => {
	bindInstallButtons();
	initializeNotificationCenter();
	initializeGoogleAdsRuntime();
});
