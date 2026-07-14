const CACHE_NAME = 'unsell-mobile-cache-v2';
const APP_SHELL = [
    '/',
    '/offline',
    '/manifest.webmanifest',
    '/branding/unsell-icon-512.png',
    '/branding/unsell-maskable-512.png',
    '/images/placeholder-listing.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(APP_SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                    return response;
                })
                .catch(() => caches.match(event.request).then((cached) => cached || caches.match('/offline')))
        );
        return;
    }

    if (requestUrl.origin === self.location.origin) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                return (
                    cached ||
                    fetch(event.request).then((response) => {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                        return response;
                    })
                );
            })
        );
    }
});

self.addEventListener('push', (event) => {
    let payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch {
            payload = {
                body: event.data.text(),
            };
        }
    }

    const title = payload.title || 'Unsell';

    const options = {
        body: payload.body || 'You have a new update.',
        icon: payload.icon || '/branding/unsell-icon-512.png',
        badge: payload.badge || '/branding/unsell-icon-512.png',
        sound: payload.sound || payload?.data?.sound || undefined,
        data: payload.data || { url: '/' },
        tag: payload.tag,
        renotify: Boolean(payload.renotify),
        requireInteraction: Boolean(payload.requireInteraction),
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const dataPayload = event.notification?.data;
    const requestedTarget = typeof dataPayload === 'string' ? dataPayload : dataPayload?.url;
    const targetUrl = new URL(requestedTarget || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        })
    );
});
