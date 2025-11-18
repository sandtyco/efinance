<?php
// PHP Script untuk Daftar Validasi Realisasi Anggaran

// Mulai sesi (asumsi diperlukan untuk pesan notifikasi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- MOCK KONEKSI DATABASE (Ganti ini dengan include 'config/conn.php' yang sebenarnya) ---
// Karena file 'conn.php' tidak disertakan, kita akan membuat mock koneksi
// yang gagal secara default agar kode PHP di bawah dapat diuji tanpa error fatal,
// namun menampilkan pesan error koneksi yang informatif.
global $conn;

// Hapus baris di atas dan ganti dengan:
// include 'config/conn.php';
// global $conn; // Mengasumsikan $conn didefinisikan di conn.php

// --- FUNGSI PENCEGAH UNDEFINED FUNCTION ---

/**
 * Mengembalikan kelas warna label Bootstrap 3 berdasarkan kode status Realisasi.
 * Disesuaikan untuk alur status 0-5.
 */
if (!function_exists('get_status_color')) {
    function get_status_color($status) {
        switch ((int)$status) {
            case 0:
                return 'label-info'; // Draft (Abu-abu)
            case 1:
                return 'label-warning'; // Diajukan (Kuning - Menunggu diproses)
            case 2:
                return 'label-primary'; // Disetujui Keuangan (Biru - Lulus tahap 1)
            case 3:
                return 'label-success'; // Disetujui Rektorat (Hijau - Final)
            case 4:
                return 'label-danger';  // Ditolak (Merah)
            case 5:
                return 'label-success'; // Selesai (Abu-abu - Sudah Final dan Closed)
            default:
                return 'label-default';
        }
    }
}

/**
 * Mengembalikan label status Realisasi yang mudah dibaca.
 * SESUAI DENGAN PERMINTAAN BARU (0-5).
 */
if (!function_exists('get_status_label')) {
    function get_status_label($status) {
        switch ((int)$status) {
            case 0:
                return "0 Draft";
            case 1:
                return "1 Diajukan";
            case 2:
                return "2 Disetujui Keuangan";
            case 3:
                return "3 Disetujui Rektorat";
            case 4:
                return "4 Ditolak";
            case 5:
                return "5 Selesai";
            default:
                return "Tidak Diketahui";
        }
    }
}

// Fungsi format_rupiah()
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        // Anggap saja kita menggunakan fungsi number_format standar PHP
        return number_format($angka, 0, ',', '.');
    }
}

// --- 1. Ambil Data dari Database ---
$query = "
    SELECT 
        r.id_realisasi, 
        r.nomor_dokumen, 
        r.tanggal_realisasi, 
        r.total_realisasi, 
        r.status, 
        r.id_rab,
        rab.judul AS judul_rab
    FROM 
        realisasi r 
    LEFT JOIN 
        rab rab ON r.id_rab = rab.id_rab 
    WHERE 
        r.status >= -1 
    ORDER BY 
        r.tanggal_realisasi DESC
";

$transaksi_list = [];


// Cek ulang ketersediaan koneksi sebelum menjalankan query
// MOCK DATA JIKA KONEKSI GAGAL (Hanya untuk demonstrasi tampilan)
if (isset($conn) && $conn instanceof mysqli) {
    // Logic fetch data yang sebenarnya
    $result = $conn->query($query);
    if ($result) {
        $transaksi_list = $result->fetch_all(MYSQLI_ASSOC);
        $error = null; // Hapus error jika sukses
    } else {
        $error = "Gagal mengambil data transaksi: " . $conn->error;
    }
} else {
    // Jika koneksi gagal, gunakan mock data untuk menunjukkan tampilan tabel
    if (isset($error) && !empty($error)) {
        $transaksi_list = [
            ['id_realisasi' => 101, 'judul_rab' => 'Proyek Digitalisasi Dokumen', 'nomor_dokumen' => 'R001/IV/2025', 'total_realisasi' => 5500000, 'status' => 1],
            ['id_realisasi' => 102, 'judul_rab' => 'Pengadaan Alat Laboratorium', 'nomor_dokumen' => 'R002/IV/2025', 'total_realisasi' => 12800000, 'status' => 2],
            ['id_realisasi' => 103, 'judul_rab' => 'Seminar Nasional Pendidikan', 'nomor_dokumen' => 'R003/IV/2025', 'total_realisasi' => 25000000, 'status' => 3],
            ['id_realisasi' => 104, 'judul_rab' => 'Perbaikan Gedung', 'nomor_dokumen' => 'R004/IV/2025', 'total_realisasi' => 7500000, 'status' => 4],
            ['id_realisasi' => 105, 'judul_rab' => 'Pembayaran Gaji Karyawan', 'nomor_dokumen' => 'R005/IV/2025', 'total_realisasi' => 45000000, 'status' => 5],
            ['id_realisasi' => 106, 'judul_rab' => 'Pengajuan Dana Cepat', 'nomor_dokumen' => 'R006/IV/2025', 'total_realisasi' => 900000, 'status' => 0],
        ];
    }
}
?>

