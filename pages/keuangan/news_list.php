<?php
// FILE: pages/sysadmin/news_list.php
// Halaman untuk menampilkan daftar pengumuman, kini dengan nomor urut.

include 'config/conn.php'; 
include_once 'function.php'; 

global $footer_scripts;

// Cek Hak Akses
$allowed_roles = ['Direktur Keuangan'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    redirect_to('dashboard.php'); 
}

$root_path = ''; 
$upload_dir = 'assets/uploads/'; 

// ----------------------------------------------------
// LOGIKA CRUD: DELETE PENGUMUMAN
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if ($_SESSION['role'] !== 'Direktur Keuangan') {
        $_SESSION['flash_message'] = "error|Aksi hapus hanya dapat dilakukan oleh Direktur Keuangan.";
        redirect_to('dashboard.php?page=news_list.php');
        exit;
    }
    
    // MENGGUNAKAN id_pengumuman
    $id_pengumuman_del = mysqli_real_escape_string($conn, $_GET['id']);

    // 1. Ambil nama file lampiran untuk dihapus dari server
    $get_file_query = "SELECT file_lampiran FROM pengumuman WHERE id_pengumuman = '$id_pengumuman_del'";
    $file_result = mysqli_query($conn, $get_file_query);
    $file_data = mysqli_fetch_assoc($file_result);
    $old_file_name = $file_data['file_lampiran'] ?? null;

    mysqli_begin_transaction($conn);

    try {
        // 2. Hapus data dari database
        $delete_query = "DELETE FROM pengumuman WHERE id_pengumuman = '$id_pengumuman_del'";
        if (!mysqli_query($conn, $delete_query)) {
            throw new Exception("Gagal menghapus data pengumuman dari database.");
        }

        // 3. Hapus file fisik
        if ($old_file_name && file_exists($upload_dir . $old_file_name)) {
            unlink($upload_dir . $old_file_name);
        }

        mysqli_commit($conn);
        $_SESSION['flash_message'] = "success|Pengumuman berhasil dihapus!";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = "error|Gagal menghapus pengumuman: " . $e->getMessage();
    }

    redirect_to('dashboard.php?page=news_list.php'); 
}

// ----------------------------------------------------
// LOGIKA READ: Ambil semua data pengumuman
// ----------------------------------------------------
$query_list = "
SELECT 
    p.id_pengumuman,      
    p.judul, 
    p.isi,                
    p.file_lampiran,
    p.tgl_dibuat,         
    p.id_user_pembuat,    
    du.nama_lengkap AS pembuat
FROM 
    pengumuman p
LEFT JOIN 
    user u ON p.id_user_pembuat = u.id_user 
LEFT JOIN
    detail_user du ON u.id_user = du.id_user
ORDER BY 
    p.tgl_dibuat DESC";   // PENGURUTAN BERDASARKAN TANGGAL TERBARU

$result_list = mysqli_query($conn, $query_list);

