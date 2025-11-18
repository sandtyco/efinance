<?php 
// FILE: /efinance/includes/header.php

// Asumsi: $conn sudah tersedia di dashboard.php sebelum header.php di-include
// Variabel sesi
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Tamu';
$role_user = isset($_SESSION['role']) ? $_SESSION['role'] : 'N/A';
$id_user_session = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 0; 

// PENTING: Deklarasikan global $footer_scripts agar bisa diisi
global $footer_scripts;

// -------------------------------------------------------------------
// QUERY FETCH DATA DETAIL USER YANG SEDANG LOGIN
// -------------------------------------------------------------------
$data_profil = [];
if ($id_user_session != 0 && isset($conn)) {
    $profil_query = "
        SELECT 
            u.username, 
            du.nip_nidn, 
            du.nama_lengkap, 
            du.email, 
            du.telp,
            du.alamat,
            r.nama_role
        FROM 
            user u
        JOIN 
            detail_user du ON u.id_user = du.id_user
        JOIN
            role r ON du.id_role = r.id_role
        WHERE 
            u.id_user = '$id_user_session'";
    
    $profil_result = mysqli_query($conn, $profil_query);
    if ($profil_result && mysqli_num_rows($profil_result) > 0) {
        $data_profil = mysqli_fetch_assoc($profil_result);
    }
}
?>

<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="dashboard.php">
                <img src="./assets/img/log.png" width="250px" alt="logo">
            </a>
        </div>

        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav navbar-right">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <span class="glyphicon glyphicon-user"></span> 
                        Halo, Anda login sebagai: <?php echo $nama_user; ?> (<?php echo $role_user; ?>) 
                        <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li>
                            <a href="#" data-toggle="modal" data-target="#profilModal">
                                <span class="glyphicon glyphicon-cog"></span> Profil Saya
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li><a href="logout.php"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
body { padding-top: 100px; } 
</style>

<div class="modal fade" id="profilModal" tabindex="-1" role="dialog" aria-labelledby="profilModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="profilModalLabel"><span class="glyphicon glyphicon-user"></span> Profil Saya</h4>
            </div>
            <div class="modal-body">
                
                <?php if (!empty($data_profil)): ?>
                
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active"><a href="#detail" aria-controls="detail" role="tab" data-toggle="tab">Detail</a></li>
                    <li role="presentation"><a href="#password" aria-controls="password" role="tab" data-toggle="tab">Ubah Password</a></li>
                </ul>

                <div class="tab-content" style="padding-top: 15px;">
                    
                    <div role="tabpanel" class="tab-pane active" id="detail">
                        <table class="table table-hover">
                            <tr><th>Username</th><td><?php echo htmlspecialchars($data_profil['username']); ?></td></tr>
                            <tr><th>NIP/NIDN</th><td><?php echo htmlspecialchars($data_profil['nip_nidn']); ?></td></tr>
                            <tr><th>Nama Lengkap</th><td><?php echo htmlspecialchars($data_profil['nama_lengkap']); ?></td></tr>
                            <tr><th>Role</th><td><span class="label label-primary"><?php echo htmlspecialchars($data_profil['nama_role']); ?></span></td></tr>
                            <tr><th>Email</th><td><?php echo htmlspecialchars($data_profil['email']); ?></td></tr>
                            <tr><th>Telepon</th><td><?php echo htmlspecialchars($data_profil['telp']); ?></td></tr>
                            <tr><th>Alamat</th><td><?php echo htmlspecialchars($data_profil['alamat']); ?></td></tr>
                        </table>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="password">
                        <form id="formUbahPassword" action="ajax/update_password.php" method="POST">
                            <input type="hidden" name="id_user" value="<?php echo htmlspecialchars($id_user_session); ?>">
                            
                            <div class="form-group">
                                <label for="password_lama">Password Lama <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_lama" name="password_lama" required>
                            </div>
                            <div class="form-group">
                                <label for="password_baru">Password Baru <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_baru" name="password_baru" required>
                            </div>
                            <div class="form-group">
                                <label for="konfirmasi_baru">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="konfirmasi_baru" name="konfirmasi_baru" required>
                            </div>

                            <button type="submit" class="btn btn-danger btn-block">
                                <span class="glyphicon glyphicon-refresh"></span> Ubah Password
                            </button>
                            <div id="passwordMessage" style="margin-top: 10px;"></div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                    <div class="alert alert-danger">Gagal mengambil data profil. Silakan coba *refresh* halaman.</div>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php
// Pastikan skrip ini diapit oleh tanda kutip ganda atau menggunakan backslash untuk escape
$script_profil_ajax = "
<script>
$(document).ready(function() {
    $('#formUbahPassword').submit(function(e) {
        e.preventDefault(); 
        
        var form = $(this);
        var url = form.attr('action');
        var messageDiv = $('#passwordMessage');
        messageDiv.html(''); 

        var passBaru = $('#password_baru').val();
        var konfirmasi = $('#konfirmasi_baru').val();

        if (passBaru !== konfirmasi) {
            messageDiv.html('<div class=\"alert alert-warning\">Konfirmasi password baru tidak cocok.</div>');
            return;
        }

        // Kirim data menggunakan AJAX
        $.ajax({
            type: \"POST\",
            url: url,
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    messageDiv.html('<div class=\"alert alert-success\">' + response.message + '</div>');
                    form[0].reset(); 
                    
                    // --- LOGIKA AUTO-LOGOUT ---
                    setTimeout(function() {
                        window.location.href = 'logout.php'; 
                    }, 2000); 
                    
                } else {
                    messageDiv.html('<div class=\"alert alert-danger\">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                // Ini akan terpicu jika PHP mengembalikan non-JSON (misal: Fatal Error, HTML)
                messageDiv.html('<div class=\"alert alert-danger\">Terjadi kesalahan pada respons server. (Status: ' + status + ')</div>');
            }
        });
    });

    $('#profilModal').on('hidden.bs.modal', function () {
        $('#formUbahPassword')[0].reset();
        $('#passwordMessage').html('');
    });
});
</script>";

$footer_scripts[] = $script_profil_ajax;
?>