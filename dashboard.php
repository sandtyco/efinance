<?php
// FILE: /efinance/dashboard.php

// =========================================================
// PERBAIKAN KRITIS: OUTPUT BUFFERING
// Baris ini harus menjadi BARIS KODE PHP PERTAMA di file ini
ob_start(); 
// =========================================================

// PASTIKAN: file 'function.php' sudah memiliki session_start() di dalamnya
include 'config/conn.php';
include_once 'function.php'; // Di sini session_start() dipanggil

// 1. Cek User Login
if (!isset($_SESSION['id_user'])) {
    redirect_to('index.php');
}

$role = $_SESSION['role']; 
$id_user = $_SESSION['id_user']; 
$nama_user = $_SESSION['nama'];

// BARIS INI PENTING: INISIALISASI VARIABEL FOOTER SCRIPT
$footer_scripts = []; 

// 2. Tentukan Folder dan Halaman Default berdasarkan Role
$base_dir = 'pages/';
$folder = '';
$default_page = '';

switch ($role) {
    case 'SysAdmin':
        $folder = 'sysadmin/';
        $default_page = 'dashboard_admin.php'; 
        break;
    case 'Departemen':
        $folder = 'departemen/';
        $default_page = 'dashboard_dept.php';
        break;
    case 'Direktur Keuangan':
        $folder = 'keuangan/';
        $default_page = 'dashboard_keu.php';
        break;
    case 'Rektorat':
        $folder = 'rektorat/';
        $default_page = 'dashboard_rekt.php';
        break;
    default:
        redirect_to('logout.php');
        break;
}

// ========================================================
// 3. LOGIKA PENENTUAN KONTEN YANG AKAN DI-INCLUDE
// ========================================================
$requested_filename = isset($_GET['page']) ? basename($_GET['page']) : $default_page;
$content_page = ''; // Inisialisasi kosong

// Urutan prioritas pencarian folder untuk konten yang diminta:
// 1. Coba folder role yang sedang login (paling spesifik)
// 2. Coba folder 'departemen' (karena banyak list dan view ada di sini)
// 3. Coba folder 'sysadmin' (untuk halaman manajemen umum)
// 4. Coba folder 'laporan'

$search_paths = [
    $folder, // 1. Folder Role Saat Ini (misal: 'keuangan/')
    'departemen/', // 2. Folder Departemen (List RAB/Transaksi, View Detail)
    'sysadmin/', // 3. Folder Admin (User Management, Akun Anggaran)
    'laporan/', // 4. Folder Laporan
];

// Loop untuk mencari file di setiap folder
foreach ($search_paths as $search_folder) {
    $temp_path = $base_dir . $search_folder . $requested_filename;
    
    // Pengecualian: Pastikan Direktur Keuangan hanya mengakses RAB/Transaksi Approval/Validation
    // dan tidak sengaja mengakses Form Tambah/Edit RAB milik departemen.
    if ($role === 'Direktur Keuangan' || $role === 'Rektorat') {
        // Jika file yang diminta adalah form input Departemen, lewati pencarian ini
        if (in_array($requested_filename, ['rab_add.php', 'rab_edit.php', 'transaksi_add.php', 'transaksi_edit.php'])) {
            continue;
        }
    }

    if (file_exists($temp_path)) {
        $content_page = $temp_path;
        break; // Ditemukan, hentikan pencarian
    }
}

// 4. Fallback ke Halaman Default jika tidak ada yang ditemukan
if (empty($content_page)) {
    $content_page = $base_dir . $folder . $default_page;
    // Cek lagi apakah halaman default ditemukan
    if (!file_exists($content_page)) {
        // Fallback ultimate jika default dashboard pun hilang
        $content_page = 'pages/error/404.php'; // Asumsikan Anda memiliki halaman 404
        // Atau: $content_page = $base_dir . 'welcome.php';
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard <?php echo " | " . htmlspecialchars($role); ?> | E-Finance</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css"/>

    <link rel="stylesheet" href="assets/css/carousel.css">

    <style>
    body { padding-top: 70px; } 
    .sidebar {
    position: fixed; top: 50px; bottom: 0; left: 0; z-index: 1000;
    display: block; padding: 20px; overflow-x: hidden; overflow-y: auto; 
    background-color: #f8f8f8; border-right: 1px solid #eee;
    }
    .main-content { padding-right: 40px; padding-left: 40px; }
    @media (min-width: 768px) {
    .main-content { padding-right: 20px; padding-left: 20px; }
    }
    .sidebar-header { color: #999; font-size: 0.8em; margin-top: 15px; margin-bottom: 5px; padding-left: 10px; text-transform: uppercase; }
    .sidebar .dropdown-menu { position: relative; margin: 0; border: none; box-shadow: none; background-color: transparent; width: 100%; padding-left: 20px; }
    .sidebar .dropdown-menu > li > a { padding: 5px 15px; color: #333; }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
<div class="row">
<div class="col-sm-3 col-md-2 sidebar">
<?php include 'includes/sidebar.php'; ?>
</div>

<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main-content">

<?php 
if (file_exists($content_page)) {
    include $content_page; 
} else {
    // Pada titik ini, harusnya $content_page sudah berisi path yang valid (termasuk fallback default)
    // Jika masih masuk ke sini, ada masalah path yang sangat spesifik.
    echo '<div class="alert alert-danger">Kesalahan Fatal: File konten tidak dapat dimuat di path: ' . htmlspecialchars($content_page) . '</div>';
}
?>

</div>
</div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/jquery-1.12.4.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>

<script type="text/javascript" src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<script src="assets/js/holder.min.js"></script>

<?php 
// 2. MENCETAK SEMUA SKRIP YANG DIKUMPULKAN
if (!empty($footer_scripts)) {
echo implode("\n", $footer_scripts); // Ini akan mencetak skrip AJAX dari header.php
}
?>

<script>
$(document).ready(function () {
// Kode JS global seperti dropdown, dsb.
$('.dropdown-toggle').dropdown();
});
</script>
</body>
</html>

<?php
// AKHIR OUTPUT BUFFERING
ob_end_flush();
?>