<?php
// PENTING: Mendeklarasikan variabel koneksi global ($conn)
global $conn;

// --- FUNGSI UTILITY (Status dan Format) ---

/**
 * Mengembalikan label status Realisasi yang mudah dibaca,
 * menggunakan skema status yang baru.
 */
if (!function_exists('get_status_label')) {
    function get_status_label($status) {
        switch ($status) {
            case 0: return '0 Draft';
            case 1: return '1 Diajukan';
            case 2: return '2 Disetujui Keuangan';
            case 3: return '3 Disetujui Rektorat';
            case 4: return '4 Ditolak';
            case 5: return '5 Selesai';
            default: return 'Tidak Diketahui';
        }
    }
}

/**
 * Mengembalikan kelas CSS Bootstrap untuk warna status (label).
 * Disesuaikan dengan skema status yang baru.
 */
if (!function_exists('get_status_color')) {
    function get_status_color($status) {
        switch ($status) {
            case 1: return 'label-warning'; // Diajukan (Menunggu Validasi)
            case 2: return 'label-primary'; // Disetujui Keuangan (Lanjut ke Rektorat)
            case 3: return 'label-success'; // Disetujui Rektorat (Final Approved)
            case 4: return 'label-danger';  // Ditolak
            case 5: return 'label-default'; // Selesai
            case 0: return 'label-info';    // Draft
            default: return 'label-info';
        }
    }
}

if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return number_format($angka, 0, ',', '.');
    }
}

$validation_message = null;
$validation_error = null;
$transaksi_data = null; // Inisialisasi