<!-- Menggunakan struktur Bootstrap 3 (container, panel, table, button, label) -->
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Panel Primer BS3 untuk Konten Utama -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-list"></i> Daftar Validasi Realisasi Anggaran
                    </h3>
                </div>
                <div class="panel-body">
                    <!-- Notifikasi & Error Display -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Kesalahan:</strong> <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tabel Daftar Transaksi -->
                    <?php if (!empty($transaksi_list)): ?>
                        <div class="table-responsive">
                            <!-- table-striped untuk estetika, table-hover untuk interaksi -->
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID Realisasi</th>
                                        <th>Judul RAB</th>
                                        <th>No. Dokumen</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_list as $transaksi): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($transaksi['id_realisasi']); ?></td>
                                            <!-- Gunakan null coalescing operator (??) untuk fallback jika judul_rab null -->
                                            <td><?= htmlspecialchars($transaksi['judul_rab'] ?? 'RAB Tidak Ditemukan'); ?></td>
                                            <td><?= htmlspecialchars($transaksi['nomor_dokumen']); ?></td>
                                            
                                            <!-- Panggil format_rupiah() -->
                                            <td class="text-right text-danger">Rp <?= format_rupiah($transaksi['total_realisasi']); ?></td>
                                            
                                            <td class="text-center">
                                                <!-- Panggil get_status_color() dan get_status_label() yang baru -->
                                                <span class="label <?= get_status_color($transaksi['status']); ?>">
                                                    <?= htmlspecialchars(get_status_label($transaksi['status'])); ?>
                                                </span>
                                            </td>
                                            
                                            <td class="text-center">
                                                <!-- Gunakan btn-group untuk menempatkan tombol aksi -->
                                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Transaksi">
                                                    <!-- Tombol Detail (Selalu muncul) -->
                                                    <a href="dashboard.php?page=transaksi_detail.php&id_realisasi=<?= $transaksi['id_realisasi']; ?>" 
                                                        class="btn btn-info" title="Lihat Detail Transaksi">
                                                        <i class="fa fa-eye"></i> Detail
                                                    </a>
                                                    
                                                    <?php 
                                                        // Tombol Validasi diasumsikan muncul pada status 'Diajukan' (1) 
                                                        // atau 'Disetujui Keuangan' (2) tergantung peran pengguna.
                                                        // Di sini, kita pertahankan logika yang sama untuk Status 1 (Diajukan)
                                                        // yang merupakan tahap awal validasi (misalnya oleh Keuangan).
                                                        if ($transaksi['status'] == 1): 
                                                    ?>
                                                        <!-- Tombol Validasi (Hanya muncul jika status = 1) -->
                                                        <a href="dashboard.php?page=transaksi_validation.php&id_realisasi=<?= $transaksi['id_realisasi']; ?>" 
                                                            class="btn btn-success" title="Proses Validasi">
                                                            <i class="fa fa-check-circle"></i> Validasi
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fa fa-inbox fa-3x"></i>
                            <h4>Tidak ada Realisasi Transaksi yang ditemukan.</h4>
                            <p class="text-muted">Semua transaksi realisasi yang relevan akan ditampilkan di sini.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="panel-footer">
                    <!-- Opsional: Footer panel -->
                    <small class="text-muted">Data diambil dari tabel realisasi. Tampilan ini menggunakan mock data jika koneksi database tidak tersedia.</small>
                </div>
            </div>
        </div>
    </div>
</div>