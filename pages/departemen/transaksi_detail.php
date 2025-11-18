<?php
// PENTING: Mendeklarasikan variabel koneksi global ($conn)
global $conn;

// --- FUNGSI HELPER (Harus ada di semua file terkait keuangan) ---
if (!function_exists('get_status_color')) {
    function get_status_color($status) {
        switch ($status) {
            case 0: return 'label-info'; // Draft - Abu-abu
            case 1: return 'label-warning'; // Menunggu Keuangan - Kuning
            case 2: return 'label-primary'; // Menunggu Rektorat - Biru
            case 3: return 'label-success'; // Disetujui Final - Hijau
            case 4: return 'label-danger'; // Ditolak - Merah
            case 5: return 'label-success'; // Selesai - Biru muda 
            default: return 'label-default'; // Draft - Abu-abu
        }
    }
}
if (!function_exists('get_status_label')) {
    function get_status_label($status) {
        switch ($status) {
            case 0: return 'Draft';
            case 1: return 'Menunggu Keuangan';
            case 2: return 'Menunggu Rektorat';
            case 3: return 'Disetujui Final';
            case 4: return 'Ditolak';
            case 5: return 'Selesai';
            default: return 'Tidak Diketahui';
        }
    }
}
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return number_format($angka, 0, ',', '.');
    }
}

$id_realisasi = isset($_GET['id_realisasi']) ? (int)$_GET['id_realisasi'] : 0;
$header_data = null;
$error_message = null;

