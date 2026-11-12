// TagNPark service worker: offline shell only. Never caches API or private data.
const CACHE='tagnpark-v3';
const FILES=['./','./index.html','./manifest.json','./icon-192.png','./icon-512.png','./jsqr.min.js'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.addAll(FILES)).catch(()=>{}));self.skipWaiting()});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==CACHE).map(x=>caches.delete(x)))));self.clients.claim()});
self.addEventListener('fetch',e=>{
  const u=new URL(e.request.url);
  if(e.request.method!=='GET'||u.origin!==location.origin||/\/api\/|install\.php|\/uploads\//.test(u.pathname))return;
  e.respondWith(fetch(e.request).then(r=>{if(r.ok){const c=r.clone();caches.open(CACHE).then(x=>x.put(e.request,c)).catch(()=>{})}return r}).catch(()=>caches.match(e.request).then(r=>r||caches.match('./index.html'))));
});
