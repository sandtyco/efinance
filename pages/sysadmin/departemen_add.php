<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php'); 
}

// ----------------------------------------------------
// LOGIKA CRUD: PROSES TAMBAH DEPARTEMEN (CREATE)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_departemen'])) {
    
    // 1. Sanitasi dan Ambil Data
    $kode = mysqli_real_escape_string($conn, $_POST['kode_departemen']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_departemen']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // 2. Validasi: Cek apakah kode atau nama departemen sudah ada
    $check_query = "SELECT id_departemen FROM departemen WHERE kode_departemen = '$kode' OR nama_departemen = '$nama' LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        // Data sudah ada, set flash message error
        $_SESSION['flash_message'] = "error|Gagal! Kode Departemen atau Nama Departemen sudah terdaftar.";
    } else {
        // 3. Proses Insert Data
        $insert_query = "INSERT INTO departemen (kode_departemen, nama_departemen, deskripsi) VALUES ('$kode', '$nama', '$deskripsi')";
        
        if (mysqli_query($conn, $insert_query)) {
            // Berhasil, redirect ke halaman list
            $_SESSION['flash_message'] = "success|Departemen <b>" . htmlspecialchars($nama) . "</b> berhasil ditambahkan.";
            redirect_to('dashboard.php?page=departemen_list.php');
        } else {
            // Gagal, set flash message error
            $_SESSION['flash_message'] = "error|Gagal menambahkan Departemen: " . mysqli_error($conn);
        }
    }
    
    // Jika ada error pada proses (bukan saat redirect berhasil), tetap di halaman ini (tidak ada redirect_to)
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-briefcase"></span> Tambah Departemen
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?page=departemen_list.php">Data Departemen</a></li>
            <li class="active">Tambah Baru</li>
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

// Simpan data POST sementara jika ada error validasi agar form tidak kosong
$old_kode = isset($_POST['kode_departemen']) ? htmlspecialchars($_POST['kode_departemen']) : '';
$old_nama = isset($_POST['nama_departemen']) ? htmlspecialchars($_POST['nama_departemen']) : '';
$old_deskripsi = isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Formulir Tambah Departemen Baru</h3>
            </div>
            <div class="panel-body">

                <form action="dashboard.php?page=departemen_add.php" method="POST" role="form">
                    
                    <div class="form-group">
                        <label for="kode_departemen">Kode Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="kode_departemen" name="kode_departemen" 
                               value="<?php echo $old_kode; ?>" required maxlength="10" 
                               placeholder="Contoh: KEU, PSDM">
                        <p class="help-block">Maksimal 10 karakter.</p>
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
                    <button type="submit" name="submit_departemen" class="btn btn-success">
                        <span class="glyphicon glyphicon-floppy-disk"></span> Simpan Data
                    </button>
                    <a href="dashboard.php?page=departemen_list.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-remove"></span> Batal
                    </a>
                </form>

            </div>
        </div>
    </div>
</div>