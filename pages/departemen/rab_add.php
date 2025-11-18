<?php
// FILE: /efinance/pages/departemen/rab_add.php

global $conn;

// Ambil ID Departemen yang sedang login
$id_departemen = $_SESSION['id_departemen'] ?? 0;
$error_akun = null;

// Ambil daftar AKUN (Kode Akun dan Nama Akun) untuk dropdown
$sql_akun = "SELECT id_akun, kode_akun, nama_akun FROM akun ORDER BY kode_akun ASC";
$query_akun = mysqli_query($conn, $sql_akun);
$list_akun = [];
if ($query_akun) {
    while ($row = mysqli_fetch_assoc($query_akun)) {
        $list_akun[] = $row;
    }
} else {
    $error_akun = "Error mengambil data Akun: " . mysqli_error($conn);
}

// ----------------------------------------------------
// PROSES PENYIMPANAN DATA (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ambil data Header
    $judul = clean_input($_POST['judul'] ?? '');
    $tahun_anggaran = clean_input($_POST['tahun_anggaran'] ?? '');
    $deskripsi = clean_input($_POST['deskripsi'] ?? '');
    $total_anggaran = clean_input($_POST['total_anggaran'] ?? 0);
    $action_type = $_POST['action_type'] ?? 'draft'; // Bisa 'draft' (0) atau 'submit' (1)
    
    // Tentukan status berdasarkan action
    $status_keuangan = ($action_type === 'submit') ? 1 : 0; 

    // 2. Ambil data Detail
    $details = [];
    if (isset($_POST['id_akun']) && is_array($_POST['id_akun'])) {
        for ($i = 0; $i < count($_POST['id_akun']); $i++) {
            $volume = floatval($_POST['volume'][$i]);
            $harga_satuan = floatval($_POST['harga_satuan'][$i]);

            // Hanya proses baris yang memiliki detail minimal
            if (!empty($_POST['uraian'][$i]) && $volume > 0 && $harga_satuan > 0) {
                $details[] = [
                    'id_akun' => $_POST['id_akun'][$i],
                    'uraian' => $_POST['uraian'][$i],
                    'volume' => $volume,
                    'satuan' => $_POST['satuan'][$i],
                    'harga_satuan' => $harga_satuan
                ];
            }
        }
    }

    // 3. Validasi Dasar
    if (empty($judul) || empty($tahun_anggaran) || empty($details)) {
        $_SESSION['message'] = "Judul, Tahun Anggaran, dan minimal satu Detail RAB wajib diisi.";
        $_SESSION['message_type'] = "danger";
    } else {
        // 4. Panggil fungsi insert
        if (insert_rab_and_detail($id_departemen, $judul, $tahun_anggaran, $deskripsi, $details, $status_keuangan, $total_anggaran)) {
            $msg = ($status_keuangan == 1) ? "RAB berhasil diajukan dan menunggu persetujuan Keuangan." : "RAB berhasil disimpan sebagai Draft.";
            $_SESSION['message'] = $msg;
            $_SESSION['message_type'] = "success";
            redirect_to('dashboard.php?page=rab_list.php');
        } else {
            $_SESSION['message'] = "Gagal menyimpan RAB ke database. Silakan coba lagi.";
            $_SESSION['message_type'] = "danger";
        }
    }
    // Jika gagal, tampilkan error di halaman yang sama
}

// Ambil nilai POST lama (jika ada error) untuk diisi ulang di form
$old_post = $_POST ?? [];
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-plus-sign"></span> Buat RAB Baru
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="dashboard.php?page=rab_list.php">Daftar RAB</a></li>
            <li class="active">Tambah RAB</li>
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

if ($error_akun): // Tampilkan error jika gagal ambil data akun
?>
<div class="alert alert-danger"><?= $error_akun; ?></div>
<?php endif; ?>

