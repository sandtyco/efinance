<?php
// pages/rektorat/transaksi_validation.php
// Handler untuk memproses Persetujuan atau Penolakan Realisasi oleh Rektorat.
// Status yang diproses: 2 (Disetujui Keuangan)
// Status hasil: 3 (Disetujui Rektorat) atau 4 (Ditolak)
global $conn;

// Ambil data POST
$id_realisasi = isset($_POST['id_realisasi']) ? (int)$_POST['id_realisasi'] : 0;
// action: 1=Approve (Setujui Final -> Status 3), 0=Reject (Tolak -> Status 4)
$action = isset($_POST['action']) ? (int)$_POST['action'] : -1; 
// Catatan Rektorat (Disimpan di kolom catatan_rektorat jika ada, atau menggunakan catatan_keuangan jika skema tidak diubah)
// Karena tidak ada kolom catatan_rektorat di skema awal, kita tetap menggunakan `catatan_keuangan` 
// untuk menyimpan catatan penolakan Rektorat, atau membuat kolom baru (catatan_rektorat) untuk skema yang lebih baik.
// Untuk menjaga kompatibilitas dengan skema yang ada, kita anggap Catatan Rektorat disimpan di kolom baru `catatan_rektorat`
// Jika kolom `catatan_rektorat` tidak ada, ini akan menyebabkan error database.
// Sebagai solusi terbaik, saya akan menggunakan kolom yang diasumsikan ada di tabel realisasi: `catatan_rektorat`.
// Catatan: Jika skema database Anda hanya memiliki `catatan_keuangan`, Anda harus menyesuaikan `$query_update` Penolakan.
$catatan_rektorat = isset($_POST['catatan_rektorat']) ? trim($_POST['catatan_rektorat']) : '';

// Validasi dasar
if ($id_realisasi === 0 || !in_array($action, [0, 1])) {
    $_SESSION['flash_message'] = "<div class='alert alert-danger'>Error: Permintaan validasi tidak valid. (ID Realisasi atau Aksi tidak dikenal).</div>";
    header('Location: dashboard.php?page=rektorat/transaksi_list.php');
    exit;
}

// Tambahkan validasi jika action = Tolak (0), catatan wajib ada
if ($action === 0 && empty($catatan_rektorat)) {
    $_SESSION['flash_message'] = "<div class='alert alert-danger'>Error: Catatan Rektorat wajib diisi untuk penolakan.</div>";
    header('Location: dashboard.php?page=rektorat/transaksi_detail.php&id_realisasi=' . $id_realisasi);
    exit;
}

// Mulai Transaksi Database
$conn->begin_transaction();
$success = false;
$message = "";

try {
    // 1. Cek status Realisasi saat ini (hanya Realisasi dengan status=2 yang boleh diproses)
    $query_status = "SELECT status FROM realisasi WHERE id_realisasi = ?";
    $stmt_status = $conn->prepare($query_status);
    if ($stmt_status === false) { throw new Exception("Prepare status check failed: " . $conn->error); }
    $stmt_status->bind_param('i', $id_realisasi);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    $data_status = $result_status->fetch_assoc();
    $stmt_status->close();

    if (!$data_status || $data_status['status'] != 2) {
        throw new Exception("Dokumen Realisasi #{$id_realisasi} tidak dalam status '2 Disetujui Keuangan' dan tidak dapat divalidasi.");
    }

    if ($action === 1) {
        // A. Persetujuan FINAL (Status = 3)
        $new_status = 3;
        $message = "<div class='alert alert-success'>Realisasi #{$id_realisasi} berhasil Disetujui Final (Status: 3 Disetujui Rektorat).</div>";
        
        // Update status saja
        $query_update = "
            UPDATE realisasi SET 
                status = ?
            WHERE id_realisasi = ?
        ";
        $stmt_update = $conn->prepare($query_update);
        if ($stmt_update === false) { throw new Exception("Prepare approval failed: " . $conn->error); }
        // Binding 2 parameter: status, id_realisasi
        $stmt_update->bind_param('ii', $new_status, $id_realisasi);
        
    } else if ($action === 0) {
        // B. Penolakan (Status = 4)
        $new_status = 4;
        $message = "<div class='alert alert-danger'>Realisasi #{$id_realisasi} DITOLAK (Status: 4 Ditolak). Catatan Rektorat telah disimpan.</div>";
        
        // Update status dan kolom catatan_rektorat (Diasumsikan kolom ini ada untuk menyimpan catatan dari Rektorat)
        $query_update = "
            UPDATE realisasi SET 
                status = ?, 
                catatan_rektorat = ?
            WHERE id_realisasi = ?
        ";
        $stmt_update = $conn->prepare($query_update);
        if ($stmt_update === false) { throw new Exception("Prepare rejection failed: " . $conn->error); }
        // Binding 3 parameter: status, catatan_rektorat, id_realisasi
        $stmt_update->bind_param('isi', $new_status, $catatan_rektorat, $id_realisasi);
    }
    
    // Eksekusi Update
    $stmt_update->execute();
    $stmt_update->close();
    
    // Commit transaksi
    $conn->commit();
    $success = true;

} catch (Exception $e) {
    $conn->rollback();
    // Gunakan pesan error yang lebih informatif
    $message = "Gagal memproses validasi. Error: " . $e->getMessage();
}

// Redirect setelah selesai
if ($success) {
    $_SESSION['flash_message'] = $message;
    header('Location: dashboard.php?page=rektorat/transaksi_list.php');
} else {
    // Tambahkan pesan error sebagai flash message agar terlihat di halaman detail
    $_SESSION['flash_message'] = "<div class='alert alert-danger'>{$message}</div>";
    header('Location: dashboard.php?page=rektorat/transaksi_detail.php&id_realisasi=' . $id_realisasi);
}
exit;
?>