<?php
// FILE: index.php (Diperbarui untuk menyimpan id_departemen ke Sesi)
// ---------------------------------------------------------------------------------
// 1. Sertakan file konfigurasi dan fungsi
ob_start(); // Tambahkan ob_start() jika belum ada di index.php
include 'config/conn.php';
include 'function.php'; // File ini harus memanggil session_start()

// Pastikan jika user sudah login, dia tidak bisa mengakses halaman index lagi
if (isset($_SESSION['id_user'])) {
    redirect_to('dashboard.php');
}

$error_message = ''; // Variabel untuk menyimpan pesan kesalahan

// 2. Proses Form Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data input dan sanitasi
    $username = mysqli_real_escape_string($conn, $_POST['nip_nidn']); // Nip/Nidn digunakan sebagai username
    $input_password = $_POST['password'];

    // Query untuk mengambil data user
    // PERBAIKAN KRITIS: Tambahkan du.id_departemen ke dalam SELECT statement
    $query = "
    SELECT 
        u.id_user, u.password, du.nama_lengkap, r.nama_role, r.id_role, du.id_departemen  
    FROM 
        user u 
    JOIN 
        detail_user du ON u.id_user = du.id_user
    JOIN
        role r ON du.id_role = r.id_role
    WHERE 
        u.username = '$username'"; // Tambahkan r.id_role ke SELECT

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        // VERIFIKASI PASSWORD MENGGUNAKAN FUNGSI verify_password() dari function.php
        if (verify_password($input_password, $user['password'])) {
            // Password Benar: Buat Session
            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['username'] = $username;
            
            // --- PERBAIKAN KRITIS UNTUK AKSES ---
            $_SESSION['role'] = $user['nama_role']; // Simpan nama role (optional)
            $_SESSION['id_role'] = $user['id_role']; // <<< SIMPAN ID ROLE DI SINI! (Angka 2)
            // ------------------------------------

            // Simpan id_departemen
            if ($user['nama_role'] == 'Departemen') {
                $_SESSION['id_departemen'] = $user['id_departemen'];
            }
            // Jika role lain, id_departemen tidak perlu diset

            redirect_to('dashboard.php');

        } else {
            $error_message = "Username atau Password salah.";
        }
    } else {
        $error_message = "Username tidak terdaftar.";
    }
}

// 3. LOGIKA PAGINATION UNTUK PENGUMUMAN (Kode PHP lainnya sama seperti sebelumnya)
$per_page = 4; // Jumlah pengumuman per halaman

// Menentukan halaman saat ini dari URL. Default ke halaman 1.
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Hitung total pengumuman
$count_query = "SELECT COUNT(*) as total FROM pengumuman";
$count_result = mysqli_query($conn, $count_query);
$total_pengumuman = 0;
if ($count_result) {
    $total_pengumuman = mysqli_fetch_assoc($count_result)['total'];
}

$total_pages = ceil($total_pengumuman / $per_page);

// Pastikan halaman saat ini valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung OFFSET untuk query SQL
$offset = ($current_page - 1) * $per_page;
if ($offset < 0) $offset = 0; // Pastikan offset tidak negatif

// 4. Ambil Data Pengumuman Terbaru (sesuai pagination)
$announcements = [];
$pengumuman_query = "
    SELECT judul, isi, LEFT(isi, 250) as isi_singkat, tgl_dibuat, file_lampiran 
    FROM pengumuman 
    ORDER BY tgl_dibuat DESC 
    LIMIT $per_page 
    OFFSET $offset";

$pengumuman_result = mysqli_query($conn, $pengumuman_query);

if ($pengumuman_result) {
    while ($row = mysqli_fetch_assoc($pengumuman_result)) {
        $announcements[] = $row;
    }
}

// 5. FUNGSI UNTUK TANGGAL DAN WAKTU (PHP)
date_default_timezone_set('Asia/Jakarta'); // Tetapkan zona waktu Indonesia

