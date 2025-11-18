-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 18, 2025 at 12:32 AM
-- Server version: 8.2.0
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `finance`
--

-- --------------------------------------------------------

--
-- Table structure for table `akun`
--

DROP TABLE IF EXISTS `akun`;
CREATE TABLE IF NOT EXISTS `akun` (
  `id_akun` int NOT NULL AUTO_INCREMENT,
  `kode_akun` varchar(20) NOT NULL COMMENT 'Nomor Akun, Contoh: 51101',
  `nama_akun` varchar(150) NOT NULL COMMENT 'Nama Akun, Contoh: Beban Gaji Pegawai',
  `tipe_akun` enum('Aset','Kewajiban','Ekuitas','Pendapatan','Beban') NOT NULL COMMENT 'Jenis utama Akun',
  `deskripsi` text COMMENT 'Penjelasan singkat tentang Akun',
  PRIMARY KEY (`id_akun`),
  UNIQUE KEY `kode_akun_unique` (`kode_akun`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `akun`
--

INSERT INTO `akun` (`id_akun`, `kode_akun`, `nama_akun`, `tipe_akun`, `deskripsi`) VALUES
(3, '21100', 'Hutang Usaha', 'Kewajiban', 'Kewajiban pembayaran kepada pemasok atau vendor atas pembelian barang/jasa secara kredit.'),
(4, '31100', 'Modal Awal', 'Ekuitas', 'Investasi awal pemilik atau lembaga dalam entitas.'),
(5, '41100', 'Pendapatan Jasa Pendidikan', 'Pendapatan', 'Pendapatan utama dari kegiatan inti lembaga (contoh: SPP).'),
(6, '51101', 'Beban Gaji Pegawai Tetap', 'Beban', 'Beban rutin terkait pembayaran gaji dan tunjangan karyawan tetap.'),
(7, '51105', 'Operasional Departemen', 'Kewajiban', 'Beban untuk kebutuhan sehari-hari kantor seperti ATK, air, listrik, dan internet.'),
(8, '52102', 'Dana Pengabdian Masyarakat', 'Kewajiban', 'Dana yang dikeluarkan lembaga untuk proses Pengabdian Masyarakat bagi pelaksana dosen'),
(9, '51123', 'Dana Penelitian', 'Kewajiban', 'Dana yang dikeluarkan untuk penelitian dosen.'),
(10, '51109', 'Operasional Kegiatan Departemen', 'Kewajiban', 'Biaya Operasional Departemen');

-- --------------------------------------------------------

--
-- Table structure for table `departemen`
--

DROP TABLE IF EXISTS `departemen`;
CREATE TABLE IF NOT EXISTS `departemen` (
  `id_departemen` int NOT NULL AUTO_INCREMENT,
  `nama_departemen` varchar(100) NOT NULL,
  `deskripsi` text,
  `kode_departemen` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_departemen`),
  UNIQUE KEY `nama_departemen` (`nama_departemen`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Daftar Departemen/Unit di Universitas';

--
-- Dumping data for table `departemen`
--

INSERT INTO `departemen` (`id_departemen`, `nama_departemen`, `deskripsi`, `kode_departemen`) VALUES
(1, 'Fakultas Ilmu Komputer', NULL, 'FIK'),
(2, 'Fakultas Bisnis dan Ilmu Sosial', NULL, 'FBIS'),
(3, 'Fakultas Ilmu Kesehatan', NULL, 'FIKES'),
(4, 'Direktorat Keuangan', NULL, 'BAU'),
(5, 'Kerumahtanggaan', NULL, 'RT'),
(6, 'Perpusatakaan', NULL, 'LIB'),
(8, 'Marketing', NULL, 'MAR'),
(9, 'Hubungan Masyarakat', NULL, 'HUMAS'),
(10, 'Bagian Administrasi Akademik', 'Bagian Administrasi Akademik merupakan bagian pengelolaan seperti Kepantiaan Ujian, Pengadaan Ijazah, Pencetakan Foto KTM dan sebagainya.', 'BAA'),
(11, 'Lembaga Penelitian Dan Pengabdian Masyarakat', NULL, 'LPPM'),
(12, 'Lembaga Pengembangan Pendidikan dan Penjaminan Mutu', NULL, 'LP3M'),
(13, 'Pusat Layanan Teknologi Informasi', NULL, 'PLTI'),
(14, 'Bagian Kepegawaian Dan Hukum', 'Bagian ini merupakan pengelolaan terkait Kepegawaian dan Hukum. Anggaran yang dikelola oleh bagian ini adalah, Rekrutmen, Pelatihan Pegawai, Perjalanan Dinas Pegawai, dan yang berkaitan dengan fasilitas pendampingan hukum.', 'BKH'),
(15, 'Sekretariat', NULL, 'SK'),
(16, 'Rektorat', NULL, 'RKT'),
(17, 'Direktorat Pasca Sarjana', 'Direktorat Pasca Sarjana membawahi anggaran yang dipergunakan untuk keperluan operasional Pascasarjana.', 'PASCA');

-- --------------------------------------------------------

--
-- Table structure for table `detail_user`
--

DROP TABLE IF EXISTS `detail_user`;
CREATE TABLE IF NOT EXISTS `detail_user` (
  `id_detail` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `nip_nidn` varchar(10) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `id_departemen` int NOT NULL,
  `id_role` int NOT NULL,
  `alamat` text,
  `telp` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_detail`),
  UNIQUE KEY `id_user` (`id_user`),
  UNIQUE KEY `nip_nidn` (`nip_nidn`),
  UNIQUE KEY `email` (`email`),
  KEY `id_departemen` (`id_departemen`),
  KEY `id_role` (`id_role`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tabel Detail Informasi Pribadi User';

--
-- Dumping data for table `detail_user`
--

INSERT INTO `detail_user` (`id_detail`, `id_user`, `nip_nidn`, `nama_lengkap`, `id_departemen`, `id_role`, `alamat`, `telp`, `email`, `foto`, `created_at`, `updated_at`) VALUES
(1, 1, '2025122001', 'Administrator', 13, 1, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111706', 'sandtyco@gmail.com', 'user_6912be8f51a38.jpg', '2025-11-09 20:31:37', '2025-11-12 01:52:24'),
(2, 2, '2017122129', 'Dr. Irfan Santiko, S.Kom., M.Kom.', 11, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111706', 'irfan.santiko@amikompurwokerto.ac.id', 'user_69128ba0ad44b.jpg', '2025-11-09 20:31:37', '2025-11-12 01:52:28'),
(3, 3, '3344556677', 'Catur Winarsih, S.Kom., M.M.', 4, 3, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111706', 'keuangan@amikompurwokerto.ac.id', 'catur.jpg', '2025-11-09 20:31:37', '2025-11-12 01:52:31'),
(4, 4, '2005091001', 'Dr. Berlilana, M.Kom., M.Si.', 3, 4, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111706', 'berli@amikompurwokerto.ac.id', 'berli.jpg', '2025-11-09 20:31:37', '2025-11-12 03:27:42'),
(5, 5, '2012091003', 'Prof. Dr. Eng. Ir. Imam Tahyudin, MM.', 1, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111708', 'imam.tahyudin@amikompurwokerto.ac.id', 'user_691282d65360a.png', '2025-11-11 00:27:02', '2025-11-12 01:52:45'),
(6, 6, '2009091002', 'Dr. Yusmedi Nurfaizal, S.Sos. MM.', 2, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111710', 'yusmedi@amikompurwokerto.ac.id', 'user_691286c584081.jpg', '2025-11-11 00:43:49', '2025-11-12 01:52:49'),
(7, 7, '2025012002', 'Uti Lestari, M.Kes', 3, 2, 'Jl Dr. Sutomo, No.43, Cilacap Selatan', '081227111713', 'uti@amikompurwokerto.ac.id', 'user_691288d68540d.png', '2025-11-11 00:52:38', '2025-11-11 00:52:38'),
(8, 8, '2007091002', 'Akto Hariawan, S.Kom.,  M.Si.', 9, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111715', 'akto@amikompurwokerto.ac.id', 'user_69129b60babaa.png', '2025-11-11 02:11:44', '2025-11-12 01:52:55'),
(9, 9, '2007091013', 'Agung Prasetyo, M.Kom', 14, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111555', 'agung.pras@amikompurwokerto.ac.id', 'user_69129bee3cd5e.png', '2025-11-11 02:14:06', '2025-11-12 01:52:59'),
(10, 10, '2020122999', 'Bagus Adhi Kusuma, ST, MT.', 5, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '081227111212', 'bagus_ak@amikompurwokerto.ac.id', 'user_69129c69e8c5c.png', '2025-11-11 02:16:10', '2025-11-12 01:53:03'),
(12, 12, '2012091032', 'Prof. Taqwa Hariguna, ST, M.Kom., Ph.D', 17, 2, 'Jl. Letjend Pol. Soemarto No.127, Watumas, Purwanegara, Kec. Purwokerto Utara, Kabupaten Banyumas, Jawa Tengah 53127', '0812773663556', 'taqwa@amikompurwokerto.ac.id', 'user_6912afecd03fe.png', '2025-11-11 03:39:24', '2025-11-12 01:53:06');

-- --------------------------------------------------------

--
-- Table structure for table `pengumuman`
--

DROP TABLE IF EXISTS `pengumuman`;
CREATE TABLE IF NOT EXISTS `pengumuman` (
  `id_pengumuman` int NOT NULL AUTO_INCREMENT,
  `judul` varchar(255) NOT NULL,
  `isi` text NOT NULL,
  `tgl_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `id_user_pembuat` int DEFAULT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL COMMENT 'Nama atau path relatif file lampiran (PDF, dll.)',
  PRIMARY KEY (`id_pengumuman`),
  KEY `id_user_pembuat` (`id_user_pembuat`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengumuman`
--

INSERT INTO `pengumuman` (`id_pengumuman`, `judul`, `isi`, `tgl_dibuat`, `id_user_pembuat`, `file_lampiran`) VALUES
(1, 'Batas Akhir Pengajuan RAB Triwulan I Tahun Anggaran 2026', 'Kepada seluruh Kepala Departemen dan unit kerja, diberitahukan bahwa batas akhir pengajuan Rencana Anggaran dan Biaya (RAB) untuk Triwulan I Tahun Anggaran 2026 adalah pada tanggal 30 Desember 2025. Mohon agar pengajuan dilakukan melalui sistem E-Finance Manajemen System ini dengan melengkapi semua dokumen pendukung yang diperlukan. Pengajuan yang melewati batas waktu tidak akan diproses.', '2025-11-11 15:10:35', 1, 'file1.pdf'),
(2, 'Sosialisasi Prosedur Pencairan Dana Anggaran Baru', 'Direktorat Keuangan akan mengadakan sosialisasi terkait perubahan prosedur dan mekanisme pencairan dana anggaran yang efektif berlaku mulai Januari 2026. Sosialisasi akan diadakan secara daring pada hari Jumat, 15 November 2025 pukul 09:00 WIB. Tautan Zoom/Meet akan dibagikan melalui email resmi masing-masing Kepala Unit. Kehadiran sangat diwajibkan.', '2025-11-11 15:10:35', 1, 'file2.pdf'),
(3, 'Pembaruan Akun Anggaran dan Kode COA', 'Telah dilakukan pembaruan pada daftar Akun Anggaran dan Kode Chart of Accounts (COA) di sistem E-Finance. Seluruh Departemen diwajibkan untuk menggunakan kode COA terbaru saat membuat Rencana Anggaran. Daftar kode terbaru dapat diakses melalui menu Master Data COA setelah login. Jika ada kendala, silakan hubungi tim SysAdmin.', '2025-11-11 15:10:35', 1, 'file2.pdf'),
(4, 'Pengumuman Libur Bersama Akhir Tahun 2025', 'Dalam rangka menyambut hari raya Natal dan Tahun Baru, diumumkan bahwa Kantor Universitas Amikom Purwokerto akan melaksanakan cuti bersama dari tanggal 24 Desember 2025 hingga 2 Januari 2026. Pelayanan keuangan akan ditutup sementara. Mohon proses administratif diselesaikan sebelum tanggal tersebut.', '2025-11-11 15:10:35', 1, 'file1.pdf'),
(5, 'Pencairan Tahap 2 Hibah Amikom 2025', 'Kepada seluruh pengusul Hibah Amikom Tahun Anggaran 2025, untuk pencairan tahap 2 sebesar 50% telah di transferkan ke rekening masing-masing pengusul. Jika ada ketidaksesuaian nominal, silahkan dapat menghubungi bagian keuangan.', '2025-11-11 16:49:24', 1, 'file2.pdf'),
(6, 'Batas Akhir Pengajuan RAB Triwulan I Tahun Anggaran 2030', 'Kepada seluruh Kepala Departemen dan unit kerja, diberitahukan bahwa batas akhir pengajuan Rencana Anggaran dan Biaya (RAB) untuk Triwulan I Tahun Anggaran 2026 adalah pada tanggal 30 Desember 2025. Mohon agar pengajuan dilakukan melalui sistem E-Finance Manajemen System ini dengan melengkapi semua dokumen pendukung yang diperlukan. Pengajuan yang melewati batas waktu tidak akan diproses.', '2025-11-11 20:20:41', 1, 'file2.pdf'),
(7, 'Sosialisasi Prosedur Pencairan Dana Anggaran Baru', 'Direktorat Keuangan akan mengadakan sosialisasi terkait perubahan prosedur dan mekanisme pencairan dana anggaran yang efektif berlaku mulai Januari 2026. Sosialisasi akan diadakan secara daring pada hari Jumat, 15 November 2025 pukul 09:00 WIB. Tautan Zoom/Meet akan dibagikan melalui email resmi masing-masing Kepala Unit. Kehadiran sangat diwajibkan.', '2025-11-11 17:11:29', 1, 'file2.pdf'),
(8, 'Peraturan Proses Pengajuan Realisasi Anggaran Departemen', 'Sesuai peraturan pada Surat Edaran Direktorat Keuangan No.001/AMIKOMPWT/DPKKU/X/2025 yang menjelaskan bahwa mekanisme proses pengajuan dan pencairan realisasi anggaran departemen.', '2025-11-16 07:20:20', 1, 'file1.pdf'),
(9, 'Pengumuman Libur Bersama Akhir Tahun 2025', 'Dalam rangka menyambut hari raya Natal dan Tahun Baru, diumumkan bahwa Kantor Universitas Amikom Purwokerto akan melaksanakan cuti bersama dari tanggal 24 Desember 2025 hingga 2 Januari 2026. Pelayanan keuangan akan ditutup sementara. Mohon proses administratif diselesaikan sebelum tanggal tersebut.', '2025-11-11 17:11:29', 1, 'file2.pdf'),
(10, 'Pelaporan Realisasi Anggaran Triwulan IV 2025 Wajib Tepat Waktu', 'Semua unit kerja diinstruksikan untuk segera menyelesaikan dan mengajukan laporan realisasi anggaran Triwulan IV 2025. Batas waktu pelaporan adalah 10 Januari 2026. Data realisasi yang tidak masuk akan mempengaruhi alokasi anggaran unit di tahun berikutnya.', '2025-11-11 17:11:29', 1, 'file1.pdf'),
(11, 'Workshop Penggunaan Modul Aset Tetap E-Finance', 'Untuk meningkatkan efisiensi pencatatan aset, Direktorat Aset mengadakan workshop Modul Aset Tetap pada hari Rabu, 18 Desember 2025. Peserta wajib mendaftar melalui laman internal IT. Kuota terbatas.', '2025-11-11 17:11:29', 1, 'file2.pdf'),
(12, 'Pemberitahuan Audit Internal Keuangan Periodik', 'Akan dilaksanakan audit internal keuangan periodik mulai tanggal 5 hingga 10 Januari 2026. Mohon kepada seluruh Departemen untuk menyiapkan dokumen keuangan yang relevan. Kerjasama dan transparansi sangat kami harapkan.', '2025-11-11 17:11:29', 1, 'file1.pdf'),
(13, 'Kenaikan Standar Biaya Perjalanan Dinas Mulai Tahun 2026', 'Diberitahukan bahwa telah terjadi penyesuaian dan kenaikan pada standar biaya perjalanan dinas (SPPD) untuk semua level pegawai, yang akan efektif berlaku pada 1 Januari 2026. Rincian tabel standar biaya terbaru dapat dilihat di intranet.', '2025-11-11 17:11:29', 1, 'file2.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `rab`
--

DROP TABLE IF EXISTS `rab`;
CREATE TABLE IF NOT EXISTS `rab` (
  `id_rab` int NOT NULL AUTO_INCREMENT,
  `id_departemen` varchar(50) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `total_anggaran` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tahun_anggaran` varchar(4) NOT NULL,
  `deskripsi` text,
  `status_keuangan` tinyint(1) NOT NULL DEFAULT '0',
  `status_rektorat` tinyint(1) NOT NULL DEFAULT '0',
  `tanggal_pengajuan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tanggal_persetujuan_keuangan` datetime DEFAULT NULL,
  `catatan_keuangan` text,
  `tanggal_persetujuan_rektorat` datetime DEFAULT NULL,
  `catatan_rektorat` text,
  PRIMARY KEY (`id_rab`),
  KEY `idx_departemen` (`id_departemen`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rab`
--

INSERT INTO `rab` (`id_rab`, `id_departemen`, `judul`, `total_anggaran`, `tahun_anggaran`, `deskripsi`, `status_keuangan`, `status_rektorat`, `tanggal_pengajuan`, `tanggal_persetujuan_keuangan`, `catatan_keuangan`, `tanggal_persetujuan_rektorat`, `catatan_rektorat`) VALUES
(2, '11', 'RAB LPPM Tahun 2025', 179999980.00, '2025', 'RAB LPPM Tahun 2025', 5, 1, '2025-11-14 00:53:49', '2025-11-15 02:22:44', 'Dilanjutkan ke persetujuan Rektor', '2025-11-15 03:44:14', NULL),
(4, '11', 'RAB LPPM Tahun 2026', 52500000.00, '2026', 'RAB LPPM Tahun 2026', 5, 1, '2025-11-14 01:10:45', '2025-11-17 10:00:50', '', '2025-11-17 10:00:59', NULL),
(5, '11', 'RAB LPPM Tahun 2024', 62000000.00, '2024', 'RAB LPPM Tahun 2024', 5, 1, '2025-11-17 02:59:35', '2025-11-17 09:59:58', '', '2025-11-17 10:00:17', ''),
(6, '11', 'RAB LPPM Tahun 2023', 322500000.00, '2025', 'RAB LPPM Tahun 2023', 5, 1, '2025-11-14 18:40:37', '2025-11-17 04:29:35', 'Sesuai ajuan dan rencana tahunan', '2025-11-17 04:29:44', NULL),
(7, '1', 'Operasional Kegiatan Prodi', 250000000.00, '2025', 'Anggaran operasional kegiatan prodi', 5, 1, '2025-11-15 03:33:58', '2025-11-17 02:08:22', '', '2025-11-17 02:08:39', NULL),
(8, '1', 'RAB FIK Tahun 2024', 450000000.00, '2024', 'Anggaran Operasional Fakultas Ilmu Komputer', 5, 1, '2025-11-15 03:36:45', '2025-11-17 00:54:57', '', '2025-11-17 03:28:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rab_detail`
--

DROP TABLE IF EXISTS `rab_detail`;
CREATE TABLE IF NOT EXISTS `rab_detail` (
  `id_rab_detail` int NOT NULL AUTO_INCREMENT,
  `id_rab` int NOT NULL,
  `id_akun` int NOT NULL,
  `uraian` varchar(255) NOT NULL,
  `volume` int NOT NULL DEFAULT '1',
  `satuan` varchar(50) NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `volume_terpakai_kumulatif` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total volume (QTY) dari Realisasi yang telah menggunakan RAB ini.',
  `biaya_terpakai_kumulatif` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total biaya (Rupiah) dari Realisasi yang telah menggunakan RAB ini.',
  PRIMARY KEY (`id_rab_detail`),
  KEY `fk_rab_header` (`id_rab`),
  KEY `fk_rab_akun` (`id_akun`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rab_detail`
--

INSERT INTO `rab_detail` (`id_rab_detail`, `id_rab`, `id_akun`, `uraian`, `volume`, `satuan`, `harga_satuan`, `subtotal`, `volume_terpakai_kumulatif`, `biaya_terpakai_kumulatif`) VALUES
(11, 2, 9, 'Anggaran hibah penelitian skema PDMA', 20, 'org', 4000000.00, 80000000.00, 0.00, 0.00),
(12, 2, 9, 'Anggaran hibah penelitian skema PKA', 10, 'org', 9999998.00, 99999980.00, 0.00, 0.00),
(15, 6, 9, 'Hibah Amikom PDMA', 30, 'org', 4000000.00, 120000000.00, 0.00, 0.00),
(16, 6, 8, 'Hibah Amikom AMM', 30, 'org', 3000000.00, 90000000.00, 0.00, 0.00),
(17, 6, 8, 'Hibah Amikom AMMKI', 15, 'org', 7500000.00, 112500000.00, 0.00, 0.00),
(29, 8, 4, 'Modal Kegiatan Tahunan', 1, 'fk', 100000000.00, 100000000.00, 0.00, 0.00),
(30, 8, 10, 'Anggaran operasional kegiatan program studi', 3, 'ps', 50000000.00, 150000000.00, 0.00, 0.00),
(31, 8, 7, 'Anggaran Tahunan Fakultas Ilmu Komputer', 1, 'fk', 200000000.00, 200000000.00, 0.00, 0.00),
(32, 7, 10, 'Operasional kegiatan program studi', 3, 'ps', 50000000.00, 150000000.00, 0.00, 0.00),
(33, 7, 7, 'Operasional Fakultas Ilmu Komputer', 1, 'fk', 100000000.00, 100000000.00, 0.00, 0.00),
(34, 5, 7, 'Honor Panitia ICITISEE', 40, 'org', 1000000.00, 40000000.00, 0.00, 0.00),
(35, 5, 6, 'Gaji Karyawan', 4, 'org', 5000000.00, 20000000.00, 0.00, 0.00),
(36, 5, 3, 'Pajak honor', 40, 'org', 50000.00, 2000000.00, 0.00, 0.00),
(37, 4, 8, 'Anggaran hibah pengabdian skema AMM', 5, 'org', 3000000.00, 15000000.00, 0.00, 0.00),
(38, 4, 8, 'Anggaran hibah pengabdian skema AMMKI', 5, 'org', 7500000.00, 37500000.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `realisasi`
--

DROP TABLE IF EXISTS `realisasi`;
CREATE TABLE IF NOT EXISTS `realisasi` (
  `id_realisasi` int NOT NULL AUTO_INCREMENT,
  `id_rab` int NOT NULL,
  `id_departemen` int NOT NULL,
  `tanggal_realisasi` date NOT NULL,
  `nomor_dokumen` varchar(50) NOT NULL,
  `deskripsi` text NOT NULL,
  `total_realisasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` int NOT NULL DEFAULT '1' COMMENT '0=Draft, 1=Diajukan, 2=Disetujui Keuangan, 3=Disetujui Rektorat, 4=Ditolak',
  `catatan_keuangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `id_validator_keuangan` int DEFAULT NULL,
  `id_validator_rektorat` int DEFAULT NULL,
  PRIMARY KEY (`id_realisasi`),
  KEY `id_rab` (`id_rab`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `realisasi`
--

INSERT INTO `realisasi` (`id_realisasi`, `id_rab`, `id_departemen`, `tanggal_realisasi`, `nomor_dokumen`, `deskripsi`, `total_realisasi`, `status`, `catatan_keuangan`, `created_at`, `updated_at`, `created_by`, `id_validator_keuangan`, `id_validator_rektorat`) VALUES
(1, 2, 11, '2025-12-02', '001/LPPM/AMIKOMPWT/XII/2025', '0', 35000000.00, 3, 'Sudah memenuhi persyaratan', '2025-11-15 05:23:36', '2025-11-15 23:38:05', 2, NULL, NULL),
(6, 7, 1, '2025-11-17', '007/FIK/AMIKOMPWT/XII/2025', '0', 23000000.00, 3, 'Sudah memenuhi persyaratan pengajuan anggaran', '2025-10-28 19:12:22', NULL, 5, NULL, NULL),
(2, 2, 11, '2025-11-20', '015/LPPM/AMIKOMPWT/XI/2025', '0', 90000000.00, 3, 'Sudah memenuhi persyaratan', '2025-11-15 12:00:07', NULL, 2, NULL, NULL),
(3, 2, 11, '2025-11-30', '022/LPPM/AMIKOMPWT/XI/2025', '0', 10500000.00, 3, 'sudah sesuai persyaratan', '2025-11-15 12:10:22', '2025-11-17 00:05:22', 0, NULL, NULL),
(4, 2, 11, '2025-11-27', '007/LPPM/AMIKOMPWT/XII/2025', '0', 5000000.00, 3, 'sudah sesuai persyaratan', '2025-11-16 08:23:45', '2025-11-17 00:04:59', 2, NULL, NULL),
(5, 2, 11, '2025-11-17', '023/LPPM/AMIKOMPWT/XI/2025', '0', 15000000.00, 1, 'Tidak sesuai pos akun, berikan lampiran ijin rektor.', '2025-11-16 19:06:31', '2025-11-17 10:13:59', 2, NULL, NULL),
(7, 7, 1, '2025-11-17', '011/FIK/AMIKOMPWT/XII/2025', '0', 87000000.00, 2, '', '2025-11-16 19:13:28', NULL, 5, NULL, NULL),
(8, 7, 1, '2025-11-17', '024/FIK/AMIKOMPWT/XI/2025', '0', 10500000.00, 1, NULL, '2025-11-16 19:20:34', NULL, 5, NULL, NULL),
(9, 7, 1, '2025-11-17', '001/FIK/AMIKOMPWT/XII/2025', '0', 11420000.00, 1, NULL, '2025-11-16 19:24:15', NULL, 5, NULL, NULL),
(10, 7, 1, '2025-11-17', '007/FIK/AMIKOMPWT/XII/2025', '0', 5000000.00, 1, NULL, '2025-11-16 19:29:28', NULL, 5, NULL, NULL),
(11, 8, 1, '2025-11-17', '016/FIK/AMIKOMPWT/XII/2025', '0', 45.00, 3, 'coba lg', '2025-11-17 04:05:35', NULL, 5, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `realisasi_detail`
--

DROP TABLE IF EXISTS `realisasi_detail`;
CREATE TABLE IF NOT EXISTS `realisasi_detail` (
  `id_realisasi_detail` int NOT NULL AUTO_INCREMENT,
  `id_realisasi` int NOT NULL,
  `id_rab_detail` int NOT NULL,
  `uraian` varchar(255) NOT NULL,
  `jumlah_realisasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_realisasi_detail`),
  KEY `id_realisasi` (`id_realisasi`),
  KEY `id_rab_detail` (`id_rab_detail`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `realisasi_detail`
--

INSERT INTO `realisasi_detail` (`id_realisasi_detail`, `id_realisasi`, `id_rab_detail`, `uraian`, `jumlah_realisasi`, `created_at`) VALUES
(8, 1, 0, 'Anggaran hibah penelitian skema PKA', 20000000.00, '2025-11-15 16:38:05'),
(7, 1, 0, 'Anggaran hibah penelitian skema PDMA', 15000000.00, '2025-11-15 16:38:05'),
(3, 2, 11, 'Anggaran hibah penelitian skema PDMA', 40000000.00, '2025-11-15 12:00:07'),
(4, 2, 12, 'Anggaran hibah penelitian skema PKA', 50000000.00, '2025-11-15 12:00:07'),
(23, 3, 0, 'Anggaran hibah penelitian skema PKA', 4500000.00, '2025-11-16 17:05:22'),
(22, 3, 0, 'Anggaran hibah penelitian skema PDMA', 6000000.00, '2025-11-16 17:05:22'),
(21, 4, 0, 'Anggaran hibah penelitian skema PKA', 3000000.00, '2025-11-16 17:04:59'),
(20, 4, 0, 'Anggaran hibah penelitian skema PDMA', 2000000.00, '2025-11-16 17:04:59'),
(37, 5, 0, 'Anggaran hibah penelitian skema PKA', 8000000.00, '2025-11-17 03:13:59'),
(36, 5, 0, 'Anggaran hibah penelitian skema PDMA', 7000000.00, '2025-11-17 03:13:59'),
(26, 6, 33, 'Honor Panitia Ujian', 8000000.00, '2025-11-16 19:12:22'),
(27, 6, 32, 'Workshop MBKM', 15000000.00, '2025-11-16 19:12:22'),
(28, 7, 33, 'Wisuda Sarjana', 80000000.00, '2025-11-16 19:13:28'),
(29, 7, 32, 'Workshop Penulisan Skripsi', 7000000.00, '2025-11-16 19:13:28'),
(30, 8, 33, 'Operasional Fakultas Ilmu Komputer', 7500000.00, '2025-11-16 19:20:34'),
(31, 8, 32, 'Operasional kegiatan program studi', 3000000.00, '2025-11-16 19:20:34'),
(32, 9, 33, 'Operasional Fakultas Ilmu Komputer', 6750000.00, '2025-11-16 19:24:15'),
(33, 9, 32, 'Operasional kegiatan program studi', 4670000.00, '2025-11-16 19:24:15'),
(34, 10, 33, 'Operasional Fakultas Ilmu Komputer', 3000000.00, '2025-11-16 19:29:28'),
(35, 10, 32, 'Operasional kegiatan program studi', 2000000.00, '2025-11-16 19:29:28'),
(38, 11, 29, 'Modal Kegiatan Tahunan', 10.00, '2025-11-17 04:05:35'),
(39, 11, 31, 'Anggaran Tahunan Fakultas Ilmu Komputer', 23.00, '2025-11-17 04:05:35'),
(40, 11, 30, 'Anggaran operasional kegiatan program studi', 12.00, '2025-11-17 04:05:35');

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
CREATE TABLE IF NOT EXISTS `role` (
  `id_role` int NOT NULL AUTO_INCREMENT,
  `nama_role` varchar(50) NOT NULL,
  `deskripsi` text NOT NULL,
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `nama_role` (`nama_role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Daftar Kategori Role Pengguna';

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`id_role`, `nama_role`, `deskripsi`) VALUES
(1, 'SysAdmin', 'Sys Admin merupakan petugas yang berfungsi sebagai pengelola system ini. Tugasnya adalah untuk mengelola secara teknis dan mengelola pengguna yang ada dalam system E-Finance ini.'),
(2, 'Departemen', 'Pengguna dengan level Departemen merupakan pengguna yang dapat melakukan tugasnya yaitu (1) Merancang RAB, (2) Mengajukan RAB, (3) Melakukan transaksi realisasi penggunaan RAB, dan (4) Melaporkan hasil realisasi RAB.'),
(3, 'Direktur Keuangan', 'Pengguna dengan level Direktur Keuangan merupakan pengguna yang dapat melakukan tugasnya yaitu, (1) Melakukan koreksi terhadap ajuan RAB dari Departemen, (2) Melakukan Approval tahap pertama sebelum di setujui Rektorat, (3) Memberikan persetujuan atas transaksi yang diajukan oleh Departemen. dan (4) Memberikan saran dan arahan kepada Departemen dalam hal Pengajuan.'),
(4, 'Rektorat', 'Pengguna dengan level Rektorat merupakan pengguna yang bertugas untuk melakukan Persetujuan tahap 2 yang merupakan Keputusan Akhir atas RAB yang diajukan, dan dilaporkan.');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL COMMENT 'Digunakan untuk Login (misal: NIP/NIDN)',
  `password` varchar(255) NOT NULL COMMENT 'Password yang sudah di-hash',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tabel Master Login User';

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `username`, `password`) VALUES
(1, '2025122001', '$2y$10$LJC2nM4vWG7qUwrJ4YDOHukBFyIgss/1.gDaA2uQwbsN/KDdlhKry'),
(2, '2017122129', '$2y$10$XeNfulCkuKEaU8AwoVkBe.nTMQK/3jZw2N/CV9wt5FEetYkqdKQGG'),
(3, '3344556677', '$2y$10$XeNfulCkuKEaU8AwoVkBe.nTMQK/3jZw2N/CV9wt5FEetYkqdKQGG'),
(4, '2005091001', '$2y$10$XeNfulCkuKEaU8AwoVkBe.nTMQK/3jZw2N/CV9wt5FEetYkqdKQGG'),
(5, '2012091003', '$2y$10$E3zq5bobBbJwLYm9MBuwcuvTr2S6RP5zwJ9PRbFSr/.JbMt4K/CMi'),
(6, '2009091002', '$2y$10$iq6epkKDYrn2hFS5BEYJLOzS6YErXSCrEP0AnJK0HyYPZrnjd0qX.'),
(7, '2025012002', '$2y$10$Z7I9OHIXDI7PbxAhIu1S4OCy5NCMYX.5JPVb12aD5.TR1XJIL/F86'),
(8, '2007091002', '$2y$10$Yh9cDvAeyilrh9hMOvGxfe76xzFHuyc689zQ0r1knzuBvotCBTZhy'),
(9, '2007091013', '$2y$10$bmr4hfpgsoYtRKko0zis6eqm41NCzWPO.9HJvSN2FEwGqnz4HLfkW'),
(10, '2020122999', '$2y$10$YaBzm/43UUgUgVMYviX3cOU5lIYZKadNkeb/5G6DjW7ZCwOh5bjGe'),
(12, '2012091032', '$2y$10$M15QVVGZ8Rd5kpAToOyBQ.3opSOoAWNq8kBqakB5A9v88swqlXRqG');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_user`
--
ALTER TABLE `detail_user`
  ADD CONSTRAINT `detail_user_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_user_ibfk_2` FOREIGN KEY (`id_departemen`) REFERENCES `departemen` (`id_departemen`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_user_ibfk_3` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `rab_detail`
--
ALTER TABLE `rab_detail`
  ADD CONSTRAINT `fk_rab_akun` FOREIGN KEY (`id_akun`) REFERENCES `akun` (`id_akun`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rab_header` FOREIGN KEY (`id_rab`) REFERENCES `rab` (`id_rab`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