// --- 1. Ambil ID dari URL dan Cek Status ---
if (!isset($_GET['id_realisasi']) || !is_numeric($_GET['id_realisasi'])) {
    $validation_error = "ID Realisasi tidak valid.";
    $id_realisasi = 0; // Set ke 0 agar query tidak jalan
} else {
    $id_realisasi = (int)$_GET['id_realisasi'];

    // --- 2. Handle POST Submission (Validasi: Logic Tombol) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action']; // 'approve' atau 'reject'
        
        $catatan_keuangan = trim($_POST['catatan_keuangan']);
        
        // Tentukan status baru berdasarkan action (menggunakan skema status baru)
        // 2: Disetujui Keuangan, 4: Ditolak
        $new_status = ($action === 'approve') ? 2 : 4; 
        $status_label = get_status_label($new_status);
        
        // Query untuk update status dan catatan_keuangan di tabel 'realisasi'
        // Hanya bisa diubah jika status saat ini adalah 1 (Diajukan)
        $query_update = "
            UPDATE 
                realisasi 
            SET 
                status = ?, 
                catatan_keuangan = ? 
            WHERE 
                id_realisasi = ? AND status = 1
        ";
        
        $stmt_update = $conn->prepare($query_update);
        
        if ($stmt_update === false) {
             $validation_error = "Gagal menyiapkan query update: " . $conn->error;
        } else {
            $stmt_update->bind_param('isi', $new_status, $catatan_keuangan, $id_realisasi);

            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    // Berhasil update, simpan pesan sukses
                    // Gunakan $_SESSION['success_message'] jika tersedia
                    // $_SESSION['success_message'] = "Validasi berhasil! Transaksi #{$id_realisasi} telah diubah status menjadi: {$status_label}.";
                    
                    // Redirect ke halaman daftar setelah berhasil
                    echo "<script>window.location.href='dashboard.php?page=transaksi_list.php';</script>";
                    exit;
                } else {
                    $validation_error = "Gagal memproses validasi. Status transaksi mungkin sudah berubah (bukan lagi '1 Diajukan').";
                }
            } else {
                $validation_error = "Gagal mengupdate database: " . $conn->error;
            }
            $stmt_update->close();
        }
    }
    
    // --- 3. Ambil Detail Transaksi untuk Display ---
    $query_transaksi = "
        SELECT 
            r.*, 
            rab.judul AS judul_rab, 
            rab.id_rab AS nomor_rab, 
            du.nama_lengkap AS nama_pembuat -- Ambil nama dari detail_user
        FROM 
            realisasi r 
        LEFT JOIN 
            rab rab ON r.id_rab = rab.id_rab 
        LEFT JOIN 
            user u ON r.created_by = u.id_user -- JOIN ke tabel user melalui created_by
        LEFT JOIN
            detail_user du ON u.id_user = du.id_user -- JOIN ke detail_user untuk nama_lengkap
        WHERE 
            r.id_realisasi = ?
    ";
    
    $stmt = $conn->prepare($query_transaksi);
    
    if ($stmt === false) {
         $validation_error = "Gagal menyiapkan query detail: " . $conn->error;
    } else {
        $stmt->bind_param('i', $id_realisasi);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaksi_data = $result->fetch_assoc();
        $stmt->close();

        if (!$transaksi_data) {
            $validation_error = "Data Realisasi dengan ID #{$id_realisasi} tidak ditemukan.";
        } elseif ($transaksi_data['status'] != 1) {
            $validation_error = "Transaksi ini tidak dalam status '1 Diajukan' dan tidak dapat divalidasi.";
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-pencil-square-o"></i> Validasi Realisasi Keuangan #<?= htmlspecialchars($id_realisasi ?? 'N/A'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <!-- Notifikasi Error/Pesan -->
                    <?php if (isset($validation_error) && !empty($validation_error)): ?>
                        <div class="alert alert-danger">
                            <strong>Kesalahan!</strong> <?= htmlspecialchars($validation_error); ?>
                            <br><a href="dashboard.php?page=transaksi_list.php" class="btn btn-sm btn-default" style="margin-top: 10px;">
                                <i class="fa fa-arrow-left"></i> Kembali ke Daftar
                            </a>
                        </div>
                    <?php return; endif; ?>
                    
                    <?php 
                    // Tampilkan hanya jika data ada DAN statusnya benar-benar 1 (Diajukan)
                    if ($transaksi_data && $transaksi_data['status'] == 1): 
                    ?>
                        
                        <!-- Informasi Ringkas Transaksi -->
                        <div class="well well-sm">
                            <p><strong>Dibuat Oleh:</strong> <?= htmlspecialchars($transaksi_data['nama_pembuat'] ?? 'User Tidak Diketahui'); ?></p>
                            <p><strong>No. Dokumen:</strong> <?= htmlspecialchars($transaksi_data['nomor_dokumen']); ?></p>
                            <p><strong>Judul RAB:</strong> <?= htmlspecialchars($transaksi_data['judul_rab'] ?? 'N/A'); ?></p>
                            <p><strong>Total Realisasi:</strong> <span class="text-danger">Rp <?= format_rupiah($transaksi_data['total_realisasi']); ?></span></p>
                            <p><strong>Status Saat Ini:</strong> 
                                <span class="label <?= get_status_color($transaksi_data['status']); ?>">
                                    <?= htmlspecialchars(get_status_label($transaksi_data['status'])); ?>
                                </span>
                            </p>
                            <p class="text-muted small">
                                Pastikan Anda telah memeriksa detail transaksi (lampiran, item, dan total) sebelum melakukan validasi.
                                <a href="dashboard.php?page=transaksi_detail.php&id_realisasi=<?= $id_realisasi; ?>" target="_blank">Lihat Detail Lengkap</a>
                            </p>
                        </div>
                        
                        <!-- Form Validasi -->
                        <form method="POST" action="dashboard.php?page=transaksi_validation.php&id_realisasi=<?= $id_realisasi; ?>">
                            <div class="form-group">
                                <label for="catatan_keuangan">Catatan Keuangan (Opsional)</label>
                                <textarea name="catatan_keuangan" id="catatan_keuangan" class="form-control" rows="3" 
                                    placeholder="Tuliskan catatan persetujuan atau alasan penolakan di sini..."></textarea>
                                <p class="help-block">Catatan ini akan tercatat dalam riwayat transaksi.</p>
                            </div>
                            
                            <hr>
                            
                            <div class="form-group text-right">
                                <!-- Tombol Tolak (Status 4) -->
                                <button type="submit" name="action" value="reject" 
                                    class="btn btn-danger btn-lg" 
                                    onclick="return confirm('ANDA YAKIN INGIN MENOLAK transaksi ini? Status akan menjadi (4 Ditolak). Tindakan ini tidak dapat dibatalkan.');">
                                    <i class="fa fa-times-circle"></i> Tolak Transaksi (Status: 4 Ditolak)
                                </button>
                                
                                <!-- Tombol Setuju (Status 2) -->
                                <button type="submit" name="action" value="approve" 
                                    class="btn btn-success btn-lg"
                                    onclick="return confirm('ANDA YAKIN INGIN MENYETUJUI transaksi ini? Status akan menjadi (2 Disetujui Keuangan) dan diteruskan ke Rektorat.');">
                                    <i class="fa fa-check-circle"></i> Setujui & Lanjut (Status: 2 Disetujui Keuangan)
                                </button>
                            </div>
                        </form>

                    <?php endif; ?>
                </div>
                
                <div class="panel-footer">
                    <a href="dashboard.php?page=transaksi_list.php" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>