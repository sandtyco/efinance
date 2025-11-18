<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php'); 
}

// Daftar Tipe Akun yang diperbolehkan (Sesuai dengan ENUM di tabel akun)
$tipe_akun_options = ['Aset', 'Kewajiban', 'Ekuitas', 'Pendapatan', 'Beban'];

// ----------------------------------------------------
// 1. LOGIKA FETCH DATA UNTUK FORM (READ)
// ----------------------------------------------------

// Pastikan ID ada di URL
if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = "error|ID Akun tidak ditemukan.";
    redirect_to('dashboard.php?page=akun_list.php');
}

$id_akun = mysqli_real_escape_string($conn, $_GET['id']);

// Query untuk mengambil data lama
$fetch_query = "SELECT kode_akun, nama_akun, tipe_akun, deskripsi FROM akun WHERE id_akun = '$id_akun'";
$fetch_result = mysqli_query($conn, $fetch_query);

if (mysqli_num_rows($fetch_result) == 0) {
    $_SESSION['flash_message'] = "error|Data Akun tidak ditemukan di database.";
    redirect_to('dashboard.php?page=akun_list.php');
}

// Data Akun lama yang akan diisi ke formulir
$data_lama = mysqli_fetch_assoc($fetch_result);
$old_kode = $data_lama['kode_akun'];
$old_nama = $data_lama['nama_akun'];
$old_tipe = $data_lama['tipe_akun']; // Nilai lama untuk select
$old_deskripsi = $data_lama['deskripsi'];


// ----------------------------------------------------
// 2. LOGIKA CRUD: PROSES UBAH AKUN (UPDATE)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_edit_akun'])) {
    
    // Ambil data baru dari form
    $kode_baru = mysqli_real_escape_string($conn, $_POST['kode_akun']);
    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_akun']);
    $tipe_baru = mysqli_real_escape_string($conn, $_POST['tipe_akun']);
    $deskripsi_baru = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // 2a. Validasi Tipe Akun
    if (!in_array($tipe_baru, $tipe_akun_options)) {
        $_SESSION['flash_message'] = "error|Gagal! Tipe Akun tidak valid.";
    } else {
        // 2b. Validasi Duplikasi: Cek apakah kode atau nama baru sudah digunakan oleh akun LAIN
        $check_query = "SELECT id_akun FROM akun 
                        WHERE kode_akun = '$kode_baru' 
                        AND id_akun != '$id_akun' LIMIT 1"; // Hanya cek kode unik, karena nama akun boleh sama
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Kode Akun sudah ada di akun lain, set flash message error
            $_SESSION['flash_message'] = "error|Gagal! Kode Akun <b>" . htmlspecialchars($kode_baru) . "</b> sudah terdaftar di akun lain.";
            
            // Tetap di halaman ini, dan update nilai 'lama' dengan nilai POST
            $old_kode = htmlspecialchars($_POST['kode_akun']);
            $old_nama = htmlspecialchars($_POST['nama_akun']);
            $old_tipe = htmlspecialchars($_POST['tipe_akun']);
            $old_deskripsi = htmlspecialchars($_POST['deskripsi']);

        } else {
            // 3. Proses Update Data
            $update_query = "UPDATE akun SET 
                                kode_akun = '$kode_baru', 
                                nama_akun = '$nama_baru', 
                                tipe_akun = '$tipe_baru',
                                deskripsi = '$deskripsi_baru' 
                             WHERE id_akun = '$id_akun'";
            
            if (mysqli_query($conn, $update_query)) {
                // Berhasil, redirect ke halaman list
                $_SESSION['flash_message'] = "success|Akun <b>" . htmlspecialchars($nama_baru) . "</b> berhasil diperbarui.";
                redirect_to('dashboard.php?page=akun_list.php');
            } else {
                // Gagal, set flash message error
                $_SESSION['flash_message'] = "error|Gagal memperbarui Akun: " . mysqli_error($conn);
            }
        }
    }
    
    // Jika ada error pada proses, tetap di halaman ini.
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-piggy-bank"></span> Edit Akun Keuangan
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?page=akun_list.php">Data Akun</a></li>
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
                <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Formulir Edit Data Akun</h3>
            </div>
            <div class="panel-body">

                <form action="dashboard.php?page=akun_edit.php&id=<?php echo htmlspecialchars($id_akun); ?>" method="POST" role="form">
                    
                    <p class="text-info">Anda sedang mengedit Akun dengan ID: <b><?php echo htmlspecialchars($id_akun); ?></b></p>
                    <hr>

                    <div class="form-group">
                        <label for="kode_akun">Kode Akun (Chart of Accounts) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="kode_akun" name="kode_akun" 
                               value="<?php echo $old_kode; ?>" required maxlength="20" 
                               placeholder="Contoh: 51101 (Beban Gaji)">
                        <p class="help-block">Kode ini harus unik dan menjadi referensi utama RAB.</p>
                    </div>

                    <div class="form-group">
                        <label for="nama_akun">Nama Akun <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_akun" name="nama_akun" 
                               value="<?php echo $old_nama; ?>" required maxlength="150" 
                               placeholder="Contoh: Beban Gaji Pegawai Tetap">
                    </div>

                    <div class="form-group">
                        <label for="tipe_akun">Tipe Akun <span class="text-danger">*</span></label>
                        <select class="form-control" id="tipe_akun" name="tipe_akun" required>
                            <option value="">-- Pilih Tipe Akun --</option>
                            <?php foreach ($tipe_akun_options as $tipe) : ?>
                                <option value="<?php echo $tipe; ?>" 
                                    <?php echo ($old_tipe == $tipe) ? 'selected' : ''; ?>>
                                    <?php echo $tipe; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4" 
                                  placeholder="Penjelasan singkat mengenai penggunaan akun ini."><?php echo $old_deskripsi; ?></textarea>
                    </div>

                    <hr>
                    <button type="submit" name="submit_edit_akun" class="btn btn-warning">
                        <span class="glyphicon glyphicon-save"></span> Perbarui Data
                    </button>
                    <a href="dashboard.php?page=akun_list.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-remove"></span> Batal
                    </a>
                </form>

            </div>
        </div>
    </div>
</div>