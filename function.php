<?php
// FILE: /efinance/function.php

// Fungsi ini mencegah error jika session_start() dipanggil lebih dari sekali.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi untuk melakukan hashing password menggunakan BCRYPT.
 * @param string $password Password plain text
 * @return string Password yang sudah di-hash
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Fungsi untuk memverifikasi password.
 * @param string $input_password Password plain text dari form
 * @param string $hashed_password Password hash dari database
 * @return bool True jika cocok, False jika tidak
 */
function verify_password($input_password, $hashed_password) {
    return password_verify($input_password, $hashed_password);
}

/**
 * Fungsi untuk melakukan redirect (pengarahan halaman).
 * @param string $location Path atau URL tujuan
 */
function redirect_to($location) {
    header("Location: " . $location);
    exit;
}

/**
 * Fungsi untuk membersihkan input dari XSS dan SQL Injection.
 * Menggunakan MySQLi Procedural.
 * @param string $data Input yang akan dibersihkan
 * @return string Data yang sudah dibersihkan
 */
function clean_input($data) {
    global $conn; // Mengakses variabel koneksi global
    $data = trim($data);
    $data = stripslashes($data);
    if (isset($conn) && $conn) {
        // Hanya lakukan escape jika koneksi sudah ada
        $data = mysqli_real_escape_string($conn, $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// FILE: /efinance/function.php

/**
 * Mengambil daftar RAB berdasarkan ID Departemen.
 * @param int $id_departemen ID Departemen yang sedang login.
 * @return array Daftar RAB.
 */
function get_rab_by_departemen($id_departemen) {
    global $conn;
    
    // Pastikan ID Departemen adalah integer untuk keamanan
    $id_departemen = (int)$id_departemen;
    
    // Query mengambil SEMUA RAB milik departemen, termasuk status 5 (FINAL)
    $sql = "SELECT r.* FROM rab r WHERE r.id_departemen = {$id_departemen} ORDER BY r.tanggal_pengajuan DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $rabs = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rabs[] = $row;
        }
    } else {
        // Log error jika terjadi kegagalan query
        // error_log("Error fetching RAB by departemen: " . mysqli_error($conn));
    }
    return $rabs;
}

// FILE: /efinance/function.php

/**
 * Mendapatkan label HTML status RAB berdasarkan status_keuangan dan status_rektorat.
 * @param int $status_keuangan Status dari Direktur Keuangan (0=Draft, 1=Menunggu Keu, 2=Ditolak Keu, 3=Disetujui Keu/Menunggu Rekt, 4=Ditolak Rekt, 5=Final)
 * @param int $status_rektorat Status dari Rektorat (1=Disetujui Final)
 * @return string Label HTML berwarna.
 */
function get_rab_status_label($status_keuangan, $status_rektorat = 0) {
    
    switch ((int)$status_keuangan) {
        case 0:
            return '<span class="label label-default">Draft (Belum Diajukan)</span>';
        case 1:
            return '<span class="label label-info">Menunggu Persetujuan Keuangan</span>';
        case 2:
            return '<span class="label label-danger">Ditolak Keuangan (Revisi)</span>';
        case 3:
            return '<span class="label label-primary">Disetujui Keuangan, Menunggu Rektorat</span>';
        case 4:
            return '<span class="label label-warning">Ditolak Rektorat (Revisi)</span>';
        case 5:
            // Status Final
            return '<span class="label label-success">Disetujui Final (Rektorat)</span>'; 
        default:
            return '<span class="label label-default">Status Tidak Diketahui</span>';
    }
}

/**
 * Menyimpan data RAB dan Detailnya (Menggunakan MySQLi Procedural Manual Transaction).
 * @param int $id_departemen
 * @param string $judul
 * @param int $tahun_anggaran
 * @param string $deskripsi
 * @param array $details Array of associative arrays (id_akun, uraian, volume, satuan, harga_satuan)
 * @param int $status_keuangan Status awal (0=Draft, 1=Kirim)
 * @param float $total_biaya Total biaya keseluruhan
 * @return bool True jika berhasil, False jika gagal
 */
function insert_rab_and_detail($id_departemen, $judul, $tahun_anggaran, $deskripsi, $details, $status_keuangan, $total_biaya) {
    global $conn;
    
    if (!$conn) {
        echo "<div class='alert alert-danger'>Koneksi Database Gagal. Variabel \$conn tidak tersedia.</div>";
        return false;
    }
    
    mysqli_autocommit($conn, false);

    try {
        $judul_safe = clean_input($judul);
        $deskripsi_safe = clean_input($deskripsi);
        $total_safe = floatval($total_biaya);
        $tahun_safe = (int)$tahun_anggaran;
        $status_rektorat_initial = 0; 
        
        // 2. Insert Header RAB
        $sql_rab = "INSERT INTO rab (id_departemen, judul, total_anggaran, tahun_anggaran, deskripsi, status_keuangan, status_rektorat, tanggal_pengajuan) 
                    VALUES ('{$id_departemen}', '{$judul_safe}', '{$total_safe}', '{$tahun_safe}', '{$deskripsi_safe}', '{$status_keuangan}', '{$status_rektorat_initial}', NOW())";
                    
        if (!mysqli_query($conn, $sql_rab)) {
            throw new Exception("Gagal insert RAB Header: " . mysqli_error($conn) . " Query: " . $sql_rab);
        }
        $id_rab = mysqli_insert_id($conn);

        // 3. Insert Detail RAB
        $sql_detail_base = "INSERT INTO rab_detail (id_rab, id_akun, uraian, volume, satuan, harga_satuan, subtotal) VALUES ";
        $values = [];
        
        foreach ($details as $detail) {
            $id_akun = (int)clean_input($detail['id_akun']); 
            $uraian = clean_input($detail['uraian']);
            $vol = floatval($detail['volume']);
            $sat = clean_input($detail['satuan']);
            $hrg = floatval($detail['harga_satuan']);
            $jml = $vol * $hrg; 

            $values[] = "('{$id_rab}', '{$id_akun}', '{$uraian}', '{$vol}', '{$sat}', '{$hrg}', '{$jml}')";
        }

        if (!empty($values)) {
            $sql_detail = $sql_detail_base . implode(', ', $values);
            if (!mysqli_query($conn, $sql_detail)) {
                throw new Exception("Gagal insert RAB Detail: " . mysqli_error($conn) . " Query: " . $sql_detail);
            }
        }

        // 4. Commit Transaksi
        mysqli_commit($conn);
        mysqli_autocommit($conn, true); 
        return true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true); 
        
        echo "<div class='alert alert-danger' style='border: 1px solid red; padding: 15px; margin-top: 20px;'>";
        echo "<h4><span class='glyphicon glyphicon-fire'></span> DEBUG SQL ERROR!</h4>";
        echo "<p><strong>Pesan Error:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
        
        return false;
    }
}

/**
 * Mendapatkan data RAB Header dan Detail berdasarkan ID RAB.
 * @param int $id_rab ID RAB
 * @return array|null Array yang berisi data RAB dan detailnya, atau NULL jika tidak ditemukan.
 */
function get_rab_data($id_rab) {
    global $conn;
    $rab_id_safe = (int)$id_rab;
    
    // 1. Ambil Data Header RAB
    $sql_header = "SELECT * FROM rab WHERE id_rab = '{$rab_id_safe}'";
    $result_header = mysqli_query($conn, $sql_header);
    
    if (!$result_header || mysqli_num_rows($result_header) === 0) {
        return null;
    }
    $rab_data = mysqli_fetch_assoc($result_header);
    mysqli_free_result($result_header);

    // 2. Ambil Data Detail RAB (Join dengan tabel akun untuk nama akun)
    $sql_detail = "SELECT rd.*, a.kode_akun, a.nama_akun 
                   FROM rab_detail rd 
                   JOIN akun a ON rd.id_akun = a.id_akun
                   WHERE rd.id_rab = '{$rab_id_safe}' 
                   ORDER BY rd.id_rab_detail ASC";
                   
    $result_detail = mysqli_query($conn, $sql_detail);
    $details = [];
    if ($result_detail) {
        while ($row = mysqli_fetch_assoc($result_detail)) {
            $details[] = $row;
        }
        mysqli_free_result($result_detail);
    }
    
    $rab_data['details'] = $details;
    return $rab_data;
}

