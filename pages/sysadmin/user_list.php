<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// BARIS BARU KRITIS: Deklarasi variabel global
global $footer_scripts;

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
redirect_to('dashboard.php'); 
}

// ----------------------------------------------------
// LOGIKA CRUD: DELETE USER
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
$id_user = mysqli_real_escape_string($conn, $_GET['id']);

// Sebelum menghapus data user, ambil nama file foto untuk dihapus dari server
$get_foto_query = "SELECT du.foto FROM detail_user du WHERE du.id_user = '$id_user'";
$foto_result = mysqli_query($conn, $get_foto_query);
$foto_data = mysqli_fetch_assoc($foto_result);
$old_foto_name = $foto_data['foto'];

// Menentukan PATH ABSOLUT untuk penghapusan
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/efinance/'; 
$delete_path = $base_path . "assets/img/users/"; 

mysqli_begin_transaction($conn);

try {
$delete_query = "DELETE FROM user WHERE id_user = '$id_user'";
if (!mysqli_query($conn, $delete_query)) {
throw new Exception("Gagal menghapus data user dari database.");
}

// Jika ada foto lama dan bukan 'user.png', hapus dari folder
if ($old_foto_name && $old_foto_name !== 'user.png' && file_exists($delete_path . $old_foto_name)) {
unlink($delete_path . $old_foto_name);
}

mysqli_commit($conn);
$_SESSION['flash_message'] = "success|Pengguna berhasil dihapus!";

} catch (Exception $e) {
mysqli_rollback($conn);
$_SESSION['flash_message'] = "error|Gagal menghapus pengguna: " . $e->getMessage() . " " . mysqli_error($conn);
}

redirect_to('dashboard.php?page=user_list.php'); 
}

// ----------------------------------------------------
// LOGIKA READ: Ambil semua data pengguna
// ----------------------------------------------------
$query_list = "
SELECT 
u.id_user, u.username, 
du.nip_nidn, du.nama_lengkap, du.email, du.alamat, du.foto, du.telp, 
d.nama_departemen, 
r.nama_role
FROM 
user u
JOIN 
detail_user du ON u.id_user = du.id_user
JOIN 
departemen d ON du.id_departemen = d.id_departemen
JOIN 
role r ON du.id_role = r.id_role
ORDER BY 
du.nama_lengkap ASC";

$result_list = mysqli_query($conn, $query_list);
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-user"></span> Manajemen User
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li class="active">Daftar Pengguna</li>
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
<div class="col-lg-12">
<div class="panel panel-default">
<div class="panel-heading">
<h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Daftar Pengguna</h3>
</div>
<div class="panel-body">

<div class="text-right" style="margin-bottom: 15px;">
<a href="dashboard.php?page=user_add.php" class="btn btn-primary">
<span class="glyphicon glyphicon-plus"></span> Tambah Pengguna Baru
</a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover table-striped" id="userTable">
    <thead>
        <tr>
            <th class="text-center" width="5%">No.</th>
            <th class="text-center" width="8%">Foto</th> 
            <th>Detail Pengguna</th>
            <th width="10%">Kontak</th> 
            <th>Role</th>
            <th width="15%">Alamat</th> 
            <th class="text-center" width="12%">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if (mysqli_num_rows($result_list) > 0):
            while($data = mysqli_fetch_assoc($result_list)): 
                // Tentukan path foto (path web)
                $foto_path = (!empty($data['foto']) && file_exists('assets/img/users/' . $data['foto'])) ? 
                            'assets/img/users/' . $data['foto'] : 
                            'assets/img/users/user.png'; // Gambar default
        ?>
        <tr>
            <td class="text-center"><?php echo $no++; ?>.</td>
            <td class="text-center">
                <img src="<?php echo $foto_path; ?>" alt="Foto Pengguna" width="80" height="80" class="img-thumbnail">
            </td>
            <td>
                <span class="glyphicon glyphicon-asterisk"></span> <?php echo htmlspecialchars($data['nip_nidn']); ?><br>
                <span class="glyphicon glyphicon-bookmark"></span> <b><?php echo htmlspecialchars($data['nama_lengkap']); ?></b><br>
                <span class="glyphicon glyphicon-home"></span> Departemen / Bagian:<br>
                <b><?php echo htmlspecialchars($data['nama_departemen']); ?></b>
            </td>
            <td>
                <span class="glyphicon glyphicon-envelope"></span> :<br>
                <a href="mailto: <?php echo htmlspecialchars($data['email']); ?>">Kirim Email</a><br>
                <span class="glyphicon glyphicon-earphone"></span> :<br>
                <?php echo htmlspecialchars($data['telp']); ?>
            </td> 
            <td><span class="label label-info"><?php echo htmlspecialchars($data['nama_role']); ?></span></td>
            <td><?php echo htmlspecialchars(substr($data['alamat'], 0, 50)) . (strlen($data['alamat']) > 50 ? '...' : ''); ?></td>
            <td class="text-right">
                <a href="dashboard.php?page=user_edit.php&id=<?php echo $data['id_user']; ?>" class="btn btn-warning btn-sm">
                    <span class="glyphicon glyphicon-edit"></span> Edit
                </a><br>
                <a href="dashboard.php?page=user_list.php&action=delete&id=<?php echo $data['id_user']; ?>" 
                   class="btn btn-danger btn-sm" 
                   onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">
                    <span class="glyphicon glyphicon-trash"></span> Hapus
                </a>
            </td>
        </tr>
        <?php 
            endwhile;
        else:
        ?>
        <tr>
            <td colspan="7" class="text-center">Belum ada data pengguna yang terdaftar.</td>
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
// ----------------------------------------------------
// PENANAMAN SKRIP DATATABLES KE FOOTER DASHBOARD.PHP
// ----------------------------------------------------
$script = "
<script>
$(document).ready(function() {
    // INISIALISASI DATATABLES UNTUK TABEL PENGGUNA
    $('#userTable').DataTable({
        \"language\": {
            \"url\": \"//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json\"
        },
        \"pageLength\": 10,
        // Nonaktifkan pengurutan (sorting) pada kolom 'Foto' dan 'Aksi'
        \"columnDefs\": [
            { \"orderable\": false, \"targets\": [1, 6] }
        ]
    });
});
</script>";

// Tambahkan skrip ke array global ($footer_scripts) yang didefinisikan di dashboard.php
$footer_scripts[] = $script;
?>