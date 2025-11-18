<?php
// PENTING: Mendeklarasikan variabel koneksi global ($conn)
global $conn;

// --- FUNGSI HELPER (Sudah disesuaikan dengan skema status baru) ---

if (!function_exists('format_rupiah')) {
    /**
     * Memformat angka menjadi format mata uang Rupiah (contoh: 1.000.000)
     */
    function format_rupiah($angka) {
        return number_format($angka, 0, ',', '.');
    }
}

if (!function_exists('get_status_label')) {
    /**
     * Mengembalikan label status Realisasi yang mudah dibaca.
     * SKEMA STATUS BARU: 0=Draft, 1=Diajukan, 2=Disetujui Keuangan, 3=Disetujui Rektorat, 4=Ditolak, 5=Selesai.
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
}

$id_realisasi = isset($_GET['id_realisasi']) ? (int)$_GET['id_realisasi'] : 0;
$realisasi_data = null;
$detail_items = [];
$rab_options = [];
$error_message = null;
$success_message = null;

if ($id_realisasi === 0) {
    $error_message = "ID Realisasi tidak valid.";
    goto render_page; // Langsung ke rendering jika ID tidak valid
}

// --- 1. Ambil Data Realisasi yang Akan Diedit ---
$query_realisasi = "SELECT * FROM realisasi WHERE id_realisasi = ?";
$stmt_realisasi = $conn->prepare($query_realisasi);

if ($stmt_realisasi === false) {
    $error_message = "Gagal menyiapkan query realisasi. Error: " . $conn->error;
    goto render_page;
}

$stmt_realisasi->bind_param('i', $id_realisasi);
$stmt_realisasi->execute();
$result_realisasi = $stmt_realisasi->get_result();
$realisasi_data = $result_realisasi->fetch_assoc();
$stmt_realisasi->close();

if (!$realisasi_data) {
    $error_message = "Data Realisasi dengan ID #{$id_realisasi} tidak ditemukan.";
    goto render_page;
}

// Hanya izinkan pengeditan jika statusnya Draft (0) atau Ditolak (4).
// Logic disesuaikan dari status lama (-1) ke status baru (4).
if ($realisasi_data['status'] != 0 && $realisasi_data['status'] != 4) {
    $error_message = "Dokumen ini sedang dalam proses validasi (Status: " . get_status_label($realisasi_data['status']) . ") dan tidak dapat direvisi.";
    goto render_page;
}

// --- 2. Ambil Daftar RAB untuk Opsi Pilihan (Hanya yang status disetujui final) ---
// Filter RAB Final berdasarkan logika yang ada: status_keuangan = 5 DAN status_rektorat = 1
$query_rab = "SELECT id_rab, judul FROM rab WHERE status_keuangan = 5 AND status_rektorat = 1 ORDER BY judul ASC"; 
$result_rab = $conn->query($query_rab);
if ($result_rab) {
    while ($row = $result_rab->fetch_assoc()) {
        $rab_options[] = $row;
    }
} else {
    // Menampilkan error yang lebih spesifik jika query RAB gagal
    $error_message = "Gagal mengambil daftar RAB. Error Detail: " . $conn->error;
    goto render_page;
}

// --- 3. Ambil Detail Item (realisasi_detail) ---
$query_detail = "SELECT uraian, jumlah_realisasi FROM realisasi_detail WHERE id_realisasi = ? ORDER BY id_realisasi_detail ASC";
$stmt_detail = $conn->prepare($query_detail);
if ($stmt_detail === false) {
    // Jika realisasi_detail belum ada, kita bisa menggunakan item dummy minimal
    $detail_items = [['uraian' => '', 'jumlah_realisasi' => 0]];
} else {
    $stmt_detail->bind_param('i', $id_realisasi);
    if ($stmt_detail->execute()) {
        $result_detail = $stmt_detail->get_result();
        while ($row = $result_detail->fetch_assoc()) {
            // Karena realisasi_detail sederhana, kita hanya ambil uraian dan jumlah realisasi
            $detail_items[] = [
                'uraian' => $row['uraian'],
                'jumlah_realisasi' => $row['jumlah_realisasi']
            ];
        }
        $stmt_detail->close();
    }
}
// Jika tidak ada detail sama sekali, inisialisasi satu baris kosong
if (empty($detail_items)) {
    $detail_items = [['uraian' => '', 'jumlah_realisasi' => 0]];
}

// --- 4. Proses Form Submission (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize dan ambil data POST
    $id_rab_new = (int)($_POST['id_rab'] ?? 0);
    $nomor_dokumen = htmlspecialchars(trim($_POST['nomor_dokumen'] ?? ''));
    $tanggal_realisasi = htmlspecialchars($_POST['tanggal_realisasi'] ?? date('Y-m-d'));
    $deskripsi = htmlspecialchars(trim($_POST['deskripsi'] ?? ''));
    $uraian_detail = $_POST['uraian_detail'] ?? [];
    $jumlah_detail = $_POST['jumlah_detail'] ?? [];

    $total_realisasi = 0;
    $new_detail_items = [];

    // Validasi dan hitung total realisasi dari item detail
    foreach ($uraian_detail as $i => $uraian) {
        // Konversi format Rupiah (ex: 1.000.000,00) ke float (ex: 1000000.00)
        // Hapus semua titik, lalu ganti koma dengan titik (jika ada)
        $jumlah_raw = str_replace('.', '', $jumlah_detail[$i]);
        $jumlah = (float)str_replace(',', '.', $jumlah_raw); 
        
        // Hanya proses baris yang memiliki uraian dan jumlah > 0
        if (!empty(trim($uraian)) && $jumlah > 0) {
            $total_realisasi += $jumlah;
            $new_detail_items[] = [
                'uraian' => htmlspecialchars(trim($uraian)),
                'jumlah_realisasi' => $jumlah
            ];
        }
    }

    if (empty($new_detail_items)) {
        $error_message = "Detail item realisasi minimal harus ada satu baris dengan jumlah lebih dari Rp 0.";
        // Untuk form yang gagal, kita perlu memuat ulang data detail_items dengan input POST yang gagal
        $detail_items = array_map(function($uraian, $jumlah_raw) {
            // Konversi kembali ke format display untuk ditampilkan di form
            $jumlah = (float)str_replace(',', '.', str_replace('.', '', $jumlah_raw));
            return ['uraian' => htmlspecialchars(trim($uraian)), 'jumlah_realisasi' => $jumlah];
        }, $uraian_detail, $jumlah_detail);
        goto render_page;
    }
    if (empty($nomor_dokumen)) {
        $error_message = "Nomor Dokumen wajib diisi.";
        goto render_page;
    }
    // Validasi RAB hanya dilakukan jika ada RAB yang tersedia
    if (!empty($rab_options) && $id_rab_new <= 0) {
        $error_message = "Anda harus memilih RAB yang terkait.";
        goto render_page;
    }
    if (empty($rab_options) && $id_rab_new <= 0) {
        // Jika tidak ada RAB yang disetujui, realisasi tidak dapat diajukan
        $error_message = "Tidak ada RAB yang disetujui penuh. Anda tidak dapat mengajukan realisasi.";
        goto render_page;
    }


    // Mulai Transaksi Database
    $conn->begin_transaction();
    try {
        // A. Update Header Realisasi
        $query_update_header = "
            UPDATE realisasi SET 
                id_rab = ?, 
                nomor_dokumen = ?, 
                tanggal_realisasi = ?, 
                deskripsi = ?, 
                total_realisasi = ?,
                status = 1, -- Kembalikan status ke '1 Diajukan' setelah direvisi
                updated_at = NOW()
            WHERE id_realisasi = ?
        ";
        $stmt_update = $conn->prepare($query_update_header);
        
        // CEK 1: Gagal Prepare Update Header
        if ($stmt_update === false) {
             throw new Exception("Gagal menyiapkan query UPDATE realisasi: " . $conn->error);
        }
        
        $stmt_update->bind_param(
            'issdsi', 
            $id_rab_new, 
            $nomor_dokumen, 
            $tanggal_realisasi, 
            $deskripsi, 
            $total_realisasi, 
            $id_realisasi
        );
        $stmt_update->execute();
        $stmt_update->close();

        // B. Hapus Detail Lama
        $query_delete_detail = "DELETE FROM realisasi_detail WHERE id_realisasi = ?";
        $stmt_delete = $conn->prepare($query_delete_detail);
        
        // CEK 2: Gagal Prepare Delete Detail
        if ($stmt_delete === false) {
             throw new Exception("Gagal menyiapkan query DELETE realisasi_detail: " . $conn->error);
        }
        
        $stmt_delete->bind_param('i', $id_realisasi);
        $stmt_delete->execute();
        $stmt_delete->close();

        // C. Insert Detail Baru
        $query_insert_detail = "INSERT INTO realisasi_detail (id_realisasi, uraian, jumlah_realisasi) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($query_insert_detail);
        
        // CEK 3: Gagal Prepare Insert Detail
        if ($stmt_insert === false) {
             throw new Exception("Gagal menyiapkan query INSERT realisasi_detail: " . $conn->error); 
        }

        foreach ($new_detail_items as $item) {
            $stmt_insert->bind_param('isd', $id_realisasi, $item['uraian'], $item['jumlah_realisasi']);
            $stmt_insert->execute();
        }
        $stmt_insert->close();

        // Commit transaksi jika semua berhasil
        $conn->commit();
        $success_message = "Dokumen Realisasi #{$id_realisasi} berhasil direvisi dan diajukan kembali untuk validasi. Status: 1 Diajukan.";
        
        // Reload data header dan detail yang baru untuk tampilan
        $realisasi_data['id_rab'] = $id_rab_new;
        $realisasi_data['nomor_dokumen'] = $nomor_dokumen;
        $realisasi_data['tanggal_realisasi'] = $tanggal_realisasi;
        $realisasi_data['deskripsi'] = $deskripsi;
        $realisasi_data['total_realisasi'] = $total_realisasi;
        $realisasi_data['status'] = 1; // Diperbarui menjadi status 1
        $detail_items = $new_detail_items;


    } catch (Exception $e) {
        $conn->rollback();
        // Menampilkan pesan error detail dari database
        $error_message = "Gagal menyimpan perubahan. Transaksi dibatalkan. Error Detail Database: " . $e->getMessage();
        
        // Memastikan detail items dimuat kembali dari POST agar user tidak kehilangan input
        $detail_items = $new_detail_items;
    }
}

render_page:
?>

<div class="container-fluid">
    <div class="row">
        <!-- Menggunakan col-md-12 karena col-md-offset-1 mungkin tidak tersedia di semua environment Bootstrap -->
        <div class="col-md-12"> 
            <div class="panel panel-default" style="border-top: 3px solid #f39c12; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="background-color: #ffffff; border-bottom: 1px solid #eeeeee;">
                    <h3 class="panel-title" style="font-weight: 700; color: #333;">
                        <i class="fa fa-edit"></i> Revisi Dokumen Realisasi #<?= htmlspecialchars($id_realisasi); ?>
                        <!-- Menggunakan class label yang lebih sesuai -->
                        <span class="label label-warning pull-right" style="background-color: #f39c12;"><?= htmlspecialchars(get_status_label($realisasi_data['status'] ?? 0)); ?></span>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" style="border-radius: 4px;">
                            <strong class="text-xl"><i class="fa fa-times-circle"></i> Gagal:</strong> 
                            <?= htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" style="border-radius: 4px;">
                            <strong class="text-xl"><i class="fa fa-check-circle"></i> Berhasil:</strong> 
                            <?= htmlspecialchars($success_message); ?>
                            <br>Dokumen telah diajukan kembali.
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Revisi izin edit: status 0 (Draft) atau status 4 (Ditolak)
                    $can_edit = ($realisasi_data && ($realisasi_data['status'] == 0 || $realisasi_data['status'] == 4)); 
                    // Tampilkan form jika bisa edit, atau jika baru saja sukses (agar data baru terlihat)
                    if ($can_edit || !empty($success_message)): 
                    ?>

                        <form method="POST" id="realisasiForm">
                            <input type="hidden" name="id_realisasi" value="<?= $id_realisasi; ?>">

                            <h4 style="border-bottom: 2px solid #3c8dbc; color: #3c8dbc; padding-bottom: 5px; margin-top: 15px; font-weight: 600;"><i class="fa fa-file-text-o"></i> Data Utama Dokumen</h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="id_rab">Terkait RAB (Rencana Anggaran Biaya) <span class="text-danger">*</span></label>
                                        <?php if (empty($rab_options)): ?>
                                            <!-- Peringatan Debugging ditampilkan jika tidak ada RAB yang memenuhi syarat -->
                                            <select class="form-control" id="id_rab" name="id_rab" required disabled>
                                                <option value="">-- Tidak ada RAB yang Disetujui Penuh --</option>
                                            </select>
                                            <p class="text-danger" style="margin-top: 5px; font-size: 11px;">
                                                <i class="fa fa-warning"></i> **PENTING:** Tidak ada RAB yang memenuhi syarat filter **`status_keuangan = 5` DAN `status_rektorat = 1`** untuk Realisasi. 
                                                <br>Pastikan RAB telah melalui proses persetujuan Rektorat.
                                            </p>
                                        <?php else: ?>
                                            <select class="form-control" id="id_rab" name="id_rab" required>
                                                <option value="">-- Pilih RAB --</option>
                                                <?php foreach ($rab_options as $rab): ?>
                                                    <option value="<?= $rab['id_rab']; ?>" 
                                                            <?= ($rab['id_rab'] == ($realisasi_data['id_rab'] ?? 0)) ? 'selected' : ''; ?>>
                                                        [#<?= $rab['id_rab']; ?>] <?= htmlspecialchars($rab['judul']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nomor_dokumen">Nomor Dokumen <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nomor_dokumen" name="nomor_dokumen" required 
                                                   value="<?= htmlspecialchars($realisasi_data['nomor_dokumen'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_realisasi">Tanggal Realisasi</label>
                                        <input type="date" class="form-control" id="tanggal_realisasi" name="tanggal_realisasi" 
                                                   value="<?= htmlspecialchars($realisasi_data['tanggal_realisasi'] ?? date('Y-m-d')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="deskripsi">Deskripsi Singkat Realisasi</label>
                                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($realisasi_data['deskripsi'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Detail Item Table -->
                            <h4 style="border-bottom: 2px solid #3c8dbc; color: #3c8dbc; padding-bottom: 5px; margin-top: 25px; font-weight: 600;"><i class="fa fa-list"></i> Detail Item Pengeluaran</h4>
                            <div class="table-responsive" style="border: 1px solid #ddd; border-radius: 4px;">
                                <table class="table table-striped table-condensed" id="detail_table" style="margin-bottom: 0;">
                                    <thead>
                                        <tr class="info">
                                            <th style="vertical-align: middle;">Uraian Item <span class="text-danger">*</span></th>
                                            <th style="width: 200px; vertical-align: middle;" class="text-right">Jumlah Realisasi (Rp) <span class="text-danger">*</span></th>
                                            <th style="width: 50px; vertical-align: middle;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Gunakan $detail_items dari hasil query atau dari POST yang gagal
                                        $display_items = $realisasi_data && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message) ? $detail_items : $detail_items;
                                        foreach ($display_items as $item): 
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="text" class="form-control input-sm" name="uraian_detail[]" placeholder="Contoh: Pembelian ATK" value="<?= htmlspecialchars($item['uraian']); ?>" required>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control input-sm text-right rupiah-input" name="jumlah_detail[]" 
                                                       value="<?= format_rupiah($item['jumlah_realisasi']); ?>" required>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-xs remove-row" title="Hapus Baris"><i class="fa fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="2" class="text-right" style="padding-top: 15px;"><strong>TOTAL REALISASI:</strong></td>
                                            <td class="text-right" style="padding-top: 15px;"><strong id="total_realisasi_display" class="text-danger lead">Rp <?= format_rupiah($realisasi_data['total_realisasi'] ?? 0); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="padding: 10px;">
                                                <button type="button" class="btn btn-sm btn-info btn-block" id="add_row" style="background-color: #5bc0de; border-color: #46b8da;">
                                                    <i class="fa fa-plus"></i> Tambah Item
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="text-right" style="margin-top: 20px;">
                                <a href="dashboard.php?page=departemen/transaksi_detail.php&id_realisasi=<?= $id_realisasi; ?>" class="btn btn-default" style="border-radius: 4px;">
                                    <i class="fa fa-times"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-success" style="border-radius: 4px; background-color: #00a65a; border-color: #008d4c;">
                                    <i class="fa fa-save"></i> Simpan Revisi & Ajukan Kembali
                                </button>
                            </div>

                        </form>

                    <?php endif; // End if $realisasi_data ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailTable = document.getElementById('detail_table');
    const addButton = document.getElementById('add_row');
    const totalDisplay = document.getElementById('total_realisasi_display');
    const form = document.getElementById('realisasiForm');

    // --- Helper untuk format Rupiah ---
    function formatRupiah(angka) {
        // Hanya menerima angka, menghapus semua kecuali digit dan koma (jika ada)
        let number_string = angka.toString().replace(/[^,\d]/g, '').toString();
        // Memastikan hanya ada satu koma untuk desimal
        number_string = number_string.replace(/,/g, (match, offset, string) => (string.indexOf(',') === offset ? match : ''));
        
        let split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        // Tambahkan desimal (koma) jika ada
        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        return rupiah;
    }

    // --- Helper untuk menghitung dan menampilkan total ---
    function calculateTotal() {
        let total = 0;
        const rows = detailTable.querySelector('tbody').querySelectorAll('tr');

        rows.forEach(row => {
            const input = row.querySelector('.rupiah-input');
            if (input) {
                // Hapus titik ribuan, ganti koma desimal dengan titik untuk parsing float
                let value = input.value.replace(/\./g, '').replace(',', '.');
                total += parseFloat(value) || 0;
            }
        });

        totalDisplay.textContent = 'Rp ' + formatRupiah(total.toFixed(0)); // ToFixed(0) untuk menghindari desimal di total
    }

    // --- Menambah Baris Baru ---
    addButton.addEventListener('click', function() {
        const tbody = detailTable.querySelector('tbody');
        const newRow = tbody.insertRow();
        newRow.innerHTML = `
            <td>
                <input type="text" class="form-control input-sm" name="uraian_detail[]" placeholder="Contoh: Pembelian ATK" required>
            </td>
            <td>
                <input type="text" class="form-control input-sm text-right rupiah-input" name="jumlah_detail[]" value="0" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-xs remove-row" title="Hapus Baris"><i class="fa fa-trash"></i></button>
            </td>
        `;
        // Attach event listeners to new elements
        attachEventListeners(newRow);
        calculateTotal();
    });

    // --- Menghapus Baris ---
    detailTable.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row') || e.target.closest('.remove-row')) {
            const row = e.target.closest('tr');
            if (detailTable.querySelector('tbody').rows.length > 1) {
                row.remove();
                calculateTotal();
            } else {
                // Biarkan baris terakhir, set nilainya kembali ke kosong/nol
                const uraianInput = row.querySelector('input[name="uraian_detail[]"]');
                const jumlahInput = row.querySelector('input[name="jumlah_detail[]"]');
                
                if (uraianInput) uraianInput.value = '';
                if (jumlahInput) jumlahInput.value = '0';
                
                // Trigger re-formatting and recalculation
                if (jumlahInput) jumlahInput.dispatchEvent(new Event('blur'));
                calculateTotal();
            }
        }
    });

    // --- Mengelola Input Rupiah dan Perhitungan Total ---
    function attachEventListeners(element) {
        const rupiahInputs = element.querySelectorAll('.rupiah-input');
        rupiahInputs.forEach(input => {
            // Hapus karakter non-digit/koma saat input (untuk mencegah input karakter aneh)
            input.addEventListener('keyup', function(e) {
                let value = this.value;
                // Hanya izinkan digit, titik (untuk ribuan, akan dihapus saat blur), dan koma (untuk desimal)
                this.value = value.replace(/[^0-9.,]/g, '');
            });

            // Format saat blur
            input.addEventListener('blur', function() {
                // Hapus titik ribuan, ganti koma desimal dengan titik untuk parsing float
                let value = this.value.replace(/\./g, '').replace(',', '.');
                // Konversi kembali ke Rupiah yang diformat
                this.value = formatRupiah(parseFloat(value) || 0);
            });
            // Hitung total saat ada perubahan (untuk live update)
            input.addEventListener('input', calculateTotal);
            
            // Re-format saat pertama kali di load
            input.dispatchEvent(new Event('blur'));
        });
    }

    // Attach listeners ke baris yang sudah ada saat halaman dimuat
    attachEventListeners(detailTable.querySelector('tbody'));

    // Pastikan total dihitung saat pertama kali loading (untuk data yang ada)
    calculateTotal();
});
</script>