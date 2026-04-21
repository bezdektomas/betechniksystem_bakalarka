importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

const CACHE_NAME = 'betechnik-is-v3';
const STATIC_CACHE_NAME = 'betechnik-static-v3';

const STATIC_ASSETS = [
    '/offline',
    '/img/logo.png',
    '/img/icons/icon-192x192.png',
    '/img/icons/icon-512x512.png',
];

const OFFLINE_URL = '/offline';

firebase.initializeApp({
    apiKey:            '',
    authDomain:        '',
    projectId:         '',
    storageBucket:     '',
    messagingSenderId: '',
    appId:             '',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const { title, body } = payload.notification ?? {};
    const data = payload.data ?? {};

    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        const chatOpen = clients.some((c) => {
            try {
                return new URL(c.url).pathname === (data.url ?? '/chat') && c.visibilityState === 'visible';
            } catch { return false; }
        });
        if (chatOpen) return;

        return self.registration.showNotification(title ?? 'BeTechnik', {
            body:     body ?? '',
            icon:     '/img/icons/icon-192x192.png',
            badge:    '/img/icons/icon-96x96.png',
            tag:      data.collapseKey ?? 'default',
            renotify: true,
            vibrate:  [100, 50, 100],
            data:     { url: data.url ?? '/chat' },
        });
    });
});

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames
                .filter((name) => name !== CACHE_NAME && name !== STATIC_CACHE_NAME)
                .map((name) => caches.delete(name))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/_')) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((r) => r || caches.match(OFFLINE_URL)))
        );
        return;
    }

    if (['style', 'script', 'font'].includes(request.destination)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(STATIC_CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    if (request.destination === 'image') {
        event.respondWith(
            caches.open(STATIC_CACHE_NAME).then((cache) =>
                cache.match(request).then((cached) => {
                    const fetchPromise = fetch(request).then((response) => {
                        if (response.ok) cache.put(request, response.clone());
                        return response;
                    });
                    return cached || fetchPromise;
                })
            )
        );
        return;
    }

    event.respondWith(fetch(request).catch(() => caches.match(request)));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const urlToOpen = event.notification.data?.url ?? '/chat';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(urlToOpen);
                    return client.focus();
                }
            }
            return clients.openWindow?.(urlToOpen);
        })
    );
});
