<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Direktur Keuangan') {
    redirect_to('dashboard.php'); 
}

// Daftar Tipe Akun yang diperbolehkan (Sesuai dengan ENUM di tabel akun)
$tipe_akun_options = ['Aset', 'Kewajiban', 'Ekuitas', 'Pendapatan', 'Beban'];

// ----------------------------------------------------
// LOGIKA CRUD: PROSES TAMBAH AKUN (CREATE)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_akun'])) {
    
    // 1. Sanitasi dan Ambil Data
    $kode = mysqli_real_escape_string($conn, $_POST['kode_akun']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_akun']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe_akun']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // Validasi Tipe Akun (Memastikan input adalah salah satu dari ENUM)
    if (!in_array($tipe, $tipe_akun_options)) {
         $_SESSION['flash_message'] = "error|Gagal! Tipe Akun tidak valid.";
    } else {

        // 2. Validasi: Cek apakah Kode Akun sudah ada
        $check_query = "SELECT id_akun FROM akun WHERE kode_akun = '$kode' LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Kode Akun sudah ada, set flash message error
            $_SESSION['flash_message'] = "error|Gagal! Kode Akun <b>" . htmlspecialchars($kode) . "</b> sudah terdaftar.";
        } else {
            // 3. Proses Insert Data
            $insert_query = "INSERT INTO akun (kode_akun, nama_akun, tipe_akun, deskripsi) VALUES ('$kode', '$nama', '$tipe', '$deskripsi')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Berhasil, redirect ke halaman list
                $_SESSION['flash_message'] = "success|Akun <b>" . htmlspecialchars($nama) . "</b> berhasil ditambahkan.";
                redirect_to('dashboard.php?page=akun_list.php');
            } else {
                // Gagal, set flash message error
                $_SESSION['flash_message'] = "error|Gagal menambahkan Akun: " . mysqli_error($conn);
            }
        }
    }
    
    // Jika ada error, data POST dipertahankan di form
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-piggy-bank"></span> Tambah Akun Keuangan
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?page=akun_list.php">Data Akun</a></li>
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
    // Hanya unset jika tidak ada POST data. Jika ada POST, kita akan unset setelah mengisi OLD data.
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        unset($_SESSION['flash_message']);
    }
}

// Simpan data POST sementara jika ada error validasi agar form tidak kosong
$old_kode = isset($_POST['kode_akun']) ? htmlspecialchars($_POST['kode_akun']) : '';
$old_nama = isset($_POST['nama_akun']) ? htmlspecialchars($_POST['nama_akun']) : '';
$old_tipe = isset($_POST['tipe_akun']) ? htmlspecialchars($_POST['tipe_akun']) : '';
$old_deskripsi = isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '';

// Unset flash message setelah data lama diambil
unset($_SESSION['flash_message']);
?>

<div class="row">
    <div class="col-lg-8">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Formulir Tambah Akun Baru</h3>
            </div>
            <div class="panel-body">

                <form action="dashboard.php?page=akun_add.php" method="POST" role="form">
                    
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
                    <button type="submit" name="submit_akun" class="btn btn-success">
                        <span class="glyphicon glyphicon-floppy-disk"></span> Simpan Data
                    </button>
                    <a href="dashboard.php?page=akun_list.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-remove"></span> Batal
                    </a>
                </form>

            </div>
        </div>
    </div>
</div>