/**
 * Mengupdate data RAB dan Detailnya (Menggunakan MySQLi Procedural Manual Transaction).
 * Hanya RAB berstatus Draft (0), Ditolak Keuangan (2), atau Ditolak Rektorat (4) yang bisa diubah/direvisi.
 * @param int $id_rab
 * @param int $id_departemen
 * @param string $judul, $tahun_anggaran, $deskripsi
 * @param array $details Array of associative arrays (id_akun, uraian, volume, satuan, harga_satuan)
 * @param int $status_keuangan Status (0=Draft, 1=Kirim)
 * @param float $total_biaya Total biaya keseluruhan
 * @return bool True jika berhasil, False jika gagal
 */
function update_rab_and_detail($id_rab, $id_departemen, $judul, $tahun_anggaran, $deskripsi, $details, $status_keuangan, $total_biaya) {
    global $conn;

    if (!$conn) {
        echo "<div class='alert alert-danger'>Koneksi Database Gagal saat Update.</div>";
        return false;
    }
    
    mysqli_autocommit($conn, false);

    try {
        $id_rab_safe = (int)$id_rab;
        $judul_safe = clean_input($judul);
        $deskripsi_safe = clean_input($deskripsi);
        $total_safe = floatval($total_biaya);
        $tahun_safe = (int)$tahun_anggaran;

        // 1. Cek Status Awal dan Hak Kepemilikan
        $sql_check = "SELECT status_keuangan, id_departemen, status_rektorat FROM rab WHERE id_rab = '{$id_rab_safe}'";
        $res_check = mysqli_query($conn, $sql_check);
        if (!$res_check || mysqli_num_rows($res_check) == 0) {
              throw new Exception("RAB tidak ditemukan.");
        }
        $rab_old = mysqli_fetch_assoc($res_check);
        
        if ($rab_old['id_departemen'] != $id_departemen) {
              throw new Exception("Akses ditolak. RAB bukan milik departemen ini.");
        }
        
        // --- GUARDRAIL CHECK ---
        $allowed_statuses = [0, 2, 4];
        $current_status = (int)$rab_old['status_keuangan'];

        if (!in_array($current_status, $allowed_statuses)) {
             throw new Exception("RAB ini sedang dalam proses persetujuan (Status " . $current_status . ") atau sudah disetujui final dan tidak dapat diedit.");
        }

        // 2. LOGIKA PENANGANAN STATUS
        
        $set_fields = "";
        
        // A. Jika status lama adalah Ditolak (2 atau 4) dan action adalah 'submit' ($status_keuangan=1)
        if (($current_status == 2 || $current_status == 4) && $status_keuangan == 1) {
            // Ini adalah proses pengajuan ulang (Revisi Selesai). Reset semua kolom persetujuan.
            $set_fields .= ", 
                            status_rektorat = 0, 
                            catatan_keuangan = '',  /* PERBAIKAN: Ganti NULL ke Empty String */
                            catatan_rektorat = '',  /* PERBAIKAN: Ganti NULL ke Empty String */
                            tanggal_persetujuan_keuangan = NULL, 
                            tanggal_persetujuan_rektorat = NULL,
                            tanggal_pengajuan = NOW()"; // Perbarui tanggal pengajuan
            
            $new_status_keuangan = 1; // Paksa status menjadi Menunggu Keuangan
            
        // B. Jika status lama adalah Ditolak (2 atau 4) dan action adalah 'draft' ($status_keuangan=0)
        } elseif (($current_status == 2 || $current_status == 4) && $status_keuangan == 0) {
            // Jika user hanya menyimpan sebagai draft, JANGAN reset kolom persetujuan, dan JANGAN ubah status dari 2/4.
            $new_status_keuangan = $current_status; // Pertahankan status Ditolak (2 atau 4)
            
        // C. Jika status lama adalah Draft (0)
        } else {
             $new_status_keuangan = $status_keuangan; 
             $set_fields .= ", status_rektorat = 0";
        }


        // 3. Update Header RAB
        $sql_update_rab = "UPDATE rab SET 
                             judul = '{$judul_safe}', 
                             total_anggaran = '{$total_safe}', 
                             tahun_anggaran = '{$tahun_safe}', 
                             deskripsi = '{$deskripsi_safe}', 
                             status_keuangan = '{$new_status_keuangan}'
                             {$set_fields}
                             WHERE id_rab = '{$id_rab_safe}'"; 
                             
        if (!mysqli_query($conn, $sql_update_rab)) {
            throw new Exception("Gagal update RAB Header: " . mysqli_error($conn) . " Query: " . $sql_update_rab);
        }

        // 4. Hapus semua Detail lama
        $sql_delete_detail = "DELETE FROM rab_detail WHERE id_rab = '{$id_rab_safe}'";
        mysqli_query($conn, $sql_delete_detail); 
        
        // 5. Insert Detail RAB baru
        $sql_detail_base = "INSERT INTO rab_detail (id_rab, id_akun, uraian, volume, satuan, harga_satuan, subtotal) VALUES ";
        $values = [];
        
        $recalculated_total = 0;

        foreach ($details as $detail) {
            $id_akun = (int)clean_input($detail['id_akun']); 
            $uraian = clean_input($detail['uraian']);
            $vol = floatval($detail['volume']);
            $sat = clean_input($detail['satuan']);
            $hrg = floatval($detail['harga_satuan']);
            $jml = $vol * $hrg; 
            
            $recalculated_total += $jml;

            $values[] = "('{$id_rab_safe}', '{$id_akun}', '{$uraian}', '{$vol}', '{$sat}', '{$hrg}', '{$jml}')";
        }

        if (!empty($values)) {
            $sql_detail = $sql_detail_base . implode(', ', $values);
            if (!mysqli_query($conn, $sql_detail)) {
                throw new Exception("Gagal insert RAB Detail baru: " . mysqli_error($conn) . " Query: " . $sql_detail);
            }
        }

        // 6. Commit Transaksi
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);
        
        // Set success message
        if ($new_status_keuangan == 1) {
            $_SESSION['message'] = "RAB berhasil direvisi dan diajukan ulang ke Direktur Keuangan!";
            $_SESSION['message_type'] = "success";
        } elseif ($new_status_keuangan == 0) {
            $_SESSION['message'] = "RAB Draft berhasil diperbarui.";
            $_SESSION['message_type'] = "success";
        } else {
             $_SESSION['message'] = "RAB Ditolak berhasil disimpan sebagai Draft Revisi.";
            $_SESSION['message_type'] = "success";
        }
        
        return true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        
        // SET MESSAGE ERROR UNTUK DITAMPILKAN DI RAB_EDIT.PHP
        $_SESSION['message'] = "Terjadi kesalahan sistem saat menyimpan RAB: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        
        // Hapus logika debug echo di sini jika sudah dipastikan bekerja
        
        return false;
    }
}

// FILE: /efinance/function.php (Tambahkan fungsi ini)

/**
 * Mendapatkan semua data Akun Anggaran dari tabel 'akun'.
 * @return array Array hasil query
 */
function get_all_akun() {
    global $conn;
    // Asumsikan tabel akun memiliki kolom id_akun, kode_akun, dan nama_akun
    $sql = "SELECT id_akun, kode_akun, nama_akun FROM akun ORDER BY kode_akun ASC";
    $result = mysqli_query($conn, $sql);
    
    $akuns = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $akuns[] = $row;
        }
        mysqli_free_result($result);
    }
    return $akuns;
}

