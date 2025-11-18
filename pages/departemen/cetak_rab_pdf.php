<?php
// FILE: pages/departemen/cetak_rab_pdf.php
// Skrip untuk menghasilkan laporan RAB yang disetujui dalam format PDF.
// Lokasi file ini diasumsikan berada di: pages/departemen/

// =========================================================================
// 1. INTEGRASI DOMPDF
// =========================================================================

// PATH DOMPDF
require_once '../../assets/vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// =========================================================================
// 2. FUNGSI LOGO BASE64 DAN SETUP KONEKSI
// =========================================================================

// URL gambar logo yang ingin disematkan
$logo_url = "../../assets/img/favicon.png";
$logo_base64 = '';

/**
 * Fungsi untuk mengambil gambar dari URL/Path dan mengonversinya menjadi Base64.
 */
function getLogoBase64($url) {
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

$id_rab = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_rab === 0) {
    die("ID RAB tidak valid.");
}

$rab_data = null;
$rab_details = [];

// =========================================================================
// 3. FETCH DATA RAB UTAMA (Ambil ID Departemen)
// =========================================================================
try {
    $sql_rab = "
        SELECT 
            r.judul, r.tahun_anggaran, r.total_anggaran, r.deskripsi,
            r.status_keuangan, r.tanggal_pengajuan, r.tanggal_persetujuan_rektorat, r.catatan_rektorat,
            d.nama_departemen, r.id_departemen /* Wajib diambil untuk mencari Pengaju */
        FROM 
            rab r
        JOIN 
            departemen d ON r.id_departemen = d.id_departemen
        WHERE 
            r.id_rab = ?
            AND r.status_keuangan = '5'
    ";

    if (!$stmt_rab = $conn->prepare($sql_rab)) {
        die("Error Prepare RAB Utama: " . $conn->error);
    }
    
    $stmt_rab->bind_param("i", $id_rab); 
    $stmt_rab->execute();
    $result_rab = $stmt_rab->get_result();

    if ($result_rab->num_rows > 0) {
        $rab_data = $result_rab->fetch_assoc();
    }
    $stmt_rab->close();
    
    if (!$rab_data) {
        die("Laporan RAB dengan ID $id_rab tidak ditemukan atau belum disetujui final (Status 5).");
    }
    
} catch (Exception $e) {
    die("Kesalahan saat mengambil data RAB: " . $e->getMessage());
}

// =========================================================================
// 4. FETCH DATA USER (Pengaju & Penyetuju) UNTUK TANDA TANGAN + NIP/NIDN
// =========================================================================

$role_pengaju_id = 2; // Kepala Departemen/Unit (Mengajukan)
$role_penyetuju_id = 4; // Rektor/Wakil Rektor (Penyetuju Final)

$pengaju_name = '';
$pengaju_nip_nidn = ''; // NEW: NIP/NIDN Pengaju
$pengaju_jabatan = 'Kepala Departemen/Unit'; 
$penyetuju_name = '';
$penyetuju_nip_nidn = ''; // NEW: NIP/NIDN Penyetuju
$penyetuju_jabatan = 'Rektor / Wakil Rektor'; 

// --- 4a. Fetch PENGJAJU (Role 2 Dibatasi oleh Departemen RAB) ---
if (isset($rab_data['id_departemen'])) {
    $id_departemen_rab = $rab_data['id_departemen'];
    
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
        if (!$stmt_pengaju->bind_param("ii", $role_pengaju_id, $id_departemen_rab)) {
             $pengaju_name = "ERROR BIND: " . $stmt_pengaju->error;
        } else if (!$stmt_pengaju->execute()) {
             $pengaju_name = "ERROR EXEC: " . $stmt_pengaju->error;
        } else {
            $result_pengaju = $stmt_pengaju->get_result();
            if ($row = $result_pengaju->fetch_assoc()) {
                $pengaju_name = htmlspecialchars($row['nama_lengkap']); 
                $pengaju_nip_nidn = htmlspecialchars($row['nip_nidn']); // AMBIL NIP/NIDN
            } else {
                $pengaju_name = "Data Pengaju (Role 2, Dept. {$id_departemen_rab}) Kosong";
            }
        }
        $stmt_pengaju->close();
    } else {
        $pengaju_name = "ERROR PREP: " . $conn->error;
    }
} else {
    $pengaju_name = "ERROR: ID Departemen RAB tidak tersedia.";
}