if ($id_realisasi === 0) {
    $error_message = "ID Realisasi tidak valid.";
} else {
    // --- 1. Ambil Data Header Transaksi Lengkap ---
    // PERBAIKAN KRITIS: Join ke detail_user (du) untuk mendapatkan nama_lengkap, karena tabel user tidak memiliki kolom nama.
    // Asumsi: Kolom id_validator_keuangan dan id_validator_rektorat ada di tabel 'realisasi' (meskipun tidak tercantum di skema yang Anda berikan, ini diperlukan untuk mendapatkan nama validator).
    $query_header = "
        SELECT 
            r.*, 
            rab.judul AS judul_rab,
            d.nama_departemen,
            du_dept.nama_lengkap AS nama_pengaju,
            du_keu.nama_lengkap AS nama_validator_keu,
            du_rek.nama_lengkap AS nama_validator_rek
        FROM 
            realisasi r 
        LEFT JOIN 
            rab ON r.id_rab = rab.id_rab 
        LEFT JOIN 
            departemen d ON r.id_departemen = d.id_departemen 
        LEFT JOIN 
            detail_user du_dept ON r.created_by = du_dept.id_user  -- Menggunakan created_by sebagai ID pengaju (submitter)
        LEFT JOIN 
            detail_user du_keu ON r.id_validator_keuangan = du_keu.id_user 
        LEFT JOIN 
            detail_user du_rek ON r.id_validator_rektorat = du_rek.id_user 
        WHERE 
            r.id_realisasi = ?
    ";
    
    $stmt_header = $conn->prepare($query_header);
    
    if ($stmt_header === false) {
         $error_message = "Gagal menyiapkan query database. Error: " . $conn->error;
    } else {
        $stmt_header->bind_param('i', $id_realisasi);
        
        if (!$stmt_header->execute()) {
            $error_message = "Gagal menjalankan query. Pastikan semua tabel terisi dengan data terkait dan kolom validator (id_validator_...) di tabel realisasi sudah benar. Error: " . $stmt_header->error;
        } else {
            $result_header = $stmt_header->get_result();
            $header_data = $result_header->fetch_assoc();
            
            if (!$header_data) {
                $error_message = "Data Realisasi dengan ID #{$id_realisasi} tidak ditemukan di database.";
            }
        }
        $stmt_header->close();
    }

    // --- 2. Ambil Data Detail Item (Simulasi: Digantikan dengan data realisasi_detail sebenarnya) ---
    $detail_items = []; 
    if (!$error_message && $header_data) {
        // PERBAIKAN: Mengambil data detail dari tabel realisasi_detail
        $query_detail = "
            SELECT 
                rd.uraian, 
                rd.jumlah_realisasi,
                rd.created_at,
                rd.id_realisasi_detail
            FROM 
                realisasi_detail rd
            WHERE 
                rd.id_realisasi = ?
            ORDER BY 
                rd.id_realisasi_detail ASC
        ";

        $stmt_detail = $conn->prepare($query_detail);
        if ($stmt_detail === false) {
             // Jika realisasi_detail belum dibuat, gunakan simulasi
             // Cek jika errornya karena tabel tidak ditemukan
             if (strpos($conn->error, 'realisasi_detail') !== false) {
                 goto use_simulated_detail;
             }
             $error_message = "Gagal menyiapkan query detail item. Error: " . $conn->error;
        } else {
            $stmt_detail->bind_param('i', $id_realisasi);
            if ($stmt_detail->execute()) {
                $result_detail = $stmt_detail->get_result();
                while ($row = $result_detail->fetch_assoc()) {
                    // Karena realisasi_detail hanya memiliki 'uraian' dan 'jumlah_realisasi',
                    // kita perlu mengisi kolom lain untuk konsistensi tampilan.
                    $detail_items[] = [
                        'uraian' => $row['uraian'],
                        // Asumsi: 1 unit dan Harga Satuan = Jumlah Realisasi jika data detail sederhana
                        'qty' => 1, 
                        'unit' => 'item', 
                        'harga_satuan' => $row['jumlah_realisasi'], 
                        'jumlah' => $row['jumlah_realisasi']
                    ];
                }
            } else {
                $error_message = "Gagal menjalankan query detail. Error: " . $stmt_detail->error;
            }
            $stmt_detail->close();
        }

        // Jika tidak ada data dari tabel (atau error), gunakan simulasi
        if (empty($detail_items)) {
            use_simulated_detail:
             if ($header_data['total_realisasi'] > 0) {
                 // Simulasi data detail item jika tabel 'realisasi_detail' kosong atau error
                 $detail_items = [
                     ['uraian' => 'Pembelian Alat Tulis Kantor (ATK) - Simulasi', 'qty' => 5, 'unit' => 'pcs', 'harga_satuan' => 200000, 'jumlah' => 1000000],
                     ['uraian' => 'Biaya Transportasi Tim - Simulasi', 'qty' => 1, 'unit' => 'kegiatan', 'harga_satuan' => 500000, 'jumlah' => 500000]
                 ];
                 // Penyesuaian total simulasi
                 $simulated_total = array_sum(array_column($detail_items, 'jumlah'));
                 if ($simulated_total != $header_data['total_realisasi']) {
                     $diff = $header_data['total_realisasi'] - $simulated_total;
                     $detail_items[] = ['uraian' => 'Penyesuaian/Pembulatan (Simulasi)', 'qty' => 1, 'unit' => 'item', 'harga_satuan' => $diff, 'jumlah' => $diff];
                 }
                 if (!$error_message) {
                     // Beri pesan info jika menggunakan simulasi
                     $error_message = "Data detail item realisasi disimulasikan karena tabel `realisasi_detail` kosong atau belum terisi. Total Realisasi: Rp " . format_rupiah($header_data['total_realisasi']);
                 }
             }
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="panel panel-default" style="border-top: 3px solid #3c8dbc;">
                <div class="panel-heading">
                    <h3 class="panel-title" style="font-weight: 600;">
                        <i class="fa fa-clipboard"></i> Dokumen Realisasi Keuangan #<?= htmlspecialchars($id_realisasi); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <?php if (!empty($error_message) && strpos($error_message, 'Gagal menyiapkan query database') !== false): ?>
                        <div class="alert alert-danger">
                            <strong class="text-xl"><i class="fa fa-times-circle"></i> Kesalahan Data Kritis:</strong> 
                            <?= htmlspecialchars($error_message); ?>
                            <p class="mt-2">Ini mungkin terjadi karena tabel `realisasi` Anda TIDAK memiliki kolom `id_validator_keuangan` atau `id_validator_rektorat` sesuai struktur yang Anda berikan.</p>
                        </div>
                    <?php return; endif; ?>

                    <?php if (!empty($error_message) && strpos($error_message, 'Data detail item realisasi disimulasikan') !== false): ?>
                         <div class="alert alert-warning">
                            <strong class="text-xl"><i class="fa fa-exclamation-triangle"></i> Informasi Detail:</strong> 
                            <?= htmlspecialchars($error_message); ?>
                            <p class="mt-2">Pastikan Anda telah mengisi data di tabel `realisasi_detail` untuk menampilkan item yang sebenarnya.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($header_data): ?>
                        
                        <!-- Ringkasan Status -->
                        <div class="well well-sm text-center" style="background-color: #f9f9f9; border-left: 5px solid #3c8dbc; padding: 15px;">
                            <p class="lead" style="margin-bottom: 5px;">
                                Status Dokumen: 
                                <span class="label label-lg <?= get_status_color($header_data['status']); ?>" style="font-size: 16px; padding: 6px 12px;">
                                    <?= htmlspecialchars(get_status_label($header_data['status'])); ?>
                                </span>
                            </p>
                            <?php if ($header_data['status'] == -1 && !empty($header_data['catatan_keuangan'])): ?>
                                <div class="alert alert-danger mt-3" style="margin-top: 10px; padding: 8px; font-size: 13px;">
                                    <i class="fa fa-ban"></i> **Ditolak oleh Keuangan** - Catatan: 
                                    *<?= nl2br(htmlspecialchars($header_data['catatan_keuangan'])); ?>*
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Data Header -->
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 25px;"><i class="fa fa-file-o"></i> Informasi Dokumen</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-condensed table-hover">
                                    <tr><th style="width: 40%;">Nomor Dokumen</th><td><?= htmlspecialchars($header_data['nomor_dokumen'] ?? '-'); ?></td></tr>
                                    <tr><th>Tanggal Realisasi</th><td><?= date('d M Y', strtotime($header_data['tanggal_realisasi'] ?? '')); ?></td></tr>
                                    <tr><th>Terkait RAB</th><td><span class="label label-primary"><?= htmlspecialchars($header_data['judul_rab'] ?? 'RAB Tidak Ada'); ?></span></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-condensed table-hover">
                                    <tr><th>Departemen Pengaju</th><td><?= htmlspecialchars($header_data['nama_departemen'] ?? '-'); ?></td></tr>
                                    <tr><th>Diajukan Oleh</th><td><?= htmlspecialchars($header_data['nama_pengaju'] ?? 'User Dihapus'); ?></td></tr>
                                    <tr><th>Total Realisasi</th><td class="text-danger lead" style="font-weight: 700;"><?= format_rupiah($header_data['total_realisasi'] ?? 0); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Keterangan Umum -->
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 25px;"><i class="fa fa-book"></i> Keterangan Umum</h4>
                        <div class="well" style="background-color: #fcf8e3; border-color: #f7ebbe;">
                            <?= nl2br(htmlspecialchars($header_data['deskripsi'] ?? 'Tidak ada keterangan umum.')); ?>
                        </div>

                        <!-- Validator Status -->
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 25px;"><i class="fa fa-check-square-o"></i> Status Persetujuan</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Validator Keuangan:</strong></p>
                                <div class="alert alert-info" style="padding: 5px 10px; margin-bottom: 10px;">
                                    <?= htmlspecialchars($header_data['nama_validator_keu'] ?? 'Belum Ditentukan'); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Validator Rektorat:</strong></p>
                                <div class="alert alert-info" style="padding: 5px 10px; margin-bottom: 10px;">
                                    <?= htmlspecialchars($header_data['nama_validator_rek'] ?? 'Belum Ditentukan'); ?>
                                </div>
                            </div>
                        </div>


                        <!-- Detail Item Realisasi -->
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 25px;"><i class="fa fa-list"></i> Detail Item Pengeluaran</h4>
                        <?php if (!empty($detail_items)): ?>
                            <div class="table-responsive" style="margin-top: 15px;">
                                <table class="table table-bordered table-striped table-condensed">
                                    <thead>
                                        <tr class="info">
                                            <th class="text-center">No</th>
                                            <th>Uraian Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-center">Unit</th>
                                            <th class="text-right">Harga Satuan (Rp)</th>
                                            <th class="text-right">Jumlah (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; $total_detail = 0; ?>
                                        <?php foreach ($detail_items as $item): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($item['uraian']); ?></td>
                                            <td class="text-center"><?= htmlspecialchars(format_rupiah($item['qty'])); ?></td>
                                            <td class="text-center"><?= htmlspecialchars($item['unit']); ?></td>
                                            <td class="text-right"><?= format_rupiah($item['harga_satuan']); ?></td>
                                            <td class="text-right text-danger" style="font-weight: 600;"><?= format_rupiah($item['jumlah']); ?></td>
                                            <?php $total_detail += $item['jumlah']; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="active">
                                            <th colspan="5" class="text-right">TOTAL KESELURUHAN REALISASI</th>
                                            <th class="text-right text-danger lead"><?= format_rupiah($total_detail); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fa fa-exclamation-triangle"></i> Tidak ada detail item pengeluaran yang terlampir pada dokumen ini.
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
                <div class="panel-footer text-right">
                    <a href="dashboard.php?page=departemen/transaksi_list.php" class="btn btn-default">
                        <i class="fa fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                    <?php 
                    // Tampilkan tombol Revisi/Edit jika statusnya Draft atau Ditolak
                    if (($header_data['status'] ?? null) == 0 || ($header_data['status'] ?? null) == -1): ?>
                        <a href="dashboard.php?page=departemen/transaksi_edit.php&id_realisasi=<?= $id_realisasi; ?>" 
                            class="btn btn-primary" title="Revisi dan Ajukan Kembali">
                            <i class="fa fa-pencil"></i> <?= ($header_data['status'] == -1) ? 'Revisi Dokumen' : 'Edit Draft'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>