// Cek kegagalan query 
if (!$result_list) {
    $error_msg = "Query Gagal! Pastikan tabel `pengumuman` dan kolom yang diperlukan sudah ada. MySQL Error: " . mysqli_error($conn);
    $_SESSION['flash_message'] = "error|" . $error_msg;
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <i class="glyphicon glyphicon-comment"></i> Papan Informasi
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li class="active">Daftar Pengumuman</li>
        </ol>
    </div>
</div>

<?php 
// Tampilkan pesan flash jika ada
if (isset($_SESSION['flash_message'])) {
    list($type, $message) = explode('|', $_SESSION['flash_message'], 2);
    echo '<div class="alert alert-' . ($type == 'success' ? 'success' : 'danger') . ' alert-dismissible" role="alert">';
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo $message;
    echo '</div>';
    
    // Fix Kompatibilitas PHP (mengganti str_contains menjadi strpos)
    if ($type !== 'error' || strpos($message, 'Query Gagal!') === false) { 
        unset($_SESSION['flash_message']);
    }
}
?>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-list"></i> Daftar Pengumuman</h3>
            </div>
            <div class="panel-body">

                <!-- Tombol Tambah hanya untuk Direktur Keuangan -->
                <?php if ($_SESSION['role'] === 'Direktur Keuangan'): ?>
                <div class="text-right" style="margin-bottom: 15px;">
                    <a href="dashboard.php?page=news_add.php" class="btn btn-primary">
                        <span class="fa fa-plus"></span> Tambah Pengumuman Baru
                    </a>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped" id="newsTable">
                    <thead>
                        <tr>
                            <!-- KOLOM BARU UNTUK NOMOR URUT -->
                            <th class="text-center" width="5%">No.</th> 
                            <th>Judul</th>
                            <th width="30%">Isi Singkat</th>
                            <th width="12%">Lampiran</th>
                            <th width="15%">Info Posting</th>
                            <th class="text-center" width="12%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_list && mysqli_num_rows($result_list) > 0):
                            $no = 1; // INISIALISASI NOMOR URUT
                            while($data = mysqli_fetch_assoc($result_list)): 
                                // Konten dipotong untuk tampilan tabel
                                $konten_singkat = htmlspecialchars(substr($data['isi'], 0, 100)) . (strlen($data['isi']) > 100 ? '...' : '');

                                // Tentukan ikon lampiran
                                $lampiran_html = '<span class="label label-danger">Tidak Ada</span>';
                                if (!empty($data['file_lampiran'])) {
                                    $lampiran_html = '<a href="' . $upload_dir . htmlspecialchars($data['file_lampiran']) . '" target="_blank" class="label label-success"><i class="fa fa-download"></i> Unduh File</a>';
                                }
                        ?>
                        <tr>
                            <!-- TAMPILKAN NOMOR URUT -->
                            <td class="text-center"><?php echo $no++; ?></td> 
                            <td><b><?php echo htmlspecialchars($data['judul']); ?></b></td>
                            <td><?php echo $konten_singkat; ?></td>
                            <td class="text-center"><?php echo $lampiran_html; ?></td>
                            <td>
                                <small>
                                    Dibuat oleh: <b><?php echo htmlspecialchars($data['pembuat'] ?? 'N/A'); ?></b><br>
                                    Tanggal: <?php echo date('d M Y H:i', strtotime($data['tgl_dibuat'])); ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <!-- Menggunakan id_pengumuman pada URL -->
                                <a href="dashboard.php?page=news_edit.php&id=<?php echo $data['id_pengumuman']; ?>" class="btn btn-warning btn-sm" title="Edit Pengumuman">
                                    <span class="fa fa-edit"></span> Edit
                                </a>
                                
                                <!-- Menggunakan id_pengumuman pada URL -->
                                <?php if ($_SESSION['role'] === 'SysAdmin'): ?>
                                <a href="dashboard.php?page=news_list.php&action=delete&id=<?php echo $data['id_pengumuman']; ?>" 
                                    class="btn btn-danger btn-sm" 
                                    title="Hapus Pengumuman"
                                    onclick="return confirm('Apakah Anda yakin ingin menghapus pengumuman ini? Tindakan ini tidak dapat dibatalkan.');">
                                    <span class="fa fa-trash"></span> Hapus
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada pengumuman yang dibuat atau terjadi kesalahan saat mengambil data.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$script = "
<script>
$(document).ready(function() {
    // Hanya inisialisasi DataTables jika tidak ada error query fatal
    if (!$('div.alert-danger').text().includes('Query Gagal!')) {
        $('#newsTable').DataTable({
            \"language\": {
                \"url\": \"//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json\"
            },
            \"pageLength\": 10,
            \"columnDefs\": [
                { \"orderable\": false, \"targets\": [3, 5] },
                { \"orderable\": false, \"targets\": [0] } // Nomor urut tidak perlu di-sort
            ],
            \"order\": [[4, 'desc']] // Urutkan default berdasarkan kolom Tanggal Posting (kolom indeks 4)
        });
    }
});
</script>";

$footer_scripts[] = $script;
?>