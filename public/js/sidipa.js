/* SiDiPa - JavaScript vanilla, tanpa build step. */
(function () {
  'use strict';

  function token() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function tutupPesan(tombol) {
    var pesan = tombol.closest('.pesan');
    if (pesan) { pesan.remove(); }
  }

  document.addEventListener('click', function (event) {
    var tutup = event.target.closest('[data-tutup-pesan]');
    if (tutup) { tutupPesan(tutup); }
    var cetak = event.target.closest('[data-cetak]');
    if (cetak) { window.print(); }
  });

  /* Konfirmasi tindakan yang mengubah data. */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var pengirim = event.submitter || null;
    var teksKonfirmasi = form.dataset.konfirmasi || (pengirim ? pengirim.dataset.konfirmasi : '');
    if (teksKonfirmasi && !window.confirm(teksKonfirmasi)) {
      event.preventDefault();
      return;
    }
    var tombol = pengirim || form.querySelector('[type="submit"]');
    if (tombol && !tombol.disabled) {
      tombol.dataset.teksAsli = tombol.innerHTML;
      tombol.disabled = true;
      tombol.classList.add('ditekan');
      tombol.innerHTML = 'Memproses...';
      window.setTimeout(function () {
        if (tombol.disabled) {
          tombol.disabled = false;
          tombol.classList.remove('ditekan');
          tombol.innerHTML = tombol.dataset.teksAsli || 'Simpan';
        }
      }, 8000);
    }
    var muat = form.querySelector('.status-memuat');
    if (muat) { muat.classList.add('tampil'); }
  });

  /* Keterangan wajib untuk kejadian tingkat sedang / berat. */
  document.addEventListener('change', function (event) {
    if (!event.target.matches('[data-katalog]')) { return; }
    var pilihan = event.target.options[event.target.selectedIndex];
    var tingkat = pilihan ? (pilihan.dataset.tingkat || '') : '';
    var keterangan = document.querySelector('[data-keterangan]');
    if (!keterangan) { return; }
    var wajib = (tingkat === 'sedang' || tingkat === 'berat');
    keterangan.required = wajib;
    var bantuan = document.querySelector('[data-bantuan-keterangan]');
    if (bantuan) {
      bantuan.textContent = wajib
        ? 'Wajib diisi untuk kejadian tingkat ' + tingkat + '.'
        : 'Opsional, isi bila perlu keterangan tambahan.';
    }
  });

  /* Saring baris tabel secara lokal. */
  document.querySelectorAll('[data-saring-tabel]').forEach(function (input) {
    input.addEventListener('input', function () {
      var target = document.querySelectorAll(input.dataset.saringTabel);
      if (!target.length) { return; }
      var kunci = input.value.trim().toLowerCase();
      target.forEach(function (tabel) {
        tabel.querySelectorAll('tbody tr').forEach(function (baris) {
          baris.style.display = baris.textContent.toLowerCase().indexOf(kunci) !== -1 ? '' : 'none';
        });
      });
    });
  });

  /* Pembantu fetch dengan token CSRF untuk endpoint JSON. */
  window.SiDiPa = {
    token: token,
    post: function (url, data) {
      var body = new URLSearchParams(data || {});
      body.set('csrf_token', token());
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-CSRF-Token': token(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: body.toString()
      }).then(function (res) { return res.json(); });
    },
    get: function (url, data) {
      var params = new URLSearchParams(data || {});
      params.set('csrf_token', token());
      return fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + params.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': token() }
      }).then(function (res) { return res.json(); });
    }
  };
}());
