<?php
// FILE: pages/sysadmin/news_add.php
// Halaman untuk menambahkan pengumuman baru ke database.

include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses: SysAdmin dan Direktur Keuangan diperbolehkan menambah.
$allowed_roles = ['Direktur Keuangan'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    redirect_to('dashboard.php'); 
}

$root_path = ''; 
$upload_dir = 'assets/uploads/'; // Direktori tempat file lampiran akan disimpan

$judul = '';
$isi = '';
$errors = [];

// ----------------------------------------------------
// LOGIKA CREATE (POST): Simpan data pengumuman baru
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // a. Sanitasi dan Ambil Data Form
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $isi = mysqli_real_escape_string($conn, $_POST['isi']);
    $id_user_pembuat = $_SESSION['id_user'] ?? 0; // Ambil ID user dari sesi
    $file_lampiran_db = null; // Default: tidak ada file

    // b. Validasi Input Dasar
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul pengumuman wajib diisi.';
    }
    // Menggunakan trim() untuk memastikan konten benar-benar ada (tidak hanya spasi)
    if (empty(trim($_POST['isi']))) { 
        $errors[] = 'Konten pengumuman wajib diisi.';
    }
    if (empty($id_user_pembuat)) {
        $errors[] = 'ID Pengguna tidak ditemukan di sesi. Harap login ulang.';
    }
    
    // c. Penanganan Upload File Lampiran
    if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['file_lampiran'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = 'Format file lampiran tidak diizinkan (Hanya PDF, DOC/X, JPG, PNG).';
        }
        if ($file_size > $max_file_size) {
            $errors[] = 'Ukuran file lampiran terlalu besar (maksimal 5MB).';
        }

        if (empty($errors)) {
            // Pindahkan file yang diunggah
            $new_file_name = uniqid('news_') . '.' . $file_ext;
            $file_target = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $file_target)) {
                $file_lampiran_db = $new_file_name; // Simpan nama file ke DB
            } else {
                $errors[] = 'Gagal mengunggah file. Periksa izin direktori: ' . $upload_dir;
            }
        }
    } elseif (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] != UPLOAD_ERR_NO_FILE) {
        // Tangani error upload lainnya (misalnya, melebihi max_filesize PHP)
        $errors[] = 'Terjadi kesalahan saat mengunggah file. Kode error: ' . $_FILES['file_lampiran']['error'];
    }

    // d. Jika tidak ada error, lakukan INSERT ke database
    if (empty($errors)) {
        // PERHATIAN: Kolom id_pengumuman diasumsikan AUTO_INCREMENT
        $insert_query = "INSERT INTO pengumuman 
                         (judul, isi, tgl_dibuat, id_user_pembuat, file_lampiran) 
                         VALUES 
                         ('$judul', '$isi', NOW(), '$id_user_pembuat', " . (is_null($file_lampiran_db) ? "NULL" : "'$file_lampiran_db'") . ")";

        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['flash_message'] = "success|Pengumuman berhasil dipublikasikan oleh " . htmlspecialchars($_SESSION['role']) . "!";
            redirect_to('dashboard.php?page=news_list.php'); // Arahkan ke daftar setelah sukses
            exit;
        } else {
            // Jika ada file yang terlanjur di-upload, hapus kembali
            if ($file_lampiran_db && file_exists($upload_dir . $file_lampiran_db)) {
                unlink($upload_dir . $file_lampiran_db);
            }
            $_SESSION['flash_message'] = "error|Gagal mempublikasikan pengumuman: " . mysqli_error($conn);
            // Tetap di halaman ini
        }
    } else {
        // Jika ada error validasi
        $error_message = '<strong>Gagal menambah pengumuman:</strong><ul>';
        foreach ($errors as $error) {
            $error_message .= '<li>' . $error . '</li>';
        }
        $error_message .= '</ul>';
        $_SESSION['flash_message'] = "error|{$error_message}";
        // Tetap di halaman ini
    }
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <i class="fa fa-plus-circle"></i> Tambah Pengumuman Baru
        </h1>
        <ol class="breadcrumb">
            <li><i class="fa fa-dashboard"></i> <a href="dashboard.php">Dashboard</a></li>
            <li><i class="fa fa-bullhorn"></i> <a href="dashboard.php?page=news_list.php">Daftar Pengumuman</a></li>
            <li class="active"><i class="fa fa-plus-circle"></i> Tambah Baru</li>
        </ol>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <?php 
        // Tampilkan pesan flash jika ada
        if (isset($_SESSION['flash_message'])) {
            list($type, $message) = explode('|', $_SESSION['flash_message'], 2);
            echo '<div class="alert alert-' . ($type == 'success' ? 'success' : 'danger') . ' alert-dismissible" role="alert">';
            echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            echo $message;
            // Kami biarkan flash message tetap ada jika ada error (tipe 'error') agar data form tidak hilang
            // Tapi jika bukan error, kita unset
            if ($type !== 'error') {
                unset($_SESSION['flash_message']);
            }
        }
        ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-file-text-o fa-fw"></i> Form Tambah Pengumuman</h3>
            </div>
            <div class="panel-body">
                <!-- FORM UNTUK TAMBAH PENGUMUMAN -->
                <!-- Penting: Tambahkan enctype="multipart/form-data" untuk upload file -->
                <form role="form" method="POST" action="dashboard.php?page=news_add.php" enctype="multipart/form-data">
                    
                    <!-- Judul Pengumuman -->
                    <div class="form-group">
                        <label for="judul">Judul Pengumuman <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" 
                               value="<?php echo htmlspecialchars($judul); ?>" required>
                    </div>

                    <!-- Konten Pengumuman (Textarea) -->
                    <div class="form-group">
                        <label for="isi">Konten Pengumuman <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="isi" name="isi" rows="10" required><?php echo htmlspecialchars($isi); ?></textarea>
                    </div>

                    <!-- Upload File Lampiran -->
                    <div class="form-group">
                        <label for="file_lampiran">Lampiran (Opsional)</label>
                        <input type="file" id="file_lampiran" name="file_lampiran" class="form-control-file">
                        <p class="help-block">Maksimal 5MB. Format yang diizinkan: PDF, DOC/X, JPG, PNG.</p>
                    </div>

                    <!-- Tombol Aksi -->
                    <button type="submit" class="btn btn-primary"><i class="fa fa-bullhorn"></i> Publikasikan Pengumuman</button>
                    <a href="dashboard.php?page=news_list.php" class="btn btn-default"><i class="fa fa-arrow-circle-left"></i> Batal / Kembali</a>
                </form>
            </div>
        </div>
    </div>
</div>