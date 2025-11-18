<?php
// FILE: pages/departemen/cetak_Realisasi_pdf.php (Disesuaikan dengan Skema Tabel Baru)
// Skrip untuk menghasilkan laporan Realisasi yang disetujui dalam format PDF (A4 Portrait).

// =========================================================================
// 1. INTEGRASI DOMPDF
// =========================================================================

// PATH DOMPDF (Sesuaikan jika path ini tidak benar di lingkungan Anda)
require_once '../../assets/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// =========================================================================
// 2. FUNGSI LOGO BASE64 DAN SETUP KONEKSI
// =========================================================================

// URL gambar logo yang ingin disematkan (Sesuaikan path)
$logo_url = "../../assets/img/favicon.png";
$logo_base64 = '';

/**
 * Fungsi untuk mengambil gambar dari URL/Path dan mengonversinya menjadi Base64.
 */
function getLogoBase64($url) {
    // Fungsi ini mungkin membutuhkan konfigurasi allow_url_fopen = On di php.ini jika URL adalah eksternal
    $data = @file_get_contents($url);
    if ($data !== FALSE) {
        $mime_type = mime_content_type_from_path($url);
        if ($mime_type === false) { $mime_type = 'image/png'; }
        return 'data:' . $mime_type . ';base64,' . base64_encode($data);
    }
    return '';
}

/**
 * Fungsi helper untuk mendapatkan mime type
 */
function mime_content_type_from_path($file) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    switch (strtolower($extension)) {
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'gif':
            return 'image/gif';
        case 'svg':
            return 'image/svg+xml';
        case 'png':
        default:
            return 'image/png';
    }
}

// Eksekusi fungsi untuk mendapatkan Base64 logo
$logo_base64 = getLogoBase64($logo_url);

// !!! JALUR KONEKSI DIBERIKAN OLEH PENGGUNA !!!
// Path relatif dari pages/departemen/ ke config/conn.php
require_once '../../config/conn.php';

global $conn;

if (!isset($conn) || !is_object($conn)) {
    die("Kesalahan FATAL: Variabel koneksi database (\$conn) tidak ditemukan atau tidak valid setelah meng-include file koneksi.");
}

$id_Realisasi = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_Realisasi === 0) {
    die("ID Realisasi tidak valid.");
}

$Realisasi_data = null;
$details = []; // Variabel untuk menampung detail realisasi

// =========================================================================
// 3. FETCH DATA REALISASI UTAMA (DAN DETAIL)
// =========================================================================

// --- 3a. Fetch Data Realisasi Utama ---
// Mengambil data dari tabel `realisasi`, `departemen`, dan `rab`
$sql_Realisasi = "
    SELECT
        r.id_realisasi, r.nomor_dokumen, r.tanggal_realisasi, r.total_realisasi, r.created_at, r.catatan_keuangan,
        r.id_departemen, d.nama_departemen,
        rab.tahun_anggaran
    FROM
        realisasi r
    JOIN
        departemen d ON r.id_departemen = d.id_departemen
    JOIN
        rab ON r.id_rab = rab.id_rab -- Menggunakan id_rab dari tabel realisasi untuk join ke rab
    WHERE
        r.id_realisasi = ?
    LIMIT 1
";

if ($stmt_realisasi = $conn->prepare($sql_Realisasi)) {
    if (!$stmt_realisasi->bind_param("i", $id_Realisasi)) {
        die("ERROR BIND (R): " . $stmt_realisasi->error);
    } else if (!$stmt_realisasi->execute()) {
        die("ERROR EXEC (R): " . $stmt_realisasi->error);
    } else {
        $result_realisasi = $stmt_realisasi->get_result();
        $Realisasi_data = $result_realisasi->fetch_assoc();
        if (!$Realisasi_data) {
            die("Data Realisasi dengan ID {$id_Realisasi} tidak ditemukan.");
        }
    }
    $stmt_realisasi->close();
} else {
    die("ERROR PREP (R): " . $conn->error);
}


