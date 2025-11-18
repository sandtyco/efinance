<?php
// PERBAIKAN PATH dan INCLUDE ONCE
include 'config/conn.php'; 
include_once 'function.php'; 

// Cek Hak Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SysAdmin') {
    redirect_to('dashboard.php'); 
}

// ----------------------------------------------------
// LOGIKA READ: Ambil semua data role dengan Jumlah Pengguna
// ----------------------------------------------------
$query_list = "
SELECT 
    r.id_role, 
    r.nama_role, 
    r.deskripsi, 
    COUNT(du.id_user) AS jumlah_pengguna
FROM 
    role r
LEFT JOIN 
    detail_user du ON r.id_role = du.id_role
GROUP BY 
    r.id_role, r.nama_role, r.deskripsi
ORDER BY 
    r.id_role ASC";

$result_list = mysqli_query($conn, $query_list);

// NOTE: Karena tidak menggunakan DataTables, kita tidak memerlukan global $footer_scripts;
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">
            <span class="glyphicon glyphicon-lock"></span> Manajemen Role
        </h1>
        <ol class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li class="active">Data Master Role</li>
        </ol>
    </div>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="glyphicon glyphicon-list"></i> Daftar Role Sistem</h3>
            </div>
            <div class="panel-body">
                
                <p>Data Role ini bersifat permanen, statis, dan tidak memerlukan fungsi CRUD.</p>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th class="text-center" width="5%">ID</th>
                                <th width="20%">Nama Role</th>
                                <th width="45%">Deskripsi</th>
                                <th class="text-center" width="15%">Jml. Pengguna</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($result_list) > 0):
                                while($data = mysqli_fetch_assoc($result_list)): 
                            ?>
                            <tr>
                                <td class="text-center"><?php echo htmlspecialchars($data['id_role']); ?>.</td>
                                <td><?php echo htmlspecialchars($data['nama_role']); ?></td>
                                <td><?php echo htmlspecialchars($data['deskripsi']); ?></td>
                                <td class="text-center">
                                    <span class="badge" style="font-size: 1.1em; padding: 5px 8px; background-color: #337ab7;">
                                        <?php echo htmlspecialchars($data['jumlah_pengguna']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="4" class="text-center">Belum ada data role yang terdaftar.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Semua kode DataTables dan penanaman $footer_scripts telah dihapus.
?>