<?php
// FILE: pages/keuangan/news_edit.php
// Halaman untuk mengedit pengumuman yang sudah ada.

include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses: HANYA Direktur Keuangan yang boleh mengedit
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Direktur Keuangan') {
    redirect_to('dashboard.php'); 
}

$root_path = ''; 
$upload_dir = 'assets/uploads/'; 

// Inisialisasi variabel untuk form
$id_pengumuman = '';
$judul = '';
$isi = '';
$file_lampiran_lama = null;
$errors = [];

// ----------------------------------------------------
// LOGIKA READ (GET): Ambil data pengumuman berdasarkan ID
// ----------------------------------------------------
if (isset($_GET['id'])) {
    $id_pengumuman = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Gunakan kolom yang benar
    $query_get = "SELECT id_pengumuman, judul, isi, file_lampiran FROM pengumuman WHERE id_pengumuman = '$id_pengumuman'";
    $result_get = mysqli_query($conn, $query_get);

    if ($result_get && mysqli_num_rows($result_get) === 1) {
        $data = mysqli_fetch_assoc($result_get);
        $judul = htmlspecialchars($data['judul']);
        $isi = htmlspecialchars($data['isi']);
        $file_lampiran_lama = $data['file_lampiran'];
    } else {
        $_SESSION['flash_message'] = "error|Pengumuman tidak ditemukan atau ID tidak valid.";
        redirect_to('dashboard.php?page=news_list.php');
    }
} else {
    $_SESSION['flash_message'] = "error|ID pengumuman tidak disediakan.";
    redirect_to('dashboard.php?page=news_list.php');
}


// ----------------------------------------------------
// LOGIKA UPDATE (POST): Simpan perubahan
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pengumuman'])) {
    
    // a. Sanitasi dan Ambil Data Form
    $id_pengumuman_post = mysqli_real_escape_string($conn, $_POST['id_pengumuman']);
    $judul_post = mysqli_real_escape_string($conn, $_POST['judul']);
    $isi_post = mysqli_real_escape_string($conn, $_POST['isi']);
    $file_lampiran_db = $file_lampiran_lama; // Default: pertahankan file lama
    $id_user_pembuat = $_SESSION['id_user'] ?? 0; // Ambil ID user yang melakukan update

    // b. Validasi Input
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul pengumuman wajib diisi.';
    }
    if (empty(trim($_POST['isi']))) { 
        $errors[] = 'Konten pengumuman wajib diisi.';
    }
    if (empty($id_user_pembuat)) {
        $errors[] = 'ID Pengguna tidak ditemukan di sesi. Harap login ulang.';
    }
    
    // c. Penanganan Penghapusan File Lama (Jika checkbox dicentang)
    if (isset($_POST['delete_old_file']) && $_POST['delete_old_file'] == '1') {
        if ($file_lampiran_lama && file_exists($upload_dir . $file_lampiran_lama)) {
            unlink($upload_dir . $file_lampiran_lama);
        }
        $file_lampiran_db = null; // Set di DB menjadi NULL
    }

    // d. Penanganan Upload File Lampiran BARU
    if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['file_lampiran'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = 'Format file lampiran tidak diizinkan.';
        }
        if ($file_size > $max_file_size) {
            $errors[] = 'Ukuran file lampiran terlalu besar (maksimal 5MB).';
        }

        if (empty($errors)) {
            // Hapus file lama jika ada file baru diupload
            if ($file_lampiran_lama && file_exists($upload_dir . $file_lampiran_lama)) {
                unlink($upload_dir . $file_lampiran_lama);
            }
            
            $new_file_name = uniqid('news_') . '.' . $file_ext;
            $file_target = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $file_target)) {
                $file_lampiran_db = $new_file_name; // Update nama file baru di DB
            } else {
                $errors[] = 'Gagal mengunggah file baru. Periksa izin direktori.';
            }
        }
    }

    // e. Jika tidak ada error, lakukan UPDATE ke database
    if (empty($errors)) {
        // PERHATIAN: Semua kolom harus menggunakan nama yang benar
        $update_query = "UPDATE pengumuman SET 
                         judul = '$judul_post', 
                         isi = '$isi_post', 
                         file_lampiran = " . (is_null($file_lampiran_db) ? "NULL" : "'$file_lampiran_db'") . ", 
                         id_user_pembuat = '$id_user_pembuat',
                         tgl_dibuat = NOW() /* Update tanggal juga untuk menandai perubahan */
                         WHERE id_pengumuman = '$id_pengumuman_post'";

        if (mysqli_query($conn, $update_query)) {
            $_SESSION['flash_message'] = "success|Pengumuman berhasil diperbarui!";
            redirect_to('dashboard.php?page=news_list.php');
        } else {
            $_SESSION['flash_message'] = "error|Gagal memperbarui pengumuman: " . mysqli_error($conn);
            // Re-assign data agar tidak hilang di form
            $judul = htmlspecialchars($_POST['judul']);
            $isi = htmlspecialchars($_POST['isi']);
        }
        exit;
    } else {
        // Jika ada error validasi
        $error_message = '<strong>Gagal memperbarui pengumuman:</strong><ul>';
        foreach ($errors as $error) {
            $error_message .= '<li>' . $error . '</li>';
        }
        $error_message .= '</ul>';
        $_SESSION['flash_message'] = "error|{$error_message}";
        
        // Re-assign data agar tidak hilang di form
        $judul = htmlspecialchars($_POST['judul']);
        $isi = htmlspecialchars($_POST['isi']);
    }
}