function format_hari_tanggal($waktu) {
    $hari_indonesia = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    $bulan_indonesia = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    // Menggunakan strtotime jika input adalah string tanggal dari DB, atau langsung menggunakan $waktu jika sudah timestamp
    $timestamp = is_numeric($waktu) ? $waktu : strtotime($waktu);

    $pecah_waktu = explode('-', date('w-d-n-Y', $timestamp));
    $hari = $hari_indonesia[$pecah_waktu[0]];
    $tanggal = $pecah_waktu[1] . ' ' . $bulan_indonesia[$pecah_waktu[2]] . ' ' . $pecah_waktu[3];
    return $hari . ', ' . $tanggal;
}

// Data yang akan ditampilkan di header
$hari_tanggal_sekarang = format_hari_tanggal(time());
// Tambahkan waktu awal untuk di-update oleh JavaScript
$waktu_sekarang_awal = date('H:i:s');
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RAB Manajemen System | E-Finance </title>
<!-- PASTIKAN JALUR FILE INI BENAR: -->
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<style>
/* CSS Kustom untuk Halaman Index (Tidak Diubah) */
body { 
    background-color: #f4f7f9; 
    padding-top: 0; 
    font-family: Arial, sans-serif;
    min-height: 100vh; 
    display: flex;
    flex-direction: column;
}
.header-system {
    background-color: #337ab7; 
    color: white;
    padding: 20px 0;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
.header-system h1 {
    margin: 0;
    font-weight: 300;
    font-size: 24px;
}
.header-system p {
    margin-top: 5px;
    font-size: 14px;
}
.login-panel {
    margin-top: 20px;
    box-shadow: 0 6px 12px rgba(0,0,0,.1);
    border: none;
    border-radius: 8px;
}
.announcement-panel {
    margin-top: 20px;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 6px 12px rgba(0,0,0,.1);
    min-height: 500px; 
}
.announcement-item {
    border-bottom: 1px solid #eee;
    padding: 15px 0;
}
.announcement-item:last-child {
    border-bottom: none;
}
.announcement-title {
    color: #337ab7;
    font-weight: bold;
    margin-top: 0;
    font-size: 1.2em;
    cursor: pointer;
    transition: color 0.2s;
}
.announcement-title:hover {
    color: #337ab7;
    text-decoration: underline;
}
.announcement-date {
    font-size: 0.85em;
    color: #999;
    margin-bottom: 5px;
}
.main-content-wrapper {
    flex: 1; 
    padding-top: 20px;
}
.modal-title {
    color: #337ab7;
}
.header-date {
    text-align: right;
    font-size: 1.1em;
    font-weight: bold;
    margin-top: 10px;
}
/* Menyesuaikan style untuk Jam */
.header-date #live-time {
    display: block; /* Memastikan jam di baris baru */
    font-weight: bold;
    font-size: 1em; 
    margin-top: 5px;
}
.header-date small {
    display: block;
    font-weight: normal;
    font-size: 0.8em;
    opacity: 0.8;
}
.pagination-container {
    margin-top: 20px;
    text-align: center;
}
/* CSS BARU untuk tautan lampiran */
.attachment-link { 
    font-size: 0.9em; 
    margin-top: 10px; 
    display: inline-block; 
}
</style>
</head>
<body>

