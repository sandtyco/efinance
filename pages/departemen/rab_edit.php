<?php
// FILE: /efinance/pages/departemen/rab_edit.php
// Halaman untuk Edit/Revisi RAB oleh Departemen

global $conn;

// Pastikan user adalah Departemen (id_role=3) dan memiliki id_departemen di session
$id_departemen_session = $_SESSION['id_departemen'] ?? 0;

// --- 1. Ambil Nama Departemen untuk Display ---
// Menggunakan fungsi yang sudah ada untuk mengambil nama departemen
$nama_departemen_display = get_nama_departemen($id_departemen_session) ?? 'N/A'; // N/A jika tidak ditemukan

// --- 2. Ambil ID RAB dari URL dan data RAB ---
$id_rab = (int)($_GET['id'] ?? 0);
$rab_data = get_rab_data($id_rab); 

if (!$rab_data) {
    $_SESSION['message'] = "RAB dengan ID tersebut tidak ditemukan.";
    $_SESSION['message_type'] = "danger";
    redirect_to('dashboard.php?page=rab_list.php');
}

// --- 3. Cek Hak Akses Edit/Revisi ---
// Diizinkan: Draft (0), Ditolak Keuangan (2), Ditolak Rektorat (4)
$allowed_statuses = [0, 2, 4];
$current_status = (int)$rab_data['status_keuangan'];
$is_editable = in_array($current_status, $allowed_statuses) && $rab_data['id_departemen'] == $id_departemen_session;
$is_revisi = ($current_status == 2 || $current_status == 4);

if (!$is_editable && $rab_data['id_departemen'] != $id_departemen_session) {
    // Jika RAB bukan milik departemen ini
    $_SESSION['message'] = "Akses ditolak. Anda tidak berhak mengubah RAB ini.";
    $_SESSION['message_type'] = "danger";
    redirect_to('dashboard.php?page=rab_list.php');
}

// --- 4. Logika Pemrosesan Form (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_editable) {
    $action = clean_input($_POST['action'] ?? ''); // 'draft' atau 'submit'
    
    // Status baru yang akan dikirim ke fungsi update_rab_and_detail
    // 0 = Simpan Draft, 1 = Ajukan (kembali ke antrian Keuangan)
    $new_status = ($action === 'submit') ? 1 : 0; 
    
    // Ambil data dari form
    $judul = clean_input($_POST['judul'] ?? '');
    $tahun_anggaran = clean_input($_POST['tahun_anggaran'] ?? '');
    $deskripsi = clean_input($_POST['deskripsi'] ?? '');
    
    $details = [];
    $total_biaya = 0;

    // Kumpulkan detail RAB dari input array
    if (isset($_POST['id_akun']) && is_array($_POST['id_akun'])) {
        foreach ($_POST['id_akun'] as $i => $id_akun) {
            $volume = floatval($_POST['volume'][$i] ?? 0);
            // Hilangkan format ribuan (' . ') sebelum konversi ke float
            $harga_satuan = floatval(str_replace('.', '', $_POST['harga_satuan'][$i] ?? 0)); 
            $subtotal = $volume * $harga_satuan;
            
            // Hanya masukkan item yang memiliki ID Akun yang valid dan Volume > 0
            if (!empty($id_akun) && $volume > 0) {
                $details[] = [
                    'id_akun' => $id_akun,
                    'uraian' => clean_input($_POST['uraian'][$i] ?? ''),
                    'volume' => $volume,
                    'satuan' => clean_input($_POST['satuan'][$i] ?? ''),
                    'harga_satuan' => $harga_satuan,
                    'subtotal' => $subtotal
                ];
                $total_biaya += $subtotal;
            }
        }
    }
    
    if (empty($details)) {
        $_SESSION['message'] = "Gagal menyimpan. RAB harus memiliki setidaknya satu item dengan Akun yang dipilih dan Volume > 0.";
        $_SESSION['message_type'] = "danger";
        redirect_to('dashboard.php?page=rab_edit.php&id=' . $id_rab);
    }
    
    // Panggil fungsi update_rab_and_detail
    if (update_rab_and_detail($id_rab, $id_departemen_session, $judul, $tahun_anggaran, $deskripsi, $details, $new_status, $total_biaya)) {
        // Redireksi setelah berhasil (Pesan sudah diset di dalam fungsi update_rab_and_detail)
        redirect_to('dashboard.php?page=rab_list.php');
    }
    
    // Jika gagal, refresh halaman untuk menampilkan error dari session
    redirect_to('dashboard.php?page=rab_edit.php&id=' . $id_rab);
}

// --- 5. Tampilan HTML ---

// Ambil daftar akun untuk dropdown (hanya jika editable)
$akun_list = ($is_editable) ? get_all_akun() : []; 

