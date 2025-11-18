<?php
// FILE: /efinance/pages/rektorat/dashboard_rekt.php

// Ambil data user dari session
$nama_user = $_SESSION['nama'] ?? 'User';
$id_departemen = $_SESSION['id_departemen'] ?? 0;

// **********************************************
// BARIS PERBAIKAN: Ambil nama departemen
// **********************************************
$nama_departemen = get_nama_departemen($id_departemen);
// Pastikan nama departemen tidak null
$nama_departemen = $nama_departemen ?? 'Departemen Tidak Dikenal';

// Pastikan fungsi count_pending_rab_rektorat() sudah ada di function.php
$pending_rab_count = count_pending_rab_rektorat();
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <i class="glyphicon glyphicon-home"></i> Dashboard Rektorat
        </h1>
        <ol class="breadcrumb">
            <li class="active"><i class="fa fa-dashboard"></i> Selamat Datang, <b><?php echo htmlspecialchars($nama_user); ?></b> Anda login sebagai <b>Rektorat</b></li>
        </ol>
    </div>
</div>

<div class="row">
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-red">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="glyphicon glyphicon-bell fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge"><?= number_format($pending_rab_count); ?></div>
                        <div>RAB Menunggu Finalisasi!</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=rab_approval_rekt.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat Detail Persetujuan</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <?php 
        // Anda mungkin perlu membuat fungsi ini nanti: count_approved_rab_final()
        $approved_rab_final = get_total_rab_by_status(5); // Misal Status Keuangan 5 = Disetujui Final
        ?>
        <div class="panel panel-green">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="glyphicon glyphicon-ok-sign fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge"><?= number_format($approved_rab_final); ?></div>
                        <div>Total RAB Disetujui (Final)</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=rab_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Lihat Semua RAB</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="panel panel-yellow">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="glyphicon glyphicon-stats fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge">Lihat</div>
                        <div>Laporan & Realisasi</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=laporan_rab.php">
                <div class="panel-footer">
                    <span class="pull-left">Akses Modul Laporan</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
</div>

<hr>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-time"></i> Aktivitas Terbaru</h3>
            </div>
            <div class="panel-body">
                <p>Tugas utama Rektorat adalah meninjau RAB yang telah disetujui oleh Direktur Keuangan.</p>
                <p>Silakan klik pada kartu merah di atas atau menu **Approval Rektor** untuk memulai peninjauan.</p>
            </div>
        </div>
    </div>
</div>