<div class="header-system">
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <!-- PASTIKAN JALUR FILE INI BENAR: -->
                <img src="./assets/img/syslog.png" width="500px" alt="Logo Universitas" style="float:left; margin-right: 15px;">
            </div>
            <!-- Bagian Kanan untuk Tanggal dan Jam (Diperbarui) -->
            <div class="col-md-4 text-right">
                <h4>Waktu saat ini:</h4>
                <p>
                    <!-- Tampilan Tanggal -->
                    <span class="glyphicon glyphicon-calendar" aria-hidden="true"></span>
                    <?php echo $hari_tanggal_sekarang; ?>
                    <!-- Tampilan Jam Dinamis (akan diperbarui oleh JS) --><br>
                    <small id="live-time">
                        <span class="glyphicon glyphicon-time" aria-hidden="true"></span> 
                        <?php echo $waktu_sekarang_awal; ?> WIB
                    </small>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container"> 
    <!-- Carousel
    ================================================== -->
    <div id="myCarousel" class="carousel slide" data-ride="carousel">
        <!-- Indicators -->
        <ol class="carousel-indicators">
            <li data-target="#myCarousel" data-slide-to="0" class="active"></li>
            <li data-target="#myCarousel" data-slide-to="1"></li>
            <li data-target="#myCarousel" data-slide-to="2"></li>
        </ol>
        <div class="carousel-inner" role="listbox">
            <div class="item active">
            <img class="first-slide" src="assets/img/gb1.jpg" alt="First slide">
            <div class="container">
                <div class="carousel-caption">
                <h1>Example headline.</h1>
                <p>Note: If you're viewing this page via a <code>file://</code> URL, the "next" and "previous" Glyphicon buttons on the left and right might not load/display properly due to web browser security rules.</p>
                </div>
            </div>
            </div>
            <div class="item">
            <img class="second-slide" src="assets/img/gb2.jpg" alt="Second slide">
            <div class="container">
                <div class="carousel-caption">
                <h1>Another example headline.</h1>
                <p>Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus. Nullam id dolor id nibh ultricies vehicula ut id elit.</p>
                </div>
            </div>
            </div>
            <div class="item">
            <img class="third-slide" src="assets/img/gb3.jpg" alt="Third slide">
            <div class="container">
                <div class="carousel-caption">
                <h1>One more for good measure.</h1>
                <p>Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus. Nullam id dolor id nibh ultricies vehicula ut id elit.</p>
                </div>
            </div>
            </div>
        </div>
        <a class="left carousel-control" href="#myCarousel" role="button" data-slide="prev">
            <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
            <span class="sr-only">Previous</span>
        </a>
        <a class="right carousel-control" href="#myCarousel" role="button" data-slide="next">
            <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
            <span class="sr-only">Next</span>
        </a>
    </div><!-- /.carousel -->
</div>

