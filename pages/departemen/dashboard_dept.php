<?php
// FILE: /efinance/pages/departemen/dashboard_dept.php

// PENTING: Variabel koneksi $conn harus diakses secara global
global $conn;

// Ambil data user dari session
$nama_user = $_SESSION['nama'] ?? 'User';
$id_departemen = $_SESSION['id_departemen'] ?? 0;

// **********************************************
// BARIS PERBAIKAN: Ambil nama departemen
// **********************************************
$nama_departemen = get_nama_departemen($id_departemen);
// Pastikan nama departemen tidak null
$nama_departemen = $nama_departemen ?? 'Departemen Tidak Dikenal';

// =========================================================
// 1. STATISTIK RENCANA ANGGARAN BIAYA (RAB)
// =========================================================
// Logika Status:
// - Draft: status_keuangan NULL atau 0
// - Menunggu Keuangan: status_keuangan = 1
// - Disetujui Final: status_keuangan = 3 (Setuju Keu) DAN status_rektorat = 2 (Setuju Rekt)

$sql_summary_rab = "SELECT 
                    COUNT(CASE WHEN status_keuangan IS NULL OR status_keuangan = 0 THEN 1 END) AS total_draft,
                    COUNT(CASE WHEN status_keuangan = 1 THEN 1 END) AS menunggu_keu,
                    COUNT(CASE WHEN status_keuangan = 3 AND status_rektorat = 2 THEN 1 END) AS disetujui_final
                FROM rab 
                WHERE id_departemen = '{$id_departemen}'"; 

$query_summary_rab = mysqli_query($conn, $sql_summary_rab);

if (!$query_summary_rab) {
    echo '<div class="alert alert-danger">Error Query Statistik RAB: ' . mysqli_error($conn) . '</div>';
    $summary_rab = ['total_draft' => 0, 'menunggu_keu' => 0, 'disetujui_final' => 0];
} else {
    $summary_rab = mysqli_fetch_assoc($query_summary_rab);
}

// =========================================================
// 2. STATISTIK REALISASI TRANSAKSI
// =========================================================
// ASUMSI: Tabel 'realisasi' menggunakan kolom status tunggal. 
// Sesuaikan query di bawah ini jika 'transaksi' juga menggunakan status_keuangan/rektorat.
$sql_summary_transaksi = "SELECT 
                    COUNT(CASE WHEN status = 0 THEN 1 END) AS transaksi_draft,
                    COUNT(CASE WHEN status = 1 THEN 1 END) AS transaksi_menunggu,
                    COUNT(CASE WHEN status = 5 THEN 1 END) AS transaksi_disetujui
                FROM realisasi 
                WHERE id_departemen = '{$id_departemen}'"; 

$query_summary_transaksi = mysqli_query($conn, $sql_summary_transaksi);

if (!$query_summary_transaksi) {
    echo '<div class="alert alert-danger">Error Query Statistik Transaksi: ' . mysqli_error($conn) . '</div>';
    $summary_transaksi = ['transaksi_draft' => 0, 'transaksi_menunggu' => 0, 'transaksi_disetujui' => 0];
} else {
    $summary_transaksi = mysqli_fetch_assoc($query_summary_transaksi);
}

?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-home"></span> Dashboard Departemen
        </h1>
        <ol class="breadcrumb">
            <li class="active"><i class="fa fa-dashboard"></i> Selamat Datang, <b><?php echo htmlspecialchars($nama_user); ?></b> Anda login dari Departemen  <b><?php echo htmlspecialchars($nama_departemen); ?></b></li>
        </ol>
    </div>
</div>

📅 Ringkasan RAB (Rencana Anggaran Biaya)

<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center"><span class="glyphicon glyphicon-folder-open" style="font-size: 3em;"></span></div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $summary_rab['total_draft']; ?></div>
                        <div>RAB Masih Draft</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=rab_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat & Edit Draft RAB</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center"><span class="glyphicon glyphicon-time" style="font-size: 3em;"></span></div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $summary_rab['menunggu_keu']; ?></div>
                        <div>Menunggu Persetujuan Keuangan</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=rab_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Cek Status Pengajuan</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-success">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center"><span class="glyphicon glyphicon-ok" style="font-size: 3em;"></span></div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $summary_rab['disetujui_final']; ?></div>
                        <div>RAB Disetujui Final</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=laporan_rab.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat Laporan RAB</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
</div>

🛒 Ringkasan Realisasi (Transaksi)

<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center"><span class="glyphicon glyphicon-usd" style="font-size: 3em;"></span></div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $summary_transaksi['transaksi_draft']; ?></div>
                        <div>Transaksi Masih Draft</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=transaksi_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Input Realisasi Baru</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center"><span class="glyphicon glyphicon-time" style="font-size: 3em;"></span></div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $summary_transaksi['transaksi_menunggu']; ?></div>
                        <div>Menunggu Validasi Keuangan</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=transaksi_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Cek Status Realisasi</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-success">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center"><span class="glyphicon glyphicon-check" style="font-size: 3em;"></span></div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $summary_transaksi['transaksi_disetujui']; ?></div>
                        <div>Realisasi Telah Divalidasi</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=transaksi_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat Riwayat Transaksi</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
</div>

ℹ️ Informasi & Panduan

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-info-sign"></i> Panduan Singkat Departemen</h3>
            </div>
            <div class="panel-body">
                <p>Fokus utama Anda adalah mengajukan Rencana Anggaran Biaya (RAB) dan mencatat realisasi pengeluaran (Transaksi).</p>
                <ul>
                    <li>Gunakan menu Perencanaan (RAB) untuk membuat dan mengajukan anggaran tahunan atau periode.</li>
                    <li>Gunakan menu Realisasi Transaksi untuk mencatat setiap pengeluaran yang menggunakan anggaran yang telah disetujui.</li>
                    <li>Pastikan RAB Anda sudah berstatus Disetujui Final sebelum melakukan pengeluaran.</li>
                </ul>
            </div>
        </div>
    </div>
</div>