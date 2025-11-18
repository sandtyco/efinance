<?php
// FILE: /efinance/pages/departemen/transaksi_list.php
// Halaman daftar realisasi yang HANYA menampilkan data milik departemen yang sedang login.

// PENTING: Mendeklarasikan variabel koneksi global ($conn)
global $conn;

// --- 1. AMBIL ID DEPARTEMEN PENGGUNA DARI SESI ---
// Asumsi: Session telah dimulai di file induk (dashboard.php)
$id_departemen_user = (int)($_SESSION['id_departemen'] ?? 0); 

// --- FUNGSI HELPER (Harus ada di semua file terkait keuangan) ---

/**
 * Mengembalikan kelas warna Bootstrap untuk label status.
 */
if (!function_exists('get_status_color')) {
    function get_status_color($status) {
        switch ($status) {
            case 0: return 'label-info';    // Draft
            case 1: return 'label-warning'; // Diajukan/Menunggu Persetujuan
            case 2: return 'label-primary'; // Disetujui Keuangan/Menunggu Rektorat
            case 3: return 'label-success'; // Disetujui Rektorat (Disetujui Final)
            case 4: return 'label-danger';  // Ditolak
            case 5: return 'label-success'; // Selesai (Closed)
            default: return 'label-default';
        }
    }
}

/**
 * Mengembalikan label status Realisasi yang mudah dibaca (Sesuai Permintaan).
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
 * Memformat angka menjadi Rupiah tanpa desimal.
 */
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        // Cek jika angka adalah null atau bukan numerik
        if (!is_numeric($angka)) {
            $angka = 0;
        }
        return number_format($angka, 0, ',', '.');
    }
}

// Inisialisasi variabel
$transaksi_data = [];
$list_error = null;

// --- 2. LOGIKA KUERI DENGAN FILTER DEPARTEMEN ---
if ($id_departemen_user === 0) {
    // Jika ID Departemen tidak ditemukan, set pesan error dan jangan jalankan kueri
    $list_error = "ID Departemen tidak teridentifikasi. Pastikan Anda telah login sebagai Departemen.";
} else {
    // Query untuk mengambil data realisasi, termasuk catatan penolakan dari Keuangan.
    $query_list = "
        SELECT 
            r.id_realisasi, 
            r.tanggal_realisasi, 
            r.nomor_dokumen, 
            r.total_realisasi, 
            r.status, 
            r.catatan_keuangan, 
            rab.judul AS judul_rab, 
            d.nama_departemen 
        FROM 
            realisasi r 
        LEFT JOIN 
            rab rab ON r.id_rab = rab.id_rab 
        LEFT JOIN 
            departemen d ON r.id_departemen = d.id_departemen 
        WHERE 
            r.id_departemen = {$id_departemen_user} /* FILTER KEAMANAN DITERAPKAN */
        ORDER BY 
            r.created_at DESC
    ";

    // Menggunakan objek koneksi global yang dideklarasikan di awal
    $result = $conn->query($query_list);

    if ($result) {
        $transaksi_data = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $list_error = "Gagal mengambil data realisasi: " . $conn->error;
    }
}
?>

