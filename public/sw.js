const CACHE_VERSION = 'smart-attendance-static-v3';
const STATIC_ASSETS = [
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/charts.js',
  '/assets/js/scanner.js',
  '/assets/js/kiosk.js',
  '/assets/js/vendor/jsqr.js',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/manifest.json',
  '/offline.html',
  '/ilustrasi.gif',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_VERSION)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Hanya tangani origin sendiri
  if (url.origin !== self.location.origin) {
    return;
  }

  // JANGAN PERNAH menyimpan cache untuk POST, API, QR token, kiosk, atau endpoint auth
  if (
    req.method !== 'GET' ||
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/kiosk') ||
    url.pathname === '/login' ||
    url.pathname === '/logout'
  ) {
    event.respondWith(
      fetch(req).catch(() => {
        if (url.pathname.startsWith('/api/')) {
          return new Response(
            JSON.stringify({
              ok: false,
              message: 'Koneksi jaringan terputus. Absensi QR tidak diperbolehkan secara offline.',
            }),
            {
              status: 503,
              headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            }
          );
        }
        return caches.match('/offline.html');
      })
    );
    return;
  }

  // Aset statis: cache-first dengan fallback jaringan
  const isStatic =
    url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/icons/') ||
    url.pathname === '/manifest.json' ||
    url.pathname === '/offline.html';

  if (isStatic) {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) {
          return cached;
        }
        return fetch(req).then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const copy = networkRes.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(req, copy));
          }
          return networkRes;
        });
      })
    );
    return;
  }

  // Halaman HTML dinamis: network-first, jatuh ke halaman offline bila terputus (tidak pernah menyimpan data sesi)
  event.respondWith(
    fetch(req).catch(() => caches.match('/offline.html'))
  );
});
