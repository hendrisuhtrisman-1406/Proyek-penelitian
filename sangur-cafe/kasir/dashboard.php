<?php
// ============================================================
// KASIR - DASHBOARD: daftar pesanan masuk real-time (polling 4 detik)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['kasir', 'owner']);

$page_title = 'Pesanan Masuk';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-1">Pesanan Aktif</h4>
        <small class="text-muted">Pesanan masuk diperbarui otomatis setiap 4 detik.</small>
    </div>
    <div>
        <a href="<?= BASE_URL ?>kasir/transactions.php" class="btn btn-outline-cafe btn-sm">Riwayat Transaksi</a>
    </div>
</div>

<!-- Pencarian + Filter Status -->
<div class="row g-2 mb-3 no-print">
    <div class="col-md-6">
        <input type="text" id="searchInput" class="form-control" placeholder="Cari nomor pesanan / nama pelanggan...">
    </div>
    <div class="col-md-3">
        <select id="statusFilter" class="form-select">
            <option value="">Semua Status</option>
            <option value="pending">Menunggu Konfirmasi</option>
            <option value="disiapkan">Sedang Disiapkan</option>
            <option value="siap">Siap Disajikan</option>
            <option value="disajikan">Sudah Disajikan</option>
        </select>
    </div>
    <div class="col-md-3 text-md-end">
        <span class="badge bg-secondary" id="orderCount">0 pesanan</span>
    </div>
</div>

<!-- Container daftar pesanan (di-render ulang via AJAX) -->
<div id="orders-container" class="row g-3">
    <div class="col-12 text-center py-5 text-muted">
        <div style="font-size:3rem;">&#9749;</div>
        <p class="mt-2 mb-0">Belum ada pesanan masuk</p>
    </div>
</div>

<script>
(function(){
    var lastIds = {}; // id -> true, untuk deteksi pesanan baru

    var STATUS_LABEL = {
        'pending':   {text:'Menunggu Konfirmasi', cls:'bg-warning'},
        'disiapkan': {text:'Sedang Disiapkan',  cls:'bg-info'},
        'siap':      {text:'Siap Disajikan',    cls:'bg-primary'},
        'disajikan': {text:'Sudah Disajikan',   cls:'bg-success'}
    };

    function formatRp(n){
        return 'Rp ' + parseInt(n||0,10).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    }

    function renderOrders(orders){
        var search = ($('#searchInput').val()||'').toLowerCase().trim();
        var filterStatus = $('#statusFilter').val();
        var html = '';
        var shown = 0;

        if (!orders || orders.length === 0){
            $('#orders-container').html(
                '<div class="col-12 text-center py-5 text-muted">' +
                '<div style="font-size:3rem;">&#9749;</div>' +
                '<p class="mt-2 mb-0">Belum ada pesanan masuk</p></div>'
            );
            $('#orderCount').text('0 pesanan');
            return;
        }

        var newIds = {};
        orders.forEach(function(o){
            newIds[o.id] = true;
            var isNew = !lastIds[o.id];

            // Filter client-side
            var hay = ('#' + o.id + ' ' + (o.customer_name||'') + ' meja ' + (o.table_number||'')).toLowerCase();
            if (search && hay.indexOf(search) === -1) return;
            if (filterStatus && o.status !== filterStatus) return;

            shown++;
            var st = STATUS_LABEL[o.status] || {text:o.status, cls:'bg-secondary'};
            var payBadge = (o.payment_status === 'paid')
                ? '<span class="badge bg-success">Lunas</span>'
                : '<span class="badge bg-danger">Belum Bayar</span>';

            var itemsHtml = '';
            (o.items||[]).forEach(function(it){
                itemsHtml += '<li class="list-group-item d-flex justify-content-between align-items-start py-1">'
                    + '<span><strong>' + it.quantity + 'x</strong> ' + $('<div/>').text(it.name).html() + '</span>'
                    + '<span class="text-muted small">' + formatRp(it.subtotal) + '</span></li>';
            });

            var bayarBtn = '';
            if (o.payment_status !== 'paid'){
                bayarBtn = '<a href="<?= BASE_URL ?>kasir/payment.php?order=' + o.id
                    + '" class="btn btn-cafe btn-sm">Proses Pembayaran</a> ';
            }

            html += '<div class="col-md-6 col-lg-4 order-card-wrapper" data-order-id="' + o.id + '">'
                + '<div class="card order-card status-' + o.status + (isNew ? ' order-baru':'') + ' p-3">'
                +   '<div class="d-flex justify-content-between align-items-start mb-2">'
                +     '<div><strong>#' + o.id + '</strong> <span class="text-muted small">Meja ' + $('<div/>').text(o.table_number||'-').html() + '</span></div>'
                +     '<span class="badge ' + st.cls + '">' + st.text + '</span>'
                +   '</div>'
                +   '<div class="mb-1"><strong>' + $('<div/>').text(o.customer_name||'Umum').html() + '</strong></div>'
                +   '<div class="text-muted small mb-2">' + (o.ordered_at||'') + '</div>'
                +   '<ul class="list-group list-group-flush mb-2 small">' + itemsHtml + '</ul>'
                +   '<div class="d-flex justify-content-between align-items-center mb-2">'
                +     '<span class="fw-bold">Total: ' + formatRp(o.total_price) + '</span>'
                +     payBadge
                +   '</div>'
                +   '<div class="d-flex gap-2">'
                +     bayarBtn
                +     '<a href="<?= BASE_URL ?>kasir/payment.php?order=' + o.id + '" class="btn btn-outline-cafe btn-sm">Lihat Detail</a>'
                +   '</div>'
                + '</div></div>';
        });

        if (shown === 0){
            html = '<div class="col-12 text-center py-5 text-muted">'
                 + '<div style="font-size:3rem;">&#128269;</div>'
                 + '<p class="mt-2 mb-0">Tidak ada pesanan yang cocok dengan pencarian/filter.</p></div>';
        }

        $('#orders-container').html(html);
        $('#orderCount').text(shown + ' pesanan');
        lastIds = newIds;
    }

    function loadOrders(){
        $.getJSON('<?= BASE_URL ?>api/get-orders.php?role=kasir', function(data){
            renderOrders(data.orders || []);
        }).fail(function(){
            // silent fail, coba lagi di polling berikutnya
        });
    }

    // Event filter client-side (render ulang dari data terakhir)
    var lastData = [];
    var origRender = renderOrders;
    renderOrders = function(orders){ lastData = orders; origRender(orders); };

    $('#searchInput, #statusFilter').on('input change', function(){
        origRender(lastData);
    });

    loadOrders();
    setInterval(loadOrders, 4000);
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