// --------------------------------------------------------------------------------------------------
// FUNGSI BARU UNTUK TRANSAKSI (REALISASI ANGGARAN)
// --------------------------------------------------------------------------------------------------

/**
 * Mendapatkan Daftar Transaksi Berdasarkan ID Departemen.
 * @param int $id_departemen ID Departemen
 * @return array Array hasil query
 */
function get_transaksi_by_departemen($id_departemen) {
    global $conn;
    // Mengambil data transaksi dan join ke tabel RAB untuk mendapatkan Judul RAB
    $sql = "SELECT t.*, r.judul AS judul_rab 
            FROM transaksi t
            JOIN rab r ON t.id_rab = r.id_rab
            WHERE t.id_departemen = '{$id_departemen}' 
            ORDER BY t.tanggal_transaksi DESC";
            
    $result = mysqli_query($conn, $sql);
    
    $transaksis = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $transaksis[] = $row;
        }
        mysqli_free_result($result);
    }
    return $transaksis;
}

/**
 * Mendapatkan Daftar RAB yang SUDAH DISETUJUI (Final) untuk digunakan di form Transaksi.
 * Kita asumsikan status final adalah status_keuangan = 3 dan status_rektorat = 2.
 * @param int $id_departemen ID Departemen
 * @return array Array hasil query
 */
function get_approved_rabs($id_departemen) {
    global $conn;
    $sql = "SELECT id_rab, judul, total_anggaran FROM rab 
            WHERE id_departemen = '{$id_departemen}' 
            AND status_keuangan = 3 
            AND status_rektorat = 2 
            ORDER BY judul ASC";
            
    $result = mysqli_query($conn, $sql);
    
    $rabs = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rabs[] = $row;
        }
    }
    return $rabs;
}

/**
 * Mendapatkan status Transaksi dalam bentuk label.
 * @param int $status_approval Nilai dari kolom status_approval (0=Draft/Belum Ajukan, 1=Menunggu Keuangan, 5=Disetujui/Validasi)
 * @return string Label status dalam tag HTML
 */
function get_transaksi_status_label($status_approval) {
    if ($status_approval == 0) {
        return '<span class="label label-default">Draft / Menunggu Ajuan</span>';
    } elseif ($status_approval == 1) {
        return '<span class="label label-warning">Menunggu Validasi Keuangan</span>';
    } elseif ($status_approval == 5) {
        return '<span class="label label-success">Tervalidasi Keuangan</span>';
    }
    return '<span class="label label-inverse">Status Tidak Dikenal</span>';
}

// FILE: /efinance/function.php (Tambahkan di bagian FUNGSI KHUSUS TRANSAKSI)

/**
 * Menyimpan data Transaksi Realisasi Anggaran.
 * @param int $id_departemen
 * @param int $id_rab RAB yang disetujui
 * @param string $nomor_bukti
 * @param string $tanggal_transaksi
 * @param string $uraian_transaksi
 * @param float $total_biaya
 * @param int $status_approval (0=Draft)
 * @return bool True jika berhasil, False jika gagal
 */
function insert_transaksi($id_departemen, $id_rab, $nomor_bukti, $tanggal_transaksi, $uraian_transaksi, $total_biaya, $status_approval) {
    global $conn;

    if (!$conn) {
        // Jika koneksi gagal, tampilkan error
        echo "<div class='alert alert-danger'>Koneksi Database Gagal. Variabel \$conn tidak tersedia.</div>";
        return false;
    }
    
    // Transaksi ini hanya satu insert, tapi kita pakai try-catch untuk debugging yang baik
    try {
        // Sanitize data
        $id_rab_safe = (int)$id_rab;
        $nomor_bukti_safe = clean_input($nomor_bukti);
        $tanggal_safe = clean_input($tanggal_transaksi);
        $uraian_safe = clean_input($uraian_transaksi);
        $total_safe = floatval($total_biaya);
        $status_safe = (int)$status_approval;
        
        // Query Insert
        $sql_trx = "INSERT INTO transaksi (id_departemen, id_rab, nomor_bukti, tanggal_transaksi, uraian_transaksi, total_biaya, status_approval, tanggal_input) 
                    VALUES ('{$id_departemen}', '{$id_rab_safe}', '{$nomor_bukti_safe}', '{$tanggal_safe}', '{$uraian_safe}', '{$total_safe}', '{$status_safe}', NOW())";
                    
        if (!mysqli_query($conn, $sql_trx)) {
            // Jika query gagal, throw exception dengan pesan error SQL
            throw new Exception("Gagal insert Transaksi: " . mysqli_error($conn) . " Query: " . $sql_trx);
        }

        return true;

    } catch (Exception $e) {
        // Tampilkan pesan error spesifik dari database
        echo "<div class='alert alert-danger' style='border: 1px solid red; padding: 15px; margin-top: 20px;'>";
        echo "<h4><span class='glyphicon glyphicon-fire'></span> DEBUG SQL ERROR (INSERT TRANSAKSI)!</h4>";
        echo "<p><strong>Pesan Error:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
        
        return false;
    }
}

// FILE: /efinance/function.php (Tambahkan fungsi ini)

/**
 * Mendapatkan Nama Departemen berdasarkan ID Departemen.
 * @param int $id_departemen ID Departemen
 * @return string|null Nama Departemen atau NULL jika tidak ditemukan
 */
function get_nama_departemen($id_departemen) {
    global $conn;
    $id_safe = (int)$id_departemen;
    
    $sql = "SELECT nama_departemen FROM departemen WHERE id_departemen = '{$id_safe}'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['nama_departemen'];
    }
    return null;
}

// FILE: /efinance/function.php (Tambahkan di bagian FUNGSI KHUSUS RAB)

/**
 * Mendapatkan jumlah RAB yang menunggu persetujuan Keuangan (status_keuangan = 1)
 * dan jumlah Transaksi yang menunggu validasi Keuangan (status_approval = 1).
 * @return array Array asosiatif berisi counts
 */
function get_approval_counts() {
    global $conn;
    $counts = [
        'rab_menunggu' => 0,
        'transaksi_menunggu' => 0
    ];

    // 1. Hitung RAB Menunggu Persetujuan Keuangan (status_keuangan = 1)
    $sql_rab = "SELECT COUNT(*) AS total FROM rab WHERE status_keuangan = 1";
    $result_rab = mysqli_query($conn, $sql_rab);
    if ($result_rab && $row = mysqli_fetch_assoc($result_rab)) {
        $counts['rab_menunggu'] = (int)$row['total'];
    }

    // 2. Hitung Transaksi Menunggu Validasi Keuangan (status_approval = 1)
    $sql_trx = "SELECT COUNT(*) AS total FROM transaksi WHERE status_approval = 1";
    $result_trx = mysqli_query($conn, $sql_trx);
    if ($result_trx && $row = mysqli_fetch_assoc($result_trx)) {
        $counts['transaksi_menunggu'] = (int)$row['total'];
    }

    return $counts;
}

// FILE: /efinance/function.php (Tambahkan di bagian FUNGSI KHUSUS RAB)

/**
 * Mendapatkan Daftar RAB yang statusnya Menunggu Persetujuan Keuangan (status_keuangan = 1).
 * Dilengkapi dengan data Departemen.
 * @return array Array hasil query
 */
function get_rab_for_keuangan() {
    global $conn;
    $sql = "SELECT r.*, d.nama_departemen 
            FROM rab r
            JOIN departemen d ON r.id_departemen = d.id_departemen
            WHERE r.status_keuangan = 1
            ORDER BY r.tanggal_pengajuan ASC"; // Prioritaskan yang lebih lama
            
    $result = mysqli_query($conn, $sql);
    
    $rabs = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rabs[] = $row;
        }
        mysqli_free_result($result);
    }
    return $rabs;
}

// FILE: /efinance/function.php (Tambahkan fungsi ini)

