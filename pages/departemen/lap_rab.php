<?php
// FILE: pages/departemen/lap_rab.php
// Tampilan Laporan RAB Tahunan (Menggunakan Modal Tanpa AJAX)
// Menambahkan filter berdasarkan peran (role) dan departemen user yang sedang login.

// Ambil koneksi database global 
global $conn;

if (!isset($conn)) {
echo "<div class='alert alert-danger'><h2>Kesalahan: Koneksi database tidak tersedia.</h2></div>";
return;
}

// ------------------------------------------------------------------
// 0. DETEKSI USER LOGIN & FILTER PARAMETER
// ------------------------------------------------------------------

// *** ASUMSI: SESI DATA INI SUDAH TERSEDIA SETELAH LOGIN ***
$logged_in_role = $_SESSION['id_role'] ?? 0;
$logged_in_dept_id = $_SESSION['id_departemen'] ?? 0; 
// *************************************************************

$current_year = date('Y');
$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : $current_year;

$data_rab = [];
$all_rab_details = [];
$rab_ids = [];

// ------------------------------------------------------------------
// 1. LOGIKA KONSTRUKSI QUERY SQL DENGAN FILTER ROLE
// ------------------------------------------------------------------

// 1a. Definisi kondisi dasar WHERE
$where_conditions = ["r.status_keuangan = '5'", "r.tahun_anggaran = ?"];
$bind_types = "i"; // Tipe data untuk $selected_year (integer)
$bind_params = [$selected_year];

// 1b. Logika Filter Berdasarkan Role
// Asumsi: Role 1/2 adalah user departemen. Role 3 ke atas (Keuangan/Rektorat) adalah global view.
if ($logged_in_role > 0 && $logged_in_role <= 2 && $logged_in_dept_id > 0) {
    // Jika user adalah Staff atau Kepala Unit/Dept dan memiliki ID departemen yang valid,
    // maka batasi laporan HANYA pada departemennya.
    $where_conditions[] = "r.id_departemen = ?";
    $bind_types .= "i"; // Tambahkan tipe data integer untuk id_departemen
    $bind_params[] = $logged_in_dept_id; // Tambahkan id_departemen ke parameter bind
    $filter_applied = " (Departemen ID: " . $logged_in_dept_id . ")";
} else {
    $filter_applied = " (Semua Departemen - Global View)";
}

// 1c. Gabungkan semua kondisi WHERE
$sql_where_clause = implode(' AND ', $where_conditions);

// 1d. Final Query RAB Utama
$sql_rab = "
    SELECT 
        r.id_rab, r.judul, r.tahun_anggaran, r.total_anggaran, d.nama_departemen,
        (SELECT COUNT(id_rab_detail) FROM rab_detail WHERE id_rab = r.id_rab) AS jumlah_rincian
    FROM 
        rab r
    JOIN 
        departemen d ON r.id_departemen = d.id_departemen
    WHERE 
        $sql_where_clause
    ORDER BY 
        d.nama_departemen, r.tahun_anggaran DESC
";

