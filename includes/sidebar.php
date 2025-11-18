<?php
// FILE: includes/sidebar.php
// Menu navigasi utama (sidebar) yang disesuaikan berdasarkan $_SESSION['role'] (String)

// --- MAPPING ROLE STRING ke ID (Untuk Logika Akses) ---
// Kita buat mapping ini di sidebar agar tidak perlu mengubah struktur sesi di login/dashboard.php
$role_map = [
    'SysAdmin'            => 1, // ID Role 1
    'Departemen'          => 2, // ID Role 2
    'Direktur Keuangan' => 3, // ID Role 3
    'Rektorat'            => 4, // ID Role 4
];

// 1. Cek Ketersediaan Role di Session dan Mapping
if (!isset($_SESSION['role']) || !isset($role_map[$_SESSION['role']])) {
    // Guard clause: Jika role tidak ada atau tidak terdaftar, tampilkan menu login ulang
    echo "<li><a href='logout.php'><i class='fa fa-sign-out fa-fw'></i> Silakan Login Ulang</a></li>";
    return;
}

// 2. Tentukan Role ID dan Halaman Saat Ini
$current_role_string = $_SESSION['role'];
$current_role_id = $role_map[$current_role_string]; // Ambil ID Role (1, 2, 3, atau 4)
$page = isset($_GET['page']) ? basename($_GET['page']) : ''; // Ambil hanya nama file (misal: rab_list.php)

// --- Fungsi untuk Cek Akses (ID Role: 1=SysAdmin, 2=Departemen, 3=Keuangan, 4=Rektorat) ---
function has_access($allowed_roles, $current_role_id) {
    return in_array($current_role_id, $allowed_roles);
}
// --- Fungsi untuk Cek Menu Aktif ---
function is_menu_active($current_page, $target_pages) {
    return in_array($current_page, $target_pages);
}
?>

