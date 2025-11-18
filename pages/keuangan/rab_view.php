<?php
// FILE: /efinance/pages/keuangan/rab_view.php
// Halaman Detail dan Aksi Persetujuan/Penolakan RAB oleh Direktur Keuangan

// Asumsi: function.php, koneksi database ($conn), dan otorisasi sudah di-include/diproses sebelumnya.

global $conn;

// --- VALIDASI ID RAB DARI URL ---
$id_rab = (int)($_GET['id'] ?? 0);
// Menggunakan fungsi komposit yang baru ditambahkan di function.php
$rab_data = get_rab_data($id_rab); 

if (!$rab_data) {
    $_SESSION['message'] = "RAB dengan ID tersebut tidak ditemukan.";
    $_SESSION['message_type'] = "danger";
    // Kembali ke halaman daftar persetujuan
    redirect_to('dashboard.php?page=rab_approval.php');
}

// ----------------------------------------------------
// LOGIKA PROSES PERSETUJUAN/PENOLAKAN (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = clean_input($_POST['action']);
    $catatan = clean_input($_POST['catatan'] ?? '');
    
    // Periksa status sebelum memproses
    if ($rab_data['status_keuangan'] == 1) { // Hanya proses jika statusnya Menunggu Keuangan
        // Menggunakan fungsi pemrosesan baru dari function.php
        if (process_rab_approval_keuangan($id_rab, $action, $catatan)) {
            // Setelah berhasil, redirect kembali ke daftar approval
            redirect_to('dashboard.php?page=rab_approval.php');
        }
        // Jika gagal, process_rab_approval_keuangan sudah mengatur $_SESSION['message']
        // Redirect kembali ke halaman ini untuk menampilkan pesan error.
        redirect_to('dashboard.php?page=rab_view.php&id=' . $id_rab . '&role=keuangan');
        
    } else {
        $_SESSION['message'] = "RAB ini sudah diproses dan tidak bisa diubah lagi.";
        $_SESSION['message_type'] = "warning";
        // Redirect untuk refresh dan membersihkan POST
        redirect_to('dashboard.php?page=rab_view.php&id=' . $id_rab . '&role=keuangan'); 
    }
}

// Ambil Nama Departemen untuk ditampilkan di header
$nama_departemen = get_nama_departemen($rab_data['id_departemen']) ?? 'N/A';

// Cek apakah RAB sedang menunggu persetujuan Keuangan (status_keuangan = 1)
$is_pending = ($rab_data['status_keuangan'] == 1); 

// Tentukan Breadcrumb kembali ke dashboard Keuangan
$keuangan_dashboard = 'dashboard.php?page=dashboard_keu.php'; 
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-search"></span> Tinjau RAB: **RAB-<?= $id_rab; ?>**
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $keuangan_dashboard; ?>"><i class="fa fa-dashboard"></i> Dashboard Keuangan</a></li>
            <!-- Mengarahkan ke halaman daftar persetujuan yang baru kita sepakati -->
            <li><a href="dashboard.php?page=rab_approval.php">Persetujuan RAB</a></li>
            <li class="active">Tinjau RAB</li>
        </ol>
    </div>
</div>

