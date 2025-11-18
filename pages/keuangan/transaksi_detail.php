<?php
// PENTING: Mendeklarasikan variabel koneksi global ($conn)
global $conn;

// --- FUNGSI PENCEGAH UNDEFINED FUNCTION ---
if (!function_exists('get_status_color')) {
    function get_status_color($status) {
        switch ($status) {
            case 1: return 'label-warning';
            case 2: return 'label-primary';
            case 3: return 'label-success';
            case 4: return 'label-default';
            case -1: return 'label-danger';
            default: return 'label-info';
        }
    }
}
if (!function_exists('get_status_label')) {
    function get_status_label($status) {
        switch ($status) {
            case 1: return 'Menunggu Keuangan';
            case 2: return 'Menunggu Rektorat';
            case 3: return 'Disetujui Final';
            case 4: return 'Selesai';
            case -1: return 'Ditolak';
            default: return 'Tidak Diketahui';
        }
    }
}
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return number_format($angka, 0, ',', '.');
    }
}

$detail_error = null;
$transaksi_data = null;
$items_data = [];

// --- 1. Ambil ID dari URL ---
if (!isset($_GET['id_realisasi']) || !is_numeric($_GET['id_realisasi'])) {
    $detail_error = "ID Realisasi tidak valid.";
} else {
    $id_realisasi = (int)$_GET['id_realisasi'];

    // --- 2. Query untuk Detail Transaksi (KOREKSI FINAL DENGAN TRIPLE JOIN) ---
    $query_transaksi = "
        SELECT 
            r.*, 
            rab.judul AS judul_rab,
            rab.id_rab AS nomor_rab, 
            du.nama_lengkap AS nama_pembuat -- KOREKSI: Ambil nama dari detail_user
        FROM 
            realisasi r 
        LEFT JOIN 
            rab rab ON r.id_rab = rab.id_rab 
        LEFT JOIN 
            user u ON r.created_by = u.id_user -- JOIN ke tabel user melalui created_by
        LEFT JOIN
            detail_user du ON u.id_user = du.id_user -- JOIN ke detail_user untuk nama_lengkap
        WHERE 
            r.id_realisasi = ?
    ";
    
    $stmt = $conn->prepare($query_transaksi);
    
    // Pengecekan error prepare
    if ($stmt === false) {
        // Ini adalah error yang sering terjadi, log error MySQL untuk debugging
        $detail_error = "Gagal menyiapkan query detail transaksi: " . $conn->error; 
    } else {
        $stmt->bind_param('i', $id_realisasi);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaksi_data = $result->fetch_assoc();
        $stmt->close();

        if (!$transaksi_data) {
            $detail_error = "Data Realisasi dengan ID #{$id_realisasi} tidak ditemukan.";
        }
    }
    
    // --- 3. Query untuk Item Realisasi (Menggunakan tabel 'realisasi_detail' sesuai skema) ---
    if (empty($detail_error) && $transaksi_data) {
        $query_items = "
            SELECT 
                uraian, 
                jumlah_realisasi 
            FROM 
                realisasi_detail 
            WHERE 
                id_realisasi = ?
            -- Perhatian: Data harga_satuan dan total item tidak ada di realisasi_detail, 
            -- sehingga ini hanya menampilkan kolom yang tersedia.
            -- Jika butuh total, Anda harus JOIN ke rab_detail.
        ";
        
        $stmt_items = $conn->prepare($query_items);
        
        if ($stmt_items === false) {
             $detail_error = "Gagal menyiapkan query item realisasi: " . $conn->error;
        } else {
            $stmt_items->bind_param('i', $id_realisasi);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            $items_data = $result_items->fetch_all(MYSQLI_ASSOC);
            $stmt_items->close();
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-info-circle"></i> Detail Transaksi Realisasi #<?= htmlspecialchars($id_realisasi ?? 'N/A'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <!-- Handling Error -->
                    <?php if (isset($detail_error) && !empty($detail_error)): ?>
                        <div class="alert alert-danger text-center">
                            <strong>Gagal!</strong> <?= htmlspecialchars($detail_error); ?>
                            <br><a href="dashboard.php?page=transaksi_list.php" class="btn btn-sm btn-default" style="margin-top: 10px;">
                                <i class="fa fa-arrow-left"></i> Kembali ke Daftar
                            </a>
                        </div>
                    <?php return; endif; ?>

                    <?php if ($transaksi_data): ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Informasi Dokumen Utama -->
                                <table class="table table-bordered table-striped">
                                    <tr>
                                        <th style="width: 35%;">No. Dokumen</th>
                                        <td><?= htmlspecialchars($transaksi_data['nomor_dokumen']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Realisasi</th>
                                        <td><?= date('d M Y', strtotime($transaksi_data['tanggal_realisasi'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status Dokumen</th>
                                        <td>
                                            <span class="label <?= get_status_color($transaksi_data['status']); ?>">
                                                <?= htmlspecialchars(get_status_label($transaksi_data['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="col-md-6">
                                <!-- Informasi Terkait RAB -->
                                <table class="table table-bordered table-striped">
                                    <tr>
                                        <th style="width: 35%;">Terkait RAB</th>
                                        <td><?= htmlspecialchars($transaksi_data['judul_rab'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Nomor RAB</th>
                                        <td><?= htmlspecialchars($transaksi_data['nomor_rab'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td><?= htmlspecialchars($transaksi_data['nama_pembuat'] ?? 'User Tidak Diketahui'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Keterangan -->
                        <div class="panel panel-default">
                            <div class="panel-heading">Keterangan / Deskripsi Transaksi</div>
                            <div class="panel-body">
                                <p><?= nl2br(htmlspecialchars($transaksi_data['deskripsi'] ?? 'Tidak ada keterangan tambahan.')); ?></p>
                            </div>
                        </div>

                        <!-- Daftar Item Realisasi -->
                        <h4>Daftar Item Realisasi</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr class="info">
                                        <th>#</th>
                                        <th>Uraian Item (Dari Realisasi)</th>
                                        <th class="text-center">Jumlah Realisasi</th>
                                        <!-- Kolom Harga Satuan & Total dihilangkan karena tidak ada di realisasi_detail -->
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if (empty($items_data)):
                                    ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Tidak ada item yang terdaftar untuk realisasi ini.</td>
                                        </tr>
                                    <?php
                                    else:
                                        foreach ($items_data as $item): 
                                    ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($item['uraian']); ?></td>
                                            <td class="text-center"><?= htmlspecialchars($item['jumlah_realisasi']); ?></td>
                                            <td><small class="text-muted">Item hanya menampilkan Uraian dan Jumlah Realisasi (sesuai skema `realisasi_detail`).</small></td>
                                        </tr>
                                    <?php 
                                        endforeach; 
                                    endif;
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-right">TOTAL REALISASI</th>
                                        <th class="text-right text-danger" colspan="2">Rp <?= format_rupiah($transaksi_data['total_realisasi']); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    <?php endif; ?>
                </div>
                
                <div class="panel-footer text-right">
                    <a href="dashboard.php?page=transaksi_list.php" class="btn btn-default">
                        <i class="fa fa-arrow-circle-left"></i> Kembali ke Daftar Transaksi
                    </a>
                    
                    <?php 
                    // Tombol Validasi jika status = 1
                    if ($transaksi_data && $transaksi_data['status'] == 1): 
                    ?>
                        <a href="dashboard.php?page=transaksi_validation.php&id_realisasi=<?= $id_realisasi; ?>" 
                            class="btn btn-success">
                            <i class="fa fa-check"></i> Lanjut ke Validasi
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>