<div class="collapse navbar-collapse navbar-ex1-collapse">
    <ul class="nav navbar-nav side-nav">
        <li class="<?php echo ($page == 'dashboard_admin.php' || $page == 'dashboard_dept.php' || $page == 'dashboard_keu.php' || $page == 'dashboard_rekt.php' || $page == '' ? 'active' : ''); ?>">
            <a href="dashboard.php"><i class="glyphicon glyphicon-home"></i> Dashboard Utama</a>
        </li>

        <?php 
        // Tambahkan rab_view.php di sini agar menu tetap aktif saat melihat detail approval
        $rab_pages = ['rab_add.php', 'rab_edit.php', 'rab_list.php', 'rab_approval.php', 'rab_view.php', 'rab_approval_rekt.php']; 
        $is_rab_active = is_menu_active($page, $rab_pages);
        if (has_access([2, 3, 4], $current_role_id)): // Departemen, Direktur Keuangan, Rektorat
        ?>
        <li class="<?php echo ($is_rab_active ? 'active' : ''); ?>">
            <a href="#" data-toggle="collapse" data-target="#rab-menu">
                <i class="glyphicon glyphicon-calendar"></i> Perencanaan (RAB) <b class="caret"></b>
            </a>
            <ul id="rab-menu" class="collapse <?php echo ($is_rab_active ? 'in' : ''); ?>">
                
                <?php if (has_access([2], $current_role_id)): // Departemen (pemohon) ?>
                <li class="<?php echo ($page == 'rab_add.php' ? 'active' : ''); ?>">
                    <a href="dashboard.php?page=rab_add.php"><i class="glyphicon glyphicon-edit"></i> Pengajuan RAB Baru</a>
                </li>
                <?php endif; ?>

                <?php if (has_access([2], $current_role_id)): // Departemen (List RAB) ?>
                <li class="<?php echo is_menu_active($page, ['rab_list.php', 'rab_edit.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=rab_list.php"><i class="glyphicon glyphicon-th-list"></i> List Status RAB</a>
                </li>
                <?php endif; ?>

                <?php if (has_access([3], $current_role_id)): // Direktur Keuangan (Approval Tahap 1) ?>
                <li class="<?php echo is_menu_active($page, ['rab_approval.php', 'rab_view.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=rab_approval.php"><i class="glyphicon glyphicon-ok"></i> Approval RAB (Keu)</a>
                </li>
                <?php endif; ?>

                <?php if (has_access([4], $current_role_id)): // Rektorat (Approval Tahap 2) ?>
                <li class="<?php echo is_menu_active($page, ['rab_approval_rekt.php', 'rab_view_rekt.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=rab_approval_rekt.php"><i class="glyphicon glyphicon-ok"></i> Approval RAB (Rekt)</a>
                </li>
                <?php endif; ?>

            </ul>
        </li>
        <?php endif; ?>

        <?php 
        // Tambahkan transaksi_detail.php ke array agar saat melihat detail transaksi, menu Realisasi tetap aktif
        $transaksi_pages = ['transaksi_list.php', 'transaksi_add.php', 'transaksi_edit.php', 'transaksi_validation.php', 'transaksi_detail.php', 'transaksi_approval.php'];
        $is_transaksi_active = is_menu_active($page, $transaksi_pages);
        if (has_access([2, 3, 4], $current_role_id)): // Departemen, Direktur Keuangan, Rektorat
        ?>
        <li class="<?php echo ($is_transaksi_active ? 'active' : ''); ?>">
            <a href="#" data-toggle="collapse" data-target="#transaksi-menu">
                <i class="glyphicon glyphicon-shopping-cart"></i> Realisasi Transaksi <b class="caret"></b>
            </a>
            <ul id="transaksi-menu" class="collapse <?php echo ($is_transaksi_active ? 'in' : ''); ?>">
                
                <?php if (has_access([2], $current_role_id)): // Departemen (Input Realisasi) ?>
                <li class="<?php echo is_menu_active($page, ['transaksi_list.php', 'transaksi_add.php', 'transaksi_edit.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=transaksi_list.php"><i class="glyphicon glyphicon-th-list"></i> List Transaksi</a>
                </li>
                <?php endif; ?>

                <?php if (has_access([3], $current_role_id)): // Keuangan (Validasi Realisasi Tahap 1) ?>
                <li class="<?php echo is_menu_active($page, ['transaksi_validation.php', 'transaksi_detail.php']) ? 'active' : ''; ?>">
                    <!-- PERBAIKAN: Menambahkan dashboard.php?page= di link -->
                    <a href="dashboard.php?page=transaksi_list.php"><i class="glyphicon glyphicon-ok"></i> Validasi Realisasi (Keu)</a>
                </li>
                <?php endif; ?>
                
                <?php if (has_access([4], $current_role_id)): // Rektorat (Validasi Realisasi Tahap 2) ?>
                <li class="<?php echo is_menu_active($page, ['transaksi_validation.php', 'transaksi_detail.php']) ? 'active' : ''; ?>">
                    <!-- PERBAIKAN: Mengganti nama file menjadi transaksi_approval.php (sesuai rencana kita) dan menambahkan link -->
                    <a href="dashboard.php?page=transaksi_list.php"><i class="glyphicon glyphicon-ok"></i> Approval Realisasi (Rekt)</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <?php 
        $laporan_pages = ['lap_rab.php', 'lap_transaksi.php'];
        $is_laporan_active = is_menu_active($page, $laporan_pages);
        if (has_access([1, 2, 3, 4], $current_role_id)): // Akses oleh SEMUA ROLE
        ?>
        <li class="<?php echo ($is_laporan_active ? 'active' : ''); ?>">
            <a href="#" data-toggle="collapse" data-target="#laporan-menu">
                <i class="glyphicon glyphicon-stats"></i> Laporan <b class="caret"></b>
            </a>
            <ul id="laporan-menu" class="collapse <?php echo ($is_laporan_active ? 'in' : ''); ?>">
                <li class="<?php echo ($page == 'lap_rab.php' ? 'active' : ''); ?>">
                    <a href="dashboard.php?page=lap_rab.php"><i class="glyphicon glyphicon-stats"></i> RAB Tahunan</a>
                </li>
                <li class="<?php echo ($page == 'lap_transaksi.php' ? 'active' : ''); ?>">
                    <a href="dashboard.php?page=lap_transaksi.php"><i class="glyphicon glyphicon-stats"></i> Periode Transaksi</a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php 
        $admin_pages = ['user_list.php', 'user_add.php', 'user_edit.php', 'role_list.php', 'departemen_list.php', 'departemen_add.php', 'departemen_edit.php', 'akun_list.php', 'akun_add.php', 'akun_edit.php', 'news_list.php', 'news_add.php', 'news_edit.php'];
        $is_admin_active = is_menu_active($page, $admin_pages);
        if (has_access([1, 3], $current_role_id)): // SysAdmin & Direktur Keuangan
        ?>
        <li class="<?php echo ($is_admin_active ? 'active' : ''); ?>">
            <a href="#" data-toggle="collapse" data-target="#admin-menu">
                <i class="glyphicon glyphicon-star"></i> Administrasi Sistem <b class="caret"></b>
            </a>
            <ul id="admin-menu" class="collapse <?php echo ($is_admin_active ? 'in' : ''); ?>">
                
                <?php if (has_access([1, 3], $current_role_id)): ?>
                <li class="<?php echo is_menu_active($page, ['news_list.php', 'news_add.php', 'news_edit.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=news_list.php"><i class="glyphicon glyphicon-comment"></i> Pengumuman</a>
                </li>
                <?php endif; ?>

                <?php if (has_access([1, 3], $current_role_id)): ?>
                <li class="<?php echo is_menu_active($page, ['akun_list.php', 'akun_add.php', 'akun_edit.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=akun_list.php"><i class="glyphicon glyphicon-list-alt"></i> Akun Anggaran</a>
                </li>
                <?php endif; ?>

                <?php if (has_access([1], $current_role_id)): ?>
                <li class="<?php echo is_menu_active($page, ['user_list.php', 'user_add.php', 'user_edit.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=user_list.php"><i class="glyphicon glyphicon-user"></i> Manajemen User</a>
                </li>
                <li class="<?php echo is_menu_active($page, ['departemen_list.php', 'departemen_add.php', 'departemen_edit.php']) ? 'active' : ''; ?>">
                    <a href="dashboard.php?page=departemen_list.php"><i class="glyphicon glyphicon-briefcase"></i> Data Departemen</a>
                </li>
                <li class="<?php echo ($page == 'role_list.php' ? 'active' : ''); ?>">
                    <a href="dashboard.php?page=role_list.php"><i class="glyphicon glyphicon-tag"></i> Role List</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <li>
            <a href="logout.php"><i class="glyphicon glyphicon-ban-circle"></i> Log Out</a>
        </li>
    </ul>
</div>