<?php 
// Menampilkan pesan sesi (dari redirect setelah POST atau validasi)
if (isset($_SESSION['message'])): 
    // Menggunakan ternary operator untuk menentukan kelas alert
    $alert_class = ($_SESSION['message_type'] == 'success') ? 'alert-success' : 
                   (($_SESSION['message_type'] == 'danger') ? 'alert-danger' : 'alert-warning');
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
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-info-sign"></i> Ringkasan RAB</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Dari Departemen:</strong> <?= htmlspecialchars($nama_departemen); ?></p>
                        <p><strong>Judul RAB:</strong> <?= htmlspecialchars($rab_data['judul']); ?></p>
                        <p><strong>Tahun Anggaran:</strong> <?= htmlspecialchars($rab_data['tahun_anggaran']); ?></p>
                    </div>
                    <div class="col-md-6 text-right">
                        <!-- Menggunakan fungsi get_rab_status_label() dari function.php -->
                        <p><strong>Status Saat Ini:</strong> <?= get_rab_status_label($rab_data['status_keuangan'], $rab_data['status_rektorat']); ?></p>
                        <!-- Menggunakan fungsi format_rupiah() dari function.php -->
                        <p><strong>Total Anggaran:</strong> <span class="lead text-primary"><?= format_rupiah($rab_data['total_anggaran']); ?></span></p>
                    </div>
                </div>
                <hr>
                <p><strong>Deskripsi:</strong> <?= nl2br(htmlspecialchars($rab_data['deskripsi'])); ?></p>
            </div>
        </div>

        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Detail Rencana Anggaran Biaya</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kode Akun</th>
                                <th width="35%">Uraian Kegiatan</th>
                                <th width="10%">Volume</th>
                                <th width="10%">Satuan</th>
                                <th width="15%" class="text-right">Harga Satuan</th>
                                <th width="10%" class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php if (empty($rab_data['details'])): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Detail RAB tidak ditemukan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rab_data['details'] as $detail): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <!-- Pastikan kode_akun diambil dari JOIN di get_rab_details_by_rab_id() -->
                                    <td><?= htmlspecialchars($detail['kode_akun']); ?></td>
                                    <td><?= htmlspecialchars($detail['uraian']); ?></td>
                                    <td><?= number_format($detail['volume'], 2, ',', '.'); ?></td>
                                    <td><?= htmlspecialchars($detail['satuan']); ?></td>
                                    <!-- Menggunakan format_rupiah() -->
                                    <td class="text-right"><?= format_rupiah($detail['harga_satuan']); ?></td>
                                    <td class="text-right"><?= format_rupiah($detail['subtotal']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-right"><strong>TOTAL KESELURUHAN:</strong></td>
                                <!-- Menggunakan format_rupiah() -->
                                <td class="text-right"><strong><?= format_rupiah($rab_data['total_anggaran']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-gavel"></i> Tindak Lanjut Direktur Keuangan</h3>
            </div>
            <div class="panel-body">
                <?php if ($is_pending): ?>
                <!-- Form diarahkan ke halaman yang sama untuk memproses POST -->
                <form action="dashboard.php?page=rab_view.php&id=<?= $id_rab; ?>&role=keuangan" method="POST">
                    <div class="form-group">
                        <label for="catatan">Catatan/Komentar Keuangan (Wajib diisi jika Ditolak)</label>
                        <!-- Mengisi ulang catatan jika ada error validasi POST -->
                        <textarea name="catatan" id="catatan" class="form-control" rows="3" placeholder="Tuliskan alasan persetujuan/penolakan..."><?= htmlspecialchars($_POST['catatan'] ?? $rab_data['catatan_keuangan'] ?? ''); ?></textarea>
                    </div>

                    <p class="text-right">
                        <button type="submit" name="action" value="reject" class="btn btn-danger" 
                            onclick="return confirm('Anda yakin menolak RAB ini? Catatan penolakan sangat disarankan dan wajib jika alasan penolakan serius.');">
                            <i class="glyphicon glyphicon-remove"></i> Tolak RAB
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-success" 
                            onclick="return confirm('Anda yakin menyetujui RAB ini? RAB akan otomatis diteruskan ke Rektorat.');">
                            <i class="glyphicon glyphicon-ok"></i> Setujui & Lanjutkan ke Rektorat
                        </button>
                    </p>
                </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        **Status Saat Ini:** RAB ini sudah selesai diproses oleh Keuangan.<br>
                        <?php if (!empty($rab_data['catatan_keuangan'])): ?>
                            **Catatan Keuangan:** <?= nl2br(htmlspecialchars($rab_data['catatan_keuangan'])); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>