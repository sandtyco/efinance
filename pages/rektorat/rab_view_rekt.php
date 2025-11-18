<?php
// FILE: /efinance/pages/rektorat/rab_view_rekt.php
// Halaman untuk melihat detail RAB oleh Rektorat (Role ID 4).
include 'config/conn.php'; 
include_once 'function.php'; 

global $conn;

// Pastikan ID RAB ada
$id_rab = (int)($_GET['id'] ?? 0);
if ($id_rab == 0) {
    echo "<div class='alert alert-danger'>ID RAB tidak ditemukan.</div>";
    exit;
}

// Ambil data RAB Header
$sql_rab = "SELECT 
    r.*, 
    d.nama_departemen, 
    du.nama_lengkap AS nama_pengaju 
    FROM rab r
    JOIN departemen d ON r.id_departemen = d.id_departemen
    /* Step 1: Join Detail User ke Departemen */
    JOIN detail_user du ON d.id_departemen = du.id_departemen 
    /* Step 2: Join Detail User ke User (Diperlukan hanya untuk memastikan user ada, tapi kita filter role di detail_user) */
    JOIN user u ON du.id_user = u.id_user 
    /* Filter: Pengguna yang mengajukan RAB adalah dari departemen yang sama DAN punya role Departemen (id_role = 2) */
    WHERE r.id_rab = {$id_rab} 
    AND du.id_role = 2"; // <-- KOREKSI KRITIS: Menggunakan du.id_role BUKAN u.id_role

$result_rab = mysqli_query($conn, $sql_rab);

// Cek apakah query gagal
if (!$result_rab) {
    // Tampilkan error SQL yang sebenarnya dan hentikan eksekusi
    echo "<div class='alert alert-danger'>Kesalahan Fatal pada Query RAB Header!</div>";
    echo "<p>Pesan Error: " . mysqli_error($conn) . "</p>";
    echo "<p>Query Gagal: <code>" . htmlspecialchars($sql_rab) . "</code></p>";
    exit;
}

$rab = mysqli_fetch_assoc($result_rab); // Line 25 sekarang menjadi aman

if (!$rab) {
    // Jika query berhasil tapi tidak ada data (RAB tidak ditemukan)
    echo "<div class='alert alert-danger'>RAB dengan ID #{$id_rab} tidak ditemukan atau belum disetujui Keuangan.</div>";
    exit;
}

// --- 2. Ambil data RAB Detail ---
$sql_detail = "SELECT rd.*, a.kode_akun, a.nama_akun 
               FROM rab_detail rd
               JOIN akun a ON rd.id_akun = a.id_akun
               WHERE rd.id_rab = {$id_rab}
               ORDER BY a.kode_akun ASC";
$result_detail = mysqli_query($conn, $sql_detail);
$details = [];
if ($result_detail) {
    while ($row = mysqli_fetch_assoc($result_detail)) {
        $details[] = $row;
    }
} else {
    // Penanganan jika query detail gagal
    echo "<div class='alert alert-danger'>Kesalahan saat mengambil Detail RAB: " . mysqli_error($conn) . "</div>";
    exit;
}

// Tentukan Breadcrumb
$dashboard_link = 'dashboard.php?page=dashboard_rekt.php'; // Link khusus Rektorat

