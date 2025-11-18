<?php
// FILE: /efinance/pages/departemen/transaksi_add.php
// Halaman untuk pengajuan Realisasi Anggaran oleh Departemen
include 'config/conn.php'; 
include_once 'function.php'; 

global $conn;

// Pastikan sesi sudah dimulai dan variabel session tersedia (untuk menghindari Notice: Undefined index)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil ID dari sesi, berikan default 0 jika belum ada
$id_departemen_session = $_SESSION['id_departemen'] ?? 0;
$id_user_session = $_SESSION['id_user'] ?? 0;
$id_role_session = $_SESSION['id_role'] ?? 0;

// Cek hak akses
if ($id_role_session != 2 || $id_departemen_session == 0) {
    // Jika user bukan Departemen atau tidak memiliki ID Departemen, redirect
    // Pastikan fungsi redirect_to() tersedia
    // redirect_to('dashboard.php'); 
}

$id_rab_selected = (int)($_GET['rab'] ?? 0);
$rab_data_selected = [];
$rab_details = [];

// --- 1. Logika Pemrosesan Form (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tentukan aksi dan status berdasarkan tombol yang diklik
    $action_type = $_POST['action'] ?? 'draft'; // Ambil nilai 'draft' atau 'submit'
    
    // Status 0 = Draft (Simpan Draft)
    // Status 1 = Diajukan (Ajukan Transaksi)
    $status_realisasi = ($action_type == 'submit') ? 1 : 0; 
    $message_action = ($action_type == 'submit') ? 'diajukan' : 'disimpan sebagai Draft';
    $message_type = ($action_type == 'submit') ? 'success' : 'warning';


    $id_rab = (int)($_POST['id_rab'] ?? 0);
    $tanggal_realisasi = clean_input($_POST['tanggal_realisasi'] ?? '');
    $nomor_dokumen = clean_input($_POST['nomor_dokumen'] ?? '');
    $deskripsi_umum = clean_input($_POST['deskripsi_umum'] ?? '');

    $details = [];
    $total_realisasi = 0;

    // Kumpulkan detail Realisasi dari input array
    if (isset($_POST['id_rab_detail']) && is_array($_POST['id_rab_detail'])) {
        foreach ($_POST['id_rab_detail'] as $i => $id_rab_detail) {
            // Hilangkan format ribuan sebelum konversi ke float
            // Tambahkan strip_tags untuk keamanan ekstra pada uraian
            $jumlah_realisasi = floatval(str_replace('.', '', $_POST['jumlah_realisasi'][$i] ?? 0));
            $uraian_detail = clean_input(strip_tags($_POST['uraian_detail'][$i] ?? ''));
            
            // Hanya masukkan item yang direalisasikan (> 0)
            if ($jumlah_realisasi > 0) {
                $details[] = [
                    'id_rab_detail' => $id_rab_detail,
                    'uraian' => $uraian_detail,
                    'jumlah_realisasi' => $jumlah_realisasi,
                ];
                $total_realisasi += $jumlah_realisasi;
            }
        }
    }

    // Validasi dasar
    if (empty($tanggal_realisasi) || empty($nomor_dokumen) || $id_rab == 0) {
        $_SESSION['message'] = "Gagal menyimpan. Data utama RAB, Tanggal dan Nomor Dokumen wajib diisi.";
        $_SESSION['message_type'] = "danger";
    } elseif (empty($details)) {
        $_SESSION['message'] = "Gagal menyimpan. Realisasi harus memiliki setidaknya satu item dengan Jumlah Realisasi > 0.";
        $_SESSION['message_type'] = "danger";
    } else {
        // Panggil fungsi penyimpanan
        // Catatan: Fungsi save_realisasi_transaction() harus diubah untuk menerima $status_realisasi
        $id_transaksi = save_realisasi_transaction(
            $id_rab, 
            $id_departemen_session, 
            $tanggal_realisasi, 
            $nomor_dokumen, 
            $deskripsi_umum, 
            $details, 
            $id_user_session,
            $status_realisasi // <--- STATUS BARU DIKIRIM KE FUNGSI!
        );

        if ($id_transaksi) {
            $_SESSION['message'] = "Realisi berhasil **{$message_action}**.";
            $_SESSION['message_type'] = $message_type;
            
            // Redirect ke daftar transaksi setelah sukses
            redirect_to('dashboard.php?page=transaksi_list.php');
        } else {
            $_SESSION['message'] = "Terjadi kesalahan saat menyimpan data ke database.";
            $_SESSION['message_type'] = "danger";
        }
    }
    
    // Jika ada error (di atas), refresh halaman untuk menampilkan error dari session
    redirect_to('dashboard.php?page=transaksi_add.php&rab=' . $id_rab);
}


