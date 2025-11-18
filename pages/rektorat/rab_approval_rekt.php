<?php
// FILE: /efinance/pages/rektorat/rab_approval_rekt.php
// Halaman untuk Persetujuan RAB oleh Rektorat (Role ID 4)

// Mulai sesi jika belum dimulai (asumsi dilakukan di file utama)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'config/conn.php'; 
include_once 'function.php'; 

global $conn;

// Pastikan hanya Rektorat yang bisa mengakses (ID Role 4)
if (!isset($_SESSION['id_role']) || $_SESSION['id_role'] != 4) {
    echo "<div class='alert alert-danger'>Akses ditolak. Anda tidak memiliki hak akses untuk halaman ini.</div>";
    exit;
}

// Tentukan filter status: status_keuangan = 3 (Disetujui Keuangan)
$filter_status_keuangan = 3;
$rab_list_rektorat = [];

// --- 1. Ambil Data RAB yang sudah lolos Keuangan (Menunggu/Sudah Diproses Rektorat) ---
// Data yang diambil mencakup semua RAB yang sudah disetujui Keuangan, 
// terlepas dari status final Rektorat (0=Pending, 1=Approved, 2=Rejected)
$sql_rab = "SELECT 
    r.id_rab, r.judul, r.tahun_anggaran, r.total_anggaran, r.tanggal_pengajuan,
    d.nama_departemen,
    r.catatan_keuangan,
    r.status_rektorat,      /* Status final Rektorat (0, 1, atau 2) */
    r.catatan_rektorat      /* Catatan final dari Rektorat */
FROM rab r
JOIN departemen d ON r.id_departemen = d.id_departemen
WHERE r.status_keuangan = {$filter_status_keuangan} 
AND (r.status_rektorat = 0 OR r.status_rektorat = 1 OR r.status_rektorat = 2) /* Menampilkan Pending, Approved, dan Rejected */
ORDER BY r.tanggal_pengajuan DESC"; // Urutkan berdasarkan tanggal terbaru

$result_rab = mysqli_query($conn, $sql_rab);

if ($result_rab) {
    while ($row = mysqli_fetch_assoc($result_rab)) {
        $rab_list_rektorat[] = $row;
    }
    mysqli_free_result($result_rab);
}


// --- 2. Logika Pemrosesan Form Persetujuan/Penolakan (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rab_post = (int)($_POST['id_rab'] ?? 0);
    $action = clean_input($_POST['action'] ?? ''); // 'approve' atau 'reject'
    $catatan = clean_input($_POST['catatan'] ?? ''); // Hanya relevan untuk 'reject'

    if ($id_rab_post > 0) {
        // Panggil fungsi persetujuan/penolakan Rektorat dari function.php
        if ($action === 'approve') {
            // Status 1 = Setuju Final
            approve_rab_rektorat($id_rab_post, 1, ''); 
        } elseif ($action === 'reject') {
            // Status 2 = Ditolak / Kembali untuk Revisi (Menggunakan 2 agar tidak ambigu dengan status Pending = 0)
            approve_rab_rektorat($id_rab_post, 2, $catatan); 
        }
        
        // Cek pesan status dari fungsi (Diasumsikan fungsi 'approve_rab_rektorat' mengisi $_SESSION['message'])
        if (!isset($_SESSION['message'])) {
             $_SESSION['message'] = "Proses RAB ID {$id_rab_post} berhasil.";
             $_SESSION['message_type'] = "success";
        }
        
    } else {
        $_SESSION['message'] = "ID RAB tidak valid.";
        $_SESSION['message_type'] = "danger";
    }

    // Redirect untuk menghindari resubmission form
    redirect_to('dashboard.php?page=rab_approval_rekt.php');
}

// Tentukan Breadcrumb
$rektorat_dashboard = 'dashboard.php?page=dashboard_rekt.php'; 
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-ok-sign"></span> Persetujuan Anggaran Rektorat
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $rektorat_dashboard; ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li class="active">Persetujuan RAB</li>
        </ol>
    </div>
</div>

