<?php
include 'function.php'; // Memastikan session_start() sudah terpanggil

session_unset(); // Hapus semua variabel sesi
session_destroy(); // Hancurkan sesi

redirect_to('index.php'); // Redirect kembali ke halaman login
?>