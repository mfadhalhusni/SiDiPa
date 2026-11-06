/* ============================================================
 | SiDiPa — SARAN OTOMATIS PENCARIAN SISWA (satu komponen, semua halaman)
 | ------------------------------------------------------------
 | Modelnya sama persis dengan kolom "Cari siswa" di menu Scan Masuk /
 | Sholat Dhuha: satu input, ketik NIS atau nama, saran dari
 | api/cari_siswa.php (server, bukan daftar <select> 300 baris), pilih
 | dengan mouse / ArrowDown / ArrowUp / Enter, Esc untuk menutup.
 |
 | Cara pakai: beri atribut data-cari-siswa pada <input> di dalam
 | .pembungkus-saran, lengkap dengan .saran-siswa (role=listbox).
 | Atribut:
 |   data-endpoint   URL api/cari_siswa.php (wajib)
 |   data-sasaran    id <input type=hidden> yang diisi (mode pilih)
 |   data-nilai      "id" (default) atau "nis" — isi hidden
 |   data-tampil     "nama" (default) atau "nis" — teks pada input
 |   data-mode       "pilih" | "saring" | "centang" (default pilih)
 |   data-kirim      "1" = kirim form otomatis setelah memilih
 |   data-validasi   "0" = form mengurus submit sendiri (halaman scan)
 |   data-petunjuk   id <p class=bantuan> untuk status
 |   data-bantuan    teks awal <p> tersebut
 |   data-centang    name checkbox untuk mode centang
 |   data-daftar     id wadah checkbox untuk mode centang
 | Event: "sidipa:siswa-terpilih" (detail = data siswa) pada input, dan
 |        "sidipa:siswa-ditambah" (document) untuk checkbox baru.
 | ============================================================ */
