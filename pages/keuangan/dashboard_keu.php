<?php
// FILE: /efinance/pages/keuangan/dashboard_keu.php

global $conn;

// Ambil data user dari session
$nama_user = $_SESSION['nama'] ?? 'User';
$id_departemen = $_SESSION['id_departemen'] ?? 0;

// **********************************************
// BARIS PERBAIKAN: Ambil nama departemen
// **********************************************
$nama_departemen = get_nama_departemen($id_departemen);
// Pastikan nama departemen tidak null
$nama_departemen = $nama_departemen ?? 'Departemen Tidak Dikenal';

// Ambil data counts
$counts = get_approval_counts();
$rab_menunggu = $counts['rab_menunggu'];
$transaksi_menunggu = $counts['transaksi_menunggu'];
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-home"></span> Dashboard Direktur Keuangan
        </h1>
        <ol class="breadcrumb">
            <li class="active"><i class="fa fa-dashboard"></i> Selamat Datang, <b><?php echo htmlspecialchars($nama_user); ?></b> Anda login sebagai <b>Direktorat Keuangan</b></li>
        </ol>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-file-text fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge"><?= $rab_menunggu; ?></div>
                        <div>RAB Menunggu Persetujuan</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=rab_approval.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat Detail</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-money fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge"><?= $transaksi_menunggu; ?></div>
                        <div>Transaksi Menunggu Validasi</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=transaksi_validation.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat Detail</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="panel panel-success">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-check fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge">Rp 0</div>
                        <div>Total Anggaran Disetujui</div>
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <span class="pull-left">Data Statistik Global</span>
                <span class="pull-right"><i class="fa fa-area-chart"></i></span>
                <div class="clearfix"></div>
            </div>
        </div>
    </div>
</div>