// --- 3b. Fetch Detail Realisasi (Gabungan dengan rab_detail dan akun) ---
// Mengambil detail realisasi dan menggabungkannya dengan data perencanaan (RAB Detail) dan Akun
$sql_details = "
    SELECT
        rd.id_realisasi_detail,
        rd.uraian AS uraian_realisasi,         -- Uraian yang direalisasikan
        rd.jumlah_realisasi,                   -- Jumlah uang yang direalisasikan

        rad.volume,                            -- Volume dari RAB
        rad.satuan,                            -- Satuan dari RAB
        rad.harga_satuan,                      -- Harga Satuan dari RAB
        a.kode_akun                            -- Kode Akun (Asumsi tabel 'akun' ada)
    FROM
        realisasi_detail rd
    JOIN
        rab_detail rad ON rd.id_rab_detail = rad.id_rab_detail
    JOIN
        akun a ON rad.id_akun = a.id_akun
    WHERE
        rd.id_realisasi = ?
    ORDER BY
        rd.id_realisasi_detail ASC
";

if ($stmt_details = $conn->prepare($sql_details)) {
    if (!$stmt_details->bind_param("i", $id_Realisasi)) {
        error_log("ERROR BIND (RD): " . $stmt_details->error);
    } else if (!$stmt_details->execute()) {
        error_log("ERROR EXEC (RD): " . $stmt_details->error);
    } else {
        $result_details = $stmt_details->get_result();
        while ($row = $result_details->fetch_assoc()) {
            $details[] = $row;
        }
    }
    $stmt_details->close();
} else {
    error_log("ERROR PREP (RD): " . $conn->error);
}

// =========================================================================
// 4. FETCH DATA USER (Pengaju & Penyetuju) UNTUK TANDA TANGAN + NIP/NIDN
// (Logika query TTD tetap dipertahankan seperti kode asli Anda)
// =========================================================================

$role_pengaju_id = 2; // Kepala Departemen/Unit (Mengajukan)
// --- PERUBAHAN UTAMA: Role ID Penyetuju Final diubah menjadi 3 (Direktur Keuangan) ---
$role_rektorat_id = 3; // Direktur Keuangan (Penyetuju Final) - Sesuai permintaan pengguna

$pengaju_name = '';
$pengaju_nip_nidn = ''; // NIP/NIDN Pengaju
$pengaju_jabatan = 'Kepala Departemen / Unit'; // Jabatan yang mengajukan
$penyetuju_name = '';
$penyetuju_nip_nidn = ''; // NIP/NIDN Penyetuju
$penyetuju_jabatan = 'Direktur Keuangan'; // Jabatan yang menyetujui final

// --- 4a. Fetch PENGJAJU (Role 2 Dibatasi oleh Departemen Realisasi) ---
if (isset($Realisasi_data['id_departemen'])) {
    $id_departemen_Realisasi = $Realisasi_data['id_departemen'];
    
    // MENGAMBIL du.nama_lengkap DAN du.nip_nidn (dari detail_user)
    $sql_pengaju = "
        SELECT 
            du.nama_lengkap, du.nip_nidn
        FROM 
            detail_user du 
        JOIN 
            user u ON du.id_user = u.id_user
        WHERE 
            du.id_role = ? 
            AND du.id_departemen = ?
        LIMIT 1
    ";
    
    if ($stmt_pengaju = $conn->prepare($sql_pengaju)) {
        if (!$stmt_pengaju->bind_param("ii", $role_pengaju_id, $id_departemen_Realisasi)) {
            $pengaju_name = "ERROR BIND: " . $stmt_pengaju->error;
        } else if (!$stmt_pengaju->execute()) {
            $pengaju_name = "ERROR EXEC: " . $stmt_pengaju->error;
        } else {
            $result_pengaju = $stmt_pengaju->get_result();
            if ($row = $result_pengaju->fetch_assoc()) {
                $pengaju_name = htmlspecialchars($row['nama_lengkap']); 
                $pengaju_nip_nidn = htmlspecialchars($row['nip_nidn']); // AMBIL NIP/NIDN
            } else {
                $pengaju_name = "Data Pengaju (Role {$role_pengaju_id}, Dept. {$id_departemen_Realisasi}) Kosong";
            }
        }
        $stmt_pengaju->close();
    } else {
        $pengaju_name = "ERROR PREP: " . $conn->error;
    }
} else {
    $pengaju_name = "ERROR: ID Departemen Realisasi tidak tersedia.";
}