// Data yang ditampilkan di form (gunakan data dari GET atau data POST jika gagal update)
$current_judul = $judul;
$current_isi = $isi;
$current_file_lampiran = $file_lampiran_lama;
$current_id_pengumuman = $id_pengumuman;
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <i class="fa fa-edit"></i> Edit Pengumuman
        </h1>
        <ol class="breadcrumb">
            <li><i class="fa fa-dashboard"></i> <a href="dashboard.php">Dashboard</a></li>
            <li><i class="fa fa-bullhorn"></i> <a href="dashboard.php?page=news_list.php">Daftar Pengumuman</a></li>
            <li class="active"><i class="fa fa-edit"></i> Edit Pengumuman</li>
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
            echo '</div>';
            unset($_SESSION['flash_message']);
        }
        ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-file-text-o fa-fw"></i> Form Edit Pengumuman (ID: <?php echo htmlspecialchars($current_id_pengumuman); ?>)</h3>
            </div>
            <div class="panel-body">
                <!-- FORM UNTUK EDIT PENGUMUMAN -->
                <form role="form" method="POST" action="dashboard.php?page=news_edit.php&id=<?php echo htmlspecialchars($current_id_pengumuman); ?>" enctype="multipart/form-data">
                    
                    <input type="hidden" name="id_pengumuman" value="<?php echo htmlspecialchars($current_id_pengumuman); ?>">
                    
                    <!-- Judul Pengumuman -->
                    <div class="form-group">
                        <label for="judul">Judul Pengumuman <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" 
                               value="<?php echo $current_judul; ?>" required>
                    </div>

                    <!-- Konten Pengumuman (Textarea) - Menggunakan name="isi" dan variabel $isi -->
                    <div class="form-group">
                        <label for="isi">Konten Pengumuman <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="isi" name="isi" rows="10" required><?php echo $current_isi; ?></textarea>
                    </div>

                    <!-- Informasi Lampiran Lama -->
                    <div class="form-group">
                        <label>Lampiran Saat Ini:</label>
                        <?php if ($current_file_lampiran): ?>
                            <p class="form-control-static">
                                <i class="fa fa-file-o"></i> 
                                <a href="<?php echo $upload_dir . htmlspecialchars($current_file_lampiran); ?>" target="_blank">
                                    <?php echo htmlspecialchars($current_file_lampiran); ?>
                                </a>
                            </p>
                            
                            <!-- Opsi Hapus File Lama -->
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="delete_old_file" value="1"> Hapus lampiran yang lama
                                </label>
                                <p class="help-block text-danger">Centang jika Anda ingin menghapus lampiran ini tanpa menggantinya.</p>
                            </div>
                            
                        <?php else: ?>
                            <p class="form-control-static"><span class="label label-warning">Tidak Ada Lampiran</span></p>
                        <?php endif; ?>
                    </div>

                    <!-- Upload File Lampiran BARU -->
                    <div class="form-group">
                        <label for="file_lampiran">Ganti / Tambah Lampiran Baru (Opsional)</label>
                        <input type="file" id="file_lampiran" name="file_lampiran" class="form-control-file">
                        <p class="help-block">Maks. 5MB. Jika Anda mengunggah file baru, file lama (jika ada) akan dihapus otomatis, kecuali Anda mencentang opsi Hapus Lampiran di atas.</p>
                    </div>

                    <!-- Tombol Aksi -->
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Simpan Perubahan</button>
                    <a href="dashboard.php?page=news_list.php" class="btn btn-default"><i class="fa fa-arrow-circle-left"></i> Batal / Kembali</a>
                </form>
            </div>
        </div>
    </div>
</div>