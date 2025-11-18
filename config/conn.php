<?php
// Pengaturan Koneksi Database
$db_host    = "localhost"; // Sesuaikan jika server database di tempat lain
$db_user    = "root";      // Ganti dengan username database Anda
$db_pass    = "";          // Ganti dengan password database Anda
$db_name    = "finance";   // Nama database yang sudah Anda buat

// Membuat Koneksi
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Cek Koneksi
if (mysqli_connect_errno()) {
    // Tampilkan error jika koneksi gagal
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set timezone (Opsional, tapi disarankan untuk konsistensi waktu di sistem)
date_default_timezone_set('Asia/Jakarta');

// Anda bisa menambahkan fungsi lain di sini, tapi untuk saat ini ini cukup.
?>