/**
 * Memproses Persetujuan RAB oleh Direktur Keuangan.
 * @param int $id_rab ID RAB yang akan diproses
 * @param string $action 'approve' (Setujui) atau 'reject' (Tolak)
 * @param string $catatan Catatan dari Keuangan (opsional)
 * @return bool True jika berhasil, False jika gagal
 */
function process_rab_approval_keuangan($id_rab, $action, $catatan) {
    global $conn;
    $id_rab_safe = (int)$id_rab;
    $catatan_safe = clean_input($catatan);
    $new_status_keu = 0; // Default

    // Tentukan status baru berdasarkan action
    if ($action === 'approve') {
        // Status 3: Disetujui Keuangan, Lanjut ke Rektorat
        $new_status_keu = 3; 
        $message = "RAB berhasil disetujui oleh Keuangan dan diteruskan ke Rektorat.";
    } elseif ($action === 'reject') {
        // Status 2: Ditolak Keuangan
        $new_status_keu = 2;
        $message = "RAB berhasil ditolak oleh Keuangan.";
    } else {
        $_SESSION['message'] = "Aksi tidak valid.";
        $_SESSION['message_type'] = "danger";
        return false;
    }

    // Hanya proses RAB yang status_keuangan-nya 1 (Menunggu Persetujuan Keuangan)
    $sql = "UPDATE rab SET 
            status_keuangan = '{$new_status_keu}', 
            catatan_keuangan = '{$catatan_safe}',
            tanggal_persetujuan_keuangan = NOW()
            WHERE id_rab = '{$id_rab_safe}' AND status_keuangan = 1";

    if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = "success";
        return true;
    } else {
        $_SESSION['message'] = "Gagal memproses RAB. Pastikan RAB masih berstatus Menunggu (Status 1). SQL Error: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
        return false;
    }
}

// FILE: /efinance/function.php (Tambahkan atau pastikan fungsi ini ada)

/**
 * Menghitung jumlah RAB yang menunggu persetujuan Rektorat (Status Keuangan = 3).
 * @return int Jumlah RAB
 */
function count_pending_rab_rektorat() {
    global $conn;
    // Status Keuangan 3: Disetujui Keuangan, Status Rektorat 0: Menunggu Rektorat
    $sql = "SELECT COUNT(id_rab) AS total FROM rab WHERE status_keuangan = 3 AND status_rektorat = 0";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $data = mysqli_fetch_assoc($result);
        return (int)$data['total'];
    }
    return 0;
}


// FILE: /efinance/function.php (GANTI fungsi lama (process_rab_approval_rektorat) dengan fungsi ini)

/**
 * Memproses Persetujuan RAB oleh Rektorat (Finalisasi).
 * @param int $id_rab ID RAB yang akan diproses
 * @param int $approval_status 1 untuk Setuju (Final), 0 untuk Tolak
 * @param string $catatan Catatan Rektorat (Wajib jika status=0)
 * @return bool True jika berhasil, False jika gagal
 */
function approve_rab_rektorat($id_rab, $approval_status, $catatan) {
    global $conn;

    if ($approval_status == 0 && empty($catatan)) {
        $_SESSION['message'] = "Catatan penolakan Rektorat wajib diisi.";
        $_SESSION['message_type'] = "danger";
        return false;
    }
    
    // Mulai Transaksi Manual
    mysqli_autocommit($conn, false);

    try {
        $id_rab_safe = (int)$id_rab;
        $catatan_safe = clean_input($catatan);
        
        // 1. Cek Status saat ini: Harus 3 (Disetujui Keuangan) dan status_rektorat = 0
        $sql_check = "SELECT status_keuangan, status_rektorat FROM rab WHERE id_rab = '{$id_rab_safe}' FOR UPDATE"; // Lock row
        $res_check = mysqli_query($conn, $sql_check);
        $rab_status = mysqli_fetch_assoc($res_check);

        if (!$rab_status || $rab_status['status_keuangan'] != 3 || $rab_status['status_rektorat'] != 0) {
            throw new Exception("RAB tidak pada status yang benar (Menunggu Rektorat) untuk diproses.");
        }

        $message = "";

        if ($approval_status == 1) {
            // A. PERSETUJUAN FINAL
            $sql_update = "UPDATE rab SET 
                           status_keuangan = 5, /* 5 = Disetujui Final */
                           status_rektorat = 1, /* 1 = Disetujui */
                           tanggal_persetujuan_rektorat = NOW()
                           WHERE id_rab = '{$id_rab_safe}'";
            $message = "RAB berhasil disetujui secara FINAL.";
        } else {
            // B. PENOLAKAN
            // Status Keuangan diubah menjadi 4 (Ditolak Rektorat)
            $sql_update = "UPDATE rab SET 
                           status_keuangan = 4, /* 4 = Ditolak Rektorat */
                           status_rektorat = 0, /* Status Rektorat dipertahankan 0, status 4 yang menjadi penanda */
                           catatan_rektorat = '{$catatan_safe}'
                           WHERE id_rab = '{$id_rab_safe}'";
            $message = "RAB berhasil ditolak dan dikembalikan ke Departemen untuk direvisi.";
        }

        if (!mysqli_query($conn, $sql_update)) {
            throw new Exception("Gagal update status RAB: " . mysqli_error($conn));
        }

        // Commit Transaksi
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);
        
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = ($approval_status == 1) ? "success" : "warning";
        return true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        $_SESSION['message'] = "Terjadi kesalahan saat memproses persetujuan Rektorat: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        return false;
    }
}

// FILE: /efinance/function.php (Tambahkan kembali fungsi ini)

/**
 * Menghitung total RAB berdasarkan status keuangan (untuk dashboard).
 * @param int $status Status Keuangan yang dicari (Misal: 5 = Disetujui Final)
 * @return int Total RAB
 */
function get_total_rab_by_status($status) {
    global $conn;
    $status_safe = (int)$status;
    
    // Penanganan khusus untuk Status Final (status_keuangan = 5)
    $sql = "SELECT COUNT(id_rab) AS total FROM rab WHERE status_keuangan = '{$status_safe}'";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $data = mysqli_fetch_assoc($result);
        return (int)$data['total'];
    }
    return 0;
}

// FILE: /efinance/function.php (Bagian TRANSAKSI)

/**
 * Mendapatkan daftar RAB yang statusnya Final (Disetujui Rektorat: status_rektorat = 1) 
 * dan masih memiliki sisa anggaran yang belum direalisasikan.
 */
function get_final_rabs_for_realisasi($id_departemen) {
    global $conn;
    $id_departemen = (int)$id_departemen;

    // Keterangan status realisasi (rel.status):
    // 1: Diajukan Keuangan, 2: Disetujui Keuangan, 4: Final/Dibayarkan
    
    $sql = "SELECT 
                r.id_rab, 
                r.judul, 
                r.tahun_anggaran, 
                r.total_anggaran, -- *** KOREKSI DI SINI ***
                
                -- Jumlahkan semua total realisasi yang sudah diajukan/diproses
                COALESCE(SUM(rel.total_realisasi), 0) AS total_realisasi_disetujui,
                
                -- Hitung sisa anggaran yang tersedia (Total Anggaran - Total Realisasi)
                (r.total_anggaran - COALESCE(SUM(rel.total_realisasi), 0)) AS sisa_anggaran -- *** KOREKSI DI SINI ***
            FROM 
                rab r
            LEFT JOIN 
                realisasi rel 
            ON 
                rel.id_rab = r.id_rab 
                AND rel.status IN (1, 2, 4) 
            WHERE 
                r.id_departemen = {$id_departemen}
                AND r.status_rektorat = 1 
            GROUP BY 
                r.id_rab
            HAVING 
                sisa_anggaran > 0
            ORDER BY 
                r.id_rab DESC";

    $result = mysqli_query($conn, $sql);
    
    $rabs = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rabs[] = $row;
        }
    }
    return $rabs;
}

// FILE: /efinance/function.php

