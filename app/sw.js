// VidaKushala Service Worker
// network-first para CSS/JS, cache-first para imágenes y assets propios.
try {
  importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");
} catch(e) {
  console.warn('[VK SW] OneSignal no disponible — push desactivado en este dispositivo');
}

// VK_BUILD inyectado desde sw-loader.php?v=XXXX — versión idéntica a la del HTML
var VK_CACHE = 'vk-' + (self.VK_BUILD || 'v20');

var VK_PRECACHE = [
  '/style.css', '/app.js', '/vk-custom.css',
  '/icons/icon-72.png',  '/icons/icon-96.png',  '/icons/icon-128.png',
  '/icons/icon-144.png', '/icons/icon-152.png', '/icons/icon-192.png',
  '/icons/icon-384.png', '/icons/icon-512.png', '/icons/logomovil.png',
  '/logo.png'
];

var BYPASS = ['/vk/v1/', '/wp-json/', 'onesignal', 'accounts.google.com', 'fonts.google'];

// Activar inmediatamente al instalar: la página detecta SW_UPDATED y auto-recarga.
// No se requiere acción del usuario para obtener la última versión.
self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open(VK_CACHE).then(function(cache) {
      return Promise.allSettled(
        VK_PRECACHE.map(function(u) { return cache.add(u).catch(function(){}); })
      );
    })
    .then(function() { return self.skipWaiting(); })
  );
});

// Bug 1 fix: claim() primero, luego matchAll + postMessage para que los clientes
// ya estén controlados cuando se les envía el mensaje SW_UPDATED.
self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys.filter(function(k) { return k !== VK_CACHE; })
            .map(function(k) { return caches.delete(k); })
      );
    })
    .then(function() { return self.clients.claim(); })
    .then(function() {
      return self.clients.matchAll({ type: 'window' }).then(function(clients) {
        clients.forEach(function(c) {
          c.postMessage({ type: 'SW_UPDATED', version: VK_CACHE });
        });
      });
    })
  );
});

// El usuario confirma la actualización desde el banner o el botón de perfil
self.addEventListener('message', function(e) {
  if (e.data && e.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', function(e) {
  var req = e.request;
  var url = req.url;

  if (req.method !== 'GET') return;

  for (var i = 0; i < BYPASS.length; i++) {
    if (url.indexOf(BYPASS[i]) !== -1) return;
  }
  if (url.match(/\/(sw\.js|sw-loader\.php|manifest\.json)(\?|$)/)) return;
  if (!url.startsWith('https://app.vidakushala.com')) return;

  // Network-first: CSS, JS y PHP dinámico
  if (url.match(/\.(css|js|php)(\?|$)/)) {
    e.respondWith(
      fetch(req).then(function(r) {
        if (r && r.status === 200) {
          var toCache = r.clone();
          caches.open(VK_CACHE).then(function(c) { c.put(req, toCache); });
        }
        return r;
      }).catch(function() {
        return caches.match(req).then(function(hit) {
          if (hit) return hit;
          return caches.match(url.split('?')[0]);
        });
      })
    );
    return;
  }

  // Cache-first: imágenes, fuentes e iconos
  if (url.match(/\.(png|jpg|jpeg|webp|ico|svg|woff2?|ttf|eot)(\?|$)/i)) {
    e.respondWith(
      caches.match(req).then(function(cached) {
        if (cached) return cached;
        return fetch(req).then(function(r) {
          if (r && r.status === 200) {
            var toCache = r.clone();
            caches.open(VK_CACHE).then(function(c) { c.put(req, toCache); });
          }
          return r;
        }).catch(function() {
          return new Response('', { status: 408, statusText: 'Offline' });
        });
      })
    );
  }
});
