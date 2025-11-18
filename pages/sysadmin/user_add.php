<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php';
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php');
}

// ----------------------------------------------------
// LOGIKA PROSES TAMBAH USER (POST REQUEST)
// ----------------------------------------------------
$errors = []; 
$foto_filename = 'user.png'; // Default foto

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil dan Bersihkan Data
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $nip_nidn = mysqli_real_escape_string($conn, $_POST['nip_nidn']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telp = mysqli_real_escape_string($conn, $_POST['telp']); 
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']); 
    $id_role = mysqli_real_escape_string($conn, $_POST['id_role']);
    $id_departemen = mysqli_real_escape_string($conn, $_POST['id_departemen']);

    // 2. Validasi Data
    if (empty($username) || empty($password) || empty($nip_nidn) || empty($nama_lengkap) || empty($id_role) || empty($id_departemen)) {
        $errors[] = "Semua field bertanda * harus diisi.";
    }

    $check_user = mysqli_query($conn, "SELECT id_user FROM user WHERE username = '$username'");
    if (mysqli_num_rows($check_user) > 0) {
        $errors[] = "Username **$username** sudah digunakan. Mohon gunakan username lain.";
    }
    
    // 3. Proses Upload Foto (MENGGUNAKAN ABSOLUTE PATH)
    $target_file = null;
    if (empty($errors) && isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        
        // MENDAPATKAN PATH ABSOLUT SERVER
        $base_path = $_SERVER['DOCUMENT_ROOT'] . '/efinance/'; 
        $target_dir = $base_path . "assets/img/users/"; // Path Folder Upload
        
        $image_file_type = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $foto_filename = uniqid('user_') . '.' . $image_file_type;
        $target_file = $target_dir . $foto_filename;
        
        // Validasi
        if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg") {
            $errors[] = "Format file foto harus JPG, JPEG, atau PNG.";
        }
        if ($_FILES['foto']['size'] > 2000000) {
            $errors[] = "Ukuran file foto maksimal 2MB.";
        }
        
        // Pindahkan file jika tidak ada error
        if (empty($errors)) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true); // Pastikan folder dibuat
            }
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $errors[] = "Gagal mengupload foto ke server.";
                $foto_filename = 'user.png'; // Kembali ke default jika gagal
            }
        } else {
            $foto_filename = 'user.png';
        }
    }


    // 4. Proses Simpan ke Database
    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        try {
            // Hash Password
            $hashed_password = hash_password($password);

            // A. INSERT ke Tabel user (HANYA username dan password)
            $query_user = "INSERT INTO user (username, password) VALUES ('$username', '$hashed_password')"; 
            if (!mysqli_query($conn, $query_user)) {
                throw new Exception("Gagal menyimpan data user. " . mysqli_error($conn));
            }

            $last_user_id = mysqli_insert_id($conn);

            // B. INSERT ke Tabel detail_user (Termasuk id_role, telp, alamat, foto)
            $query_detail = "INSERT INTO detail_user (id_user, nip_nidn, nama_lengkap, id_departemen, id_role, email, telp, alamat, foto) 
                             VALUES ('$last_user_id', '$nip_nidn', '$nama_lengkap', '$id_departemen', '$id_role', '$email', '$telp', '$alamat', '$foto_filename')";
            
            if (!mysqli_query($conn, $query_detail)) {
                 // Jika gagal, hapus foto yang sudah terupload (jika ada)
                if ($foto_filename !== 'user.png' && $target_file && file_exists($target_file)) {
                    unlink($target_file);
                }
                throw new Exception("Gagal menyimpan data detail user. " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['flash_message'] = "success|Pengguna **$nama_lengkap** berhasil ditambahkan!";
            redirect_to('dashboard.php?page=user_list.php');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Proses penambahan pengguna gagal: " . $e->getMessage();
        }
    }
}

// ----------------------------------------------------
// LOGIKA READ: Ambil data Role dan Departemen untuk dropdown
// ----------------------------------------------------
$query_role = "SELECT id_role, nama_role FROM role ORDER BY nama_role ASC";
$result_role = mysqli_query($conn, $query_role);

$query_departemen = "SELECT id_departemen, nama_departemen FROM departemen ORDER BY nama_departemen ASC";
$result_departemen = mysqli_query($conn, $query_departemen);
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-plus-sign"></span> Tambah Pengguna Baru
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?page=user_list.php">Manajemen Pengguna</a></li>
            <li class="active">Tambah Pengguna</li>
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
                <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Form Data Pengguna</h3>
            </div>
            <div class="panel-body">
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan Username" required 
                               value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan Password (Minimal 6 karakter)" required>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>NIP/NIDN *</label>
                        <input type="text" name="nip_nidn" class="form-control" placeholder="Nomor Induk Pegawai/Dosen" required
                               value="<?php echo isset($nip_nidn) ? htmlspecialchars($nip_nidn) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama Lengkap dengan Gelar" required
                               value="<?php echo isset($nama_lengkap) ? htmlspecialchars($nama_lengkap) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="contoh@amikom.ac.id" 
                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="telp" class="form-control" placeholder="Contoh: 0812xxxxxx" 
                               value="<?php echo isset($telp) ? htmlspecialchars($telp) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat Domisili"><?php echo isset($alamat) ? htmlspecialchars($alamat) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Foto Profil (Max 2MB, JPG/PNG)</label>
                        <input type="file" name="foto" class="form-control">
                        <p class="help-block">Biarkan kosong jika tidak ingin mengupload foto. Default: user.png.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Role / Peran *</label>
                        <select name="id_role" class="form-control" required>
                            <option value="">-- Pilih Role --</option>
                            <?php while($role = mysqli_fetch_assoc($result_role)): ?>
                                <option value="<?php echo $role['id_role']; ?>" 
                                    <?php if(isset($id_role) && $id_role == $role['id_role']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($role['nama_role']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Departemen *</label>
                        <select name="id_departemen" class="form-control" required>
                            <option value="">-- Pilih Departemen --</option>
                            <?php while($dept = mysqli_fetch_assoc($result_departemen)): ?>
                                <option value="<?php echo $dept['id_departemen']; ?>"
                                    <?php if(isset($id_departemen) && $id_departemen == $dept['id_departemen']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($dept['nama_departemen']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="text-right">
                        <a href="dashboard.php?page=user_list.php" class="btn btn-default">Batal</a>
                        <button type="submit" class="btn btn-success">
                            <span class="glyphicon glyphicon-save"></span> Simpan Pengguna
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>