$departemen_dashboard = 'dashboard.php?page=dashboard_dept.php'; 
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-pencil"></span> 
            <?= $is_revisi ? 'Revisi Anggaran Biaya (RAB-' . $id_rab . ')' : 'Detail Anggaran Biaya (RAB-' . $id_rab . ')'; ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $departemen_dashboard; ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="dashboard.php?page=rab_list.php">Daftar RAB</a></li>
            <li class="active"><?= $is_revisi ? 'Revisi' : 'Detail'; ?> RAB-<?= $id_rab; ?></li>
        </ol>
    </div>
</div>

<?php 
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

<?php if (!$is_editable && $current_status != 5): // Tampilkan peringatan kunci kecuali statusnya Final (5) ?>
    <div class="alert alert-warning">
        <i class="glyphicon glyphicon-lock"></i> **PERHATIAN:** RAB ini sedang dalam proses persetujuan dan tidak dapat diubah.
        <br>
        Status saat ini: **<?= get_rab_status_label($current_status, $rab_data['status_rektorat']); ?>**
    </div>
<?php endif; ?>

<form action="dashboard.php?page=rab_edit.php&id=<?= $id_rab; ?>" method="POST">
    <div class="row">
        
        <div class="col-lg-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="glyphicon glyphicon-tag"></i> Data RAB Umum</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="judul">Judul RAB</label>
                        <input type="text" name="judul" id="judul" class="form-control" 
                               value="<?= htmlspecialchars($rab_data['judul'] ?? ''); ?>" required 
                               <?= $is_editable ? '' : 'disabled'; ?>>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tahun_anggaran">Tahun Anggaran</label>
                                <input type="number" name="tahun_anggaran" id="tahun_anggaran" class="form-control" 
                                       value="<?= htmlspecialchars($rab_data['tahun_anggaran'] ?? date('Y')); ?>" required 
                                       <?= $is_editable ? '' : 'disabled'; ?>>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="departemen">Departemen (Otomatis)</label>
                                <input type="text" id="departemen" class="form-control" 
                                       value="<?= htmlspecialchars($nama_departemen_display); ?>" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi Singkat Kegiatan</label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3" 
                                     <?= $is_editable ? '' : 'disabled'; ?>><?= htmlspecialchars($rab_data['deskripsi'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <?php 
            $tahun_rab = htmlspecialchars($rab_data['tahun_anggaran'] ?? date('Y'));
            $current_status = (int)$rab_data['status_keuangan'];
            
            // --- NOTIFIKASI DISetujui Final (Status 5) ---
            if ($current_status == 5): 
            ?>
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-ok-circle"></i> AJUAN DISETUJUI FINAL</h3>
                    </div>
                    <div class="panel-body">
                        <p class="lead text-success">
                            <b>SELAMAT!</b><br> Ajuan RAB Anda untuk Tahun <?= $tahun_rab; ?> telah <b>DISETUJUI</b> oleh Rektorat.
                        </p>
                        <hr style="margin-top: 5px; margin-bottom: 5px;">
                        <p style="font-size: 0.9em;">Anggaran ini telah masuk dalam rencana pelaksanaan keuangan tahunan dan tidak dapat direvisi lagi.</p>
                    </div>
                </div>
            
            <?php 
            // --- NOTIFIKASI DITOLAK REKTORAT (Status 4) ---
            elseif ($current_status == 4 && !empty($rab_data['catatan_rektorat'])): 
            ?>
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-remove-sign"></i> **Catatan Penolakan Rektorat**</h3>
                    </div>
                    <div class="panel-body">
                        <p style="white-space: pre-wrap;"><?= htmlspecialchars($rab_data['catatan_rektorat']); ?></p>
                        <p class="text-danger" style="font-size: 0.9em; margin-top: 10px;">
                            <i class="glyphicon glyphicon-info-sign"></i> Mohon revisi RAB sesuai catatan di atas dan ajukan ulang.
                        </p>
                    </div>
                </div>
                
            <?php 
            // --- NOTIFIKASI DITOLAK KEUANGAN (Status 2) ---
            elseif ($current_status == 2 && !empty($rab_data['catatan_keuangan'])): 
            ?>
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-remove-sign"></i> **Catatan Penolakan Keuangan**</h3>
                    </div>
                    <div class="panel-body">
                        <p style="white-space: pre-wrap;"><?= htmlspecialchars($rab_data['catatan_keuangan']); ?></p>
                        <p class="text-danger" style="font-size: 0.9em; margin-top: 10px;">
                            <i class="glyphicon glyphicon-info-sign"></i> Mohon revisi RAB sesuai catatan di atas dan ajukan ulang.
                        </p>
                    </div>
                </div>

            <?php 
            // --- NOTIFIKASI MENUNGGU REKTORAT (Status 3) ---
            elseif ($current_status == 3): 
            ?>
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-time"></i> **Menunggu Persetujuan Final Rektorat**</h3>
                    </div>
                    <div class="panel-body">
                        <p>Ajuan RAB Anda telah **Disetujui Direktur Keuangan** dan saat ini sedang menunggu persetujuan akhir dari Rektorat.</p>
                        <p style="font-size: 0.9em; margin-top: 10px;">
                            <i class="glyphicon glyphicon-info-sign"></i> Anda tidak dapat melakukan revisi selama status ini.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="glyphicon glyphicon-usd"></i> Detail Rencana Anggaran Biaya</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="rab_detail_table">
                            <thead>
                                <tr>
                                    <th width="15%">Kode/Nama Akun</th>
                                    <th width="30%">Uraian Kegiatan/Item</th>
                                    <th width="10%">Volume</th>
                                    <th width="10%">Satuan</th>
                                    <th width="20%">Harga Satuan</th>
                                    <th width="10%">Subtotal</th>
                                    <?php if ($is_editable): ?><th width="5%">Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_anggaran_display = 0;
                                
                                if (!empty($rab_data['details'])): 
                                    foreach ($rab_data['details'] as $i => $detail): 
                                        $total_anggaran_display += $detail['subtotal'];
                                ?>
                                    <tr class="rab-item">
                                        <td>
                                            <?php if ($is_editable): ?>
                                            <select name="id_akun[]" class="form-control id_akun" required>
                                                <option value="">Pilih Akun</option>
                                                <?php foreach ($akun_list as $akun): ?>
                                                    <option value="<?= $akun['id_akun']; ?>" 
                                                            <?= ($akun['id_akun'] == $detail['id_akun']) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($akun['kode_akun']) . ' - ' . htmlspecialchars($akun['nama_akun']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php else: ?>
                                                <?= htmlspecialchars($detail['kode_akun'] ?? 'N/A') . ' - ' . htmlspecialchars($detail['nama_akun'] ?? 'N/A'); ?>
                                                <input type="hidden" name="id_akun[]" value="<?= $detail['id_akun']; ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td><input type="text" name="uraian[]" class="form-control uraian" value="<?= htmlspecialchars($detail['uraian']); ?>" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td><input type="number" step="any" name="volume[]" class="form-control volume" value="<?= htmlspecialchars($detail['volume']); ?>" min="0" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td><input type="text" name="satuan[]" class="form-control satuan" value="<?= htmlspecialchars($detail['satuan']); ?>" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td><input type="text" name="harga_satuan[]" class="form-control harga_satuan rupiah" value="<?= number_format($detail['harga_satuan'], 0, '', '.'); ?>" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td class="subtotal_display text-right"><?= format_rupiah($detail['subtotal']); ?></td>
                                        <?php if ($is_editable): ?>
                                            <td><button type="button" class="btn btn-danger btn-xs remove-item"><i class="glyphicon glyphicon-trash"></i></button></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php 
                                    endforeach;
                                else:
                                    // Baris kosong untuk inputan awal jika tidak ada detail
                                ?>
                                    <tr class="rab-item">
                                        <td>
                                            <?php if ($is_editable): ?>
                                            <select name="id_akun[]" class="form-control id_akun" required>
                                                <option value="">Pilih Akun</option>
                                                <?php foreach ($akun_list as $akun): ?>
                                                    <option value="<?= $akun['id_akun']; ?>">
                                                        <?= htmlspecialchars($akun['kode_akun']) . ' - ' . htmlspecialchars($akun['nama_akun']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php else: ?>
                                                <input type="hidden" name="id_akun[]" value="">
                                            <?php endif; ?>
                                        </td>
                                        <td><input type="text" name="uraian[]" class="form-control uraian" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td><input type="number" step="any" name="volume[]" class="form-control volume" min="0" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td><input type="text" name="satuan[]" class="form-control satuan" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td><input type="text" name="harga_satuan[]" class="form-control harga_satuan rupiah" required <?= $is_editable ? '' : 'disabled'; ?>></td>
                                        <td class="subtotal_display text-right"><?= format_rupiah(0); ?></td>
                                        <?php if ($is_editable): ?>
                                            <td><button type="button" class="btn btn-danger btn-xs remove-item"><i class="glyphicon glyphicon-trash"></i></button></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="<?= $is_editable ? '5' : '4'; ?>" class="text-right">
                                        <?php if ($is_editable): ?>
                                            <button type="button" class="btn btn-success btn-xs" id="add-item"><i class="glyphicon glyphicon-plus"></i> Tambah Item</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><strong id="total_anggaran_display"><?= format_rupiah($total_anggaran_display); ?></strong></td>
                                    <?php if ($is_editable): ?><td></td><?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12 text-right">
            <?php if ($is_editable): ?>
                <?php if ($is_revisi): ?>
                    <button type="submit" name="action" value="draft" class="btn btn-default">
                        <i class="glyphicon glyphicon-floppy-disk"></i> Simpan Draft Revisi
                    </button>
                    <button type="submit" name="action" value="submit" class="btn btn-success" 
                            onclick="return confirm('Anda yakin RAB ini sudah direvisi dan siap diajukan ulang ke Keuangan?');">
                        <i class="glyphicon glyphicon-send"></i> Ajukan Ulang Revisi
                    </button>
                <?php else: ?>
                    <button type="submit" name="action" value="draft" class="btn btn-default">
                        <i class="glyphicon glyphicon-floppy-disk"></i> Simpan Draft
                    </button>
                    <button type="submit" name="action" value="submit" class="btn btn-success" 
                            onclick="return confirm('Anda yakin RAB ini sudah final dan siap diajukan ke Keuangan?');">
                        <i class="glyphicon glyphicon-send"></i> Ajukan ke Keuangan
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</form>

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
            // Tambahkan desimal ,00 jika angka bulat
            rupiah = rupiah.indexOf(',') === -1 ? rupiah + ',00' : rupiah;
            // Hilangkan ,00 jika angka bulat
            rupiah = rupiah.replace(/,00$/, ''); 
            return rupiah;
        }
        
        // Fungsi untuk menghitung Subtotal dan Total Keseluruhan
        function calculateTotal() {
            let grandTotal = 0;
            const isEditable = <?= $is_editable ? 'true' : 'false'; ?>;

            if (!isEditable) return; // Hentikan perhitungan jika tidak editable

            $('#rab_detail_table .rab-item').each(function() {
                const $row = $(this);
                let volume = 0;
                let harga = 0;

                volume = parseFloat($row.find('.volume').val()) || 0;
                // Hapus format ribuan (' . ') sebelum konversi ke float
                harga = parseFloat($row.find('.harga_satuan').val().replace(/\./g, '').replace(/,/g, '.')) || 0; 
                
                const subtotal = volume * harga;
                grandTotal += subtotal;

                // Tampilkan subtotal dalam format Rupiah
                $row.find('.subtotal_display').text("Rp " + formatRupiah(subtotal.toFixed(0).replace(/\.00$/, '')));
            });

            // Perbarui total_anggaran_display
            $('#total_anggaran_display').text("Rp " + formatRupiah(grandTotal.toFixed(0).replace(/\.00$/, '')));
        }

        // Template baris baru
        const templateRowHtml = `
            <td>
                <select name="id_akun[]" class="form-control id_akun" required>
                    <option value="">Pilih Akun</option>
                    <?php foreach ($akun_list as $akun): ?>
                        <option value="<?= $akun['id_akun']; ?>">
                            <?= htmlspecialchars($akun['kode_akun']) . ' - ' . htmlspecialchars($akun['nama_akun']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" name="uraian[]" class="form-control uraian" required></td>
            <td><input type="number" step="any" name="volume[]" class="form-control volume" min="0" required></td>
            <td><input type="text" name="satuan[]" class="form-control satuan" required></td>
            <td><input type="text" name="harga_satuan[]" class="form-control harga_satuan rupiah" required></td>
            <td class="subtotal_display text-right"><?= format_rupiah(0); ?></td>
            <td><button type="button" class="btn btn-danger btn-xs remove-item"><i class="glyphicon glyphicon-trash"></i></button></td>
        `;

        // Fungsi penambah item
        $('#add-item').on('click', function() {
            const $newRow = $('<tr class="rab-item"></tr>').html(templateRowHtml);
            $('#rab_detail_table tbody').append($newRow);
            bindEvents($newRow);
            calculateTotal();
        });

        // Fungsi penghapus item
        $('#rab_detail_table').on('click', '.remove-item', function() {
            // Minimal harus ada satu baris tersisa
            if ($('#rab_detail_table .rab-item').length > 1) {
                $(this).closest('tr.rab-item').remove();
                calculateTotal();
            } else {
                alert("Minimal harus ada satu item RAB.");
            }
        });

        // Binding events ke input
        function bindEvents($container) {
            $container.find('.volume, .harga_satuan').on('input', calculateTotal);

            // Terapkan auto-format Rupiah saat input dilepas fokus (blur)
            $container.find('.harga_satuan').on('blur', function() {
                let value = $(this).val().replace(/\./g, '');
                if (value) {
                    $(this).val(formatRupiah(value));
                }
            });
        }
        
        // Initial binding untuk baris yang sudah ada
        bindEvents($('#rab_detail_table tbody'));
        calculateTotal(); // Hitung total awal
    });
</script>