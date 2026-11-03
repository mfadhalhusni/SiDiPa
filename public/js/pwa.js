/* ============================================================
 | SiDiPa — pendaftaran PWA, tombol pasang, status jaringan
 | Dimuat dari semua halaman melalui app/layout.php.
 | Tidak bergantung pada basis URL absolut: memakai meta sidipa-base.
 | ============================================================ */
(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) return;
  if (document.body && document.body.getAttribute('data-abaikan-pwa') === '1') return;

  var meta = document.querySelector('meta[name="sidipa-base"]');
  var basis = meta ? (meta.getAttribute('content') || '') : '';
  if (basis === '/') basis = '';
  var lingkup = (basis || '') + '/';
  var alamatSW = lingkup + 'sw.js';

  var tawaranPasang = null;
  var sudahTerpasang = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;

  function tombolPasang() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-pasang-pwa]'));
  }

  function tampilkanTombol() {
    if (sudahTerpasang || !tawaranPasang) return;
    tombolPasang().forEach(function (el) { el.hidden = false; });
  }

  function sembunyikanTombol() {
    tombolPasang().forEach(function (el) { el.hidden = true; });
  }

  function pesanPembaruan(reg) {
    if (document.querySelector('[data-pesan-pembaruan]')) return;
    var box = document.createElement('div');
    box.className = 'pesan-pembaruan';
    box.setAttribute('data-pesan-pembaruan', '');
    box.setAttribute('role', 'status');
    box.innerHTML = '<span>Versi baru SiDiPa tersedia.</span>';
    var tombol = document.createElement('button');
    tombol.type = 'button';
    tombol.textContent = 'Muat ulang';
    tombol.addEventListener('click', function () {
      var pekerja = reg.waiting;
      if (pekerja) pekerja.postMessage({ tipe: 'SIDIPA_LEWATI_TUNGGU' });
      navigator.serviceWorker.addEventListener('controllerchange', function () {
        window.location.reload();
      }, { once: true });
    });
    box.appendChild(tombol);
    (document.body || document.documentElement).appendChild(box);
  }

  function perbaruiStatusJaringan() {
    var el = document.querySelector('[data-status-jaringan]');
    if (!el) return;
    if (navigator.onLine) {
      el.hidden = true;
      el.textContent = '';
      el.removeAttribute('data-offline');
    } else {
      el.hidden = false;
      el.setAttribute('data-offline', '1');
      el.textContent = 'Offline — data belum tersinkron';
    }
  }

  window.addEventListener('load', function () {
    navigator.serviceWorker.register(alamatSW, { scope: lingkup })
      .then(function (reg) {
        if (reg.waiting) pesanPembaruan(reg);
        reg.addEventListener('updatefound', function () {
          var baru = reg.installing;
          if (!baru) return;
          baru.addEventListener('statechange', function () {
            if (baru.state === 'installed' && navigator.serviceWorker.controller) {
              pesanPembaruan(reg);
            }
          });
        });
        // Cek pembaruan saat tab kembali aktif.
        document.addEventListener('visibilitychange', function () {
          if (!document.hidden) reg.update().catch(function () {});
        });
      })
      .catch(function () {
        // Umumnya karena bukan HTTPS / localhost; aplikasi tetap berjalan normal.
      });
  });

  window.addEventListener('beforeinstallprompt', function (acara) {
    acara.preventDefault();
    tawaranPasang = acara;
    tampilkanTombol();
  });

  window.addEventListener('appinstalled', function () {
    sudahTerpasang = true;
    tawaranPasang = null;
    sembunyikanTombol();
  });

  document.addEventListener('click', function (acara) {
    var target = acara.target;
    var tombol = target && target.closest ? target.closest('[data-pasang-pwa]') : null;
    if (!tombol || !tawaranPasang) return;
    acara.preventDefault();
    var tawaran = tawaranPasang;
    tawaran.prompt();
    tawaran.userChoice.then(function (hasil) {
      if (hasil && hasil.outcome === 'accepted') sudahTerpasang = true;
      tawaranPasang = null;
      sembunyikanTombol();
    });
  });

  window.addEventListener('online', perbaruiStatusJaringan);
  window.addEventListener('offline', perbaruiStatusJaringan);
  perbaruiStatusJaringan();
})();
