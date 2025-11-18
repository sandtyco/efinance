<?php
// FILE: /efinance/pages/departemen/rab_list.php

global $conn;

// Ambil ID Departemen yang sedang login (Asumsi id_departemen disimpan di sesi)
$id_departemen = $_SESSION['id_departemen'] ?? 0;

// Logika Hapus RAB (Hanya boleh jika status_keuangan = 0, 2, atau 4)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_rab_to_delete = (int)$_GET['id'];

    // 1. Ambil status RAB
    $sql_check = "SELECT status_keuangan, id_departemen FROM rab WHERE id_rab = '{$id_rab_to_delete}'";
    $result_check = mysqli_query($conn, $sql_check);

    if ($result_check && $rab_to_delete = mysqli_fetch_assoc($result_check)) {
        if ($rab_to_delete['id_departemen'] != $id_departemen) {
            $_SESSION['message'] = "Akses ditolak. RAB bukan milik departemen Anda.";
            $_SESSION['message_type'] = "danger";
        } elseif ($rab_to_delete['status_keuangan'] == 0 || $rab_to_delete['status_keuangan'] == 2 || $rab_to_delete['status_keuangan'] == 4) {
            // RAB boleh dihapus jika Draft (0), Ditolak Keuangan (2), atau Ditolak Rektorat (4)

            mysqli_autocommit($conn, false);
            try {
                // Hapus detail RAB terkait
                $sql_detail = "DELETE FROM rab_detail WHERE id_rab = '{$id_rab_to_delete}'";
                if (!mysqli_query($conn, $sql_detail)) {
                    throw new Exception("Gagal menghapus detail RAB.");
                }

                // Hapus RAB header
                $sql_rab = "DELETE FROM rab WHERE id_rab = '{$id_rab_to_delete}'";
                if (!mysqli_query($conn, $sql_rab)) {
                    throw new Exception("Gagal menghapus RAB header.");
                }

                mysqli_commit($conn);
                $_SESSION['message'] = "RAB dan detailnya berhasil dihapus.";
                $_SESSION['message_type'] = "success";

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['message'] = "Gagal menghapus RAB: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
            mysqli_autocommit($conn, true);
        } else {
            // Jika status 1 (Menunggu Keu), 3 (Menunggu Rektorat), atau 5 (Final)
            $_SESSION['message'] = "RAB ini sedang dalam proses persetujuan dan tidak dapat dihapus.";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        $_SESSION['message'] = "RAB tidak ditemukan.";
        $_SESSION['message_type'] = "danger";
    }
    // Redirect untuk menghilangkan parameter GET setelah aksi
    // Pastikan fungsi redirect_to() didefinisikan di function.php
    redirect_to('dashboard.php?page=rab_list.php');
}


// --- PENTING: ASUMSI PERUBAHAN DI get_rab_by_departemen() ---
// Untuk menampilkan data Sisa Anggaran, kita berasumsi fungsi get_rab_by_departemen($id_departemen)
// SUDAH DIUPDATE untuk menyertakan AGGREGAT (SUM) dari kolom kumulatif di rab_detail.
// Data yang dikembalikan harus memiliki 2 kolom baru:
// 1. 'total_terpakai' (SUM dari biaya_terpakai_kumulatif semua detail di RAB ini)
// 2. 'sisa_anggaran' (Dihitung: total_anggaran - total_terpakai)

$list_rab = get_rab_by_departemen($id_departemen);

// ----------------------------------------------------
// Tambahkan skrip DataTables dan Modal Handler
// ----------------------------------------------------
// Array untuk menampung script yang akan dieksekusi di bagian footer
if (!isset($footer_scripts)) {
    $footer_scripts = [];
}

$footer_scripts[] = "
<script>
$(document).ready(function() {
    // DataTables Initialization
    $('#dataTable').DataTable();

    // JavaScript untuk menangani Modal Catatan
    $('#catatanModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Tombol yang memicu modal
        var judul = button.data('judul'); // Ambil judul RAB
        var catatan = button.data('catatan'); // Ambil isi catatan
        var header = button.data('header'); // Ambil header catatan BARU
        var modal = $(this);

        modal.find('.modal-title').html('<i class=\"glyphicon glyphicon-bullhorn\"></i> ' + header); // Gunakan header BARU
        modal.find('#modal-rab-judul').text(judul);
        // Mengganti baris baru (newline) menjadi <br> agar format catatan rapi
        modal.find('#modal-catatan-isi').html(catatan.replace(/\\n/g, '<br>'));
    });
});
</script>";
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-list-alt"></span> Daftar Rencana Anggaran Biaya (RAB)
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php?page=dashboard_dept.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li class="active">Daftar RAB</li>
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

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="dashboard.php?page=rab_add.php" class="btn btn-primary"><i class="glyphicon glyphicon-plus"></i> Ajukan RAB Baru</a>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="25%">Judul RAB</th>
                                <th width="15%">Anggaran Awal</th>
                                <th width="15%">Anggaran Terpakai</th>
                                <th width="15%">Sisa Anggaran</th>
                                <th width="10%">Tahun</th>
                                <th width="15%">Status</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($list_rab as $rab): ?>
                            <?php
                                // Ambil nilai baru, pastikan ada (asumsi dari get_rab_by_departemen)
                                $total_terpakai = $rab['total_terpakai'] ?? 0;
                                $sisa_anggaran = $rab['total_anggaran'] - $total_terpakai;
                                $status_class = '';

                                if ($sisa_anggaran < 0) {
                                    $status_class = 'danger'; // Over Budget
                                } elseif ($total_terpakai > 0 && $sisa_anggaran <= 0) {
                                    $status_class = 'warning'; // Anggaran Habis
                                }
                            ?>
                            <tr class="<?= $status_class; ?>">
                                <td><?= $no++; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($rab['judul']); ?></strong>
                                    <br>
                                    <?php
                                    $catatan_display = '';
                                    $catatan_header = '';
                                    $is_rejected = false;

                                    // 1. Cek Penolakan Keuangan (Status 2)
                                    if ($rab['status_keuangan'] == 2 && !empty($rab['catatan_keuangan'])) {
                                        $catatan_display = htmlspecialchars($rab['catatan_keuangan']);
                                        $catatan_header = 'Catatan Revisi Direktur Keuangan';
                                        $is_rejected = true;

                                    // 2. Cek Penolakan Rektorat (Status 4)
                                    } elseif ($rab['status_keuangan'] == 4 && !empty($rab['catatan_rektorat'])) {
                                        $catatan_display = htmlspecialchars($rab['catatan_rektorat']);
                                        $catatan_header = 'Catatan Revisi Rektorat';
                                        $is_rejected = true;
                                    }
                                    ?>
                                    <?php if ($is_rejected): ?>
                                        <button class="btn btn-danger btn-xs"
                                                data-toggle="modal"
                                                data-target="#catatanModal"
                                                data-judul="<?= htmlspecialchars($rab['judul']); ?>"
                                                data-catatan="<?= $catatan_display; ?>"
                                                data-header="<?= $catatan_header; ?>">
                                            <i class="glyphicon glyphicon-bullhorn"></i> Lihat Komentar
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= format_rupiah($rab['total_anggaran']); ?>
                                </td>
                                <!-- Kolom Baru: Anggaran Terpakai -->
                                <td class="text-warning">
                                    <strong><?= format_rupiah($total_terpakai); ?></strong>
                                </td>
                                <!-- Kolom Baru: Sisa Anggaran -->
                                <td class="
                                    <?php
                                        // Beri warna berdasarkan status sisa anggaran
                                        if ($sisa_anggaran < 0) {
                                            echo 'text-danger';
                                        } elseif ($sisa_anggaran == 0) {
                                            echo 'text-warning';
                                        } else {
                                            echo 'text-success';
                                        }
                                    ?>
                                ">
                                    <strong><?= format_rupiah($sisa_anggaran); ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($rab['tahun_anggaran']); ?>
                                </td>
                                <td>
                                    <?= get_rab_status_label($rab['status_keuangan'], $rab['status_rektorat']); ?>
                                </td>
                                <td>
                                    <?php
                                    // Boleh di-Revisi/Hapus jika statusnya Draft (0), Ditolak Keuangan (2), atau Ditolak Rektorat (4)
                                    $is_editable = ($rab['status_keuangan'] == 0 || $rab['status_keuangan'] == 2 || $rab['status_keuangan'] == 4);

                                    // Link Lihat Detail (sudah disederhanakan ke rab_edit.php yang universal)
                                    ?>

                                    <a href="dashboard.php?page=rab_edit.php&id=<?= $rab['id_rab']; ?>" class="btn btn-info btn-xs" title="Lihat Detail">
                                        <i class="glyphicon glyphicon-eye-open"></i> Lihat
                                    </a>

                                    <?php if ($is_editable): ?>

                                    <a href="dashboard.php?page=rab_edit.php&id=<?= $rab['id_rab']; ?>" class="btn btn-warning btn-xs" title="Revisi">
                                        <i class="glyphicon glyphicon-pencil"></i> Revisi
                                    </a>

                                    <a href="dashboard.php?page=rab_list.php&action=delete&id=<?= $rab['id_rab']; ?>" class="btn btn-danger btn-xs"
                                        onclick="return confirm('Anda yakin menghapus RAB ini? Proses ini tidak dapat dibatalkan.');" title="Hapus Permanen">
                                        <i class="glyphicon glyphicon-trash"></i> Hapus
                                    </a>

                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="catatanModal" tabindex="-1" role="dialog" aria-labelledby="catatanModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="catatanModalLabel"><i class="glyphicon glyphicon-bullhorn"></i> Catatan Revisi</h4>

            </div>
            <div class="modal-body">
                <p><strong>Revisi RAB:</strong> <span id="modal-rab-judul"></span></p>
                <hr>
                <div class="alert alert-info" id="modal-catatan-isi" style="white-space: pre-wrap;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>