<div class="container main-content-wrapper">
    <div class="row">

        <div class="col-md-8">
            <div class="announcement-panel">
                <h3 style="border-bottom: 2px solid #3e0057ff; padding-bottom: 10px; margin-top: 0;">
                    <span class="glyphicon glyphicon-bullhorn"></span> Pengumuman & Berita Keuangan 
                    <small class="pull-right text-muted">
                        <?php if ($total_pages > 0) {
                            echo "Halaman " . $current_page . " dari " . $total_pages;
                        } else {
                            echo "Belum ada data";
                        } ?>
                    </small>
                </h3>

                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $a): ?>
                        <div class="announcement-item">
                            <p class="announcement-date">
                                <span class="glyphicon glyphicon-time"></span> Diterbitkan: <?php echo format_hari_tanggal($a['tgl_dibuat']); ?>
                            </p>
                            
                            <!-- Judul yang memicu modal, sekarang membawa data-lampiran -->
                            <h4 class="announcement-title"
                                data-toggle="modal" 
                                data-target="#announcementModal" 
                                data-judul="<?php echo htmlspecialchars($a['judul']); ?>"
                                data-isi="<?php echo htmlspecialchars($a['isi']); ?>"
                                data-tanggal="<?php echo format_hari_tanggal($a['tgl_dibuat']); ?>"
                                data-lampiran="<?php echo htmlspecialchars($a['file_lampiran'] ?? ''); ?>">
                                <?php echo htmlspecialchars($a['judul']); ?>
                                <?php if (!empty($a['file_lampiran'])): ?>
                                    <!-- Menambahkan ikon klip jika ada lampiran -->
                                    <span class="glyphicon glyphicon-paperclip text-info" style="font-size: 0.7em;"></span>
                                <?php endif; ?>
                            </h4>

                            <!-- Isi singkat tetap ditampilkan -->
                            <p><?php echo htmlspecialchars($a['isi_singkat']); ?>...</p>

                            <!-- Menampilkan tautan lampiran di preview jika ada -->
                            <?php if (!empty($a['file_lampiran'])): ?>
                                <span class="attachment-link text-info">
                                    <span class="glyphicon glyphicon-download"></span> Terdapat Lampiran: 
                                    <a href="assets/uploads/<?php echo htmlspecialchars($a['file_lampiran']); ?>" target="_blank" class="text-info">
                                        <?php echo htmlspecialchars($a['file_lampiran']); ?>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- KONTROL PAGINATION -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <ul class="pagination">
                                <!-- Tombol Sebelumnya -->
                                <li class="<?php echo ($current_page <= 1 ? 'disabled' : ''); ?>">
                                    <a href="<?php echo ($current_page <= 1 ? '#' : '?page=' . ($current_page - 1)); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span> Sebelumnya
                                    </a>
                                </li>

                                <!-- Nomor Halaman -->
                                <?php 
                                // Logic untuk menampilkan sekitar 5 tombol halaman
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="<?php echo ($i == $current_page ? 'active' : ''); ?>">
                                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Tombol Berikutnya -->
                                <li class="<?php echo ($current_page >= $total_pages ? 'disabled' : ''); ?>">
                                    <a href="<?php echo ($current_page >= $total_pages ? '#' : '?page=' . ($current_page + 1)); ?>" aria-label="Next">
                                        Berikutnya <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-info">
                        Belum ada pengumuman terbaru dari Direktur Keuangan atau SysAdmin.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel panel-primary login-panel">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">
                        <span class="glyphicon glyphicon-lock"></span> AKSES RAB SYSTEM
                    </h3>
                </div>
                <div class="panel-body">
                    <?php 
                    // Tampilkan pesan error jika ada
                    if ($error_message) {
                        echo '<div class="alert alert-danger">' . $error_message . '</div>';
                    }
                    ?>
                    <form role="form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <fieldset>
                        <div class="form-group">
                            <label for="nip_nidn">Username (NIP/NIDN)</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                <input class="form-control" placeholder="Masukkan NIP/NIDN" name="nip_nidn" type="text" autofocus required value="<?php echo isset($_POST['nip_nidn']) ? htmlspecialchars($_POST['nip_nidn']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-cog"></i></span>
                                <input class="form-control" placeholder="Masukkan Password" name="password" type="password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-lg btn-success btn-block">
                            <span class="glyphicon glyphicon-log-in"></span> Login
                        </button>
                    </fieldset>
                    </form>
                </div>
            </div>
            <div class="text-center" style="margin-top: 20px;">
                <small>Jika mengalami kesulitan untuk login, silahkan menghubungi <a href="#">ADMINISTRATOR</a>.</small>
            </div>
            <hr>
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">
                        <span class="glyphicon glyphicon-user"></span> PENGELOLA
                    </h3>
                </div>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <img src="assets/img/users/catur.jpg" class="img-circle" width="80px">
                    </div>
                    <div class="col-md-8">
                        <p style="margin-top: 15px;">
                            <b>Catur Winarsih, S.Kom., M.M.</b><br>
                            Direktur Keuangan
                        </p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <img src="assets/img/users/amel.jpg" class="img-circle" width="80px">
                    </div>
                    <div class="col-md-8">
                        <p style="margin-top: 15px;">
                            <b>Dwi Amelia Putri, SE.</b><br>
                            Staf Bendahara
                        </p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <img src="assets/img/users/yuni.jpg" class="img-circle" width="80px">
                    </div>
                    <div class="col-md-8">
                        <p style="margin-top: 15px;">
                            <b>Yuni Tri Leastari, S.Ak.</b><br>
                            Staf Accounting
                        </p>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