/**
 * Mendapatkan detail item RAB yang disetujui final, 
 * beserta total realisasi yang sudah tercatat per item.
 * @param int $id_rab ID RAB Final.
 * @return array Detail RAB siap realisasi.
 */
function get_rab_details_for_realisasi($id_rab) {
    global $conn;
    $id_rab = (int)$id_rab;

    $sql = "SELECT
                rd.*,
                a.kode_akun,
                a.nama_akun,
                (
                    SELECT SUM(rld.jumlah_realisasi)
                    FROM realisasi_detail rld
                    JOIN realisasi rl ON rl.id_realisasi = rld.id_realisasi
                    WHERE rld.id_rab_detail = rd.id_rab_detail
                    AND rl.status IN (1, 2) /* Hanya hitung yang diajukan atau disetujui */
                ) AS total_realisasi_item
            FROM
                rab_detail rd
            JOIN
                akun a ON a.id_akun = rd.id_akun
            WHERE
                rd.id_rab = {$id_rab}
            ORDER BY
                a.kode_akun ASC";

    $result = mysqli_query($conn, $sql);

    $details = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $total_realisasi = (float)$row['total_realisasi_item'];
            $row['total_realisasi_item'] = $total_realisasi;
            $row['sisa_anggaran_item'] = (float)$row['subtotal'] - $total_realisasi;
            
            // Hanya tampilkan item yang masih memiliki sisa anggaran (sisa > 0)
            if ($row['sisa_anggaran_item'] > 0) {
                $details[] = $row;
            }
        }
    }
    return $details;
}

// FILE: /efinance/function.php save_realisasi_transaction

/**
 * Menyimpan transaksi realisasi anggaran dan detailnya.
 * Status awal selalu 1 (Diajukan)
 *
 * @param int $id_rab ID RAB yang direalisasikan.
 * @param int $id_departemen ID Departemen.
 * @param string $tanggal_realisasi (YYYY-MM-DD)
 * @param string $nomor_dokumen Nomor Dokumen/Bukti Kas.
 * @param string $deskripsi Deskripsi umum realisasi.
 * @param array $details Array detail realisasi.
 * @param int $created_by ID User yang membuat.
 * @return bool
 */
function save_realisasi_transaction($id_rab, $id_departemen, $tanggal_realisasi, $nomor_dokumen, $deskripsi, $details, $created_by) {
    global $conn;
    
    // Mulai Transaksi untuk memastikan konsistensi data
    mysqli_begin_transaction($conn);
    
    try {
        $id_rab = (int)$id_rab;
        $id_departemen = (int)$id_departemen;
        $created_by = (int)$created_by;
        
        // Sanitize input
        $tanggal_realisasi = clean_input($tanggal_realisasi);
        $nomor_dokumen = clean_input($nomor_dokumen);
        $deskripsi = clean_input($deskripsi);
        $total_realisasi = 0;

        // 1. Hitung total realisasi dari detail
        foreach ($details as $detail) {
            // Pastikan jumlah realisasi adalah float dan positif
            $total_realisasi += (float)($detail['jumlah_realisasi'] ?? 0);
        }

        if ($total_realisasi <= 0) {
             throw new Exception("Total realisasi harus lebih dari Rp 0.");
        }

        // 2. Insert ke tabel realisasi (Header)
        // Status 1 = Diajukan
        $sql_realisasi = "INSERT INTO realisasi (id_rab, id_departemen, tanggal_realisasi, nomor_dokumen, deskripsi, total_realisasi, status, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, 1, ?)";
        
        $stmt = mysqli_prepare($conn, $sql_realisasi);
        mysqli_stmt_bind_param($stmt, "iissdsi", 
            $id_rab, 
            $id_departemen, 
            $tanggal_realisasi, 
            $nomor_dokumen, 
            $deskripsi, 
            $total_realisasi, 
            $created_by
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal menyimpan header transaksi realisasi: " . mysqli_error($conn));
        }
        $id_realisasi = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // 3. Insert ke tabel realisasi_detail (Details)
        $sql_detail = "INSERT INTO realisasi_detail (id_realisasi, id_rab_detail, uraian, jumlah_realisasi)
                       VALUES (?, ?, ?, ?)";
        
        $stmt_detail = mysqli_prepare($conn, $sql_detail);

        foreach ($details as $detail) {
            $id_rab_detail = (int)$detail['id_rab_detail'];
            $uraian = clean_input($detail['uraian']);
            $jumlah_realisasi = (float)($detail['jumlah_realisasi'] ?? 0);
            
            if ($jumlah_realisasi > 0) {
                mysqli_stmt_bind_param($stmt_detail, "iisd", 
                    $id_realisasi, 
                    $id_rab_detail, 
                    $uraian, 
                    $jumlah_realisasi
                );
                
                if (!mysqli_stmt_execute($stmt_detail)) {
                    throw new Exception("Gagal menyimpan detail transaksi realisasi: " . mysqli_error($conn));
                }
            }
        }
        mysqli_stmt_close($stmt_detail);

        // 4. Commit Transaction
        mysqli_commit($conn);
        $_SESSION['message'] = "Pengajuan Realisasi Anggaran berhasil disimpan.";
        $_SESSION['message_type'] = "success";
        return true;

    } catch (Exception $e) {
        // Rollback jika terjadi error
        mysqli_rollback($conn);
        $_SESSION['message'] = "Gagal menyimpan Realisasi Anggaran: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        return false;
    }
}

// FILE: /efinance/function.php (Fungsi Transaksi List)

/**
 * Mendapatkan daftar Realisasi Anggaran milik departemen tertentu.
 */
function get_realisasi_list_by_departemen($id_departemen) {
    global $conn;
    $id_departemen = (int)$id_departemen;

    $sql = "SELECT 
                rel.*, 
                r.judul AS judul_rab,
                r.id_rab
            FROM 
                realisasi rel
            LEFT JOIN 
                rab r ON rel.id_rab = r.id_rab
            WHERE 
                rel.id_departemen = {$id_departemen}
            ORDER BY 
                rel.id_realisasi DESC";

    $result = mysqli_query($conn, $sql);
    
    $list = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Pastikan total_realisasi diubah menjadi format numerik untuk perhitungan (jika diperlukan)
            $row['total_realisasi'] = (float)$row['total_realisasi']; 
            $list[] = $row;
        }
    }
    return $list;
}

/**
 * Mendapatkan label status realisasi
 */
function get_realisasi_status_label($status) {
    $status = (int)$status;
    switch ($status) {
        case 0:
            return '<span class="label label-info">Draft Ajuan</span>';
        case 1:
            return '<span class="label label-warning">Diajukan (Pending)</span>';
        case 2:
            return '<span class="label label-success">Disetujui Keuangan</span>';
        case 3:
            return '<span class="label label-primary">Disetujui Rektorat</span>';
        case 4:
            return '<span class="label label-danger">Ditolak</span>';
        case 5:
            return '<span class="label label-primary">Selesai (Telah Dibayarkan)</span>'; // Status opsional
        default:
            return '<span class="label label-default">Tidak Diketahui</span>';
    }
}

// FILE: /efinance/function.php

