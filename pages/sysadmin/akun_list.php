<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php'); 
}

// ----------------------------------------------------
// LOGIKA CRUD: DELETE AKUN
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_akun = mysqli_real_escape_string($conn, $_GET['id']);

    // PENTING: Lakukan Pengecekan Dependensi sebelum menghapus (TODO)
    // Query untuk cek apakah id_akun sudah digunakan di tabel 'rab_detail'
    // $check_dependency_query = "SELECT id_rab_detail FROM rab_detail WHERE id_akun = '$id_akun' LIMIT 1";
    // $dependency_result = mysqli_query($conn, $check_dependency_query);

    // if (mysqli_num_rows($dependency_result) > 0) {
    //     $_SESSION['flash_message'] = "error|Gagal menghapus Akun! Akun ini sudah digunakan dalam Rencana Anggaran Biaya (RAB).";
    // } else {

        $delete_query = "DELETE FROM akun WHERE id_akun = '$id_akun'";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['flash_message'] = "success|Akun berhasil dihapus.";
        } else {
            $_SESSION['flash_message'] = "error|Gagal menghapus Akun: " . mysqli_error($conn);
        }
    // }
    
    redirect_to('dashboard.php?page=akun_list.php'); 
}

// ----------------------------------------------------
// LOGIKA READ: Ambil semua data akun
// ----------------------------------------------------
$query_list = "SELECT id_akun, kode_akun, nama_akun, tipe_akun, deskripsi FROM akun ORDER BY kode_akun ASC";
$result_list = mysqli_query($conn, $query_list);

// Deklarasi variabel global untuk slot skrip (DataTables)
global $footer_scripts; 
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-briefcase"></span> Manajemen Akun
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li class="active">Data Master Akun</li>
        </ol>
    </div>
</div>

<?php 
// Menampilkan Flash Message
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
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Daftar Akun Keuangan</h3>
            </div>
            <div class="panel-body">
                
                <div class="text-right" style="margin-bottom: 15px;">
                    <a href="dashboard.php?page=akun_add.php" class="btn btn-primary">
                        <span class="glyphicon glyphicon-plus"></span> Tambah Akun Baru
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped" id="akunTable">
                        <thead>
                            <tr>
                                <th class="text-center" width="5%">No.</th>
                                <th width="15%">Kode Akun</th>
                                <th width="20%">Tipe Akun</th>
                                <th>Nama Akun</th>
                                <th class="text-center" width="10%">Detail</th> 
                                <th class="text-center" width="12%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            if (mysqli_num_rows($result_list) > 0):
                                while($data = mysqli_fetch_assoc($result_list)): 
                                    // Logika pewarnaan badge berdasarkan tipe akun
                                    $badge_class = 'label-default';
                                    if ($data['tipe_akun'] == 'Aset') $badge_class = 'label-success';
                                    else if ($data['tipe_akun'] == 'Kewajiban') $badge_class = 'label-danger';
                                    else if ($data['tipe_akun'] == 'Ekuitas') $badge_class = 'label-info'; // Tambahkan Ekuitas
                                    else if ($data['tipe_akun'] == 'Pendapatan') $badge_class = 'label-primary';
                                    else if ($data['tipe_akun'] == 'Beban') $badge_class = 'label-warning';
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?>.</td>
                                <td><?php echo htmlspecialchars($data['kode_akun']); ?></td>
                                <td><span class="label <?php echo $badge_class; ?>"><?php echo htmlspecialchars($data['tipe_akun']); ?></span></td>
                                <td><?php echo htmlspecialchars($data['nama_akun']); ?></td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-info btn-xs btn-lihat-detail" 
                                            data-toggle="modal" 
                                            data-target="#detailModal"
                                            data-nama="<?php echo htmlspecialchars($data['nama_akun']); ?>"
                                            data-deskripsi="<?php echo htmlspecialchars($data['deskripsi']); ?>">
                                        <span class="glyphicon glyphicon-search"></span> Lihat
                                    </button>
                                </td>
                                <td class="text-center">
                                    <a href="dashboard.php?page=akun_edit.php&id=<?php echo $data['id_akun']; ?>" class="btn btn-warning btn-xs">
                                        <span class="glyphicon glyphicon-edit"></span> Edit
                                    </a>
                                    <a href="dashboard.php?page=akun_list.php&action=delete&id=<?php echo $data['id_akun']; ?>" 
                                       class="btn btn-danger btn-xs" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus akun ini? Akun yang sudah terkait RAB tidak dapat dihapus.');">
                                        <span class="glyphicon glyphicon-trash"></span> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center">Belum ada data akun yang terdaftar.</td>
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
                <h4 class="modal-title" id="detailModalLabel">Detail Akun</h4>
            </div>
            <div class="modal-body">
                <h4 id="modal-nama-akun"></h4>
                <hr>
                <h5>**Deskripsi:**</h5>
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
// ----------------------------------------------------
$script = "
<script>
$(document).ready(function() {
    // 1. INISIALISASI DATATABLES
    $('#akunTable').DataTable({
        \"language\": {
            \"url\": \"//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json\"
        },
        \"pageLength\": 10,
        \"columnDefs\": [
            // Nonaktifkan ordering/sorting pada kolom Detail dan Aksi
            { \"orderable\": false, \"targets\": [4, 5] }
        ]
    });

    // 2. LOGIKA MEMUAT DATA KE MODAL (Menggunakan jQuery Data Attributes)
    $('#detailModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var nama = button.data('nama');
        var deskripsi = button.data('deskripsi'); 
        
        var modal = $(this);
        modal.find('#modal-nama-akun').text(nama);
        modal.find('#modal-deskripsi').text(deskripsi); 
    });
});
</script>";

// Tambahkan skrip ke array global ($footer_scripts) yang didefinisikan di dashboard.php
$footer_scripts[] = $script;
?>