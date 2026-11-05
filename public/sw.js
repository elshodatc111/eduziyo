/* EDUZIYO service worker: faqat statik fayllar keshlanadi. Pul va davomat sahifalari HECH QACHON keshlanmaydi (oflayn yozish yo'q). */
const VERSION = 'eduziyo-v1';
const STATIC = ['/offline.html', '/icons/icon-192.png', '/icons/icon-512.png', '/favicon.svg'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Vite build va ikonkalar: keshdan, bo'lmasa tarmoqdan (versiyali nomlar tufayli xavfsiz)
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(VERSION).then((c) => c.put(req, copy));
      return res;
    })));
    return;
  }

  // Sahifalar: har doim tarmoqdan; internet yo'q bo'lsa "oflayn" sahifasi
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
  }
});
