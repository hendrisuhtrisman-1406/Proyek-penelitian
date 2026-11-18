/* app.js - helper JS global untuk Sangur Cafe */

// Tampilkan toast Bootstrap sederhana
function cafeToast(pesan, warna = 'success') {
  var el = document.createElement('div');
  el.className = 'toast align-items-center text-white bg-' + warna + ' border-0 show';
  el.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;min-width:250px;';
  el.innerHTML = '<div class="d-flex"><div class="toast-body">' + pesan + '</div>'
    + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  document.body.appendChild(el);
  setTimeout(function(){ el.remove(); }, 3500);
}

// Konfirmasi hapus
function confirmHapus(pesan) { return confirm(pesan || 'Yakin ingin menghapus data ini?'); }

// Format Rupiah dari angka
function formatRp(angka) {
  return 'Rp ' + parseInt(angka || 0, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