<?php 
if (isset($_SESSION['message'])): 
    $alert_class = ($_SESSION['message_type'] == 'success') ? 'alert-success' : (($_SESSION['message_type'] == 'warning') ? 'alert-warning' : 'alert-danger');
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
                <h3 class="panel-title"><i class="fa fa-list"></i> Daftar RAB untuk Tinjauan Rektorat</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID RAB</th>
                                <th>Judul RAB</th>
                                <th>Departemen</th>
                                <th>Tahun</th>
                                <th>Tanggal Pengajuan</th>
                                <th class="text-right">Total Anggaran</th>
                                <th>Catatan Keuangan</th>
                                <th>Status Rektorat</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rab_list_rektorat)): ?>
                                <?php foreach ($rab_list_rektorat as $rab): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rab['id_rab']); ?></td>
                                        <td><?= htmlspecialchars($rab['judul']); ?></td>
                                        <td><?= htmlspecialchars($rab['nama_departemen']); ?></td>
                                        <td><?= htmlspecialchars($rab['tahun_anggaran']); ?></td>
                                        <td><?= date('d-m-Y', strtotime($rab['tanggal_pengajuan'])); ?></td>
                                        <td class="text-right"><strong><?= format_rupiah($rab['total_anggaran']); ?></strong></td>
                                        <td><?= nl2br(htmlspecialchars($rab['catatan_keuangan'])); ?></td>
                                        
                                        <!-- KOLOM STATUS REKTORAT -->
                                        <td>
                                            <?php if ($rab['status_rektorat'] == 0): ?>
                                                <span class="label label-warning">Menunggu Keputusan</span>
                                            <?php elseif ($rab['status_rektorat'] == 1): ?>
                                                <span class="label label-success">Disetujui Final</span>
                                                <?php if (!empty($rab['catatan_rektorat'])): ?><br><small>Catatan: <?= htmlspecialchars($rab['catatan_rektorat']); ?></small><?php endif; ?>
                                            <?php elseif ($rab['status_rektorat'] == 2): ?>
                                                <span class="label label-danger">Ditolak / Revisi</span>
                                                <?php if (!empty($rab['catatan_rektorat'])): ?><br><small>Catatan: <?= htmlspecialchars($rab['catatan_rektorat']); ?></small><?php endif; ?>
                                            <?php else: ?>
                                                 <span class="label label-default">Status Tidak Dikenal</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- KOLOM AKSI REKTORAT -->
                                        <td class="text-center">
                                            <a href="dashboard.php?page=rab_view_rekt.php&id=<?= $rab['id_rab']; ?>" class="btn btn-info btn-xs" title="Lihat Detail"><i class="glyphicon glyphicon-search"></i> Lihat</a>
                                            
                                            <?php if ($rab['status_rektorat'] == 0): ?>
                                                <!-- Aksi hanya muncul jika status Menunggu Keputusan (0) -->
                                                <button type="button" class="btn btn-success btn-xs btn-approve" onclick="confirmApprove(<?= $rab['id_rab']; ?>)">
                                                    <i class="glyphicon glyphicon-check"></i> Setujui
                                                </button>
                                                <button type="button" class="btn btn-danger btn-xs btn-reject" onclick="openRejectModal(<?= $rab['id_rab']; ?>)">
                                                    <i class="glyphicon glyphicon-remove"></i> Tolak
                                                </button>
                                            <?php else: ?>
                                                <!-- Jika sudah diproses, tampilkan info selesai -->
                                                <span class="text-muted"><i class="glyphicon glyphicon-lock"></i> Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">Tidak ada RAB yang menunggu atau telah diproses Final oleh Rektorat.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="dashboard.php?page=rab_approval_rekt.php">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="rejectModalLabel">Tolak Persetujuan RAB</h4>
                </div>
                <div class="modal-body">
                    <p>Masukkan catatan penolakan Rektorat untuk RAB ID: <strong id="modal_rab_id_display"></strong>. RAB akan dikembalikan ke Departemen untuk direvisi.</p>
                    <div class="form-group">
                        <label for="catatan">Catatan Penolakan Wajib Diisi:</label>
                        <textarea class="form-control" name="catatan" id="catatan" rows="4" required></textarea>
                    </div>
                    <input type="hidden" name="id_rab" id="modal_id_rab_reject">
                    <input type="hidden" name="action" value="reject">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak RAB</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" action="dashboard.php?page=rab_approval_rekt.php" id="approveForm">
    <input type="hidden" name="id_rab" id="approve_id_rab">
    <input type="hidden" name="action" value="approve">
</form>

<script>
    // Fungsi untuk menampilkan Modal Penolakan
    function openRejectModal(id_rab) {
        document.getElementById('modal_id_rab_reject').value = id_rab;
        document.getElementById('modal_rab_id_display').innerText = id_rab;
        document.getElementById('catatan').value = ''; // Reset catatan setiap kali modal dibuka
        // Menggunakan jQuery untuk Bootstrap Modal
        $('#rejectModal').modal('show');
    }

    // Fungsi untuk Konfirmasi dan Submit Persetujuan
    function confirmApprove(id_rab) {
        // Mengganti alert() menjadi custom modal atau prompt/confirm biasa yang diizinkan di beberapa environment
        if (confirm("Anda yakin ingin MENYETUJUI FINAL RAB ID " + id_rab + "? Persetujuan ini bersifat final dan tidak dapat diubah.")) {
            document.getElementById('approve_id_rab').value = id_rab;
            document.getElementById('approveForm').submit();
        }
    }
</script>