function delete_realisasi($id_realisasi) {
    global $conn;
    $id_realisasi = (int)$id_realisasi;
    
    // --- Langkah 1: Cek Status Transaksi ---
    $sql_check = "SELECT status FROM realisasi WHERE id_realisasi = {$id_realisasi}";
    $result_check = mysqli_query($conn, $sql_check);
    
    if (!$result_check || mysqli_num_rows($result_check) == 0) {
        $_SESSION['message'] = "Penghapusan gagal. Transaksi tidak valid.";
        $_SESSION['message_type'] = "danger";
        return false;
    }
    
    $row = mysqli_fetch_assoc($result_check); // <<< PASTI GANTI KE INI
    
    // Status yang DILARANG dihapus
    $status_disallowed = [1, 2, 4]; // 1=Diajukan, 2=Disetujui, 4=Dibayarkan/Final
    
    if (in_array((int)$row['status'], $status_disallowed)) {
        $_SESSION['message'] = "Penghapusan gagal. Transaksi sudah diproses dan tidak bisa dihapus.";
        $_SESSION['message_type'] = "danger";
        return false;
    }
    
    // --- Langkah 2: Proses Penghapusan ---
    
    // 1. Hapus detail realisasi
    $sql_delete_detail = "DELETE FROM realisasi_detail WHERE id_realisasi = {$id_realisasi}";
    mysqli_query($conn, $sql_delete_detail);

    // 2. Hapus header realisasi
    $sql_delete_header = "DELETE FROM realisasi WHERE id_realisasi = {$id_realisasi}";
    
    if (mysqli_query($conn, $sql_delete_header)) {
        $_SESSION['message'] = "Transaksi berhasil dihapus.";
        $_SESSION['message_type'] = "success";
        return true;
    }
    
    $_SESSION['message'] = "Gagal menghapus transaksi dari database.";
    $_SESSION['message_type'] = "danger";
    return false;
}

// FILE: /efinance/function.php (Tambahkan Fungsi Berikut)

/**
 * Menerjemahkan kode angka status realisasi menjadi teks label HTML.
 * @param int $status_id ID Status dari tabel realisasi.
 * @return string HTML label status.
 */
function get_status_realisasi($status_id) {
    $status_id = (int)$status_id;
    
    switch ($status_id) {
        case 0:
            $text = "DRAFT";
            $class = "label-info"; // Biru Muda
            break;
        case 1:
            $text = "DIAJUKAN";
            $class = "label-warning"; // Oranye
            break;
        case 2:
            $text = "DISETUJUI KEUANGAN";
            $class = "label-success"; // Hijau
            break;
        case 3:
            $text = "DISETUJUI REKTORAT";
            $class = "label-primary"; // Biru Tua
            break;
        case 4:
            $text = "DITOLAK";
            $class = "label-danger"; // Merah (Bisa Direvisi)
            break;
        case 5:
            $text = "SELESAI/DIBAYARKAN";
            $class = "label-primary"; // Biru Tua
            break;
        default:
            $text = "TIDAK DIKETAHUI";
            $class = "label-warning";
            break;
    }
    
    return "<span class=\"label {$class}\">{$text}</span>";
}

// FILE: /efinance/function.php (Tambahkan Fungsi Berikut)

/**
 * Mendapatkan semua item detail dari suatu Realisasi.
 */
function get_realisasi_details($id_realisasi) {
    global $conn;
    $id_realisasi = (int)$id_realisasi;

    $sql = "SELECT 
                *
            FROM 
                realisasi_detail rd
            WHERE 
                rd.id_realisasi = {$id_realisasi}
            ORDER BY 
                rd.id_detail ASC"; 

    $result = mysqli_query($conn, $sql);
    
    $details = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $details[] = $row;
        }
    }
    return $details;
}

/**
 * Mendapatkan data header Realisasi berdasarkan ID.
 */
function get_realisasi_header($id_realisasi) {
    global $conn;
    $id_realisasi = (int)$id_realisasi;

    $sql = "SELECT 
                rel.*, 
                r.judul AS judul_rab,
                r.total_anggaran AS total_anggaran_rab
            FROM 
                realisasi rel
            LEFT JOIN 
                rab r ON rel.id_rab = r.id_rab
            WHERE 
                rel.id_realisasi = {$id_realisasi}";

    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * =================================================================
 * FUNGSI UTAMA: UPDATE TRANSAKSI REALISASI
 * =================================================================
 */

/**
 * Memperbarui transaksi Realisasi (Header dan Detail)
 * Menggunakan Transaksi Database untuk memastikan integritas data.
 * @param int $id_realisasi ID Realisasi yang akan diupdate.
 * @param string $tanggal_realisasi Tanggal realisasi baru.
 * @param string $nomor_dokumen Nomor dokumen baru.
 * @param string $deskripsi_umum Deskripsi umum baru.
 * @param array $details_new Array detail item baru.
 * @param int $updated_by ID pengguna yang melakukan update.
 * @param float $total_realisasi_new Total realisasi baru.
 * @param int $status_baru Status baru (0=Draft, 1=Diajukan).
 * @return bool True jika update berhasil, False jika gagal.
 */
function update_realisasi_transaction(
    $id_realisasi, 
    $tanggal_realisasi, 
    $nomor_dokumen, 
    $deskripsi_umum, 
    $details_new, 
    $updated_by, 
    $total_realisasi_new,
    $status_baru
) {
    global $conn;

    // Mulai Transaksi Database
    mysqli_begin_transaction($conn);

    try {
        // ----------------------------------------------------
        // 1. UPDATE Header Realisasi
        // ----------------------------------------------------
        $sql_header = "UPDATE realisasi 
                       SET tanggal_realisasi = ?, 
                           nomor_dokumen = ?, 
                           deskripsi = ?, 
                           total_realisasi = ?, 
                           status = ?, 
                           updated_by = ?, 
                           updated_at = NOW()
                       WHERE id_realisasi = ?";

        $stmt_header = mysqli_prepare($conn, $sql_header);
        
        if ($stmt_header === false) {
             throw new Exception("SQL Prepare Error (Header): " . mysqli_error($conn));
        }
        
        // Asumsi tipe data: s, s, s (string), d (double/float), i (integer), i, i
        mysqli_stmt_bind_param(
            $stmt_header, 
            "sssdiis",
            $tanggal_realisasi,
            $nomor_dokumen,
            $deskripsi_umum,
            $total_realisasi_new,
            $status_baru,
            $updated_by,
            $id_realisasi
        );

        if (!mysqli_stmt_execute($stmt_header)) {
            throw new Exception("Gagal update header realisasi.");
        }
        mysqli_stmt_close($stmt_header);

        // ----------------------------------------------------
        // 2. DELETE Detail Realisasi Lama
        // ----------------------------------------------------
        $sql_delete_detail = "DELETE FROM realisasi_detail WHERE id_realisasi = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete_detail);
        
        if ($stmt_delete === false) {
             throw new Exception("SQL Prepare Error (Delete Detail): " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt_delete, "i", $id_realisasi);
        
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("Gagal menghapus detail realisasi lama.");
        }
        mysqli_stmt_close($stmt_delete);

        // ----------------------------------------------------
        // 3. INSERT Detail Realisasi Baru
        // ----------------------------------------------------
        if (!empty($details_new)) {
            $sql_detail = "INSERT INTO realisasi_detail (id_realisasi, id_rab_detail, jumlah_realisasi, uraian_item)
                           VALUES (?, ?, ?, ?)";
            $stmt_detail = mysqli_prepare($conn, $sql_detail);
            
            if ($stmt_detail === false) {
                 throw new Exception("SQL Prepare Error (Insert Detail): " . mysqli_error($conn));
            }
            
            foreach ($details_new as $detail) {
                // Konversi jumlah realisasi ke tipe data float/double
                $jumlah_realisasi = (float)$detail['jumlah_realisasi'];
                $id_rab_detail = (int)$detail['id_rab_detail'];
                $uraian = $detail['uraian'];
                
                // Binding: i (id_realisasi), i (id_rab_detail), d (jumlah_realisasi), s (uraian_item)
                mysqli_stmt_bind_param(
                    $stmt_detail, 
                    "iids", 
                    $id_realisasi, 
                    $id_rab_detail, 
                    $jumlah_realisasi, 
                    $uraian
                );
                
                if (!mysqli_stmt_execute($stmt_detail)) {
                    throw new Exception("Gagal insert detail realisasi baru.");
                }
            }
            mysqli_stmt_close($stmt_detail);
        }

        // Jika semua langkah sukses, lakukan Commit
        mysqli_commit($conn);
        return true;

    } catch (Exception $e) {
        // Jika ada Exception, lakukan Rollback
        mysqli_rollback($conn);
        // Catat error yang sebenarnya (termasuk error prepare SQL)
        error_log("Kesalahan Transaksi Realisasi Update: " . $e->getMessage());
        return false;
    }
}


/**
 * =================================================================
 * FUNGSI HELPER PENGAMBIL DATA (GETTERS)
 * (Diperlukan oleh transaksi_edit.php untuk memuat data)
 * =================================================================
 */

/**
 * Mengambil data RAB Header dan ringkasan Realisasi
 */
function get_rab_header($id_rab) {
    global $conn;
    $sql = "SELECT rab.id_rab, rab.judul, rab.total_anggaran, 
                   COALESCE(SUM(CASE WHEN r.status = 2 THEN r.total_realisasi ELSE 0 END), 0) AS total_realisasi_disetujui,
                   rab.total_anggaran - COALESCE(SUM(CASE WHEN r.status = 2 THEN r.total_realisasi ELSE 0 END), 0) AS sisa_anggaran
            FROM rab
            LEFT JOIN realisasi r ON rab.id_rab = r.id_rab
            WHERE rab.id_rab = ?
            GROUP BY rab.id_rab, rab.judul, rab.total_anggaran";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
         error_log("SQL Prepare Error (get_rab_header): " . mysqli_error($conn));
         return null; 
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id_rab);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $header = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $header;
}


