/* ============================================================
 | SiDiPa — Service Worker PWA
 | Aturan keamanan: HALAMAN PHP, API, DAN FORM TIDAK PERNAH DI-CACHE.
 | Alasannya: halaman memuat token CSRF yang terikat sesi dan data
 | berbasis peran (admin/guru/BK/siswa/wali). Menyimpannya akan
 | membocorkan data antar-akun dan membuat CSRF tidak valid.
 | Yang di-cache hanya berkas statis + halaman offline.
 | ============================================================ */
'use strict';

const VERSI_CACHE = 'sidipa-pwa-v2';
const SCOPE = self.registration.scope;              // selalu diakhiri '/'
const URL_SCOPE = new URL(SCOPE);
const AWALAN = URL_SCOPE.pathname;                  // mis. '/' atau '/sidipa/'
const u = (p) => new URL(p, SCOPE).toString();

const ASET_SHELL = [
  u('offline.html'),
  u('manifest.webmanifest'),
  u('public/css/sidipa.css'),
  u('public/css/cetak.css'),
  u('public/css/kartu.css'),
  u('public/js/sidipa.js'),
  u('public/js/pwa.js'),
  u('public/js/qrcode.min.js'),
  u('public/js/qr-sidipa.js'),
  u('public/js/html5-qrcode.min.js'),
  u('public/img/logo-sidipa.svg'),
  u('public/img/tanda-tangan.svg'),
  u('public/img/icon-192.png'),
  u('public/img/icon-512.png'),
  u('public/img/icon-maskable-512.png'),
  u('public/img/apple-touch-icon.png'),
  u('public/img/favicon.png')
];

const PATH_SHELL = new Set(ASET_SHELL.map((x) => new URL(x).pathname));

function asetStatis(pathname) {
  if (PATH_SHELL.has(pathname)) return true;
  const rel = pathname.startsWith(AWALAN) ? pathname.slice(AWALAN.length) : '';
  return /^public\/(css|js|img)\//.test(rel);
}

function pathAmanDisimpan(pathname) {
  const rel = pathname.startsWith(AWALAN) ? pathname.slice(AWALAN.length) : '';
  // Jangan pernah simpan: PHP dinamis, API, cron, app, storage, install, upload.
  if (/^(api|cron|app|storage|pages)\//.test(rel)) return false;
  if (/\.php($|\?)/.test(rel) || rel.endsWith('.php')) return false;
  if (/^(install|verifikasi|index)\.php$/.test(rel)) return false;
  if (/^public\/uploads\//.test(rel)) return false;
  return true;
}

self.addEventListener('install', (acara) => {
  acara.waitUntil((async () => {
    const cache = await caches.open(VERSI_CACHE);
    await Promise.all(ASET_SHELL.map(async (alamat) => {
      try {
        const respon = await fetch(alamat, { cache: 'reload' });
        if (respon && respon.ok) await cache.put(alamat, respon);
      } catch (_) {
        // Satu berkas gagal tidak boleh menggagalkan pemasangan.
      }
    }));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (acara) => {
  acara.waitUntil((async () => {
    const kunci = await caches.keys();
    await Promise.all(kunci
      .filter((k) => k.startsWith('sidipa-pwa-') && k !== VERSI_CACHE)
      .map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

async function sajikanStatis(permintaan) {
  const cache = await caches.open(VERSI_CACHE);
  const tersimpan = await cache.match(permintaan);
  const jaringan = fetch(permintaan)
    .then(async (respon) => {
      if (respon && respon.ok && respon.type === 'basic' && pathAmanDisimpan(new URL(permintaan.url).pathname)) {
        await cache.put(permintaan, respon.clone());
      }
      return respon;
    })
    .catch(() => null);
  return tersimpan || (await jaringan) || new Response('', { status: 504, statusText: 'Berkas tidak tersedia offline' });
}

async function sajikanNavigasi(permintaan) {
  try {
    const respon = await fetch(permintaan);
    // Sengaja TIDAK dimasukkan ke cache (CSRF + data per peran).
    return respon;
  } catch (_) {
    const cache = await caches.open(VERSI_CACHE);
    const cadangan = await cache.match(u('offline.html'));
    return cadangan || new Response(
      '<!doctype html><html lang="id"><meta charset="utf-8"><title>Offline - SiDiPa</title>' +
      '<body style="font-family:system-ui;padding:24px">Sedang offline. Sambungkan internet lalu muat ulang.</body></html>',
      { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

self.addEventListener('fetch', (acara) => {
  const permintaan = acara.request;
  if (permintaan.method !== 'GET') return;                 // POST/CSRF lewat jaringan
  const url = new URL(permintaan.url);
  if (url.origin !== self.location.origin) return;        // CDN & WA API lewat jaringan
  if (url.pathname.endsWith('/sw.js')) return;            // pembaruan SW oleh browser

  if (asetStatis(url.pathname)) {
    acara.respondWith(sajikanStatis(permintaan));
    return;
  }
  if (permintaan.mode === 'navigate') {
    acara.respondWith(sajikanNavigasi(permintaan));
    return;
  }
  // Lainnya (get API, berkas PHP, verifikasi, dll.) sengaja dibiarkan.
});

self.addEventListener('message', (acara) => {
  if (acara.data && acara.data.tipe === 'SIDIPA_LEWATI_TUNGGU') {
    self.skipWaiting();
  }
});