(function () {
  'use strict';
  if (!window.SiDiPa || typeof window.SiDiPa.get !== 'function') { return; }

  var PANJANG_MIN = 2;
  var JEDA = 180;

  function acaraKustom(nama, rinci) {
    if (typeof window.CustomEvent === 'function') {
      return new window.CustomEvent(nama, { bubbles: true, detail: rinci });
    }
    var e = document.createEvent('CustomEvent');
    e.initCustomEvent(nama, true, false, rinci);
    return e;
  }

  function pasang(input) {
    var bungkus = input.closest ? input.closest('.pembungkus-saran') : null;
    var kotak = bungkus ? bungkus.querySelector('.saran-siswa') : null;
    var url = input.dataset.endpoint || '';
    if (!bungkus || !kotak || !url) { return; }

    var tersembunyi = input.dataset.sasaran ? document.getElementById(input.dataset.sasaran) : null;
    var info = input.dataset.petunjuk ? document.getElementById(input.dataset.petunjuk) : null;
    var mode = input.dataset.mode || 'pilih';
    var dari = input.dataset.nilai || 'id';
    var tampil = input.dataset.tampil || 'nama';
    var form = input.form || null;
    var validasi = input.dataset.validasi !== '0';
    var kirimOtomatis = mode === 'saring'
      ? input.dataset.kirim !== '0'
      : input.dataset.kirim === '1';
    var daftar = input.dataset.daftar ? document.getElementById(input.dataset.daftar) : null;
    var namaCentang = input.dataset.centang || 'siswa_id[]';
    var teksBantuan = input.dataset.bantuan || 'Ketik NIS atau nama siswa, lalu pilih dari saran.';

    var pilihan = [];
    var aktif = -1;
    var timer = null;
    var urutan = 0;

    function pesan(isi, sukses) {
      if (!info) { return; }
      info.textContent = isi;
      info.classList.toggle('teks-sukses', !!sukses);
      info.classList.toggle('teks-galat', !sukses && isi !== '' && isi !== teksBantuan);
    }

    function tutup() {
      kotak.hidden = true;
      kotak.innerHTML = '';
      pilihan = [];
      aktif = -1;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    }

    function sorot(i) {
      var anak = kotak.querySelectorAll('[role="option"]');
      for (var j = 0; j < anak.length; j += 1) {
        var on = j === i;
        anak[j].classList.toggle('saran-aktif', on);
        anak[j].setAttribute('aria-selected', on ? 'true' : 'false');
      }
      aktif = i;
      if (i >= 0 && anak[i]) {
        input.setAttribute('aria-activedescendant', anak[i].id);
        anak[i].scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function render(daftarSiswa) {
      pilihan = daftarSiswa || [];
      kotak.innerHTML = '';
      aktif = -1;
      input.removeAttribute('aria-activedescendant');
      if (pilihan.length === 0) { tutup(); return; }
      for (var i = 0; i < pilihan.length; i += 1) {
        var s = pilihan[i];
        var opsi = document.createElement('button');
        var kuat = document.createElement('strong');
        var kecil = document.createElement('span');
        opsi.type = 'button';
        opsi.className = 'saran-opsi';
        opsi.id = input.id + '-opsi-' + i;
        opsi.setAttribute('role', 'option');
        opsi.setAttribute('aria-selected', 'false');
        opsi.dataset.indeks = String(i);
        kuat.textContent = s.nama || 'Siswa';
        kecil.textContent = 'NIS ' + (s.nis || '-') + ' · ' + (s.kelas || '-');
        opsi.appendChild(kuat);
        opsi.appendChild(kecil);
        kotak.appendChild(opsi);
      }
      kotak.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    /* Satu permintaan diberi nomor urut agar balasan lama tidak menimpa
       saran untuk kata kunci yang lebih baru. */
    function minta(q) {
      if (!q || q.length < PANJANG_MIN) { return Promise.resolve({ no: urutan, daftar: [] }); }
      var no = ++urutan;
      return window.SiDiPa.get(url, { q: q }).then(function (res) {
        return { no: no, daftar: (res && res.siswa) || [] };
      });
    }

    function cariDanRender() {
      minta(input.value.trim()).then(function (hasil) {
        if (hasil.no !== urutan) { return; }
        render(hasil.daftar);
      }).catch(function () { tutup(); });
    }

    function kotakCentang(s) {
      var semua = document.querySelectorAll('input[type="checkbox"]');
      var cb = null;
      for (var i = 0; i < semua.length; i += 1) {
        if (semua[i].name === namaCentang && String(semua[i].value) === String(s.id)) {
          cb = semua[i];
          break;
        }
      }
      if (!cb && daftar) {
        /* Siswa di luar 300 baris pertama tetap bisa dipilih: buat
           checkbox baru di wadah yang sama. */
        var label = document.createElement('label');
        var span = document.createElement('span');
        var judul = document.createElement('strong');
        var kecil = document.createElement('span');
        cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = namaCentang;
        cb.value = String(s.id);
        cb.style.cssText = 'width:20px;height:20px;min-height:0';
        judul.style.fontSize = '.88rem';
        judul.textContent = s.nama || 'Siswa';
        kecil.className = 'teks-kecil mono';
        kecil.textContent = (s.nis || '-') + ' · ' + (s.kelas || '-');
        span.appendChild(judul);
        span.appendChild(document.createElement('br'));
        span.appendChild(kecil);
        label.className = 'pilihan-baris';
        label.style.cssText = 'border:2px solid #000;border-radius:8px;padding:8px 10px;'
          + 'display:flex;gap:8px;align-items:center;cursor:pointer;background:#fff';
        label.appendChild(cb);
        label.appendChild(span);
        daftar.appendChild(label);
        cb.addEventListener('change', function () {
          document.dispatchEvent(acaraKustom('sidipa:siswa-ditambah', s));
        });
      }
      return cb;
    }

    function pilih(s, kirim) {
      if (!s) { return; }
      var rinci = (s.nama || 'Siswa') + ' · NIS ' + (s.nis || '-') + ' · ' + (s.kelas || '-');
      if (mode === 'centang') {
        var cb = kotakCentang(s);
        if (cb) {
          cb.checked = true;
          cb.dispatchEvent(new Event('change', { bubbles: true }));
          if (cb.scrollIntoView) { cb.scrollIntoView({ block: 'nearest' }); }
          var induk = cb.closest ? cb.closest('label') : null;
          if (induk) {
            induk.classList.add('siswa-disorot');
            window.setTimeout(function () { induk.classList.remove('siswa-disorot'); }, 1200);
          }
        }
        pesan('Ditambahkan: ' + rinci, true);
        input.value = '';
        tutup();
        input.dispatchEvent(acaraKustom('sidipa:siswa-terpilih', s));
        return;
      }
      if (mode === 'saring') {
        input.value = s.nis || '';
        tutup();
        pesan('', false);
        input.dispatchEvent(acaraKustom('sidipa:siswa-terpilih', s));
        if (form && kirim !== false && kirimOtomatis && form.submit) { form.submit(); }
        return;
      }
      if (tersembunyi) { tersembunyi.value = String(s[dari] || ''); }
      input.value = String(s[tampil] || s.nama || '');
      pesan('Dipilih: ' + rinci, true);
      tutup();
      input.dispatchEvent(acaraKustom('sidipa:siswa-terpilih', s));
      if (form && kirim !== false && kirimOtomatis && form.submit) { form.submit(); }
    }

    input.addEventListener('input', function () {
      if (tersembunyi) { tersembunyi.value = ''; }
      /* Kosongkan saran lama SEKETIKA: Enter sebelum debounce selesai tidak
         boleh memakai pilihan untuk kata kunci sebelumnya. */
      pilihan = [];
      pesan(teksBantuan, false);
      window.clearTimeout(timer);
      timer = window.setTimeout(cariDanRender, JEDA);
    });

    kotak.addEventListener('click', function (e) {
      var opsi = e.target.closest ? e.target.closest('[role="option"]') : null;
      if (!opsi) { return; }
      var i = parseInt(opsi.dataset.indeks, 10);
      if (pilihan[i]) { pilih(pilihan[i]); input.focus(); }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { tutup(); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (kotak.hidden || pilihan.length === 0) { return; }
        e.preventDefault();
        var berikut = e.key === 'ArrowDown'
          ? (aktif + 1) % pilihan.length
          : (aktif <= 0 ? pilihan.length - 1 : aktif - 1);
        sorot(berikut);
        return;
      }
      if (e.key === 'Enter' && aktif >= 0 && pilihan[aktif]) {
        e.preventDefault();
        pilih(pilihan[aktif]);
      }
    });

    document.addEventListener('click', function (e) {
      if (!bungkus.contains(e.target)) { tutup(); }
    });

    /* Validasi form: hidden wajib terisi. Bila pengguna menekan Enter tanpa
       memilih, cari sekali lagi dan pakai hasilnya bila tepat satu siswa. */
    if (form && mode === 'pilih' && validasi) {
      form.addEventListener('submit', function (e) {
        if (!tersembunyi || tersembunyi.value.trim() !== '') { return; }
        e.preventDefault();
        var q = input.value.trim();
        if (q === '') {
          pesan('Pilih siswa dari daftar saran terlebih dahulu.', false);
          input.focus();
          return;
        }
        minta(q).then(function (hasil) {
          if (hasil.no !== urutan) { return; }
          if (hasil.daftar.length === 1) {
            pilih(hasil.daftar[0], false);
            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
          } else if (hasil.daftar.length === 0) {
            pesan('Siswa tidak ditemukan. Coba NIS atau nama lain.', false);
          } else {
            render(hasil.daftar);
            pesan('Beberapa siswa cocok — pilih salah satu dari saran.', false);
          }
        }).catch(function () { pesan('Tidak ada koneksi. Coba lagi.', false); });
      });
    }

    /* API kecil untuk halaman yang mengurus submit sendiri (scan.php). */
    input.__sidipaCari = {
      cari: function () { return minta(input.value.trim()).then(function (h) { return h.daftar; }); },
      pilih: function (s) { pilih(s, false); },
      render: render,
      tutup: tutup
    };
  }

  function mulai() {
    var semua = document.querySelectorAll('[data-cari-siswa]');
    for (var i = 0; i < semua.length; i += 1) { pasang(semua[i]); }
  }

  window.SiDiPa.cariSiswa = function (el) {
    var a = el && el.__sidipaCari;
    return a ? a.cari() : Promise.resolve([]);
  };
  window.SiDiPa.pilihSiswa = function (el, s) {
    var a = el && el.__sidipaCari;
    if (a) { a.pilih(s); }
  };
  window.SiDiPa.tampilSaran = function (el, daftar) {
    var a = el && el.__sidipaCari;
    if (a) { a.render(daftar || []); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mulai);
  } else {
    mulai();
  }
}());
