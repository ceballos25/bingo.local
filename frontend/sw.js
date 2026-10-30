/* Service worker PWA — shell + push */
const CACHE = 'bingo-shell-v221';
const SHELL = [
  './',
  './index.html',
  './css/app.css',
  './js/config.js',
  './js/api.js',
  './js/auth.js',
  './js/guest-auth.js',
  './js/ui.js',
  './js/cart.js',
  './js/preloader.js',
  './js/balota.js',
  './js/tablero75.js',
  './js/draw-overlay.js',
  './js/daubs.js',
  './js/sound.js',
  './js/celebrate.js',
  './js/push.js',
  './js/app.js',
  './js/pages/landing.js',
  './manifest.webmanifest',
  './icons/logo.png',
  './icons/icon-192.png',
  './icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // No cachear API
  if (
    url.pathname.match(
      /^\/(auth|partidas|cartones|ventas|push|ciudades|balotas|reclamos|realtime|cuentas-pago|organizaciones)/
    )
  ) {
    return;
  }

  // JS/CSS de la app: red primero (evita módulos ES viejos sin exports nuevos)
  if (url.pathname.includes('/app/js/') || url.pathname.includes('/app/css/')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res && res.ok) {
            const clone = res.clone();
            caches.open(CACHE).then((cache) => cache.put(req, clone));
          }
          return res;
        })
        .catch(() => caches.match(req))
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      const fetched = fetch(req)
        .then((res) => {
          if (res && res.ok) {
            const clone = res.clone();
            caches.open(CACHE).then((cache) => cache.put(req, clone));
          }
          return res;
        })
        .catch(() => cached || caches.match('./index.html'));
      return cached || fetched;
    })
  );
});

self.addEventListener('push', (event) => {
  let data = { title: 'Bingo Virtual', body: 'Hay novedades', data: {} };
  try {
    if (event.data) {
      data = { ...data, ...event.data.json() };
    }
  } catch (e) {
    data.body = event.data ? event.data.text() : data.body;
  }
  event.waitUntil(
    self.registration.showNotification(data.title || 'Bingo Virtual', {
      body: data.body || '',
      icon: './icons/icon-192.png',
      badge: './icons/icon-192.png',
      data: data.data || {},
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const partidaId = event.notification.data && event.notification.data.partidaId;
  const target = partidaId
    ? './index.html#/vivo/' + partidaId
    : './index.html#/mis-cartones';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if ('focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(target);
      }
    })
  );
});
