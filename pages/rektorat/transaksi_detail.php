<?php
// pages/rektorat/transaksi_detail.php
// Halaman untuk menampilkan detail realisasi dan memungkinkan Rektorat untuk menyetujui/menolak.

// Pastikan variabel koneksi (misalnya $conn) tersedia secara global
global $conn;

// --- Helper Functions ---

/**
 * Memformat angka menjadi format Rupiah (tanpa simbol Rp)
 */
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return number_format($angka, 0, ',', '.');
    }
}

/**
 * Mengembalikan label status Realisasi yang mudah dibaca dengan styling Bootstrap
 */
if (!function_exists('get_realisasi_status_label')) {
    function get_realisasi_status_label($status) {
        switch ((int)$status) {
            case 0: return '<span class="label label-default">0 Draft</span>';
            case 1: return '<span class="label label-warning">1 Diajukan</span>';
            // Status 2: Disetujui Keuangan, kini menunggu keputusan Rektorat
            case 2: return '<span class="label label-info">2 Disetujui Keuangan (Menunggu Rektorat)</span>';
            case 3: return '<span class="label label-success">3 Disetujui Rektorat</span>';
            case 4: return '<span class="label label-danger">4 Ditolak</span>';
            case 5: return '<span class="label label-primary">5 Selesai</span>'; // Status final/selesai
            default: return '<span class="label label-default">Tidak Diketahui</span>';
        }
    }
}

// --- Main Logic ---

$id_realisasi = isset($_GET['id_realisasi']) ? (int)$_GET['id_realisasi'] : 0;
$realisasi_data = null;
$detail_items = [];
$error_message = null;

