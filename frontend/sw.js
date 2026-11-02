/* Service worker PWA — shell + push */
const CACHE = 'bingo-shell-v237';
const ASSET_V = '237';
const SHELL = [
  './',
  './index.html',
  `./css/app.css?v=${ASSET_V}`,
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
  `./js/app.js?v=${ASSET_V}`,
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

function networkFirst(req, fallbackUrl) {
  return fetch(req)
    .then((res) => {
      if (res && res.ok) {
        const clone = res.clone();
        caches.open(CACHE).then((cache) => cache.put(req, clone));
      }
      return res;
    })
    .catch(() =>
      caches.match(req).then((cached) => {
        if (cached) return cached;
        return fallbackUrl ? caches.match(fallbackUrl) : undefined;
      })
    );
}

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

  // HTML / navegación: SIEMPRE red primero (si no, F5 no refresca la app)
  const esHtml =
    req.mode === 'navigate' ||
    url.pathname.endsWith('.html') ||
    url.pathname.endsWith('/app') ||
    url.pathname.endsWith('/app/') ||
    url.pathname === '/app' ||
    /\/app\/?$/.test(url.pathname);

  if (esHtml) {
    event.respondWith(networkFirst(req, './index.html'));
    return;
  }

  // JS/CSS de la app: red primero
  if (url.pathname.includes('/app/js/') || url.pathname.includes('/app/css/')) {
    event.respondWith(networkFirst(req));
    return;
  }

  // Resto (iconos, manifest): caché con actualización en segundo plano
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
        .catch(() => cached);
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
