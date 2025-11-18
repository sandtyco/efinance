<?php 
// PASTIKAN: File ini di-include dari dashboard.php, sehingga variabel $conn (koneksi)
// dan $_SESSION sudah tersedia dan user dipastikan adalah SysAdmin.

// 1. Ambil data statistik dari database
// Hitung Total Pengguna Aktif
$query_user = mysqli_query($conn, "SELECT COUNT(id_user) AS total_user FROM user");
$data_user = mysqli_fetch_assoc($query_user);
$total_user = $data_user['total_user'];

// Hitung Total Departemen
$query_dept = mysqli_query($conn, "SELECT COUNT(id_departemen) AS total_dept FROM departemen");
$data_dept = mysqli_fetch_assoc($query_dept);
$total_dept = $data_dept['total_dept'];

// Hitung Total Role (Jika role bersifat statis, bisa langsung 4, tapi lebih baik diambil dari DB)
$query_role = mysqli_query($conn, "SELECT COUNT(id_role) AS total_role FROM role");
$data_role = mysqli_fetch_assoc($query_role);
$total_role = $data_role['total_role'];
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-home"></span> Dashboard SysAdmin
        </h1>
        <ol class="breadcrumb">
            <li class="active"><i class="fa fa-dashboard"></i> Selamat Datang <b><?php echo $_SESSION['nama']; ?></b>, Anda login sebagai Pusat Kontrol <b>SysAdmin</b></li>
        </ol>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center">
                        <span class="glyphicon glyphicon-user" style="font-size: 3em;"></span>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_user; ?></div>
                        <div>Total Pengguna Sistem</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=user_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Kelola Pengguna</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-success">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center">
                        <span class="glyphicon glyphicon-briefcase" style="font-size: 3em;"></span>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_dept; ?></div>
                        <div>Departemen Terdaftar</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=departemen_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Kelola Departemen</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3 text-center">
                        <span class="glyphicon glyphicon-tags" style="font-size: 3em;"></span>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_role; ?></div>
                        <div>Kategori Role</div>
                    </div>
                </div>
            </div>
            <a href="dashboard.php?page=role_list.php">
                <div class="panel-footer">
                    <span class="pull-left">Kelola Role</span>
                    <span class="pull-right"><i class="glyphicon glyphicon-circle-arrow-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-info-sign"></i> Panduan Singkat SysAdmin</h3>
            </div>
            <div class="panel-body">
                <p>Sebagai SysAdmin, fokus utama Anda adalah memastikan data master (**Pengguna**, **Departemen**, dan **Role**) akurat. Data ini menjadi fondasi bagi alur kerja RAB dan Transaksi keuangan.</p>
                <ul>
                    <li>Pastikan setiap pengguna memiliki **NIP/NIDN** dan **Role** yang benar.</li>
                    <li>Setiap **Departemen** yang mengajukan anggaran harus terdaftar.</li>
                </ul>
                
                <div class="alert alert-info">
                    Area ini akan menampilkan Log Aktivitas Pengguna atau Grafik Statistik Penggunaan Sistem.
                </div>
            </div>
        </div>
    </div>
</div>