<form action="dashboard.php?page=rab_add.php" method="POST">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="glyphicon glyphicon-pencil"></i> Data RAB (Header)</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="judul">Judul RAB *</label>
                                <input type="text" name="judul" id="judul" class="form-control" required value="<?= htmlspecialchars($old_post['judul'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tahun_anggaran">Tahun Anggaran *</label>
                                <input type="number" name="tahun_anggaran" id="tahun_anggaran" class="form-control" required min="2020" max="2099" value="<?= htmlspecialchars($old_post['tahun_anggaran'] ?? date('Y')); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi RAB</label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($old_post['deskripsi'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Detail Rencana Anggaran Biaya</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="detail_rab_table">
                            <thead>
                                <tr>
                                    <th width="20%">Akun (Kode & Nama)</th>
                                    <th width="30%">Uraian Kegiatan *</th>
                                    <th width="10%">Volume *</th>
                                    <th width="10%">Satuan</th>
                                    <th width="15%">Harga Satuan *</th>
                                    <th width="10%">Subtotal</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-right"><strong>TOTAL ANGGARAN KESELURUHAN:</strong></td>
                                    <td id="grand_total_display" class="text-right">Rp 0,00</td>
                                    <td><input type="hidden" name="total_anggaran" id="total_anggaran_input" value="0"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-success btn-sm" id="add_row"><i class="glyphicon glyphicon-plus"></i> Tambah Baris Detail</button>
                </div>
            </div>
            
            <div class="panel-footer text-right">
                <button type="submit" name="action_type" value="draft" class="btn btn-default"><i class="glyphicon glyphicon-save"></i> Simpan Sebagai Draft</button>
                <button type="submit" name="action_type" value="submit" class="btn btn-success"><i class="glyphicon glyphicon-send"></i> Ajukan RAB (Kirim)</button>
            </div>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.querySelector('#detail_rab_table tbody');
        const addButton = document.querySelector('#add_row');
        const totalInput = document.querySelector('#total_anggaran_input');
        const totalDisplay = document.querySelector('#grand_total_display');
        let rowCount = 0;

        // Data Akun dari PHP untuk digunakan di JavaScript
        const listAkun = <?= json_encode($list_akun); ?>;

        // Fungsi untuk membuat dropdown Akun
        function createAkunSelect(index) {
            let selectHtml = '<select name="id_akun[]" class="form-control input-sm" required>';
            selectHtml += '<option value="">-- Pilih Akun --</option>';
            listAkun.forEach(akun => {
                selectHtml += `<option value="${akun.id_akun}">${akun.kode_akun} - ${akun.nama_akun}</option>`;
            });
            selectHtml += '</select>';
            return selectHtml;
        }

        // Fungsi untuk menambahkan baris baru
        function addRow() {
            const newRow = tableBody.insertRow();
            newRow.id = `row_${rowCount}`;
            newRow.innerHTML = `
                <td>${createAkunSelect(rowCount)}</td>
                <td><input type="text" name="uraian[]" class="form-control input-sm" required></td>
                <td><input type="number" name="volume[]" class="form-control input-sm volume-input" step="any" min="1" value="1" required></td>
                <td><input type="text" name="satuan[]" class="form-control input-sm"></td>
                <td><input type="number" name="harga_satuan[]" class="form-control input-sm harga-input" step="any" min="0" required></td>
                <td class="text-right subtotal-display">Rp 0,00</td>
                <td><button type="button" class="btn btn-danger btn-xs remove-row"><i class="glyphicon glyphicon-remove"></i></button></td>
            `;
            rowCount++;
            attachListeners(); // Lampirkan listener ke baris baru
        }

        // Fungsi untuk melampirkan listener perubahan pada input
        function attachListeners() {
            tableBody.querySelectorAll('.volume-input, .harga-input').forEach(input => {
                // Hapus listener lama jika ada (untuk mencegah duplikasi)
                input.removeEventListener('change', calculateTotal);
                input.removeEventListener('keyup', calculateTotal);

                // Tambahkan listener baru
                input.addEventListener('change', calculateTotal);
                input.addEventListener('keyup', calculateTotal);
            });

            // Listener untuk tombol hapus
            tableBody.querySelectorAll('.remove-row').forEach(button => {
                button.onclick = function() {
                    this.closest('tr').remove();
                    calculateTotal(); // Hitung ulang total setelah baris dihapus
                };
            });
        }

        // Fungsi untuk menghitung total
        function calculateTotal() {
            let grandTotal = 0;
            tableBody.querySelectorAll('tr').forEach(row => {
                const volumeInput = row.querySelector('.volume-input');
                const hargaInput = row.querySelector('.harga-input');
                const subtotalDisplay = row.querySelector('.subtotal-display');

                if (volumeInput && hargaInput && subtotalDisplay) {
                    const volume = parseFloat(volumeInput.value) || 0;
                    const harga = parseFloat(hargaInput.value) || 0;
                    const subtotal = volume * harga;
                    grandTotal += subtotal;

                    // Format Subtotal
                    subtotalDisplay.textContent = formatRupiah(subtotal);
                }
            });

            // Update Grand Total
            totalDisplay.textContent = formatRupiah(grandTotal);
            totalInput.value = grandTotal.toFixed(2); // Simpan nilai numerik di hidden input
        }

        // Fungsi untuk format rupiah di JavaScript
        function formatRupiah(angka) {
            const reverse = angka.toFixed(2).toString().split('').reverse().join('');
            const ribuan = reverse.match(/\d{1,3}/g);
            const rupiah = ribuan.join('.').split('').reverse().join('');
            return 'Rp ' + rupiah;
        }

        // Event listener untuk tombol Tambah Baris
        addButton.addEventListener('click', addRow);

        // Tambahkan satu baris default saat halaman dimuat
        addRow(); 
    });
</script>