// --- 4b. Fetch PENYETUJU (Role 4 - Mutlak Rektorat) ---
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
    if (!$stmt_penyetuju->bind_param("i", $role_penyetuju_id)) {
        $penyetuju_name = "ERROR BIND: " . $stmt_penyetuju->error;
    } else if (!$stmt_penyetuju->execute()) {
        $penyetuju_name = "ERROR EXEC: " . $stmt_penyetuju->error;
    } else {
        $result_penyetuju = $stmt_penyetuju->get_result();
        if ($row = $result_penyetuju->fetch_assoc()) {
            $penyetuju_name = htmlspecialchars($row['nama_lengkap']);
            $penyetuju_nip_nidn = htmlspecialchars($row['nip_nidn']); // AMBIL NIP/NIDN
        } else {
            $penyetuju_name = "Data Penyetuju (Role 4) Kosong";
        }
    }
    $stmt_penyetuju->close();
} else {
    $penyetuju_name = "ERROR PREP: " . $conn->error;
}


// =========================================================================
// 5. FETCH DATA RAB DETAIL & AKUN
// =========================================================================
try {
    $sql_details = "
        SELECT 
            rd.uraian, rd.volume, rd.satuan, rd.harga_satuan, rd.subtotal, 
            a.kode_akun, a.nama_akun 
        FROM 
            rab_detail rd
        JOIN 
            akun a ON rd.id_akun = a.id_akun
        WHERE 
            rd.id_rab = ?
        ORDER BY 
            a.kode_akun ASC
    ";
    
    if (!$stmt_details = $conn->prepare($sql_details)) {
         die("Error Prepare RAB Detail: " . $conn->error);
    }
    
    $stmt_details->bind_param("i", $id_rab);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();

    while ($row = $result_details->fetch_assoc()) {
        $rab_details[] = $row;
    }
    $stmt_details->close();

} catch (Exception $e) {
    die("Kesalahan saat mengambil data RAB Detail: " . $e->getMessage());
}

// =========================================================================
// 6. GENERASI OUTPUT HTML
// =========================================================================

// --- FUNGSI HELPER UNTUK FORMAT TANGGAL ---
function formatTanggalAcc($date_string) {
    if (empty($date_string) || $date_string === '0000-00-00' || strtotime($date_string) === false) {
        return '-';
    }
    setlocale(LC_TIME, 'id_ID.utf8', 'id_ID', 'indonesian'); 
    return strftime('%d %B %Y', strtotime($date_string));
}
// ------------------------------------------

