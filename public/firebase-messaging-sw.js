const firebaseConfigUrl = new URL(self.location.href);
const firebaseConfig = {
    apiKey: firebaseConfigUrl.searchParams.get('apiKey') || '',
    projectId: firebaseConfigUrl.searchParams.get('projectId') || '',
    messagingSenderId: firebaseConfigUrl.searchParams.get('messagingSenderId') || '',
    appId: firebaseConfigUrl.searchParams.get('appId') || '',
};

const hasFirebaseConfig = Boolean(
    firebaseConfig.apiKey
    && firebaseConfig.projectId
    && firebaseConfig.messagingSenderId
    && firebaseConfig.appId
);

if (hasFirebaseConfig) {
    importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');

    firebase.initializeApp(firebaseConfig);

    const messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => {
        const notification = payload.notification || {};
        const dataPayload = payload.data || {};
        const title = notification.title || dataPayload.title || 'Unsell';
        const options = {
            body: notification.body || dataPayload.body || 'You have a new update.',
            icon: dataPayload.icon || notification.image || '/branding/unsell-icon-512.png',
            badge: dataPayload.badge || '/branding/unsell-icon-512.png',
            sound: dataPayload.sound || undefined,
            tag: dataPayload.tag || undefined,
            data: {
                url: dataPayload.url || '/',
                type: dataPayload.type || 'notification',
            },
            renotify: dataPayload.renotify === '1',
            requireInteraction: dataPayload.requireInteraction === '1',
        };

        self.registration.showNotification(title, options);
    });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const requestedTarget = event.notification?.data?.url || '/';
    const targetUrl = new URL(requestedTarget, self.location.origin).href;

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
