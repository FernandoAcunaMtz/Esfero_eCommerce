// Esfero Marketplace — Service Worker
// Estrategia: cache-first para assets estáticos, network-only para PHP/API
const CACHE = 'esfero-v1';
const STATIC = [
  '/assets/css/styles.css',
  '/assets/icons/icon-192.png',
  '/assets/icons/apple-touch-icon.png',
  '/manifest.json',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(STATIC).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  if (url.origin !== self.location.origin) return;

  // Cache-first solo para assets estáticos (CSS, imágenes, fuentes, iconos)
  if (/\.(css|png|jpg|jpeg|gif|svg|webp|woff2?|ico)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(e.request).then(cached => {
        if (cached) return cached;
        return fetch(e.request).then(res => {
          if (res.ok) {
            const clone = res.clone();
            caches.open(CACHE).then(c => c.put(e.request, clone));
          }
          return res;
        });
      })
    );
  }
  // PHP y API: network-only (sin interceptar)
});