// --- 4b. Fetch PENYETUJU (Asumsi Role 3 - Direktur Keuangan) ---
// MENGAMBIL du.nama_lengkap DAN du.nip_nidn (dari detail_user)
$sql_penyetuju = "
    SELECT 
        du.nama_lengkap, du.nip_nidn
    FROM 
        detail_user du 
    JOIN 
        user u ON du.id_user = u.id_user
    WHERE 
        du.id_role = ?
    LIMIT 1
";

if ($stmt_penyetuju = $conn->prepare($sql_penyetuju)) {
    // Role ID yang digunakan di sini adalah $role_rektorat_id yang baru (3)
    if (!$stmt_penyetuju->bind_param("i", $role_rektorat_id)) { 
        $penyetuju_name = "ERROR BIND: " . $stmt_penyetuju->error;
    } else if (!$stmt_penyetuju->execute()) {
        $penyetuju_name = "ERROR EXEC: " . $stmt_penyetuju->error;
    } else {
        $result_penyetuju = $stmt_penyetuju->get_result();
        if ($row = $result_penyetuju->fetch_assoc()) {
            $penyetuju_name = htmlspecialchars($row['nama_lengkap']);
            $penyetuju_nip_nidn = htmlspecialchars($row['nip_nidn']); // AMBIL NIP/NIDN
        } else {
            $penyetuju_name = "Data Penyetuju (Role {$role_rektorat_id}) Kosong";
        }
    }
    $stmt_penyetuju->close();
} else {
    $penyetuju_name = "ERROR PREP: " . $conn->error;
}

// =========================================================================
// 5. FUNGSI UTILITY BARU (FORMAT ANGKA)
// =========================================================================

/**
 * Mengonversi angka menjadi format mata uang Rupiah (IDR) tanpa simbol 'Rp'.
 * @param float|int $number Angka yang akan diformat.
 * @return string
 */
function format_number_idr($number) {
    // Memformat angka dengan pemisah ribuan titik (.), dan tanpa desimal.
    return number_format($number, 0, ',', '.');
}

// =========================================================================
// 6. GENERASI OUTPUT HTML
// =========================================================================

// --- FUNGSI HELPER UNTUK FORMAT TANGGAL ---
function formatTanggalAcc($date_string) {
    if (empty($date_string) || $date_string === '0000-00-00' || strtotime($date_string) === false) {
        return '-';
    }
    return date('d F Y', strtotime($date_string));
}
// ------------------------------------------

