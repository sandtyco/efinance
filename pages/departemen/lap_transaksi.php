<?php
// FILE: pages/departemen/lap_transaksi.php
// Tampilan Laporan Realisasi Anggaran Tahunan
global $conn;

if (!isset($conn) || !is_object($conn)) {
    echo "<div class='alert alert-danger'><h2>Kesalahan: Koneksi database tidak tersedia.</h2></div>";
    return;
}

// ------------------------------------------------------------------
// 0. DETEKSI USER LOGIN & FILTER PARAMETER
// ------------------------------------------------------------------
$logged_in_role = $_SESSION['id_role'] ?? 0;
$logged_in_dept_id = $_SESSION['id_departemen'] ?? 0;

$current_year = date('Y');
$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : $current_year;

$data_realisasi = [];
$all_realisasi_details = [];
$realisasi_ids = [];

/**
 * Fungsi helper untuk memformat tanggal ke format Indonesia (misal: 17 November 2025)
 */
function formatTanggalID($date_string) {
    if (empty($date_string) || $date_string === '0000-00-00' || strtotime($date_string) === false) {
        return '-';
    }
    // Menggunakan strftime untuk format tanggal lokal (diperlukan setlocale)
    setlocale(LC_TIME, 'id_ID.utf8', 'id_ID', 'indonesian'); 
    return strftime('%d %B %Y', strtotime($date_string));
}

/**
 * Mengganti format_rupiah() yang tidak tersedia.
 * @param float $number Angka yang akan diformat.
 * @return string Angka terformat.
 */
function format_number_idr($number) {
    return number_format($number, 0, ',', '.');
}


// ------------------------------------------------------------------
// 1. LOGIKA KONSTRUKSI QUERY SQL DENGAN FILTER ROLE (Realisasi Utama)
// ------------------------------------------------------------------
$where_conditions = ["r.status = '3'", "rab.tahun_anggaran = ?"];
$bind_types = "i";
$bind_params = [$selected_year];

if ($logged_in_role > 0 && $logged_in_role <= 2 && $logged_in_dept_id > 0) {
    $where_conditions[] = "r.id_departemen = ?";
    $bind_types .= "i";
    $bind_params[] = $logged_in_dept_id;
    $filter_applied = " (Departemen ID: " . $logged_in_dept_id . ")";
} else {
    $filter_applied = " (Semua Departemen - Global View)";
}

$sql_where_clause = implode(' AND ', $where_conditions);

// Realisasi Utama: Mengambil data realisasi dan judul RAB yang terkait (Dipastikan Bersih)
$sql_realisasi = "
    SELECT
        r.id_realisasi,
        r.tanggal_realisasi,
        r.nomor_dokumen,
        r.total_realisasi,
        r.deskripsi AS uraian_ringkasan,
        d.nama_departemen,
        (SELECT COUNT(id_realisasi_detail) FROM realisasi_detail WHERE id_realisasi = r.id_realisasi) AS jumlah_rincian,
        rab.judul,
        rab.tahun_anggaran
    FROM
        realisasi r
    JOIN
        departemen d ON r.id_departemen = d.id_departemen
    JOIN
        rab ON r.id_rab = rab.id_rab
    WHERE
        $sql_where_clause
    ORDER BY
        r.tanggal_realisasi DESC, r.nomor_dokumen DESC
";

