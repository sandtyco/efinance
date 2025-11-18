<?php
// pages/rektorat/transaksi_list.php

// PENTING: File ini mengasumsikan bahwa koneksi database ($conn) dan
// session ($_SESSION) sudah tersedia dan diinisialisasi di luar file ini.

global $conn;

// --- Mulai: Standardisasi Status Helper ---
/**
 * Mengembalikan label status Realisasi yang mudah dibaca dengan styling bootstrap.
 * Disesuaikan dengan kode status: 0:Draft, 1:Diajukan, 2:Disetujui Keuangan, 3:Disetujui Rektorat (Final), 4:Ditolak, 5:Selesai
 */
if (!function_exists('get_realisasi_status_label')) {
    function get_realisasi_status_label($status) {
        switch ($status) {
            // Status Awal
            case 0: return '<span class="label label-info">Draft</span>';
            // Status Diajukan
            case 1: return '<span class="label label-warning">Diajukan</span>';
            // Menunggu Aksi Rektorat
            case 2: return '<span class="label label-primary">Disetujui Keuangan</span>';
            // Status Akhir (Berhasil)
            case 3: return '<span class="label label-success">Disetujui Rektorat (Final)</span>';
            // Status Akhir (Gagal)
            case 4: return '<span class="label label-danger">Ditolak</span>';
            // Status Selesai (Tambahan sesuai permintaan)
            case 5: return '<span class="label label-success">Selesai</span>'; 
            default: return '<span class="label label-default">Tidak Diketahui</span>';
        }
    }
}

/**
 * Format angka menjadi format Rupiah tanpa desimal (e.g., 1.000.000)
 */
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return number_format($angka, 0, ',', '.');
    }
}
// --- Akhir: Standardisasi Status Helper ---

$transaksi_list = [];
$error_message = null;

// Query: Ambil semua realisasi beserta detail RAB dan Departemen.
// Rektorat melihat status 2 (perlu persetujuan), dan status 3, 4, 5 (riwayat).
$query = "
    SELECT 
        r.id_realisasi, 
        r.tanggal_realisasi, 
        r.nomor_dokumen, 
        r.total_realisasi, 
        r.status, 
        rab.judul AS judul_rab, 
        d.nama_departemen 
    FROM 
        realisasi r
    INNER JOIN 
        rab ON r.id_rab = rab.id_rab
    INNER JOIN 
        departemen d ON r.id_departemen = d.id_departemen
    ORDER BY 
        r.tanggal_realisasi DESC, r.id_realisasi DESC
";

$result = $conn->query($query);
if ($result === false) {
    $error_message = "Gagal mengambil data realisasi: " . $conn->error;
} else {
    while ($row = $result->fetch_assoc()) {
        $transaksi_list[] = $row;
    }
    $result->free();
}

// Hitung transaksi yang menunggu aksi Rektorat (Status = 2: Disetujui Keuangan)
$pending_count = array_sum(array_map(function($t) {
    return $t['status'] == 2 ? 1 : 0;
}, $transaksi_list));

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary" style="border-top: 3px solid #286090;">
                <div class="panel-heading">
                    <h3 class="panel-title" style="font-weight: 600;">
                        <i class="fa fa-university"></i> Daftar Transaksi Realisasi (Rektorat)
                        <?php if ($pending_count > 0): ?>
                            <span class="badge pull-right bg-red">
                                <?= $pending_count; ?> Menunggu Aksi
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <?php 
                    // Tampilkan Flash Message jika ada
                    // Asumsi: Variabel $_SESSION['flash_message'] digunakan untuk notifikasi
                    if (isset($_SESSION['flash_message'])) {
                        echo $_SESSION['flash_message'];
                        unset($_SESSION['flash_message']);
                    }
                    if ($error_message) {
                        echo "<div class='alert alert-danger'>{$error_message}</div>";
                    }
                    ?>

                    <div class="table-responsive">
                        <!-- ID tabel disiapkan untuk integrasi DataTables -->
                        <table class="table table-bordered table-striped table-hover dataTable" id="transaksiTable">
                            <thead>
                                <tr class="info">
                                    <th style="width: 50px;">No</th>
                                    <th>Nomor Dokumen</th>
                                    <th>Tanggal</th>
                                    <th>Departemen</th>
                                    <th>RAB Terkait</th>
                                    <th class="text-right">Total Realisasi (Rp)</th>
                                    <th style="width: 180px;">Status</th>
                                    <th style="width: 80px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($transaksi_list as $transaksi): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($transaksi['nomor_dokumen']); ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($transaksi['tanggal_realisasi']))); ?></td>
                                        <td><?= htmlspecialchars($transaksi['nama_departemen']); ?></td>
                                        <td><?= htmlspecialchars($transaksi['judul_rab']); ?></td>
                                        <td class="text-right">Rp <?= format_rupiah($transaksi['total_realisasi']); ?></td>
                                        <td><?= get_realisasi_status_label($transaksi['status']); ?></td>
                                        <td class="text-center">
                                            <!-- Tombol Detail -->
                                            <a href="dashboard.php?page=rektorat/transaksi_detail.php&id_realisasi=<?= $transaksi['id_realisasi']; ?>" 
                                                class="btn btn-xs btn-primary" title="Lihat Detail">
                                                <i class="glyphicon glyphicon-search"></i>
                                            </a>
                                            
                                            <!-- Tombol Ambil Tindakan (Hanya untuk status 2) -->
                                            <?php if ($transaksi['status'] == 2): ?>
                                                <a href="dashboard.php?page=rektorat/transaksi_detail.php&id_realisasi=<?= $transaksi['id_realisasi']; ?>#approval-form" 
                                                    class="btn btn-xs btn-success" title="Ambil Tindakan">
                                                    <i class="glyphicon glyphicon-ok"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Catatan: Script DataTables diasumsikan sudah dimuat di tempat lain (misalnya layout utama) -->

<?php
// Pastikan tidak ada karakter tak terlihat setelah tag penutup PHP jika ini adalah file yang hanya berisi PHP logic (walaupun di sini ada HTML)
// Untuk keamanan, disarankan tidak menggunakan tag penutup PHP jika ini adalah file yang hanya berisi PHP logic, namun untuk campuran seperti ini tetap diperlukan.
?>