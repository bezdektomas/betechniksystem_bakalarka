import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const el = document.querySelector('[data-firebase-config]');
if (!el) throw new Error('Firebase config element not found');

const config   = JSON.parse(el.dataset.firebaseConfig);
const vapidKey = el.dataset.firebaseVapidKey;
const tokenUrl = el.dataset.firebaseTokenUrl;

const app       = initializeApp(config);
const messaging = getMessaging(app);

async function initPushNotifications() {
    if (!('serviceWorker' in navigator) || !('Notification' in window)) return;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return;

    try {
        // Use the existing sw.js — it handles both caching and Firebase messaging
        const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;

        const token = await getToken(messaging, {
            vapidKey,
            serviceWorkerRegistration: reg,
        });

        if (token) {
            await fetch(tokenUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body:    JSON.stringify({ token }),
            });
        }
    } catch (err) {
        console.warn('FCM registration failed:', err);
    }
}

// Foreground: show notification only if user isn't viewing that conversation
onMessage(messaging, (payload) => {
    const data       = payload.data ?? {};
    const targetPath = data.url ?? '/chat';

    if (document.visibilityState === 'visible' && window.location.pathname === targetPath) return;

    if (Notification.permission === 'granted') {
        const { title, body } = payload.notification ?? {};
        const n = new Notification(title ?? 'BeTechnik', {
            body: body ?? '',
            icon: '/img/icons/icon-192x192.png',
            tag:  data.collapseKey ?? 'default',
        });
        n.onclick = () => { window.focus(); window.location.href = targetPath; };
    }
});

setTimeout(initPushNotifications, 2000);