ob_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan RAB Disetujui - <?php echo htmlspecialchars($rab_data['judul']); ?></title>
    <style>
        /* Gaya CSS untuk Dompdf */
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; 
            margin: 0; 
            padding: 0;
        }
        .container {
            width: 95%; 
            margin: auto;
        }
        
        .header-container {
            overflow: hidden; 
            margin-bottom: 10px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        .header-container img {
            width: 100px; 
            height: auto;
            float: left;
            margin-right: 15px;
        }
        .header-text {
            overflow: hidden; 
            text-align: center;
            margin-bottom: 5px;
        }
        .header-text h1 {
            font-size: 15pt;
            margin: 0;
        }
        .header-text h2 {
            font-size: 13pt;
            margin: 5px 0 0 0;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 2px 4px;
            font-size: 10pt;
        }
        table.detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.detail-table th, table.detail-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
            font-size: 9pt; 
        }
        table.detail-table th {
            background-color: #f2f2f2;
            text-align: center;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td {
            font-weight: bold;
            background-color: #ddd;
        }
        .approval-section {
            margin-top: 50px;
            width: 100%;
            border-collapse: collapse;
        }
        .approval-section td {
            width: 50%;
            text-align: center;
            padding-top: 40px; 
            vertical-align: top;
        }
        .ttd-name {
            border-bottom: 1px solid #000;
            display: inline-block;
            padding: 0 20px;
            margin-top: 50px; 
            font-weight: bold; 
        }
        /* Style baru untuk NIP/NIDN */
        .ttd-id-number {
            font-size: 8pt;
            margin-top: 2px;
            display: block;
        }
        .error-message {
            color: red;
            font-size: 7pt;
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
                <h2>LAPORAN TAHUNAN</h2>
                <h1>RENCANA ANGGARAN BIAYA (RAB)</h1>
                <p>DIREKTORAT PERENCANAAN KEUANGAN UNIVERSITAS AMIKOM PURWOKERTO</P>
            </div>
        </div>
        <!-- --- AKHIR HEADER DENGAN LOGO --- -->

        <!-- Informasi Utama RAB -->
        <table class="info-table">
            <tr>
                <td width="20%"><strong>Program RAB</strong></td>
                <td width="3%">:</td>
                <td><?php echo htmlspecialchars($rab_data['judul']); ?></td>
            </tr>
            <tr>
                <td><strong>Departemen/Unit</strong></td>
                <td>:</td>
                <td><?php echo htmlspecialchars($rab_data['nama_departemen']); ?></td>
            </tr>
            <tr>
                <td><strong>Tahun Anggaran</strong></td>
                <td>:</td>
                <td><?php echo htmlspecialchars($rab_data['tahun_anggaran']); ?></td>
            </tr>
            <tr>
                <td><strong>Total Anggaran</strong></td>
                <td>:</td>
                <td><?php echo 'Rp ' . number_format($rab_data['total_anggaran'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td><strong>Diajukan Tanggal</strong></td>
                <td>:</td>
                <td><?php echo formatTanggalAcc($rab_data['tanggal_pengajuan']); ?></td>
            </tr>
            <tr>
                <td><strong>Disetujui Tanggal</strong></td>
                <td>:</td>
                <td><?php echo formatTanggalAcc($rab_data['tanggal_persetujuan_rektorat']); ?></td>
            </tr>
        </table>
        
        <p style="font-style: italic; font-size: 9pt; margin-top: 5px;">
            Catatan Rektorat: <?php echo htmlspecialchars($rab_data['catatan_rektorat'] ?: '-'); ?>
        </p>

        <h3 style="font-size: 12pt; margin-top: 20px;">Rincian Anggaran Biaya (RAB Detail)</h3>
        
        <!-- Tabel Rincian -->
        <table class="detail-table">
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="10%">Kode Akun</th>
                    <th width="30%">Uraian Kegiatan / Barang</th>
                    <th width="8%">Vol</th>
                    <th width="8%">Satuan</th>
                    <th width="20%">Harga Satuan (Rp)</th>
                    <th width="20%">Subtotal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $grand_total = 0;
                foreach ($rab_details as $detail): 
                    $grand_total += $detail['subtotal'];
                ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($detail['kode_akun']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($detail['uraian']); ?>
                        <br><small style="font-style: italic;">(Akun: <?php echo htmlspecialchars($detail['nama_akun']); ?>)</small>
                    </td>
                    <td class="text-center"><?php echo htmlspecialchars($detail['volume']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($detail['satuan']); ?></td>
                    <td class="text-right"><?php echo number_format($detail['harga_satuan'], 0, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="6" class="text-right"><strong>TOTAL ANGGARAN YANG DISETUJUI:</strong></td>
                    <td class="text-right"><strong>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></strong></td>
                </tr>
            </tfoot>
        </table>

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
                            <br>(Cek Role 2 di Dept. <?php echo htmlspecialchars($rab_data['id_departemen']); ?>)
                        </span>
                        <span class="ttd-id-number"></span>
                    <?php else: ?>
                        <span class="ttd-name"><?php echo $pengaju_name ?: '_________________________'; ?></span>
                        <!-- Tampilkan NIP/NIDN Pengaju -->
                        <span class="ttd-id-number">NIP : <?php echo $pengaju_nip_nidn ?: ''; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- Tampilkan Nama Lengkap Penyetuju -->
                    <?php if (strpos($penyetuju_name, 'ERROR') !== false || strpos($penyetuju_name, 'Kosong') !== false): ?>
                        <span class="ttd-name error-message">
                            <?php echo $penyetuju_name; ?> 
                            <br>(Cek Role 4)
                        </span>
                        <span class="ttd-id-number"></span>
                    <?php else: ?>
                        <span class="ttd-name"><?php echo $penyetuju_name ?: '_________________________'; ?></span>
                        <!-- Tampilkan NIP/NIDN Penyetuju -->
                        <span class="ttd-id-number">NIP : <?php echo $penyetuju_nip_nidn ?: ''; ?></span>
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
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Laporan_RAB_Disetujui_Final_" . $id_rab . "_" . $rab_data['tahun_anggaran'] . ".pdf";
$dompdf->stream($filename, array("Attachment" => false));

exit(0);