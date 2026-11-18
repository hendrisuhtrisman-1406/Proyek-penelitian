<?php
// ============================================================
// KITCHEN - DASHBOARD: daftar pesanan yang harus disiapkan (pending + disiapkan)
// Polling 4 detik, urut paling lama di atas, tombol aksi sesuai status.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['kitchen', 'owner']);

$page_title = 'Dapur - Pesanan Siap Saji';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Dapur - Pesanan Masuk</h4>
        <small class="text-muted">Diperbarui otomatis setiap 4 detik. Pesanan paling lama tampil di atas.</small>
    </div>
    <span class="badge bg-secondary" id="orderCount">0 pesanan aktif</span>
</div>

<div id="orders-container" class="row g-3">
    <div class="col-12 text-center py-5 text-muted">
        <div style="font-size:3rem;">&#127859;</div>
        <p class="mt-2 mb-0">Tidak ada pesanan yang perlu disiapkan</p>
    </div>
</div>

<script>
(function(){
    var lastIds = {};

    var STATUS_LABEL = {
        'pending':   {text:'Menunggu Dapur',  cls:'bg-warning', next:'disiapkan', btn:'Mulai Siapkan'},
        'disiapkan': {text:'Sedang Disiapkan', cls:'bg-info',    next:'siap',      btn:'Siap Disajikan'}
    };

    function formatRp(n){
        return 'Rp ' + parseInt(n||0,10).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    }

    function renderOrders(orders){
        var html = '';
        if (!orders || orders.length === 0){
            $('#orders-container').html(
                '<div class="col-12 text-center py-5 text-muted">' +
                '<div style="font-size:3rem;">&#127859;</div>' +
                '<p class="mt-2 mb-0">Tidak ada pesanan yang perlu disiapkan</p></div>'
            );
            $('#orderCount').text('0 pesanan aktif');
            lastIds = {};
            return;
        }

        var newIds = {};
        orders.forEach(function(o){
            newIds[o.id] = true;
            var isNew = !lastIds[o.id];
            var st = STATUS_LABEL[o.status];
            if (!st) return; // hanya tampilkan pending & disiapkan

            var itemsHtml = '';
            (o.items||[]).forEach(function(it){
                itemsHtml += '<div class="d-flex justify-content-between align-items-center py-1 border-bottom">'
                    + '<span style="font-size:1.3rem;font-weight:800;">'
                    + '<span class="text-danger" style="font-size:1.6rem;">' + it.quantity + 'x</span> '
                    + $('<div/>').text(it.name).html()
                    + '</span></div>';
            });

            html += '<div class="col-md-6 col-lg-4" data-order-id="' + o.id + '">'
                + '<div class="card order-card status-' + o.status + (isNew ? ' order-baru':'') + ' p-3">'
                +   '<div class="d-flex justify-content-between align-items-center mb-2">'
                +     '<div><strong style="font-size:1.2rem;">#' + o.id + '</strong> '
                +     '<span class="badge bg-secondary">Meja ' + $('<div/>').text(o.table_number||'-').html() + '</span></div>'
                +     '<span class="badge ' + st.cls + '">' + st.text + '</span>'
                +   '</div>'
                +   '<div class="mb-2"><strong>' + $('<div/>').text(o.customer_name||'Umum').html() + '</strong> '
                +     '<span class="text-muted small">(' + (o.ordered_at||'') + ')</span></div>'
                +   '<div class="mb-3">' + itemsHtml + '</div>'
                +   '<button type="button" class="btn btn-cafe btn-lg w-100 btn-action" '
                +     'data-order-id="' + o.id + '" data-next="' + st.next + '">' + st.btn + '</button>'
                + '</div></div>';
        });

        if (html === ''){
            html = '<div class="col-12 text-center py-5 text-muted">'
                 + '<div style="font-size:3rem;">&#127859;</div>'
                 + '<p class="mt-2 mb-0">Tidak ada pesanan yang perlu disiapkan</p></div>';
        }

        $('#orders-container').html(html);
        $('#orderCount').text(orders.length + ' pesanan aktif');
        lastIds = newIds;
    }

    function loadOrders(){
        $.getJSON('<?= BASE_URL ?>api/get-orders.php?role=kitchen', function(data){
            renderOrders(data.orders || []);
        }).fail(function(){});
    }

    // Tombol aksi: ubah status via AJAX lalu refresh
    $(document).on('click', '.btn-action', function(){
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var next = $btn.data('next');
        $btn.prop('disabled', true).text('Memproses...');
        $.post('<?= BASE_URL ?>api/update-status.php', {order_id: orderId, status: next}, function(res){
            if (res && res.success){
                cafeToast('Pesanan #' + orderId + ' diperbarui.', 'success');
                loadOrders(); // refresh daftar (pesanan siap otomatis hilang)
            } else {
                cafeToast((res && res.error) ? res.error : 'Gagal memperbarui status.', 'danger');
                $btn.prop('disabled', false).text('Coba Lagi');
            }
        }, 'json').fail(function(){
            cafeToast('Terjadi kesalahan jaringan.', 'danger');
            $btn.prop('disabled', false).text('Coba Lagi');
        });
    });

    loadOrders();
    setInterval(loadOrders, 4000);
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
