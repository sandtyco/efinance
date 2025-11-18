<?php
// PASTIKAN INCLUDE PATH BENAR
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php'); 
}

// ----------------------------------------------------
// LOGIKA CRUD: DELETE DEPARTEMEN
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_departemen = mysqli_real_escape_string($conn, $_GET['id']);
    
    // CEK DEPENDENSI: Apakah Departemen ini digunakan oleh user?
    $check_dependency = mysqli_query($conn, "SELECT id_user FROM detail_user WHERE id_departemen = '$id_departemen' LIMIT 1");

    if (mysqli_num_rows($check_dependency) > 0) {
        $_SESSION['flash_message'] = "error|Gagal menghapus! Departemen ini masih digunakan oleh pengguna.";
    } else {
        $delete_query = "DELETE FROM departemen WHERE id_departemen = '$id_departemen'";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['flash_message'] = "success|Departemen berhasil dihapus.";
        } else {
            $_SESSION['flash_message'] = "error|Gagal menghapus departemen: " . mysqli_error($conn);
        }
    }
    
    redirect_to('dashboard.php?page=departemen_list.php'); 
}

// ----------------------------------------------------
// LOGIKA READ: Ambil semua data departemen (Termasuk deskripsi)
// ----------------------------------------------------
$query_list = "SELECT id_departemen, kode_departemen, nama_departemen, deskripsi FROM departemen ORDER BY nama_departemen ASC";
$result_list = mysqli_query($conn, $query_list);
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-briefcase"></span> Manajemen Departemen
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li class="active">Data Master Departemen</li>
        </ol>
    </div>
</div>

<?php 
if (isset($_SESSION['flash_message'])) {
    list($type, $message) = explode('|', $_SESSION['flash_message'], 2);
    echo '<div class="alert alert-' . ($type == 'success' ? 'success' : 'danger') . ' alert-dismissible" role="alert">';
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo $message;
    echo '</div>';
    unset($_SESSION['flash_message']);
}
?>

<div class="row">
    <div class="col-lg-10">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Daftar Departemen</h3>
            </div>
            <div class="panel-body">
                
                <div class="text-right" style="margin-bottom: 15px;">
                    <a href="dashboard.php?page=departemen_add.php" class="btn btn-primary">
                        <span class="glyphicon glyphicon-plus"></span> Tambah Departemen Baru
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped" id="departemenTable">
                        <thead>
                            <tr>
                                <th class="text-center" width="5%">No.</th>
                                <th width="20%">Kode Departemen</th>
                                <th>Nama Departemen</th>
                                <th class="text-center" width="10%">Detail</th> 
                                <th class="text-center" width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            if (mysqli_num_rows($result_list) > 0):
                                while($data = mysqli_fetch_assoc($result_list)): 
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?>.</td>
                                <td><?php echo htmlspecialchars($data['kode_departemen']); ?></td>
                                <td><?php echo htmlspecialchars($data['nama_departemen']); ?></td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-info btn-xs btn-lihat-detail" 
                                            data-toggle="modal" 
                                            data-target="#detailModal"
                                            data-nama="<?php echo htmlspecialchars($data['nama_departemen']); ?>"
                                            data-deskripsi="<?php echo htmlspecialchars($data['deskripsi']); ?>">
                                        <span class="glyphicon glyphicon-search"></span> Lihat
                                    </button>
                                </td>
                                <td class="text-center">
                                    <a href="dashboard.php?page=departemen_edit.php&id=<?php echo $data['id_departemen']; ?>" class="btn btn-warning btn-xs">
                                        <span class="glyphicon glyphicon-edit"></span> Edit
                                    </a>
                                    <a href="dashboard.php?page=departemen_list.php&action=delete&id=<?php echo $data['id_departemen']; ?>" 
                                       class="btn btn-danger btn-xs" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus departemen ini?');">
                                        <span class="glyphicon glyphicon-trash"></span> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada data departemen yang terdaftar.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="detailModalLabel">Detail Departemen</h4>
            </div>
            <div class="modal-body">
                <h4 id="modal-nama-departemen"></h4>
                <hr>
                <h5>Deskripsi dan Tujuan RAB: </h5>
                <p id="modal-deskripsi" style="white-space: pre-wrap;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php
// ----------------------------------------------------
// PENANAMAN SKRIP DATATABLES & MODAL KE FOOTER DASHBOARD.PHP
// (Ini menggantikan tag <script> yang Anda hapus sebelumnya)
// ----------------------------------------------------
$script = "
<script>
$(document).ready(function() {
    // 1. INISIALISASI DATATABLES
    $('#departemenTable').DataTable({
        \"language\": {
            \"url\": \"//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json\"
        },
        \"pageLength\": 10
    });

    // 2. LOGIKA MEMUAT DATA KE MODAL
    $('#detailModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var nama = button.data('nama');
        var deskripsi = button.data('deskripsi'); 
        
        var modal = $(this);
        modal.find('#modal-nama-departemen').text(nama);
        modal.find('#modal-deskripsi').text(deskripsi); 
    });
});
</script>";

// Tambahkan skrip ke array global ($footer_scripts) yang didefinisikan di dashboard.php
// Jika variabel ini tidak ada, akan muncul error, tapi karena kita sudah menambahkannya di dashboard.php, ini aman.
$footer_scripts[] = $script;
?>