// --- 2. Ambil Data RAB Siap Realisasi ---
// Pastikan fungsi get_final_rabs_for_realisasi() sudah ada di function.php
$rabs_final = get_final_rabs_for_realisasi($id_departemen_session);


// --- 3. Ambil Detail RAB jika RAB sudah dipilih ---
if ($id_rab_selected > 0) {
    // Cari data RAB yang dipilih dari daftar RAB Final
    $rab_found = array_filter($rabs_final, function($rab) use ($id_rab_selected) {
        return $rab['id_rab'] == $id_rab_selected;
    });

    if (!empty($rab_found)) {
        $rab_data_selected = reset($rab_found);
        // Ambil detail item RAB yang tersisa anggarannya
        // Pastikan fungsi get_rab_details_for_realisasi() sudah ada di function.php
        $rab_details = get_rab_details_for_realisasi($id_rab_selected);
    }
}

$departemen_dashboard = 'dashboard.php?page=dashboard_dept.php';
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-plus-sign"></span> 
            Pengajuan Realisasi Anggaran
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $departemen_dashboard; ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="dashboard.php?page=transaksi_list.php">Daftar Transaksi</a></li>
            <li class="active">Tambah Transaksi</li>
        </ol>
    </div>
</div>

<?php 
// Tampilkan pesan sukses/error
if (isset($_SESSION['message'])): 
    $alert_class = ($_SESSION['message_type'] == 'success') ? 'alert-success' : 'alert-danger';
?>
<div class="alert <?= $alert_class; ?> alert-dismissible" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <?= $_SESSION['message']; ?>
</div>
<?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
endif; 
?>

<?php if (empty($rabs_final)): ?>
    <div class="alert alert-info">
        <i class="glyphicon glyphicon-info-sign"></i> PERHATIAN: Belum ada RAB yang Disetujui Final dan masih memiliki sisa anggaran di departemen Anda.
        Transaksi Realisasi hanya dapat dilakukan pada RAB yang telah disetujui penuh oleh Rektorat.
    </div>
<?php else: ?>

