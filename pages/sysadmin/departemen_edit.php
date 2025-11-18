<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php'); 
}

// ----------------------------------------------------
// 1. LOGIKA FETCH DATA UNTUK FORM (READ)
// ----------------------------------------------------

// Pastikan ID ada di URL
if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = "error|ID Departemen tidak ditemukan.";
    redirect_to('dashboard.php?page=departemen_list.php');
}

$id_departemen = mysqli_real_escape_string($conn, $_GET['id']);

// Query untuk mengambil data lama
$fetch_query = "SELECT kode_departemen, nama_departemen, deskripsi FROM departemen WHERE id_departemen = '$id_departemen'";
$fetch_result = mysqli_query($conn, $fetch_query);

if (mysqli_num_rows($fetch_result) == 0) {
    $_SESSION['flash_message'] = "error|Data Departemen tidak ditemukan di database.";
    redirect_to('dashboard.php?page=departemen_list.php');
}

// Data Departemen lama yang akan diisi ke formulir
$data_lama = mysqli_fetch_assoc($fetch_result);
$old_kode = $data_lama['kode_departemen'];
$old_nama = $data_lama['nama_departemen'];
$old_deskripsi = $data_lama['deskripsi'];


// ----------------------------------------------------
// 2. LOGIKA CRUD: PROSES UBAH DEPARTEMEN (UPDATE)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_edit_departemen'])) {
    
    // Ambil data baru dari form
    $kode_baru = mysqli_real_escape_string($conn, $_POST['kode_departemen']);
    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_departemen']);
    $deskripsi_baru = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // 2. Validasi Duplikasi: Cek apakah kode atau nama baru sudah digunakan oleh departemen LAIN
    $check_query = "SELECT id_departemen FROM departemen 
                    WHERE (kode_departemen = '$kode_baru' OR nama_departemen = '$nama_baru') 
                    AND id_departemen != '$id_departemen' LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        // Data sudah ada di departemen lain, set flash message error
        $_SESSION['flash_message'] = "error|Gagal! Kode atau Nama Departemen yang baru sudah terdaftar di departemen lain.";
        
        // Tetap di halaman ini, dan update nilai 'lama' dengan nilai yang baru di-POST agar form terisi
        $old_kode = htmlspecialchars($_POST['kode_departemen']);
        $old_nama = htmlspecialchars($_POST['nama_departemen']);
        $old_deskripsi = htmlspecialchars($_POST['deskripsi']);

    } else {
        // 3. Proses Update Data
        $update_query = "UPDATE departemen SET 
                            kode_departemen = '$kode_baru', 
                            nama_departemen = '$nama_baru', 
                            deskripsi = '$deskripsi_baru' 
                         WHERE id_departemen = '$id_departemen'";
        
        if (mysqli_query($conn, $update_query)) {
            // Berhasil, redirect ke halaman list
            $_SESSION['flash_message'] = "success|Departemen <b>" . htmlspecialchars($nama_baru) . "</b> berhasil diperbarui.";
            redirect_to('dashboard.php?page=departemen_list.php');
        } else {
            // Gagal, set flash message error
            $_SESSION['flash_message'] = "error|Gagal memperbarui Departemen: " . mysqli_error($conn);
        }
    }
    
    // Jika ada error pada proses (bukan saat redirect berhasil), tetap di halaman ini (tidak ada redirect_to)
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-briefcase"></span> Edit Departemen
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?page=departemen_list.php">Data Departemen</a></li>
            <li class="active">Edit Data</li>
        </ol>
    </div>
</div>

<?php 
// Menampilkan Flash Message jika ada
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
    <div class="col-lg-8">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Formulir Edit Data Departemen</h3>
            </div>
            <div class="panel-body">

                <form action="dashboard.php?page=departemen_edit.php&id=<?php echo htmlspecialchars($id_departemen); ?>" method="POST" role="form">
                    
                    <p class="text-info">Anda sedang mengedit Departemen dengan ID: <b><?php echo htmlspecialchars($id_departemen); ?></b></p>
                    <hr>

                    <div class="form-group">
                        <label for="kode_departemen">Kode Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="kode_departemen" name="kode_departemen" 
                               value="<?php echo $old_kode; ?>" required maxlength="10" 
                               placeholder="Contoh: KEU, PSDM">
                    </div>

                    <div class="form-group">
                        <label for="nama_departemen">Nama Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_departemen" name="nama_departemen" 
                               value="<?php echo $old_nama; ?>" required maxlength="100" 
                               placeholder="Contoh: Keuangan, Pengembangan SDM">
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4" 
                                  placeholder="Tuliskan deskripsi singkat mengenai tugas dan fungsi departemen."><?php echo $old_deskripsi; ?></textarea>
                    </div>

                    <hr>
                    <button type="submit" name="submit_edit_departemen" class="btn btn-warning">
                        <span class="glyphicon glyphicon-save"></span> Perbarui Data
                    </button>
                    <a href="dashboard.php?page=departemen_list.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-remove"></span> Batal
                    </a>
                </form>

            </div>
        </div>
    </div>
</div>