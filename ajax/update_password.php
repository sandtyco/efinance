<?php
// FILE: /efinance/ajax/update_password.php

// Mulai sesi
session_start();

// Include koneksi dan fungsi (Pastikan path relatif ini sudah benar dari folder /ajax/)
include '../config/conn.php'; 
include '../function.php'; 

// Header untuk memberi tahu browser bahwa respons ini adalah JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Ambil dan sanitasi data POST
    $id_user = isset($_POST['id_user']) ? mysqli_real_escape_string($conn, $_POST['id_user']) : 0;
    $pass_lama = isset($_POST['password_lama']) ? $_POST['password_lama'] : '';
    $pass_baru = isset($_POST['password_baru']) ? $_POST['password_baru'] : '';
    $konfirmasi = isset($_POST['konfirmasi_baru']) ? $_POST['konfirmasi_baru'] : '';

    // Validasi Dasar
    if ($id_user == 0 || empty($pass_lama) || empty($pass_baru) || empty($konfirmasi)) {
        $response['message'] = 'Semua kolom harus diisi.';
    } elseif ($pass_baru !== $konfirmasi) {
        $response['message'] = 'Konfirmasi password baru tidak cocok.';
    } elseif (strlen($pass_baru) < 6) {
        $response['message'] = 'Password baru minimal 6 karakter.';
    } else {
        
        // 1. Cek User dan Ambil Password Hash Lama
        $check_query = "SELECT password FROM user WHERE id_user = '$id_user'";
        $check_result = mysqli_query($conn, $check_query);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $data = mysqli_fetch_assoc($check_result);
            $hash_lama = $data['password'];

            // 2. Verifikasi Password Lama (Asumsi menggunakan password_verify())
            if (password_verify($pass_lama, $hash_lama)) { 
                
                // 3. Hash Password Baru
                $hash_baru = password_hash($pass_baru, PASSWORD_DEFAULT);
                
                // 4. Update Password di Database
                $update_query = "UPDATE user SET password = '$hash_baru' WHERE id_user = '$id_user'";
                if (mysqli_query($conn, $update_query)) {
                    $response['status'] = 'success';
                    $response['message'] = 'Password berhasil diperbarui! Anda akan diarahkan ke halaman login.';
                } else {
                    $response['message'] = 'Gagal memperbarui password di database: ' . mysqli_error($conn);
                }
            } else {
                $response['message'] = 'Password lama salah.';
            }
        } else {
            $response['message'] = 'User tidak ditemukan atau ID pengguna tidak valid.';
        }
    }
}

// KIRIM RESPON JSON
echo json_encode($response);

// PENTING: HENTIKAN EKSEKUSI SCRIPT
exit();