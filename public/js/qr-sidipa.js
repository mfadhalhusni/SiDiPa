/* ============================================================
 | SiDiPa — pembuat QR statis (tanpa CDN, aman untuk cetak offline).
 | Bergantung pada public/js/qrcode.min.js (qrcode-generator, MIT).
 | Cara pakai: <div data-qr="ISI_TOKEN_ATAU_URL"></div>
 | Hasil berupa SVG vektor agar tetap tajam saat dicetak A4/kartu.
 | ============================================================ */
(function () {
  'use strict';

  function gambarSatu(el) {
    if (el.dataset.qrSelesai === '1') { return; }
    var isi = el.getAttribute('data-qr') || '';
    if (!isi) { return; }
    if (typeof window.qrcode !== 'function') {
      el.textContent = 'QR tidak tersedia';
      el.dataset.qrSelesai = '1';
      return;
    }
    try {
      var q = window.qrcode(0, 'M');
      q.addData(isi, 'Byte');
      q.make();
      el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 4, scalable: true });
      var svg = el.querySelector('svg');
      if (svg) {
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Kode QR SiDiPa');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');
      }
    } catch (galat) {
      el.textContent = 'QR gagal dibuat';
    }
    el.dataset.qrSelesai = '1';
  }

  function gambarSemua() {
    var daftar = document.querySelectorAll('[data-qr]');
    for (var i = 0; i < daftar.length; i += 1) { gambarSatu(daftar[i]); }
  }

  function siapCetak() {
    if (window.sidipaQr) { window.sidipaQr(); }
    window.print();
  }

  window.sidipaQr = gambarSemua;
  window.sidipaCetak = siapCetak;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', gambarSemua);
  } else {
    gambarSemua();
  }

  document.addEventListener('click', function (acara) {
    var tombol = acara.target.closest ? acara.target.closest('[data-cetak]') : null;
    if (tombol) { acara.preventDefault(); siapCetak(); }
  });
}());
