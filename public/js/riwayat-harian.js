/* ============================================================
 | SiDiPa — PANEL "Catatan siswa pada tanggal ini"
 | ------------------------------------------------------------
 | Begitu guru memilih siswa dan/atau mengubah tanggal pada menu
 | Catat poin / Catat banyak siswa, panel ini memuat SELURUH catatan
 | poin siswa pada tanggal itu (termasuk yang ditulis guru lain dan
 | yang berasal dari scan/cron) lewat api/riwayat_harian.php.
 |
 | Tujuannya: mencegah pencatatan pelanggaran GANDA. Panel ini TIDAK
 | membatasi jumlah poin — murni informasi. Bila kejadian yang dipilih
 | di dropdown katalog sudah tercatat untuk siswa itu pada tanggal itu,
 | barisnya disorot dan muncul peringatan (tetap boleh disimpan).
 |
 | Privasi: endpoint tidak mengirim identitas pencatat; skrip ini juga
 | tidak menampilkannya.
 | ============================================================ */
(function () {
  'use strict';
  if (!window.SiDiPa || typeof window.SiDiPa.get !== 'function') { return; }

  var panel = document.getElementById('kartu-riwayat-harian');
  if (!panel) { return; }

  var endpoint = panel.dataset.endpoint || '';
  var modeBanyak = panel.dataset.mode === 'banyak';
  var statusEl = document.getElementById('riwayat-status');
  var tabelEl = document.getElementById('riwayat-tabel');
  var isiEl = document.getElementById('riwayat-isi');
  var peringatanEl = document.getElementById('riwayat-peringatan');
  var tanggalEl = document.getElementById('tanggal');
  var katalogEl = document.getElementById('katalog_id');
  var inputSiswa = document.getElementById('cari-siswa');
  var hiddenSiswa = document.getElementById('cari-siswa-nilai');
  var KUNCI = 'sidipa_riwayat_siswa';

  var urutan = 0;
  var timer = null;
  var terakhir = null;

  function teksStatus(s) { if (statusEl) { statusEl.textContent = s; } }
  function sembunyikan() {
    if (tabelEl) { tabelEl.hidden = true; }
    if (peringatanEl) { peringatanEl.hidden = true; }
  }

  function idSiswa() {
    if (modeBanyak) {
      var semua = document.querySelectorAll('input[type="checkbox"]');
      var hasil = [];
      for (var i = 0; i < semua.length; i += 1) {
        if (semua[i].name === 'siswa_id[]' && semua[i].checked) {
          hasil.push(semua[i].value);
        }
      }
      return hasil.slice(0, 60);
    }
    return hiddenSiswa && hiddenSiswa.value.trim() !== ''
      ? [hiddenSiswa.value.trim()] : [];
  }

  function labelSumber(s) {
    if (s === 'manual') { return 'Manual'; }
    if (s === 'berkala') { return 'Penilaian bulanan'; }
    return 'Otomatis (scan/cron)';
  }

  function buatBaris(siswa, c, cocok) {
    var tr = document.createElement('tr');
    if (cocok) { tr.className = 'baris-ganda'; }
    function sel(isi, kelas) {
      var td = document.createElement('td');
      td.textContent = isi;
      if (kelas) { td.className = kelas; }
      tr.appendChild(td);
    }
    if (modeBanyak) { sel(siswa.nama + ' · ' + siswa.nis); }
    sel(c.jam || '-');
    sel(c.kejadian || 'Catatan sistem');
    sel(c.aspek || '-');
    sel((c.poin > 0 ? '+' : '') + c.poin, 'mono');
    sel(labelSumber(c.sumber));
    sel(c.status === 'aktif' ? 'Aktif' : 'Dibatalkan');
    return tr;
  }

  function render(res) {
    terakhir = res;
    if (!isiEl || !tabelEl) { return; }
    isiEl.innerHTML = '';
    var daftar = (res && res.siswa) || [];
    var katalog = katalogEl ? parseInt(katalogEl.value, 10) : 0;
    var namaGanda = [];
    var totalCatatan = 0;
    var totalPengurangan = 0;

    for (var i = 0; i < daftar.length; i += 1) {
      var s = daftar[i];
      var baris = s.catatan || [];
      for (var j = 0; j < baris.length; j += 1) {
        var c = baris[j];
        totalCatatan += 1;
        if (c.status === 'aktif' && c.poin < 0) { totalPengurangan += Math.abs(c.poin); }
        var cocok = !!(katalog && c.katalog_id === katalog && c.status === 'aktif');
        if (cocok && namaGanda.indexOf(s.nama) === -1) { namaGanda.push(s.nama); }
        isiEl.appendChild(buatBaris(s, c, cocok));
      }
    }

    if (peringatanEl) {
      if (namaGanda.length > 0) {
        peringatanEl.textContent = 'Perhatian: kejadian yang sama sudah tercatat hari ini untuk '
          + namaGanda.slice(0, 4).join(', ')
          + (namaGanda.length > 4 ? ' dan ' + (namaGanda.length - 4) + ' siswa lainnya' : '')
          + '. Periksa baris yang disorot sebelum menyimpan agar tidak ganda.';
        peringatanEl.hidden = false;
      } else {
        peringatanEl.textContent = '';
        peringatanEl.hidden = true;
      }
    }

    if (totalCatatan === 0) {
      tabelEl.hidden = true;
      teksStatus('Belum ada catatan pada tanggal ini untuk siswa yang dipilih.');
      return;
    }
    tabelEl.hidden = false;
    teksStatus(totalCatatan + ' catatan pada ' + ((res && res.tanggal) || '-')
      + ' · total pengurangan aktif ' + totalPengurangan
      + ' poin (ringkasan informasi, bukan batas).');
  }

  function muat() {
    var ids = idSiswa();
    var tgl = tanggalEl ? tanggalEl.value : '';
    if (!endpoint || ids.length === 0 || !tgl) {
      terakhir = null;
      sembunyikan();
      teksStatus('Pilih siswa dan tanggal untuk melihat pelanggaran yang sudah tercatat.');
      return;
    }
    var no = ++urutan;
    teksStatus('Memuat catatan siswa …');
    window.SiDiPa.get(endpoint, { siswa_id: ids.join(','), tanggal: tgl })
      .then(function (res) { if (no === urutan) { render(res); } })
      .catch(function () {
        if (no === urutan) {
          teksStatus('Gagal memuat daftar catatan. Coba ubah tanggal atau muat ulang halaman.');
        }
      });
  }

  function muatNanti() {
    window.clearTimeout(timer);
    timer = window.setTimeout(muat, 120);
  }

  function simpanTanggal() {
    if (modeBanyak || !window.sessionStorage) { return; }
    try {
      var data = JSON.parse(window.sessionStorage.getItem(KUNCI) || 'null') || {};
      data.tanggal = tanggalEl ? tanggalEl.value : '';
      window.sessionStorage.setItem(KUNCI, JSON.stringify(data));
    } catch (_) { /* penyimpanan sesi tidak tersedia */ }
  }

  /* Pulihkan siswa + tanggal terakhir (mis. guru kembali dari halaman
     Riwayat setelah menyimpan) supaya entri baru langsung terlihat. */
  if (!modeBanyak && hiddenSiswa && inputSiswa && window.sessionStorage) {
    try {
      var simpan = JSON.parse(window.sessionStorage.getItem(KUNCI) || 'null');
      if (simpan && simpan.id) {
        hiddenSiswa.value = simpan.id;
        inputSiswa.value = simpan.nama || '';
        if (tanggalEl && simpan.tanggal) { tanggalEl.value = simpan.tanggal; }
      }
    } catch (_) { /* abaikan data sesi rusak */ }
  }

  document.addEventListener('sidipa:siswa-terpilih', function (e) {
    if (modeBanyak || !inputSiswa || e.target !== inputSiswa) { return; }
    if (window.sessionStorage) {
      try {
        window.sessionStorage.setItem(KUNCI, JSON.stringify({
          id: hiddenSiswa ? hiddenSiswa.value : '',
          nama: (e.detail && e.detail.nama) || '',
          nis: (e.detail && e.detail.nis) || '',
          tanggal: tanggalEl ? tanggalEl.value : ''
        }));
      } catch (_) { /* abaikan */ }
    }
    muatNanti();
  });

  if (inputSiswa) { inputSiswa.addEventListener('input', muatNanti); }
  if (tanggalEl) {
    tanggalEl.addEventListener('change', function () { simpanTanggal(); muatNanti(); });
    tanggalEl.addEventListener('input', function () { simpanTanggal(); muatNanti(); });
  }
  if (katalogEl) {
    katalogEl.addEventListener('change', function () { if (terakhir) { render(terakhir); } });
  }
  if (modeBanyak) {
    document.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'siswa_id[]') { muatNanti(); }
    });
    document.addEventListener('sidipa:siswa-ditambah', muatNanti);
    document.addEventListener('click', function (e) {
      var tombol = e.target && e.target.closest
        ? e.target.closest('[data-pilih-semua]') : null;
      if (tombol) { window.setTimeout(muatNanti, 0); }
    });
  }

  muat();
}());