<hr>
<div class="container">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">
                        <span class="glyphicon glyphicon-home"></span> KONTAK
                    </h3>
                </div>
            </div>

            <div class="panel-body">
                <h4><span class="glyphicon glyphicon-road"></span> Alamat :</h4>
                <p>Gedung Utama Universitas Amikom Purwokerto. Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127</p>
                <h4><span class="glyphicon glyphicon-phone-alt"></span> Telp :</h4>
                <p>(0281) 623321</p>
                <h4><span class="glyphicon glyphicon-envelope"></span> Email :</h4>
                <p>dpkku@amikompurwokerto.ac.id</p>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">
                        <span class="glyphicon glyphicon-map-marker"></span> MAP
                    </h3>
                </div>
            </div>

            <div class="panel-body">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3956.5810804818652!2d109.22884757500108!3d-7.400745992609247!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e655f072387fab9%3A0x269c1733d358d2b7!2sUniversitas%20Amikom%20Purwokerto%20-%20Gedung%20Utama!5e0!3m2!1sid!2sid!4v1762994683751!5m2!1sid!2sid" width="520" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container-fluid">
        <hr>
        <p class="text-center text-muted">
            &copy; <?php echo date('Y'); ?> Sistem Informasi RAB Manajemen System E-Finance | Direktorat Perencanaan Keuangan - Universitas Amikom Purwokerto.<br>
            Project By: <a href="#">Educollabs</a> | E-Finance Versi 1.0
        </p>
    </div>
</footer>

<!-- MODAL UNTUK DETAIL PENGUMUMAN -->
<div class="modal fade" id="announcementModal" tabindex="-1" role="dialog" aria-labelledby="announcementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="announcementModalLabel">Judul Pengumuman</h4>
                <small class="text-muted" id="modal-date"></small>
            </div>
            <div class="modal-body">
                <!-- Konten penuh pengumuman akan ditampilkan di sini -->
                <p id="modal-content" style="white-space: pre-wrap;"></p>
                <!-- Tambahan untuk Lampiran -->
                <hr id="modal-divider" style="display:none;">
                <div id="modal-attachment-container"></div> 
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- PENTING: Menggunakan link lokal Anda, pastikan file ada di assets/js/ -->
<script src="assets/js/jquery-1.12.4.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>

<!-- Script jQuery untuk mengisi data Modal dan Jam Dinamis (Diperbarui) -->
<script>
    $(document).ready(function() {
        // --- LOGIKA JAM DINAMIS (LIVE CLOCK) ---
        function updateClock() {
            // Mengambil waktu saat ini di sisi klien
            const now = new Date();
            // Format waktu (HH:MM:SS) dan memastikan dua digit
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            // Memperbarui elemen dengan ID 'live-time'
            $('#live-time').html(`<span class="glyphicon glyphicon-time" aria-hidden="true"></span> ${hours}:${minutes}:${seconds} WIB`);
        }
        
        // Memanggil fungsi updateClock setiap 1000 milidetik (1 detik)
        setInterval(updateClock, 1000);
        // Panggil sekali saat load untuk memastikan waktu terupdate segera
        updateClock();


        // --- LOGIKA MODAL PENGUMUMAN ---
        const attachmentBasePath = 'assets/uploads/'; 

        $('#announcementModal').on('show.bs.modal', function (event) {
            // Mendapatkan elemen yang memicu modal (yaitu <h4> judul)
            var triggerElement = $(event.relatedTarget); 
            var judul = triggerElement.data('judul'); 
            var isi = triggerElement.data('isi');
            var tanggal = triggerElement.data('tanggal');
            var lampiran = triggerElement.data('lampiran'); 

            var modal = $(this);
            // Memasukkan data ke elemen-elemen di dalam modal
            modal.find('.modal-title').text(judul);
            modal.find('#modal-date').text('Diterbitkan: ' + tanggal);
            modal.find('#modal-content').text(isi); 

            // LOGIKA LAMPIRAN
            $('#modal-attachment-container').empty();
            $('#modal-divider').hide();

            if (lampiran) {
                // Jika ada lampiran, tampilkan tautan unduh
                $('#modal-divider').show();
                const attachmentHtml = `
                    <div class="alert alert-info" style="margin-top: 15px;">
                        <strong>Lampiran Tersedia:</strong> 
                        <a href="${attachmentBasePath}${lampiran}" target="_blank" class="alert-link">
                            <span class="glyphicon glyphicon-download"></span> Unduh File Lampiran (${lampiran})
                        </a>
                    </div>
                `;
                $('#modal-attachment-container').append(attachmentHtml);
            }
        });
    });
</script>

</body>
</html>