ob_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Realisasi Disetujui - <?php echo htmlspecialchars($Realisasi_data['nomor_dokumen'] ?? 'N/A'); ?></title>
    <style>
        /* Gaya CSS untuk Dompdf (Disesuaikan untuk Ukuran A4 Portrait) */
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; /* Ukuran font standar A4 */
            margin: 0; 
            padding: 0;
        }
        .container {
            /* Kontainer disesuaikan untuk A4 */
            width: 100%;
            margin: 0; /* Margin standar A4 */
        }
        
        .header-container {
            overflow: hidden; 
            margin-bottom: 15px; 
            border-bottom: 2px solid #000; /* Border lebih tebal */
            padding-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .header-container img {
            width: 100px; /* Logo lebih besar untuk A4 */
            height: auto;
            float: left;
            margin-right: 15px;
        }
        .header-text {
            overflow: hidden; 
            text-align: left;
            margin-bottom: 10;
            padding-top: 15px;
        }
        .header-text h1 {
            font-size: 14pt; /* Ukuran dokumen standar */
            margin: 0;
            line-height: 1.1;
        }
        .header-text h2 {
            font-size: 12pt;
            margin: 2px 0 0 0;
            line-height: 1.1;
        }
        .header-text p {
            font-size: 9pt;
            margin: 3px 0 0 0;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px 5px;
            font-size: 9pt; /* Font info standar A4 */
        }
        table.detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.detail-table th, table.detail-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: left;
            font-size: 9pt; /* Font detail tabel standar A4 */
            vertical-align: top;
        }
        table.detail-table th {
            background-color: #f2f2f2;
            text-align: center;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .detail-row-info { font-size: 8pt; color: #555; margin-top: 2px;}
        .total-row td {
            font-weight: bold;
            background-color: #ddd;
        }
        
        .catatan {
            font-style: italic; 
            font-size: 10pt; 
            margin-top: 10px; 
            margin-bottom: 10px;
        }
        
        .rincian-title {
            font-size: 11pt; 
            margin-top: 15px; 
            margin-bottom: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .approval-section {
            margin-top: 30px; /* Jarak TTD lebih besar untuk A4 */
            width: 100%;
            border-collapse: collapse;
        }
        .approval-section td {
            width: 50%;
            text-align: center;
            padding-top: 40px; /* Ruang tanda tangan lebih besar */
            vertical-align: top;
            font-size: 9pt;
        }
        .ttd-name {
            border-bottom: 1px solid #000;
            display: inline-block;
            padding: 0 10px;
            margin-top: 40px; /* Jarak ke garis nama standar */
            font-weight: bold; 
        }
        .ttd-id-number {
            font-size: 8pt;
            margin-top: 3px;
            display: block;
        }
        .error-message {
            color: red;
            font-size: 8pt;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- --- HEADER DENGAN LOGO --- -->
        <div class="header-container">
            <?php if (!empty($logo_base64)): ?>
            <img src="<?php echo $logo_base64; ?>" 
                 width="100" 
                 alt="Logo Universitas">
            <?php else: ?>
             <img src="<?php echo htmlspecialchars($logo_url); ?>" 
                 width="100" 
                 alt="Logo Universitas - Fallback">
            <?php endif; ?>
            
            <div class="header-text">
                <h1>BUKTI REALISASI ANGGARAN PERIODIK (RAB)</h1>
                <h1><?php echo htmlspecialchars($Realisasi_data['nama_departemen'] ?? 'N/A'); ?></h1>
                <p>No. Dokumen <?php echo htmlspecialchars($Realisasi_data['nomor_dokumen'] ?? 'Nomor Dokumen Tidak Ada'); ?></p>
                
            </div>
        </div>
        <!-- --- AKHIR HEADER DENGAN LOGO --- -->

        <!-- Informasi Utama Realisasi -->
        <table class="info-table">
            <tr>
                <td width="25%"><strong>Tahun Anggaran</strong></td>
                <td width="3%">:</td>
                <td><?php echo htmlspecialchars($Realisasi_data['tahun_anggaran'] ?? date('Y')); ?></td>
                 <td width="25%"><strong>Diajukan Tanggal</strong></td>
                <td width="3%">:</td>
                <td><?php 
                    echo formatTanggalAcc($Realisasi_data['created_at'] ?? null); 
                ?></td>
            </tr>
            <tr>
                <td><strong>Total Realisasi</strong></td>
                <td>:</td>
                <!-- Menggunakan total_realisasi dari data utama -->
                <td><?php echo 'Rp ' . number_format($Realisasi_data['total_realisasi'] ?? 0, 0, ',', '.'); ?></td>
                <td><strong>Disetujui Tanggal</strong></td>
                <td width="3%">:</td>
                <td><?php 
                    // Tanggal Realisasi dari DB
                    echo formatTanggalAcc($Realisasi_data['tanggal_realisasi'] ?? null); 
                ?></td>
            </tr>
        </table>
        
        <h3 class="rincian-title">Rincian Anggaran Biaya (Realisasi Detail)</h3>
        
        <!-- Tabel Rincian -->
        <table class="detail-table">
            <thead>
                <tr class="info">
                    <th class="text-center" style="width: 5%;">No</th>
                    <th class="text-center" style="width: 15%;">Kode Akun</th>
                    <th style="width: 60%;">Penggunaan Realisasi (Uraian)</th>
                    <!-- Kolom terakhir menggunakan jumlah_realisasi dari tabel realisasi_detail -->
                    <th class="text-center" style="width: 20%;">Jumlah Realisasi (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                // Menggunakan variabel $details yang sudah diisi di Bagian 3
                if (empty($details)): ?>
                    <tr>
                        <td colspan="4" class="text-center">Tidak ada rincian Realisasi yang ditemukan.</td>
                    </tr>
                <?php else: 
                    foreach ($details as $detail):
                        // Data yang difetch: id_realisasi_detail, uraian_realisasi, jumlah_realisasi, volume, satuan, harga_satuan, kode_akun
                        $kode_akun = $detail['kode_akun'] ?? 'N/A';
                        $uraian_realisasi = $detail['uraian_realisasi'] ?? 'Uraian Kosong';
                        $jumlah_realisasi = $detail['jumlah_realisasi'] ?? 0;
                        $volume = $detail['volume'] ?? 0;
                        $satuan = $detail['satuan'] ?? '';
                        $harga_satuan = $detail['harga_satuan'] ?? 0;
                ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="text-center">
                        <!-- Menampilkan Kode Akun -->
                        <?php echo htmlspecialchars($kode_akun); ?>
                    </td>
                    <td>
                        <!-- Menampilkan uraian dari detail realisasi -->
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($uraian_realisasi); ?></div>
                        <!-- Tambahkan detail RAB sebagai konteks -->
                        <div class="detail-row-info">
                            RAB: <?php echo format_number_idr($volume); ?> <?php echo htmlspecialchars($satuan); ?> @ Rp <?php echo format_number_idr($harga_satuan); ?>
                        </div>
                    </td>
                    <!-- Menampilkan JUMLAH Realisasi (Uang) -->
                    <td class="text-right" style="font-weight: bold;">
                        Rp <?php echo format_number_idr($jumlah_realisasi); ?>
                    </td>
                </tr>
                <?php endforeach; 
                endif; ?>
            </tbody>
        </table>

        <p class="catatan">
            Catatan Direktur Keuangan: <br>
            <?php echo htmlspecialchars($Realisasi_data['catatan_keuangan'] ?? '-'); ?>
        </p>

        <!-- Bagian Tanda Tangan - DINAMIS -->
        <table class="approval-section">
            <tr>
                <td>Diajukan Oleh,<br><b><?php echo $pengaju_jabatan; ?></b></td>
                <td>Disetujui Final Oleh,<br><b><?php echo $penyetuju_jabatan; ?></b></td>
            </tr>
            <tr>
                <td>
                    <!-- Tampilkan Nama Lengkap Pengaju -->
                    <?php if (strpos($pengaju_name, 'ERROR') !== false || strpos($pengaju_name, 'Kosong') !== false): ?>
                        <span class="ttd-name error-message">
                            <?php echo $pengaju_name; ?> 
                            <br>(Cek Role 2 di Dept. <?php echo htmlspecialchars($Realisasi_data['id_departemen'] ?? 'N/A'); ?>)
                        </span>
                        <span class="ttd-id-number"></span>
                    <?php else: ?>
                        <span class="ttd-name"><?php echo $pengaju_name ?: '_________________________'; ?></span>
                        <!-- Tampilkan NIP/NIDN Pengaju -->
                        <span class="ttd-id-number">NIP : <?php echo $pengaju_nip_nidn ?: '-'; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- Tampilkan Nama Lengkap Penyetuju -->
                    <?php if (strpos($penyetuju_name, 'ERROR') !== false || strpos($penyetuju_name, 'Kosong') !== false): ?>
                        <span class="ttd-name error-message">
                            <?php echo $penyetuju_name; ?> 
                            <br>(Cek Role <?php echo $role_rektorat_id; ?>)
                        </span>
                        <span class="ttd-id-number"></span>
                    <?php else: ?>
                        <span class="ttd-name"><?php echo $penyetuju_name ?: '_________________________'; ?></span>
                        <!-- Tampilkan NIP/NIDN Penyetuju -->
                        <span class="ttd-id-number">NIP : <?php echo $penyetuju_nip_nidn ?: '-'; ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
    </div>
</body>
</html>

<?php
// =========================================================================
// 7. EKSEKUSI DOMPDF
// =========================================================================

// Tangkap konten HTML dari output buffer
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 
$options->set('ssl_verify_peer', false); 
$options->set('chroot', '/'); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// --- PERUBAHAN UTAMA: SET UKURAN KERTAS SEBAGAI A4 PORTRAIT ---
$dompdf->setPaper('A4', 'portrait'); 

$dompdf->render();

$filename = "Laporan_Realisasi_Disetujui_Final_" . $id_Realisasi . "_" . ($Realisasi_data['tanggal_realisasi'] ?? 'tanggal_kosong') . ".pdf";
$dompdf->stream($filename, array("Attachment" => false));

exit(0);