<div class="row">
    <div class="col-lg-12">
        <h3 class="page-header">
            <span class="glyphicon glyphicon-shopping-cart"></span> Form Daftar Realisasi Anggaran
        </h3>
        
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-list"></i> Daftar Realisasi Saya (ID Dept: <?= $id_departemen_user; ?>)</h3>
                </div>
                <div class="panel-body">
                    <?php 
                    // Tampilkan pesan sukses dari SESSION jika ada
                    if (isset($_SESSION['success_message'])) {
                        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                        unset($_SESSION['success_message']); 
                    }
                    
                    if (!empty($list_error)): ?>
                        <div class="alert alert-danger">
                            <strong>Kesalahan Database:</strong> <?= htmlspecialchars($list_error); ?>
                        </div>
                    <?php elseif (empty($transaksi_data)): ?>
                        <div class="alert alert-info text-center">
                            Tidak ada data transaksi realisasi yang Anda ajukan saat ini.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered" id="data-table">
                                <thead>
                                    <tr class="info">
                                        <th>ID</th>
                                        <th>Tgl. Realisasi</th>
                                        <th>Nomor Dokumen</th>
                                        <th>Departemen</th>
                                        <th>Terkait RAB</th>
                                        <th class="text-right">Total (Rp)</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_data as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id_realisasi']); ?></td>
                                        <td><?= date('d M Y', strtotime($row['tanggal_realisasi'])); ?></td>
                                        <td><?= htmlspecialchars($row['nomor_dokumen']); ?></td>
                                        <td><?= htmlspecialchars($row['nama_departemen'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['judul_rab'] ?? 'RAB Dihapus'); ?></td>
                                        <td class="text-right text-danger"><?= format_rupiah($row['total_realisasi']); ?></td>
                                        <td class="text-center">
                                            <span class="label <?= get_status_color($row['status']); ?>">
                                                <?= htmlspecialchars(get_status_label($row['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-center" style="min-width: 200px;">
                                            <!-- Tombol Detail (Selalu Ada) -->
                                            <a href="dashboard.php?page=transaksi_detail.php&id_realisasi=<?= $row['id_realisasi']; ?>" 
                                                class="btn btn-sm btn-info" title="Lihat Detail">
                                                <i class="fa fa-search"></i> Detail
                                            </a>

                                            <?php 
                                            // Status 0 = Draft, Status 4 = Ditolak (Perlu Revisi/Edit)
                                            $is_editable = ($row['status'] == 0 || $row['status'] == 4);

                                            // --- LOGIKA TOMBOL UNTUK STATUS DRAFT (0) DAN DITOLAK (4) ---
                                            if ($is_editable): 
                                                $action_label = ($row['status'] == 4) ? 'Revisi' : 'Edit';
                                            ?>
                                                <!-- Tombol Revisi/Edit (Mengarah ke Edit Form) -->
                                                <a href="dashboard.php?page=departemen/transaksi_edit.php&id_realisasi=<?= $row['id_realisasi']; ?>" 
                                                    class="btn btn-sm btn-primary" title="<?= $action_label; ?> dan Ajukan Kembali">
                                                    <i class="fa fa-pencil"></i> <?= $action_label; ?>
                                                </a>
                                                
                                                <!-- Tombol Hapus (Dikonfirmasi di sisi server/modal non-blocking) -->
                                                <a href="dashboard.php?page=departemen/transaksi_delete.php&id_realisasi=<?= $row['id_realisasi']; ?>" 
                                                    class="btn btn-sm btn-danger" title="Hapus Transaksi">
                                                    <i class="fa fa-trash"></i> Hapus
                                                </a>
                                                
                                                <?php if ($row['status'] == 4): // Tombol Komentar hanya untuk yang Ditolak ?>
                                                    <!-- Tombol Komentar (Akan memunculkan modal) -->
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="showCommentModal('<?= htmlspecialchars($row['nomor_dokumen']); ?>', '<?= addslashes(nl2br(htmlspecialchars($row['catatan_keuangan']))); ?>');">
                                                        <i class="fa fa-comment"></i> Komentar
                                                    </button>
                                                <?php endif; ?>
                                                
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small">Total: <?= count($transaksi_data); ?> Realisasi</p>
                    <?php endif; ?>
                </div>
                <div class="panel-footer text-right">
                    <a href="dashboard.php?page=transaksi_add.php" class="btn btn-primary">
                        <i class="fa fa-plus-circle"></i> Ajukan Realisasi Baru
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

ℹ️ Informasi & Panduan

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-info-sign"></i> Panduan Singkat Pengajuan Realisasi RAB</h3>
            </div>
            <div class="panel-body">
                <p>Fokus utama Anda pada form ini adalah untuk mengajukan realisasi anggaran untuk Departemen dengan ketentuan sebagai berikut:</p>
                <ol>
                    <li>Sumber anggaran yang dipergunakan berasal dari RAB Tahun berjalan yang telah <b>DISETUJUI</b> oleh Direktur Keuangan dan Rektorat.</li>
                    <li>Sumber anggaran yang dipergunakan berdasarkan <b>Nomor Akun</b> yang telah disepakati Departemen dan <b>DISETUJUI</b> oleh Direktur Keuangan dan Rektorat.</li>
                    <li>Apabila penggunaan realisasi anggaran tidak sesuai dengan Akun dan akan menggunakan Akun tertentu harus melalui surat <b>persetujuan tertulis</b> dari Rektorat yang dilampirkan saat pengajuan.</li>
                    <li>Pengajuan realisasi anggaran dapat diajukan dengan waktu yang telah ditentukan yaitu, <b>Minggu Pertama</b> dan <b>Minggu Ketiga</b> pada setiap bulannya.</li>
                    <li>Apabila pengajuan realisasi anggaran dilakukan diluar waktu yang telah ditentukan, maka akan dimasukan pada waktu ajuan minggu berikutnya.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sederhana untuk Komentar/Catatan Penolakan (Karena alert() dilarang) -->
<div id="commentModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);">
        <h4 style="margin-top: 0; color: #d9534f;"><i class="fa fa-times-circle"></i> Catatan Penolakan</h4>
        <p><strong>Dokumen:</strong> <span id="modalDocNumber"></span></p>
        <div style="border: 1px solid #eee; padding: 10px; background-color: #f9f9f9; min-height: 80px;">
            <p id="modalCommentText"></p>
        </div>
        <div style="text-align: right; margin-top: 20px;">
            <button onclick="document.getElementById('commentModal').style.display='none';" class="btn btn-default">Tutup</button>
        </div>
    </div>
</div>

<!-- Script untuk Modal dan DataTables -->
<script>
    // Fungsi untuk menampilkan modal komentar
    function showCommentModal(docNumber, commentText) {
        document.getElementById('modalDocNumber').innerText = docNumber;
        document.getElementById('modalCommentText').innerHTML = commentText;
        document.getElementById('commentModal').style.display = 'block';
    }

    // Inisialisasi DataTables (Asumsi library jQuery dan DataTables tersedia)
    // Catatan: Pastikan library jQuery dan DataTables dimuat sebelum skrip ini dijalankan.
    $(document).ready(function() {
        if ($('#data-table').length) {
            $('#data-table').DataTable({
                "paging": true,
                "ordering": true,
                "info": true,
                "searching": true,
                // Kolom 6 (Total) diurutkan sebagai mata uang/rupiah
                "columnDefs": [
                    { "type": "num-fmt", "targets": 5 } 
                ]
            });
        }
    });

    // Tutup modal ketika pengguna mengklik di luar kotak modal
    window.onclick = function(event) {
      var modal = document.getElementById('commentModal');
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
</script>