/**
 * Mengembalikan label status Realisasi yang mudah dibaca
 */
function get_status_label($status) {
    switch ($status) {
        case 0:
            return "0 Draft";
        case 1:
            return "1 Diajukan";
        case 2:
            return "2 Disetujui Keuangan";
        case 3:
            return "3 Disetujui Rektorat";
        case 4:
            return "4 Ditolak";
        case 5:
            return "5 Selesai";
        default:
            return "Tidak Diketahui";
    }
}

/**
 * =================================================================
 * FUNGSI UNTUK VALIDASI STATUS (TAHAP KE-1 OLEH KEUANGAN)
 * =================================================================
 */

/**
 * Mengubah status Realisasi (Validasi oleh Keuangan/Direktur Keuangan atau Penolakan)
 * Fungsi ini TIDAK mengurangi budget, karena budget baru dikurangi saat final approval (Status 3).
 * @param int $id_realisasi ID Realisasi.
 * @param int $new_status Status baru (2=Disetujui Keuangan, -1=Ditolak Keuangan).
 * @param int $validated_by ID pengguna (Direktur Keuangan).
 * @return bool True jika berhasil.
 */
function validate_realisasi_status($id_realisasi, $new_status, $validated_by) {
    global $conn;
    $sql = "UPDATE realisasi 
            SET status = ?, 
                validated_by = ?, 
                validated_at = NOW() 
            WHERE id_realisasi = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        error_log("SQL Prepare Error (validate_realisasi_status): " . mysqli_error($conn));
        return false;
    }
    
    // Binding: i (status), i (validated_by), i (id_realisasi)
    mysqli_stmt_bind_param($stmt, "iii", $new_status, $validated_by, $id_realisasi);
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$success) {
        error_log("Gagal mengubah status realisasi: " . mysqli_error($conn));
    }
    
    return $success;
}


/**
 * =================================================================
 * FUNGSI UNTUK FINAL APPROVAL & DEDUCTION BUDGET (TAHAP KE-2 OLEH REKTORAT)
 * =================================================================
 */

/**
 * Melakukan persetujuan final Realisasi (Status 3) dan MENGURANGI budget pada RAB yang terkait.
 * @param int $id_realisasi ID Realisasi.
 * @param int $new_status Status baru (3=Disetujui Final, -2=Ditolak Rektorat).
 * @param int $approved_by ID pengguna (Rektorat).
 * @return bool True jika berhasil.
 */
function final_approve_realisasi($id_realisasi, $new_status, $approved_by) {
    global $conn;
    // PENTING: Semua operasi harus di dalam Transaksi SQL untuk menjaga konsistensi data
    
    mysqli_begin_transaction($conn);
    
    try {
        // 1. UPDATE status Realisasi Header
        $sql_header = "UPDATE realisasi 
                       SET status = ?, 
                           approved_by = ?, 
                           approved_at = NOW() 
                       WHERE id_realisasi = ?";
        $stmt_header = mysqli_prepare($conn, $sql_header);
        mysqli_stmt_bind_param($stmt_header, "iii", $new_status, $approved_by, $id_realisasi);
        mysqli_stmt_execute($stmt_header);
        
        // Cek apakah aksi adalah persetujuan final (Status 3)
        if ($new_status == 3) {
            // 2. Jika APPROVED FINAL (Status 3), lakukan pengurangan budget
            
            // Ambil Detail Realisasi dan id_rab_detail terkait
            $realisasi_details = get_realisasi_details($id_realisasi);
            
            if ($realisasi_details) {
                foreach ($realisasi_details as $item) {
                    $id_rab_detail = (int) $item['id_rab_detail'];
                    $jumlah_realisasi = (float) $item['jumlah_realisasi'];
                    
                    // Update Sisa Anggaran dan Total Realisasi di rab_detail
                    $sql_budget = "UPDATE rab_detail 
                                   SET sisa_anggaran_item = sisa_anggaran_item - ?, 
                                       total_realisasi_item = total_realisasi_item + ? 
                                   WHERE id_rab_detail = ?";
                    $stmt_budget = mysqli_prepare($conn, $sql_budget);
                    
                    // Binding: d (jumlah_realisasi), d (jumlah_realisasi), i (id_rab_detail)
                    mysqli_stmt_bind_param($stmt_budget, "ddi", $jumlah_realisasi, $jumlah_realisasi, $id_rab_detail);
                    mysqli_stmt_execute($stmt_budget);
                    
                    // Cek apakah update budget berhasil
                    if (mysqli_stmt_affected_rows($stmt_budget) == 0) {
                        throw new Exception("Gagal mengurangi budget RAB Detail #{$id_rab_detail}.");
                    }
                    mysqli_stmt_close($stmt_budget);
                }
            }
        }
        
        // 3. Commit Transaksi jika semua berhasil
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        // Rollback Transaksi jika ada kesalahan
        mysqli_rollback($conn);
        error_log("Gagal final approve Realisasi #{$id_realisasi}. Error: " . $e->getMessage());
        return false;
    } finally {
        // Tutup statement header jika ada
        if (isset($stmt_header)) {
             mysqli_stmt_close($stmt_header);
        }
    }
}

// =========================================================================
// 3. FUNGSI BARU UNTUK MELENGKAPI DETAIL REALISASI (Fungsi yang Anda Minta)
// =========================================================================

/**
 * Mengambil data detail item RAB berdasarkan ID.
 * @param int $id_rab_detail ID detail RAB.
 * @return array|false Data detail RAB atau false jika tidak ditemukan.
 */
