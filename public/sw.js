'use strict';

/* ===================================================
   Service Worker — The Place 818
   Estrategia: Cache-first para estáticos,
               Network-first para páginas HTML,
               Nunca cachear /api/
=================================================== */

const CACHE_NAME = 'theplace818-v1';

// Assets estáticos que siempre queremos disponibles offline
const STATIC_ASSETS = [
  '/',
  '/public/assets/css/app.css',
  '/public/assets/css/cajero.css',
  '/public/assets/css/reportes.css',
  '/public/assets/css/creditos.css',
  '/public/assets/css/usuarios.css',
  '/public/assets/js/app.js',
  '/public/assets/js/cajero.js',
  '/public/assets/js/reportes.js',
  '/public/assets/js/creditos.js',
  '/public/assets/js/usuarios.js',
  '/public/manifest.json',
];

// ── Install: pre-cachear estáticos ──────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// ── Activate: limpiar caches viejos ─────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// ── Fetch: estrategia según tipo de request ──────────
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Solo manejar requests del mismo origen
  if (url.origin !== location.origin) return;

  // Nunca interceptar API: siempre necesitan red
  if (url.pathname.startsWith('/api/')) return;

  // Assets estáticos (CSS, JS, imágenes, fonts): cache-first
  if (
    url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|webp|woff2?|ico)$/)
  ) {
    event.respondWith(
      caches.match(request).then(cached =>
        cached || fetch(request).then(response => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(c => c.put(request, clone));
          }
          return response;
        })
      )
    );
    return;
  }

  // Páginas HTML y rutas: network-first con fallback a cache
  event.respondWith(
    fetch(request)
      .then(response => {
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(c => c.put(request, clone));
        }
        return response;
      })
      .catch(() => caches.match(request).then(cached => cached || caches.match('/')))
  );
});
