<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php';
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php');
}

// ----------------------------------------------------
// 1. LOGIKA READ DATA LAMA (FETCHING)
// ----------------------------------------------------
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "error|ID Pengguna tidak ditemukan.";
    redirect_to('dashboard.php?page=user_list.php');
}

$id_user_edit = mysqli_real_escape_string($conn, $_GET['id']);

$query_old_data = "
    SELECT u.username, du.*
    FROM user u
    JOIN detail_user du ON u.id_user = du.id_user
    WHERE u.id_user = '$id_user_edit'";
        
$result_old_data = mysqli_query($conn, $query_old_data);

if (mysqli_num_rows($result_old_data) == 0) {
    $_SESSION['flash_message'] = "error|Data pengguna dengan ID tersebut tidak ditemukan.";
    redirect_to('dashboard.php?page=user_list.php');
}

$old_data = mysqli_fetch_assoc($result_old_data);


// ----------------------------------------------------
// 2. LOGIKA PROSES UPDATE USER (POST REQUEST)
// ----------------------------------------------------
$errors = []; 
$current_foto_filename = $old_data['foto']; // Default menggunakan nama foto lama

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // A. Ambil dan Bersihkan Data POST
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']); 
    $nip_nidn = mysqli_real_escape_string($conn, $_POST['nip_nidn']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telp = mysqli_real_escape_string($conn, $_POST['telp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $id_role = mysqli_real_escape_string($conn, $_POST['id_role']);
    $id_departemen = mysqli_real_escape_string($conn, $_POST['id_departemen']);

    // B. Validasi Data
    if (empty($username) || empty($nip_nidn) || empty($nama_lengkap) || empty($id_role) || empty($id_departemen)) {
        $errors[] = "Semua field bertanda * harus diisi.";
    }

    $check_user = mysqli_query($conn, "SELECT id_user FROM user WHERE username = '$username' AND id_user != '$id_user_edit'");
    if (mysqli_num_rows($check_user) > 0) {
        $errors[] = "Username **$username** sudah digunakan oleh pengguna lain.";
    }
    
    // C. Proses Upload Foto (MENGGUNAKAN ABSOLUTE PATH)
    $foto_temp = isset($_FILES['foto']) ? $_FILES['foto'] : null;
    $target_file = null;

    if (empty($errors) && $foto_temp && $foto_temp['error'] == 0) {
        
        // MENDAPATKAN PATH ABSOLUT SERVER
        $base_path = $_SERVER['DOCUMENT_ROOT'] . '/efinance/'; 
        $target_dir = $base_path . "assets/img/users/"; // Path Folder Upload
        
        $image_file_type = strtolower(pathinfo($foto_temp['name'], PATHINFO_EXTENSION));
        $new_foto_filename = uniqid('user_') . '.' . $image_file_type;
        $target_file = $target_dir . $new_foto_filename;
        
        // Validasi
        if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg") {
            $errors[] = "Format file foto harus JPG, JPEG, atau PNG.";
        }
        if ($foto_temp['size'] > 2000000) {
            $errors[] = "Ukuran file foto maksimal 2MB.";
        }
        
        if (empty($errors)) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            // Pindahkan file baru
            if (move_uploaded_file($foto_temp['tmp_name'], $target_file)) {
                // Hapus foto lama di server (jika ada dan bukan default)
                $delete_old_path = $target_dir . $current_foto_filename;
                if ($current_foto_filename && $current_foto_filename !== 'user.png' && file_exists($delete_old_path)) {
                    unlink($delete_old_path);
                }
                $current_foto_filename = $new_foto_filename; // Set nama foto ke yang baru
            } else {
                $errors[] = "Gagal mengupload foto baru ke server.";
            }
        }
    } 

    // D. Proses Update ke Database
    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        try {
            // 1. UPDATE Tabel user (HANYA username dan password)
            $update_user_fields = "username = '$username'"; 
            
            // Tambahkan password jika diisi
            if (!empty($new_password)) {
                $hashed_password = hash_password($new_password);
                $update_user_fields .= ", password = '$hashed_password'";
            }
            
            $query_user_update = "UPDATE user SET $update_user_fields WHERE id_user = '$id_user_edit'";
            if (!mysqli_query($conn, $query_user_update)) {
                throw new Exception("Gagal memperbarui data login. " . mysqli_error($conn));
            }

            // 2. UPDATE Tabel detail_user (Termasuk id_role, telp, alamat, foto)
            $query_detail_update = "
                UPDATE detail_user 
                SET 
                    nip_nidn = '$nip_nidn', 
                    nama_lengkap = '$nama_lengkap', 
                    id_departemen = '$id_departemen', 
                    id_role = '$id_role', 
                    email = '$email', 
                    telp = '$telp', 
                    alamat = '$alamat', 
                    foto = '$current_foto_filename'
                WHERE 
                    id_user = '$id_user_edit'";
            
            if (!mysqli_query($conn, $query_detail_update)) {
                throw new Exception("Gagal memperbarui data detail user. " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['flash_message'] = "success|Pengguna **$nama_lengkap** berhasil diperbarui!";
            redirect_to('dashboard.php?page=user_list.php');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Proses pembaruan pengguna gagal: " . $e->getMessage();
            // Jika transaksi gagal dan foto baru terupload, hapus foto baru tersebut
            if (isset($target_file) && $current_foto_filename !== $old_data['foto'] && file_exists($target_file)) {
                unlink($target_file);
            }
        }
    }
    // Jika ada error, muat ulang data lama agar form terisi
    $old_data = array_merge($old_data, $_POST); 
    $old_data['foto'] = $current_foto_filename; 
}


// ----------------------------------------------------
// 3. LOGIKA READ: Ambil data Role dan Departemen untuk dropdown
// ----------------------------------------------------
$query_role = "SELECT id_role, nama_role FROM role ORDER BY nama_role ASC";
$result_role = mysqli_query($conn, $query_role);

$query_departemen = "SELECT id_departemen, nama_departemen FROM departemen ORDER BY nama_departemen ASC";
$result_departemen = mysqli_query($conn, $query_departemen);

// Tentukan path foto saat ini untuk ditampilkan (path web)
$foto_display_path = (!empty($old_data['foto']) && file_exists('assets/img/users/' . $old_data['foto'])) ? 
                     'assets/img/users/' . $old_data['foto'] : 
                     'assets/img/user.png';
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-edit"></span> Edit Pengguna: <?php echo htmlspecialchars($old_data['nama_lengkap']); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?page=user_list.php">Manajemen Pengguna</a></li>
            <li class="active">Edit Pengguna</li>
        </ol>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <strong>Gagal:</strong>
        <ul>
        <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Form Edit Data Pengguna</h3>
            </div>
            <div class="panel-body">
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_user" value="<?php echo $id_user_edit; ?>">

                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan Username" required 
                               value="<?php echo htmlspecialchars($old_data['username']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Password Baru (Kosongkan jika tidak diubah)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Masukkan Password Baru (Minimal 6 karakter)">
                        <p class="help-block">Biarkan kosong jika tidak ingin mengubah password.</p>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>NIP/NIDN *</label>
                        <input type="text" name="nip_nidn" class="form-control" placeholder="Nomor Induk Pegawai/Dosen" required
                               value="<?php echo htmlspecialchars($old_data['nip_nidn']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama Lengkap dengan Gelar" required
                               value="<?php echo htmlspecialchars($old_data['nama_lengkap']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="contoh@amikom.ac.id" 
                               value="<?php echo htmlspecialchars($old_data['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="telp" class="form-control" placeholder="Contoh: 0812xxxxxx" 
                               value="<?php echo htmlspecialchars($old_data['telp']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat Domisili"><?php echo htmlspecialchars($old_data['alamat']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Foto Profil Saat Ini</label>
                        <p>
                            <img src="<?php echo $foto_display_path; ?>" alt="Foto Saat Ini" width="100" height="100" class="img-thumbnail">
                            <span class="help-block">Nama File: <?php echo htmlspecialchars($old_data['foto']); ?></span>
                        </p>
                        <label>Ganti Foto (Max 2MB, JPG/PNG)</label>
                        <input type="file" name="foto" class="form-control">
                        <p class="help-block">Pilih file baru untuk mengganti foto di atas.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Role / Peran *</label>
                        <select name="id_role" class="form-control" required>
                            <option value="">-- Pilih Role --</option>
                            <?php 
                            mysqli_data_seek($result_role, 0); // Reset pointer
                            while($role = mysqli_fetch_assoc($result_role)): 
                                $selected = ($old_data['id_role'] == $role['id_role']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $role['id_role']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($role['nama_role']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Departemen *</label>
                        <select name="id_departemen" class="form-control" required>
                            <option value="">-- Pilih Departemen --</option>
                            <?php 
                            mysqli_data_seek($result_departemen, 0); // Reset pointer
                            while($dept = mysqli_fetch_assoc($result_departemen)): 
                                $selected = ($old_data['id_departemen'] == $dept['id_departemen']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $dept['id_departemen']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($dept['nama_departemen']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="text-right">
                        <a href="dashboard.php?page=user_list.php" class="btn btn-default">Batal</a>
                        <button type="submit" class="btn btn-success">
                            <span class="glyphicon glyphicon-ok"></span> Perbarui Pengguna
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>