?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-eye-open"></span> Tinjauan RAB untuk Persetujuan Final
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $dashboard_link; ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="dashboard.php?page=rab_approval_rekt.php">Persetujuan</a></li>
            <li class="active">Detail RAB #<?= $rab['id_rab']; ?></li>
        </ol>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-info-sign"></i> Ringkasan RAB</h3>
            </div>
            <div class="panel-body">
                <div class="col-md-6">
                    <table class="table table-striped table-bordered">
                        <tr><th>ID RAB</th><td><?= htmlspecialchars($rab['id_rab']); ?></td></tr>
                        <tr><th>Judul Anggaran</th><td><strong><?= htmlspecialchars($rab['judul']); ?></strong></td></tr>
                        <tr><th>Departemen Pengaju</th><td><?= htmlspecialchars($rab['nama_departemen']); ?></td></tr>
                        <tr><th>Pengaju</th><td><?= htmlspecialchars($rab['nama_pengaju']); ?></td></tr>
                        <tr><th>Tahun Anggaran</th><td><?= htmlspecialchars($rab['tahun_anggaran']); ?></td></tr>
                        <tr><th>Tanggal Pengajuan</th><td><?= date('d-m-Y', strtotime($rab['tanggal_pengajuan'])); ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-striped table-bordered">
                        <tr><th>Total Biaya</th><td class="text-right"><h3 style="margin: 0;"><strong><?= format_rupiah($rab['total_anggaran']); ?></strong></h3></td></tr>
                        <tr><th>Deskripsi</th><td><?= nl2br(htmlspecialchars($rab['deskripsi'])); ?></td></tr>
                        <tr><th>Status Terkini</th><td><?= get_rab_status_label($rab['status_keuangan'], $rab['status_rektorat']); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading bg-success" style="background-color: #dff0d8; border-color: #d6e9c6;">
                <h3 class="panel-title" style="font-weight: bold;"><i class="glyphicon glyphicon-ok-circle"></i> Catatan Direktur Keuangan</h3>
            </div>
            <div class="panel-body">
                <?php if ($rab['status_keuangan'] == 3): ?>
                    <p class="text-success">Status: <strong>Telah Disetujui Direktur Keuangan.</strong></p>
                <?php elseif ($rab['status_keuangan'] == 2): ?>
                    <p class="text-danger">Status: <strong>Ditolak Keuangan (Menunggu Revisi).</strong></p>
                <?php else: ?>
                    <p>Status: <strong>Menunggu Persetujuan.</strong></p>
                <?php endif; ?>
                
                <p>Tanggal Proses: <?= $rab['tanggal_persetujuan_keuangan'] ? date('d-m-Y H:i', strtotime($rab['tanggal_persetujuan_keuangan'])) : '-'; ?></p>
                
                <h4 style="margin-top: 15px;">Catatan Persetujuan/Penolakan Keuangan:</h4>
                <div style="border-left: 3px solid #ccc; padding-left: 10px; margin-top: 10px;">
                    <i><?= nl2br(htmlspecialchars($rab['catatan_keuangan'] ?? 'Tidak ada catatan dari Direktur Keuangan.')); ?></i>
                </div>
            </div>
        </div>
    </div>

    <?php if ($rab['status_keuangan'] == 4 || $rab['status_rektorat'] == 1): // Jika sudah diproses Rektorat (Ditolak/Final) ?>
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-tag"></i> Keputusan Rektorat Sebelumnya</h3>
            </div>
            <div class="panel-body">
                <p>Status: 
                    <?php 
                        if ($rab['status_keuangan'] == 5 && $rab['status_rektorat'] == 1) {
                            echo '<strong class="text-success">Disetujui FINAL</strong>';
                        } elseif ($rab['status_keuangan'] == 4) {
                            echo '<strong class="text-warning">Ditolak Rektorat</strong>';
                        } else {
                            echo 'Belum diproses.';
                        }
                    ?>
                </p>
                <p>Tanggal Proses: <?= $rab['tanggal_persetujuan_rektorat'] ? date('d-m-Y H:i', strtotime($rab['tanggal_persetujuan_rektorat'])) : '-'; ?></p>
                
                <h4 style="margin-top: 15px;">Catatan Rektorat:</h4>
                <div style="border-left: 3px solid #ccc; padding-left: 10px; margin-top: 10px;">
                    <i><?= nl2br(htmlspecialchars($rab['catatan_rektorat'] ?? 'Tidak ada catatan Rektorat.')); ?></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-table"></i> Detail Item Anggaran</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Akun</th>
                                <th>Nama Akun</th>
                                <th>Uraian</th>
                                <th class="text-right">Volume</th>
                                <th>Satuan</th>
                                <th class="text-right">Harga Satuan</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $grand_total = 0;
                            foreach ($details as $detail): 
                                $grand_total += $detail['subtotal'];
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($detail['kode_akun']); ?></td>
                                    <td><?= htmlspecialchars($detail['nama_akun']); ?></td>
                                    <td><?= htmlspecialchars($detail['uraian']); ?></td>
                                    <td class="text-right"><?= number_format($detail['volume'], 2, ',', '.'); ?></td>
                                    <td><?= htmlspecialchars($detail['satuan']); ?></td>
                                    <td class="text-right"><?= format_rupiah($detail['harga_satuan']); ?></td>
                                    <td class="text-right"><strong><?= format_rupiah($detail['subtotal']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="7" class="text-right"><strong>TOTAL KESELURUHAN</strong></td>
                                <td class="text-right"><strong><?= format_rupiah($grand_total); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-right" style="margin-top: 20px;">
                    <a href="dashboard.php?page=rab_approval_rekt.php" class="btn btn-warning"><i class="fa fa-arrow-left"></i> Kembali ke Daftar Persetujuan</a>
                </div>
            </div>
        </div>
    </div>
</div>