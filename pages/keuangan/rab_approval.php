<?php
// FILE: pages/keuangan/rab_approval.php
// Halaman Daftar RAB yang Menunggu Persetujuan Direktur Keuangan

global $conn;

// --- FUNGSI PENGAMBIL DATA KHUSUS KEHILANGAN ---
// Fungsi ini harus didefinisikan di function.php
// Mengambil RAB yang status_keuangan = 1 (Menunggu Persetujuan Keuangan)
function get_rab_for_keuangan_approval() {
    global $conn;
    // Asumsi: $conn sudah terdefinisi dan terhubung ke database.

    // Pastikan tabel departemen di-JOIN untuk menampilkan nama departemen
    $sql = "SELECT r.*, d.nama_departemen 
            FROM rab r
            JOIN departemen d ON r.id_departemen = d.id_departemen
            WHERE r.status_keuangan = 1
            ORDER BY r.tanggal_pengajuan ASC";

    $result = mysqli_query($conn, $sql);
    $rabs = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rabs[] = $row;
        }
    }
    return $rabs;
}

$list_rab = get_rab_for_keuangan_approval();

// Array untuk menampung script yang akan dieksekusi di bagian footer (DataTables)
if (!isset($footer_scripts)) {
    $footer_scripts = [];
}

$footer_scripts[] = "
<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    $('#dataTable').DataTable({
        \"language\": {
            \"url\": \"//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json\"
        },
        \"order\": [[ 5, \"asc\" ]] // Urutkan berdasarkan tanggal pengajuan
    });
});
</script>";
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-check"></span> Daftar Persetujuan RAB - Direktur Keuangan
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php?page=dashboard_keu.php"><i class="fa fa-dashboard"></i> Dashboard Keuangan</a></li>
            <li class="active">Daftar Persetujuan RAB</li>
        </ol>
    </div>
</div>

<?php
// Tampilkan pesan sesi (jika ada, misalnya setelah aksi persetujuan/penolakan dari rab_view.php)
if (isset($_SESSION['message'])):
    $alert_class = ($_SESSION['message_type'] == 'success') ? 'alert-success' : 'alert-danger';
?>
<div class="alert <?= $alert_class; ?> alert-dismissible" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <?= $_SESSION['message']; ?>
</div>
<?php
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
endif;
?>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-primary">
            <div class="panel-heading">
                RAB Menunggu Review Keuangan (Status: Menunggu Persetujuan Keuangan)
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="25%">Judul RAB</th>
                                <th width="20%">Departemen Pengaju</th>
                                <th width="15%">Total Anggaran</th>
                                <th width="15%">Tanggal Pengajuan</th>
                                <th width="10%">Status</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php if (empty($list_rab)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada RAB yang menunggu persetujuan Direktur Keuangan saat ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($list_rab as $rab): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($rab['judul']); ?></td>
                                    <td><?= htmlspecialchars($rab['nama_departemen']); ?></td>
                                    <td><?= format_rupiah($rab['total_anggaran']); // Pastikan format_rupiah() tersedia ?></td>
                                    <td><?= date('d M Y', strtotime($rab['tanggal_pengajuan'])); ?></td>
                                    <td>
                                        <span class="label label-warning">Menunggu Keuangan</span>
                                    </td>
                                    <td>
                                        <!-- Link ke halaman detail/approval Keuangan: rab_view.php -->
                                        <a href="dashboard.php?page=rab_view.php&id=<?= $rab['id_rab']; ?>&role=keuangan" class="btn btn-success btn-xs" title="Review dan Proses Persetujuan">
                                            <i class="glyphicon glyphicon-edit"></i> Proses
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>