// ------------------------------------------------------------------
// 2. EKSEKUSI FETCH DATA RAB UTAMA
// ------------------------------------------------------------------
try {
    
    // Pengecekan Error Prepare SQL RAB Utama
    if (!$stmt_rab = $conn->prepare($sql_rab)) {
        // Jika prepare gagal, tampilkan error SQL yang sebenarnya dan hentikan eksekusi
        die("<div class='alert alert-danger'><strong>SQL ERROR (RAB Utama):</strong> Gagal prepare query SQL. MySQL Error: " . $conn->error . "</div>");
    }

    // Bind parameter dinamis ($selected_year, dan opsional $logged_in_dept_id)
    $stmt_rab->bind_param($bind_types, ...$bind_params); 
    $stmt_rab->execute();
    $result_rab = $stmt_rab->get_result();

    if ($result_rab) {
        while ($row = $result_rab->fetch_assoc()) {
            $data_rab[] = $row;
            $rab_ids[] = $row['id_rab']; // Kumpulkan semua ID RAB
        }
        $stmt_rab->close();
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Kesalahan saat mengambil data RAB: " . $e->getMessage() . "</div>";
}

// ------------------------------------------------------------------
// 3. LOGIKA FETCH SEMUA RAB DETAIL
// ------------------------------------------------------------------
if (!empty($rab_ids)) {
    try {
        // Buat string placeholder (?, ?, ?) untuk klausa IN
        $placeholders = implode(',', array_fill(0, count($rab_ids), '?'));
        // Tipe data untuk bind_param (semua integer 'i')
        $types = str_repeat('i', count($rab_ids));

        $sql_details = "
            SELECT 
                rd.id_rab, rd.uraian, rd.volume, rd.satuan, rd.harga_satuan, rd.subtotal, 
                a.kode_akun, a.nama_akun 
            FROM 
                rab_detail rd
            JOIN 
                akun a ON rd.id_akun = a.id_akun 
            WHERE 
                rd.id_rab IN ($placeholders)
            ORDER BY 
                rd.id_rab, a.kode_akun ASC
        ";

        // --- Pengecekan Error Prepare SQL RAB Detail ---
        if (!$stmt_details = $conn->prepare($sql_details)) {
            // Jika prepare gagal, tampilkan error SQL yang sebenarnya dan hentikan eksekusi
            die("<div class='alert alert-danger'><strong>SQL FATAL ERROR (RAB Detail):</strong> Gagal prepare query SQL. <br>MySQL Error: " . $conn->error . "</div>");
        }
        // ----------------------------------------------------

        // Bind parameter dinamis
        $stmt_details->bind_param($types, ...$rab_ids);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();

        while ($row = $result_details->fetch_assoc()) {
            // Kelompokkan detail berdasarkan id_rab
            $all_rab_details[$row['id_rab']][] = $row;
        }
        $stmt_details->close();

    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Kesalahan saat mengambil data RAB Detail: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="row">
<div class="col-lg-12">
<h1 class="page-header">
Laporan RAB Tahunan <br><small>Daftar RAB yang Disetujui Rektorat</small>
</h1>
<ol class="breadcrumb">
<li><i class="glyphicon glyphicon-stats"></i> Laporan</li>
<li class="active">RAB Tahunan</li>
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
<input type="hidden" name="page" value="lap_rab.php">
<div class="form-group">
<label for="tahun_filter">Tahun Anggaran RAB:</label>
<select name="tahun" id="tahun_filter" class="form-control">
	<?php 
	$year_range = range($current_year - 5, $current_year + 5);
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

<!-- Data Table Section (Tombol Aksi Diperbarui) -->
<div class="row">
<div class="col-lg-12">
<div class="panel panel-info">
<div class="panel-heading">
<h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Data RAB (Tahun <?php echo $selected_year; ?>)</h3>
</div>
<div class="panel-body">
<div class="table-responsive">
<table class="table table-bordered table-hover table-striped">
<thead>
	<tr>
		<th>#</th>
		<th>Tahun Anggaran</th>
		<th>Departemen</th>
		<th>Judul</th>
		<th>Total Anggaran</th>
		<th>Jml Rincian</th>
		<th>Aksi</th>
	</tr>
</thead>
<tbody>
	<?php if (!empty($data_rab)): ?>
		<?php $no = 1; foreach ($data_rab as $rab): ?>
		<tr>
			<td><?php echo $no++; ?></td>
			<td><?php echo htmlspecialchars($rab['tahun_anggaran']); ?></td>
			<td><?php echo htmlspecialchars($rab['nama_departemen']); ?></td>
			<td><?php echo htmlspecialchars($rab['judul']); ?></td>
			<td><?php echo 'Rp ' . number_format($rab['total_anggaran'], 0, ',', '.'); ?></td>
			<td><?php echo $rab['jumlah_rincian']; ?></td>
			<td>
				<!-- Tombol Detail menargetkan Modal unik berdasarkan ID RAB -->
				<button 
					class="btn btn-sm btn-success" 
					data-toggle="modal" 
					data-target="#detailModal_<?php echo $rab['id_rab']; ?>"
					title="Lihat Detail Rincian RAB">
					<i class="glyphicon glyphicon-eye-open"></i> Detail
				</button>
				<!-- Tombol untuk Mencetak PDF -->
				<a href="pages/departemen/cetak_rab_pdf.php?id=<?php echo $rab['id_rab']; ?>" target="_blank" class="btn btn-sm btn-danger" title="Cetak PDF">
					<i class="glyphicon glyphicon-print"></i> Cetak PDF
				</a>
			</td>
		</tr>
		<?php endforeach; ?>
	<?php else: ?>
		<tr>
			<td colspan="7" class="text-center">
                Tidak ada data RAB yang Disetujui Rektorat pada tahun <?php echo $selected_year; ?> 
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
<?php foreach ($data_rab as $rab): 
$rab_id = $rab['id_rab'];
$details = $all_rab_details[$rab_id] ?? [];
?>
<div class="modal fade" id="detailModal_<?php echo $rab_id; ?>" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel_<?php echo $rab_id; ?>" aria-hidden="true">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
<div class="modal-header">
<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
<h4 class="modal-title" id="detailModalLabel_<?php echo $rab_id; ?>">Rincian Anggaran Biaya (ID: <?php echo $rab_id; ?>)</h4>
</div>
<div class="modal-body">
<h5 class="text-primary">Judul RAB: <?php echo htmlspecialchars($rab['judul']); ?></h5>
<hr>

<div class="table-responsive">
<?php if (!empty($details)): ?>
<table class="table table-bordered table-striped table-hover table-condensed">
<thead>
	<tr>
		<th class="text-center">#</th>
		<th class="text-center">Kode Akun</th>
		<th class="text-center">Uraian</th>
		<th class="text-center">Vol</th>
		<th class="text-center">Satuan</th>
		<th class="text-right">Hrg Satuan (Rp)</th>
		<th class="text-right">Subtotal (Rp)</th>
	</tr>
</thead>
<tbody>
	<?php 
	$no = 1;
	$grand_total = 0;
	foreach ($details as $detail): 
		$grand_total += $detail['subtotal'];
	?>
	<tr>
		<td class="text-center"><?php echo $no++; ?></td>
		<td><?php echo htmlspecialchars($detail['kode_akun']); ?></td>
		<td><?php echo htmlspecialchars($detail['uraian']); ?></td>
		<td class="text-center"><?php echo htmlspecialchars($detail['volume']); ?></td>
		<td class="text-center"><?php echo htmlspecialchars($detail['satuan']); ?></td>
		<td class="text-right"><?php echo number_format($detail['harga_satuan'], 0, ',', '.'); ?></td>
		<td class="text-right"><?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></td>
	</tr>
	<?php endforeach; ?>
</tbody>
<tfoot>
	<tr class="success">
		<td colspan="6" class="text-right"><strong>TOTAL RAB:</strong></td>
		<td class="text-right"><strong>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></strong></td>
	</tr>
</tfoot>
</table>
<?php else: ?>
<p class="text-center text-warning">Tidak ada rincian anggaran (RAB Detail) yang ditemukan untuk RAB ini.</p>
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