if ($id_realisasi === 0) {
    $error_message = "ID Realisasi tidak valid.";
} else {
    // 1. Ambil Data Realisasi Header
    $query_header = "
        SELECT
            r.id_realisasi, r.id_rab, r.id_departemen, r.tanggal_realisasi, r.nomor_dokumen, r.deskripsi,
            r.total_realisasi, r.status, r.catatan_keuangan, r.created_at, r.created_by,
            rab.judul AS judul_rab,
            d.nama_departemen
        FROM
            realisasi r
        INNER JOIN
            rab ON r.id_rab = rab.id_rab
        INNER JOIN
            departemen d ON r.id_departemen = d.id_departemen
        WHERE
            r.id_realisasi = ?
    ";
    $stmt_header = $conn->prepare($query_header);
    if ($stmt_header === false) { 
        $error_message = "Gagal menyiapkan query header: " . $conn->error; 
    } else {
        $stmt_header->bind_param('i', $id_realisasi);
        $stmt_header->execute();
        $result_header = $stmt_header->get_result();
        $realisasi_data = $result_header->fetch_assoc();
        $stmt_header->close();

        if (!$realisasi_data) {
            $error_message = "Data Realisasi #{$id_realisasi} tidak ditemukan.";
        } else {
            // 2. Ambil Detail Item Realisasi
            $query_detail = "
                SELECT
                    uraian,
                    jumlah_realisasi
                FROM
                    realisasi_detail
                WHERE
                    id_realisasi = ?
                ORDER BY
                    id_realisasi_detail ASC
            ";
            $stmt_detail = $conn->prepare($query_detail);
            if ($stmt_detail === false) { 
                $error_message = "Gagal menyiapkan query detail: " . $conn->error; 
            } else {
                $stmt_detail->bind_param('i', $id_realisasi);
                $stmt_detail->execute();
                $result_detail = $stmt_detail->get_result();
                while ($row = $result_detail->fetch_assoc()) {
                    $detail_items[] = $row;
                }
                $stmt_detail->close();
            }
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="panel panel-primary" style="border-top: 3px solid #286090;">
                <div class="panel-heading">
                    <h3 class="panel-title" style="font-weight: 600;">
                        <i class="fa fa-eye"></i> Detail Realisasi Keuangan #<?= htmlspecialchars($id_realisasi); ?>
                        <span class="pull-right"><?= get_realisasi_status_label($realisasi_data['status'] ?? 0); ?></span>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <strong class="text-xl"><i class="fa fa-times-circle"></i> Error:</strong> 
                            <?= htmlspecialchars($error_message); ?>
                            <a href="dashboard.php?page=rektorat/transaksi_list.php" class="btn btn-danger btn-xs pull-right">Kembali ke Daftar</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($realisasi_data): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered table-striped table-condensed">
                                    <tr><th>Nomor Dokumen</th><td><?= htmlspecialchars($realisasi_data['nomor_dokumen']); ?></td></tr>
                                    <tr><th>Tanggal Realisasi</th><td><?= htmlspecialchars(date('d M Y', strtotime($realisasi_data['tanggal_realisasi']))); ?></td></tr>
                                    <tr><th>Departemen</th><td><?= htmlspecialchars($realisasi_data['nama_departemen']); ?></td></tr>
                                    <tr><th>RAB Terkait</th><td><?= htmlspecialchars($realisasi_data['judul_rab']); ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered table-striped table-condensed">
                                    <tr><th>Total Realisasi</th><td><strong class="text-danger">Rp <?= format_rupiah($realisasi_data['total_realisasi']); ?></strong></td></tr>
                                    <tr><th>Deskripsi Realisasi</th><td><?= nl2br(htmlspecialchars($realisasi_data['deskripsi'])); ?></td></tr>
                                    
                                    <!-- Catatan Keuangan/Penolakan -->
                                    <?php if ((int)$realisasi_data['status'] === 4): ?>
                                    <tr><th>Catatan Penolakan</th><td><span class="text-danger"><?= !empty($realisasi_data['catatan_keuangan']) ? nl2br(htmlspecialchars($realisasi_data['catatan_keuangan'])) : '-'; ?></span></td></tr>
                                    <?php elseif ((int)$realisasi_data['status'] === 2): ?>
                                    <tr><th>Catatan Keuangan</th><td><?= !empty($realisasi_data['catatan_keuangan']) ? nl2br(htmlspecialchars($realisasi_data['catatan_keuangan'])) : '-'; ?></td></tr>
                                    <?php endif; ?>
                                    
                                    <tr><th>Status Dokumen</th><td><?= get_realisasi_status_label($realisasi_data['status']); ?></td></tr>
                                </table>
                            </div>
                        </div>

                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 15px;"><i class="fa fa-list"></i> Detail Item Pengeluaran</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr class="info">
                                        <th style="width: 50px;">No</th>
                                        <th>Uraian Item (dari `realisasi_detail.uraian`)</th>
                                        <th class="text-right" style="width: 250px;">Jumlah Realisasi (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($detail_items as $item): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($item['uraian']); ?></td>
                                        <td class="text-right">Rp <?= format_rupiah($item['jumlah_realisasi']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-right"><strong>TOTAL KESELURUHAN (dari `realisasi.total_realisasi`):</strong></td>
                                        <td class="text-right"><strong class="text-danger lead">Rp <?= format_rupiah($realisasi_data['total_realisasi']); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Panel Persetujuan/Penolakan oleh Rektorat -->
                        <?php if ((int)$realisasi_data['status'] === 2): ?>
                            <h4 class="text-center" style="border-bottom: 2px solid #337ab7; padding-bottom: 5px; margin-top: 30px; color: #337ab7;">
                                <i class="fa fa-cogs"></i> Form Persetujuan/Penolakan Rektorat
                            </h4>
                            <div class="well">
                                <form id="rektorat-validation-form" method="POST" action="dashboard.php?page=rektorat/transaksi_validation.php">
                                    <input type="hidden" name="id_realisasi" value="<?= $id_realisasi; ?>">
                                    <input type="hidden" name="action" id="action-input" value="">
                                    
                                    <div class="form-group">
                                        <!-- Catatan ini akan dikirim dan disimpan di kolom 'catatan_keuangan' di tabel Realisasi -->
                                        <label for="catatan_rektorat">Catatan Rektorat (Opsional, Wajib diisi jika ditolak):</label>
                                        <textarea class="form-control" name="catatan_rektorat" id="catatan_rektorat" rows="3" placeholder="Masukkan catatan persetujuan atau alasan penolakan..."></textarea>
                                        <p class="help-block text-danger hidden" id="catatan-warning">Catatan Penolakan wajib diisi jika Anda memilih untuk Menolak.</p>
                                    </div>

                                    <div class="text-center">
                                        <!-- Tombol Tolak: action = 0 -->
                                        <button type="button" class="btn btn-danger btn-lg action-button" data-action="0">
                                            <i class="fa fa-times-circle"></i> Tolak
                                        </button>
                                        
                                        <!-- Tombol Setujui Final: action = 1 -->
                                        <button type="button" class="btn btn-success btn-lg action-button" data-action="1">
                                            <i class="fa fa-check-circle"></i> Setujui Final
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php elseif ((int)$realisasi_data['status'] === 3 || (int)$realisasi_data['status'] === 5): ?>
                            <div class="alert alert-success text-center">
                                <i class="fa fa-check-circle"></i> Dokumen ini **<?= ((int)$realisasi_data['status'] === 3) ? 'Sudah Disetujui Rektorat' : 'Sudah Selesai' ; ?>**.
                            </div>
                        <?php elseif ((int)$realisasi_data['status'] === 4): ?>
                            <div class="alert alert-danger">
                                <i class="fa fa-times-circle"></i> Dokumen ini **Ditolak**. Catatan Penolakan: <?= nl2br(htmlspecialchars($realisasi_data['catatan_keuangan'] ?? '-')); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-right" style="margin-top: 20px;">
                            <a href="dashboard.php?page=rektorat/transaksi_list.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali ke Daftar Transaksi</a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi (Menggantikan confirm()) -->
<div class="modal fade" id="validationModal" tabindex="-1" role="dialog" aria-labelledby="validationModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="validationModalLabel"><i class="fa fa-question-circle"></i> Konfirmasi Aksi</h4>
      </div>
      <div class="modal-body">
        <p id="modal-message" class="lead text-center"></p>
        <div id="rejection-warning" class="alert alert-warning hidden">
            <i class="fa fa-exclamation-triangle"></i> **Penting:** Untuk penolakan, kolom **Catatan Rektorat** harus diisi dengan alasan yang jelas di formulir belakang.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-close"></i> Batal</button>
        <button type="button" class="btn btn-primary" id="confirm-action-btn"><i class="fa fa-send"></i> Lanjutkan Aksi</button>
      </div>
    </div>
  </div>
</div>

<script>
    // Menangkap klik pada tombol aksi (Tolak atau Setujui Final)
    document.querySelectorAll('.action-button').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const isReject = (action === '0');
            const catatan = document.getElementById('catatan_rektorat').value.trim();

            let message = '';
            let isWarning = false;

            if (isReject) {
                message = 'Anda yakin ingin **MENOLAK** Realisasi ini? Dokumen akan dikembalikan ke Departemen.';
                if (catatan === '') {
                    isWarning = true;
                    document.getElementById('catatan-warning').classList.remove('hidden');
                } else {
                    document.getElementById('catatan-warning').classList.add('hidden');
                }
            } else {
                message = 'Anda yakin ingin **MENYETUJUI FINAL** Realisasi ini? Realisasi akan diproses.';
                document.getElementById('catatan-warning').classList.add('hidden');
            }

            // Memperbarui konten modal
            document.getElementById('modal-message').innerHTML = message;
            document.getElementById('rejection-warning').classList.toggle('hidden', !isReject);
            
            // Set data aksi ke tombol konfirmasi
            document.getElementById('confirm-action-btn').setAttribute('data-action', action);

            // Tampilkan modal
            $('#validationModal').modal('show');
        });
    });

    // Menangani klik pada tombol Lanjutkan di Modal
    document.getElementById('confirm-action-btn').addEventListener('click', function() {
        const action = this.getAttribute('data-action');
        const isReject = (action === '0');
        const catatan = document.getElementById('catatan_rektorat').value.trim();
        
        // Validasi wajib isi catatan jika aksi adalah Tolak (0)
        if (isReject && catatan === '') {
            // Tampilkan kembali peringatan di modal (atau bisa di-handle di luar modal)
            document.getElementById('modal-message').innerHTML = 'Catatan Penolakan wajib diisi jika Anda memilih untuk Menolak. Silakan isi catatan di formulir belakang dan coba lagi.';
            document.getElementById('rejection-warning').classList.remove('hidden');
            // Jangan submit, biarkan user membatalkan modal dan mengisi catatan
            return;
        }

        // Set nilai action di form utama
        document.getElementById('action-input').value = action;
        
        // Sembunyikan modal
        $('#validationModal').modal('hide');

        // Submit form utama
        document.getElementById('rektorat-validation-form').submit();
    });
</script>