// ------------------------------------------------------------------
// 2. EKSEKUSI FETCH DATA REALISASI UTAMA
// ------------------------------------------------------------------
try {
    if (!$stmt_realisasi = $conn->prepare($sql_realisasi)) {
        die("<div class='alert alert-danger'><strong>SQL ERROR (Realisasi Utama):</strong> Gagal prepare query SQL. MySQL Error: " . $conn->error . "</div>");
    }

    $stmt_realisasi->bind_param($bind_types, ...$bind_params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();

    if ($result_realisasi) {
        while ($row = $result_realisasi->fetch_assoc()) {
            $data_realisasi[] = $row;
            $realisasi_ids[] = $row['id_realisasi'];
        }
        $stmt_realisasi->close();
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Kesalahan saat mengambil data Realisasi: " . $e->getMessage() . "</div>";
}

// ------------------------------------------------------------------
// 3. LOGIKA FETCH SEMUA REALISASI DETAIL (Sederhana)
// ------------------------------------------------------------------
if (!empty($realisasi_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($realisasi_ids), '?'));
        $types = str_repeat('i', count($realisasi_ids));

        // QUERY SEDERHANA: Hanya ambil dari realisasi_detail
        $sql_details = "
            SELECT
                rd.id_realisasi,
                rd.id_realisasi_detail, 
                rd.uraian AS uraian_realisasi, 
                rd.jumlah_realisasi
            FROM
                realisasi_detail rd
            WHERE
                rd.id_realisasi IN ($placeholders)
            ORDER BY
                rd.id_realisasi, rd.id_realisasi_detail ASC
        ";

        if (!$stmt_details = $conn->prepare($sql_details)) {
            die("<div class='alert alert-danger'><strong>SQL FATAL ERROR (Realisasi Detail):</strong> Gagal prepare query SQL. <br>MySQL Error: " . $conn->error . "</div>");
        }

        $stmt_details->bind_param($types, ...$realisasi_ids);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();

        while ($row = $result_details->fetch_assoc()) {
            // Simpan detail yang dikelompokkan berdasarkan ID Realisasi
            $all_realisasi_details[$row['id_realisasi']][] = [
                'id_realisasi_detail' => $row['id_realisasi_detail'],
                'uraian_realisasi' => $row['uraian_realisasi'], 
                'volume' => $row['jumlah_realisasi'], // Ini akan menjadi Qty
            ];
        }
        $stmt_details->close();

    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Kesalahan saat mengambil data Realisasi Detail: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            Laporan Realisasi Anggaran Tahunan <br><small>Daftar Realisasi yang Disetujui Direktur Keuangan</small>
        </h1>
        <ol class="breadcrumb">
            <li><i class="glyphicon glyphicon-stats"></i> Laporan</li>
            <li class="active">Realisasi Anggaran</li>
        </ol>
    </div>
</div>

<!-- Filter Section -->
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-filter"></i> Filter Data</h3>
            </div>
            <div class="panel-body">
                <form method="GET" action="dashboard.php" class="form-inline">
                    <input type="hidden" name="page" value="lap_transaksi.php">
                    <div class="form-group">
                        <label for="tahun_filter">Tahun Anggaran RAB:</label>
                        <select name="tahun" id="tahun_filter" class="form-control">
                            <?php
                            $year_range = range(date('Y') - 5, date('Y') + 5);
                            foreach ($year_range as $year) {
                                $selected = ($year == $selected_year) ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="glyphicon glyphicon-search"></i> Tampilkan</button>
                    <span class="pull-right text-muted" style="margin-top: 8px; font-size: 11px;">
                        *Filter aktif: <?php echo $filter_applied; ?>
                    </span>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Data Table Section -->
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Data Realisasi (Tahun Anggaran RAB <?php echo $selected_year; ?>)</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal Realisasi</th>
                                <th>Nomor Dokumen</th>
                                <th>Deskripsi Realisasi</th>
                                <th>Total Realisasi</th>
                                <th>Jml Rincian</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_realisasi)): ?>
                                <?php $no = 1; foreach ($data_realisasi as $realisasi): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo formatTanggalID($realisasi['tanggal_realisasi']); ?></td>
                                    <td><?php echo htmlspecialchars($realisasi['nomor_dokumen']); ?></td>
                                    <td><?php echo htmlspecialchars($realisasi['uraian_ringkasan']); ?></td>
                                    <td><?php echo 'Rp ' . format_number_idr($realisasi['total_realisasi']); ?></td>
                                    <td><?php echo $realisasi['jumlah_rincian']; ?></td>
                                    <td>
                                        <!-- Tombol Detail menargetkan Modal unik berdasarkan ID Realisasi -->
                                        <button
                                            class="btn btn-sm btn-success"
                                            data-toggle="modal"
                                            data-target="#detailModal_<?php echo $realisasi['id_realisasi']; ?>"
                                            title="Lihat Detail Rincian Realisasi">
                                            <i class="glyphicon glyphicon-eye-open"></i> Detail
                                        </button>
                                        <!-- Link PDF Realisasi -->
                                        <a href="pages/departemen/cetak_kwitansi_pdf.php?id=<?php echo $realisasi['id_realisasi']; ?>" target="_blank" class="btn btn-sm btn-danger" title="Cetak PDF">
                                            <i class="glyphicon glyphicon-print"></i> Cetak PDF
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        Tidak ada data Realisasi yang Disetujui Rektorat pada tahun anggaran RAB <?php echo $selected_year; ?>
                                        <?php echo $filter_applied == " (Semua Departemen - Global View)" ? '.' : ' untuk departemen Anda.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- -------------------------------------------------------- -->
<!-- STRUKTUR MODAL GENERATED DENGAN PHP -->
<!-- -------------------------------------------------------- -->
<?php 
foreach ($data_realisasi as $realisasi):
    $realisasi_id = $realisasi['id_realisasi'];
    // Ambil detail yang sudah di-grouping
    $details = $all_realisasi_details[$realisasi_id] ?? []; 
?>
<div class="modal fade" id="detailModal_<?php echo $realisasi_id; ?>" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel_<?php echo $realisasi_id; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="detailModalLabel_<?php echo $realisasi_id; ?>">Rincian Transaksi Realisasi (<?php echo htmlspecialchars($realisasi['nomor_dokumen']); ?>)</h4>
            </div>
            <div class="modal-body">
                <!-- Data Utama Realisasi (r.tanggal_realisasi, r.nomor_dokumen, r.total_realisasi) -->
                <p>
                    <strong>Tanggal Realisasi:</strong> <?php echo formatTanggalID($realisasi['tanggal_realisasi']); ?><br>
                    <strong>Nomor Dokumen:</strong> <?php echo htmlspecialchars($realisasi['nomor_dokumen']); ?><br>
                    <strong>Total Realisasi (Utama):</strong> <span class="lead text-danger">Rp <?php echo format_number_idr($realisasi['total_realisasi']); ?></span>
                </p>
                <hr>
                
                <h5 class="text-primary">Detail Item Transaksi:</h5>
                <div class="table-responsive" style="margin-top: 15px;">
                    <?php if (!empty($details)): ?>
                    <table class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr class="info">
                                <th class="text-center" style="width: 5%;">No</th>
                                <th class="text-center" style="width: 15%;">ID Rincian</th>
                                <th style="width: 60%;">Uraian Item (rd.uraian)</th>
                                <th class="text-center" style="width: 20%;">Qty (rd.jumlah_realisasi)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($details as $detail):
                                $qty = $detail['volume']; 
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td class="text-center text-muted">
                                    #<?php echo htmlspecialchars($detail['id_realisasi_detail']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($detail['uraian_realisasi']); ?>
                                </td>
                                <td class="text-center text-primary" style="font-weight: 600;">
                                    <?php echo format_number_idr($qty); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-center alert alert-warning">
                        <i class="glyphicon glyphicon-info-sign"></i> Tidak ada rincian transaksi (Realisasi Detail) yang ditemukan untuk Realisasi ini.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>