function get_rab_detail_by_id($id_rab_detail) {
    global $conn;
    $id = (int) $id_rab_detail;
    if (!$id) return false;
    
    // Asumsi: tabel 'rab_detail' berisi 'uraian', 'id_akun', 'kuantitas', 'satuan', 'harga_satuan', 'jumlah'
    $stmt = $conn->prepare("SELECT id_akun, uraian FROM rab_detail WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Mengambil informasi Akun berdasarkan ID Akun.
 * @param int $id_akun ID Akun.
 * @return array|false Data Akun (kode_akun, nama) atau false jika tidak ditemukan.
 */
function get_akun_info($id_akun) {
    global $conn;
    $id = (int) $id_akun;
    if (!$id) return false;

    // Asumsi: tabel 'akun' berisi 'id', 'kode_akun', 'nama_akun'
    $stmt = $conn->prepare("SELECT kode_akun, nama_akun FROM akun WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// =========================================================================
// 4. FUNGSI TRANSAKSI / REALISASI
// =========================================================================

/**
 * Menghitung total realisasi (jumlah transaksi yang disetujui) untuk satu item detail RAB tertentu.
 * Digunakan untuk mengetahui SISA anggaran.
 * * @param int $id_rab_detail ID dari item detail RAB (rab_detail.id).
 * @return float Total realisasi yang sudah disetujui.
 */
function get_realisasi_by_rab_detail_id($id_rab_detail) {
    global $conn;
    $total_realisasi = 0.0;
    
    // Perhatikan: Kita hanya menghitung yang statusnya SUDAH DISETUJUI (status_transaksi = 3)
    $stmt = $conn->prepare("
        SELECT SUM(jumlah) AS total 
        FROM transaksi 
        WHERE id_rab_detail = ? AND status_transaksi = 3
    ");
    
    if ($stmt === false) {
        // Handle error: return 0.0 atau log error
        error_log("Prepare failed: " . $conn->error);
        return 0.0;
    }
    
    $stmt->bind_param("i", $id_rab_detail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_realisasi = (float) $row['total'];
    }
    
    $stmt->close();
    return $total_realisasi;
}

/**
 * Mengambil daftar detail transaksi (nota) berdasarkan ID Transaksi.
 * Digunakan untuk halaman transaksi.detail.php.
 * * @param int $id_transaksi ID Transaksi (transaksi.id).
 * @return array Array data detail transaksi.
 */
function get_transaksi_details($id_transaksi) {
    global $conn;
    $details = [];
    
    // Asumsi: Anda memiliki tabel 'transaksi_detail' untuk mencatat nota/bukti pengeluaran.
    // Jika tidak ada tabel transaksi_detail, ini mengembalikan array kosong.
    // Kolom yang diharapkan di 'transaksi_detail': id, id_transaksi, deskripsi_nota, jumlah_nota, id_rab_detail, no_bukti (opsional)
    
    $stmt = $conn->prepare("
        SELECT 
            td.id, 
            td.deskripsi_nota, 
            td.jumlah_nota,
            rd.uraian AS uraian_rab_detail,
            td.no_bukti
        FROM transaksi_detail td
        JOIN rab_detail rd ON td.id_rab_detail = rd.id
        WHERE td.id_transaksi = ? 
        ORDER BY td.id
    ");

    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return $details;
    }

    $stmt->bind_param("i", $id_transaksi);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $details[] = $row;
    }
    
    $stmt->close();
    return $details;
}

/**
 * Mendapatkan data utama RAB dan Realisasi Total untuk halaman Transaksi List (per RAB).
 *
 * @param int $id_rab ID RAB.
 * @return array Data RAB dengan realisasi total.
 */
function get_rab_realisasi_summary($id_rab) {
    global $conn;
    $rab_summary = [];

    // 1. Ambil data RAB
    $stmt_rab = $conn->prepare("SELECT * FROM rab WHERE id = ?");
    $stmt_rab->bind_param("i", $id_rab);
    $stmt_rab->execute();
    $result_rab = $stmt_rab->get_result();
    $rab_data = $result_rab->fetch_assoc();
    $stmt_rab->close();

    if (!$rab_data) {
        return false;
    }
    
    // 2. Hitung total realisasi yang sudah disetujui untuk RAB ini (status_transaksi = 3)
    // Melakukan JOIN ke rab_detail untuk memastikan hanya transaksi terkait detail RAB yang masuk.
    $stmt_realisasi = $conn->prepare("
        SELECT SUM(t.jumlah) AS total_realisasi
        FROM transaksi t
        JOIN rab_detail rd ON t.id_rab_detail = rd.id
        WHERE rd.id_rab = ? AND t.status_transaksi = 3
    ");
    $stmt_realisasi->bind_param("i", $id_rab);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    $realisasi_row = $result_realisasi->fetch_assoc();
    $stmt_realisasi->close();

    $rab_data['realisasi_terpakai'] = (float)($realisasi_row['total_realisasi'] ?? 0.0);
    $rab_data['sisa_anggaran'] = $rab_data['total_anggaran'] - $rab_data['realisasi_terpakai'];

    return $rab_data;
}

// =========================================================================
// FUNGSI UTAMA: MENGAMBIL DATA KUITANSI (DIKOREKSI SESUAI SKEMA BARU)
// =========================================================================

/**
 * Mengambil semua data untuk Kuitansi berdasarkan ID Realisasi.
 * Menggunakan skema tabel Realisasi dan Realisasi_Detail yang diberikan.
 * @param int $id_realisasi ID Realisasi.
 * @return array|false Data kuitansi atau false jika realisasi tidak ditemukan.
 */
function get_data_kwitansi_by_id_realisasi($id_realisasi) {
    global $conn;
    $id = (int) $id_realisasi;
    if (!$id) return false;

    $data = [
        'header' => null,
        'details' => [],
        'penandatangan' => null,
    ];

    // 1. Ambil data Realisasi (r) dan Detail Realisasi (rd)
    // Query ini secara eksplisit menggunakan rd.jumlah_realisasi dan rd.uraian
    $sql_details = "
        SELECT 
            r.tanggal_realisasi, 
            r.nomor_dokumen, 
            r.total_realisasi,
            rd.jumlah_realisasi,
            rd.uraian
        FROM 
            realisasi r
        JOIN 
            realisasi_detail rd ON r.id_realisasi = rd.id_realisasi
        WHERE 
            r.id_realisasi = ?
    ";
    
    $stmt_details = $conn->prepare($sql_details);
    
    if (!$stmt_details) {
        error_log("Gagal prepare statement detail realisasi: " . $conn->error);
        return false;
    }

    $stmt_details->bind_param("i", $id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    
    $first_row = true;
    while ($row = $result_details->fetch_assoc()) {
        if ($first_row) {
            // Ambil data header dari baris pertama
            $data['header'] = [
                'tanggal_realisasi' => $row['tanggal_realisasi'],
                'nomor_dokumen' => $row['nomor_dokumen'],
                'total_realisasi' => $row['total_realisasi'], // Ini hanya diambil sekali
            ];
            $first_row = false;
        }
        
        // Simpan data detail (Uraian dan Jumlah Realisasi)
        $data['details'][] = [
            'uraian' => $row['uraian'],
            'jumlah_realisasi' => $row['jumlah_realisasi'],
        ];
    }
    $stmt_details->close();
    
    // Jika tidak ada data realisasi yang ditemukan, kembalikan false
    if (!$data['header']) {
        return false;
    }

    // 2. Ambil data Direktur Keuangan (id_role = 3) untuk penandatangan
    // Asumsi: user (u) menyimpan informasi penandatangan, role (ro) menyimpan role name
    $stmt_signer = $conn->prepare("
        SELECT 
            u.nama_lengkap, 
            ro.nama_role 
        FROM 
            user u
        JOIN 
            role ro ON u.id_role = ro.id
        WHERE 
            u.id_role = 3 
        LIMIT 1
    ");

    if ($stmt_signer) {
        $stmt_signer->execute();
        $result_signer = $stmt_signer->get_result();
        $data['penandatangan'] = $result_signer->fetch_assoc();
        $stmt_signer->close();
    } else {
        error_log("Gagal prepare statement penandatangan: " . $conn->error);
    }

    return $data;
}

/**
 * Fungsi untuk memformat tanggal YYYY-MM-DD menjadi format Indonesia.
 * @param string $date_string Tanggal dalam format 'YYYY-MM-DD'.
 * @return string Tanggal dalam format 'DD Month YYYY'
 */
function format_tanggal_indonesia($date_string) {
    if (!$date_string) return '';
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', 
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', 
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $parts = explode('-', $date_string);
    if (count($parts) === 3) {
        return $parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];
    }
    return $date_string; // Kembalikan string asli jika format salah
}

/**
 * Fungsi untuk memformat angka menjadi format mata uang Rupiah.
 */
function format_rupiah($number) {
    if (!is_numeric($number)) {
        $number = 0;
    }
    return 'Rp. ' . number_format($number, 0, ',', '.');
}