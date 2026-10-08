// sw.js - Service Worker for OrthoKiosk Mobile Application & Push Notifications
const CACHE_NAME = 'orthokiosk-v1';
const ASSETS_TO_CACHE = [
  './',
  'style.css',
  'manifest.json',
  'images/icon-192.png',
  'images/icon-512.png'
];

// Install event: cache core static shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch((err) => {
        console.warn('Cache addAll warning:', err);
      });
    })
  );
  self.skipWaiting();
});

// Activate event: clean up stale caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event: network first, fallback to cache
self.addEventListener('fetch', (event) => {
  // Only cache GET requests
  if (event.request.method !== 'GET') return;
  
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        return response;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});

// Push notification event: triggers device notification on scheduled alarms
self.addEventListener('push', (event) => {
  let data = { title: '⏰ OrthoKiosk Medicine Alarm', body: 'Time to take your scheduled dose!' };
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: 'images/icon-192.png',
    badge: 'images/icon-192.png',
    vibrate: [400, 200, 400, 200, 600],
    data: {
      url: data.url || 'reminders.php'
    },
    actions: [
      { action: 'take', title: '✓ Taken' },
      { action: 'snooze', title: '💤 Snooze 10m' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click event: opens or brings app to focus
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : 'reminders.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(targetUrl) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