<form method="GET" action="dashboard.php" class="form-inline" style="margin-bottom: 20px;">
    <input type="hidden" name="page" value="transaksi_add.php">
    <div class="form-group">
        <label for="rab_select">Pilih RAB Final:</label>
        <select name="rab" id="rab_select" class="form-control" onchange="this.form.submit()">
            <option value="">-- Pilih RAB --</option>
            <?php foreach ($rabs_final as $rab): ?>
                <option value="<?= $rab['id_rab']; ?>" 
                        <?= ($rab['id_rab'] == $id_rab_selected) ? 'selected' : ''; ?>>
                    [RAB-<?= $rab['id_rab']; ?>] - <?= htmlspecialchars($rab['judul']); ?> (Sisa: <?= format_rupiah($rab['sisa_anggaran']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($id_rab_selected > 0): ?>
        <a href="dashboard.php?page=transaksi_add.php" class="btn btn-warning"><i class="glyphicon glyphicon-repeat"></i> Reset Pilihan</a>
    <?php endif; ?>
</form>


<?php if ($id_rab_selected > 0 && !empty($rab_data_selected)): ?>
    <form action="dashboard.php?page=transaksi_add.php" method="POST">
        <input type="hidden" name="id_rab" value="<?= $id_rab_selected; ?>">

        <div class="row">
            <div class="col-lg-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-tag"></i> Data Transaksi Umum</h3>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="rab_info">RAB Sumber Anggaran</label>
                            <input type="text" id="rab_info" class="form-control" disabled 
                                value="RAB-<?= $rab_data_selected['id_rab']; ?>: <?= htmlspecialchars($rab_data_selected['judul']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="tanggal_realisasi">Tanggal Realisasi/Pengeluaran</label>
                            <input type="date" name="tanggal_realisasi" id="tanggal_realisasi" class="form-control" 
                                value="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nomor_dokumen">Nomor Dokumen/Bukti Kas</label>
                            <input type="text" name="nomor_dokumen" id="nomor_dokumen" class="form-control" 
                                placeholder="Contoh: BKK/P-001/XII/2025" required>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi_umum">Deskripsi Singkat Realisasi</label>
                            <textarea name="deskripsi_umum" id="deskripsi_umum" class="form-control" rows="2" 
                                placeholder="Misalnya: Pembayaran Honor Narasumber Seminar..." required></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-info-sign"></i> Status Anggaran RAB</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-bordered">
                            <tr>
                                <td>Judul RAB</td>
                                <td><?= htmlspecialchars($rab_data_selected['judul']); ?></td>
                            </tr>
                            <tr>
                                <td>Total Anggaran (Final)</td>
                                <td><?= format_rupiah($rab_data_selected['total_anggaran']); ?></td>
                            </tr>
                            <tr>
                                <td>Sudah Direalisasikan</td>
                                <td><?= format_rupiah($rab_data_selected['total_realisasi_disetujui']); ?></td>
                            </tr>
                            <tr>
                                <td>Sisa Anggaran</td>
                                <td><strong class="text-success"><?= format_rupiah($rab_data_selected['sisa_anggaran']); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-usd"></i> Rincian Realisasi Per Item RAB</h3>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($rab_details)): ?>
                            <div class="alert alert-warning">Semua item anggaran dari RAB ini telah direalisasikan 100%.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="realisasi_detail_table">
                                <thead>
                                    <tr>
                                        <th width="15%">Kode Akun</th>
                                        <th width="30%">Item RAB (Anggaran)</th>
                                        <th width="15%" class="text-right">Sisa Anggaran</th>
                                        <th width="30%">Uraian Realisasi</th>
                                        <th width="10%">Jumlah Realisasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($rab_details as $detail):
                                        $sisa = $detail['sisa_anggaran_item'];
                                    ?>
                                        <tr class="realisasi-item">
                                            <td>
                                                <?= htmlspecialchars($detail['kode_akun']) . ' - ' . htmlspecialchars($detail['nama_akun']); ?>
                                                <input type="hidden" name="id_rab_detail[]" value="<?= $detail['id_rab_detail']; ?>">
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($detail['uraian']); ?>
                                                <br><small class="text-muted">(Anggaran Awal: <?= format_rupiah($detail['subtotal']); ?>)</small>
                                            </td>
                                            <td class="text-right">
                                                <strong class="sisa_anggaran_item" data-sisa="<?= $sisa; ?>">
                                                    <?= format_rupiah($sisa); ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <input type="text" name="uraian_detail[]" class="form-control uraian_detail" 
                                                    value="<?= htmlspecialchars($detail['uraian']); ?>" required>
                                            </td>
                                            <td>
                                                <input type="text" name="jumlah_realisasi[]" class="form-control jumlah_realisasi rupiah" 
                                                    data-max-realisasi="<?= $sisa; ?>" value="0" required>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-right">TOTAL REALISASI DIAJUKAN</td>
                                        <td class="text-right"><strong id="total_realisasi_display">Rp 0</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-12 text-right" style="margin-top: 20px;">
                                <a href="dashboard.php?page=transaksi_list.php" class="btn btn-default">
                                    <i class="fa fa-times"></i> Batal
                                </a>

                                <button type="submit" name="action" value="draft" class="btn btn-warning"
                                        onclick="return confirm('Anda yakin ingin menyimpan Realisasi ini sebagai DRAFT? Anda dapat mengubahnya kembali nanti.');">
                                    <i class="fa fa-save"></i> Simpan Draft
                                </button>

                                <button type="submit" name="action" value="submit" class="btn btn-success" 
                                        onclick="return confirm('Anda yakin data realisasi ini sudah benar dan siap diajukan ke Keuangan?');">
                                    <i class="glyphicon glyphicon-send"></i> Ajukan Realisasi
                                </button>
                            </div>
                        </div>
                        <?php endif; // End if empty($rab_details) ?>

                    </div>
                </div>
            </div>
        </div>
    </form>
<?php elseif ($id_rab_selected > 0 && empty($rab_data_selected)): ?>
    <div class="alert alert-danger">RAB dengan ID tersebut tidak valid atau bukan milik departemen Anda.</div>
<?php endif; // End if $id_rab_selected > 0 ?>

<?php endif; // End if empty($rabs_final) ?>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fungsi untuk format angka ke Rupiah (Tanpa Rp)
        function formatRupiah(angka) {
            var number_string = angka.toString().replace(/[^,\d]/g, ''),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            rupiah = rupiah.replace(/,00$/, ''); 
            return rupiah ? rupiah : '0';
        }
        
        // Fungsi untuk menghitung Total Keseluruhan Realisasi
        function calculateTotal() {
            let grandTotal = 0;

            $('#realisasi_detail_table .realisasi-item').each(function() {
                const $row = $(this);
                const $input = $row.find('.jumlah_realisasi');
                const maxReal = parseFloat($input.data('max-realisasi')) || 0;
                
                // Hapus format ribuan (' . ') sebelum konversi ke float
                let jumlahReal = parseFloat($input.val().replace(/\./g, '').replace(/,/g, '.')) || 0; 
                
                // Peringatan validasi: Jangan sampai melebihi sisa anggaran
                if (jumlahReal > maxReal) {
                    alert('Jumlah realisasi melebihi sisa anggaran yang tersedia (' + formatRupiah(maxReal) + ') pada item ini.');
                    // Set nilai kembali ke batas maksimum
                    jumlahReal = maxReal;
                    $input.val(formatRupiah(jumlahReal.toFixed(0)));
                }

                grandTotal += jumlahReal;
            });

            // Perbarui total_realisasi_display
            $('#total_realisasi_display').text("Rp " + formatRupiah(grandTotal.toFixed(0)));
        }

        // Binding events ke input
        function bindEvents($container) {
            $container.find('.jumlah_realisasi').on('input', calculateTotal);

            // Terapkan auto-format Rupiah saat input dilepas fokus (blur)
            $container.find('.jumlah_realisasi').on('blur', function() {
                let value = $(this).val().replace(/\./g, '');
                let maxReal = parseFloat($(this).data('max-realisasi')) || 0;
                
                if (value) {
                    let numericValue = parseFloat(value);

                    // Re-check validasi saat blur
                    if (numericValue > maxReal) {
                        numericValue = maxReal;
                    }

                    $(this).val(formatRupiah(numericValue.toFixed(0)));
                } else {
                    $(this).val('0'); // Pastikan input kosong menjadi 0
                }
                calculateTotal();
            });
        }
        
        // Initial binding untuk baris yang sudah ada
        bindEvents($('#realisasi_detail_table tbody'));
        calculateTotal(); // Hitung total awal
    });
</script>