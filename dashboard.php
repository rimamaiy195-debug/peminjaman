
<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Catat aktivitas membuka dashboard
if (!isset($_SESSION['dashboard_dicatat'])) {
    catatAktivitas($koneksi, $_SESSION['user_id'], 'Membuka dashboard', 'Pengguna membuka halaman dashboard');
    $_SESSION['dashboard_dicatat'] = true;
}

$user = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM user"));
$alat = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM alat"));
$pinjam = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM peminjaman WHERE status = 'menunggu'"));
$kembali = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pengembalian WHERE status != 'dikembalikan'"));
$level = $_SESSION['level'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Peminjaman Alat</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <span class="me-2">▦</span> Peminjaman Alat
        </a>

        <div class="d-flex align-items-center gap-3">
            <div class="text-white text-end">
                <div class="fw-semibold">
                    <?= htmlspecialchars($_SESSION['nama']) ?>
                </div>
                <small class="opacity-75">
                    <?= htmlspecialchars(ucfirst($level)) ?>
                </small>
            </div>

            <a href="logout.php" class="btn btn-light btn-sm">
                Logout
            </a>
        </div>
    </div>
</nav>

<!-- KONTEN DASHBOARD -->
<div class="container main-content">

    <div class="mb-4">
        <p class="text-primary fw-semibold mb-2">HALO, SELAMAT DATANG!</p>

        <h2>Dashboard <?= htmlspecialchars(ucfirst($level)) ?></h2>

        <p class="page-subtitle">
            Kelola peminjaman sarana dan prasarana sekolah dengan mudah.
        </p>
    </div>

    <!-- KARTU STATISTIK -->
    <?php if ($level == 'admin' || $level == 'petugas') { ?>

    <div class="row g-3 mb-5">

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Pengguna</div>
                <div class="stat-number"><?= $user ?></div>
                <small class="text-secondary">Pengguna terdaftar</small>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">🖥️</div>
                <div class="stat-label">Total Data Alat</div>
                <div class="stat-number"><?= $alat ?></div>
                <small class="text-secondary">Jenis alat terdaftar</small>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-label">Menunggu Persetujuan</div>
                <div class="stat-number"><?= $pinjam ?></div>
                <small class="text-secondary">Pengajuan peminjaman</small>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">↩️</div>
                <div class="stat-label">Belum Dikembalikan</div>
                <div class="stat-number"><?= $kembali ?></div>
                <small class="text-secondary">Data pengembalian</small>
            </div>
        </div>

    </div>

    <?php } ?>

    <!-- MENU -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Menu Utama</h4>
            <p class="text-secondary mb-0">
                Pilih menu untuk mulai bekerja.
            </p>
        </div>
    </div>

    <div class="row g-3">

        <?php if ($level == 'admin') { ?>

        <div class="col-md-4 col-sm-6">
            <a href="user.php" class="menu-card">
                <div class="menu-icon">👥</div>
                <div>
                    <h6>Kelola Pengguna</h6>
                    <p>Atur akun dan hak akses pengguna.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="kategori.php" class="menu-card">
                <div class="menu-icon">🗂️</div>
                <div>
                    <h6>Kelola Kategori</h6>
                    <p>Atur kategori sarana sekolah.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="alat.php" class="menu-card">
                <div class="menu-icon">🖥️</div>
                <div>
                    <h6>Kelola Alat</h6>
                    <p>Atur data, stok, dan kondisi alat.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="log.php" class="menu-card">
                <div class="menu-icon">📜</div>
                <div>
                    <h6>Log Aktivitas</h6>
                    <p>Lihat catatan aktivitas sistem.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <?php } ?>

        <?php if ($level == 'admin' || $level == 'petugas') { ?>

        <div class="col-md-4 col-sm-6">
            <a href="peminjaman.php" class="menu-card">
                <div class="menu-icon">📋</div>
                <div>
                    <h6>Data Peminjaman</h6>
                    <p>Periksa dan kelola pengajuan alat.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="pengembalian.php" class="menu-card">
                <div class="menu-icon">↩️</div>
                <div>
                    <h6>Data Pengembalian</h6>
                    <p>Periksa kondisi alat yang dikembalikan.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="laporan.php" class="menu-card">
                <div class="menu-icon">📊</div>
                <div>
                    <h6>Laporan</h6>
                    <p>Lihat dan cetak laporan peminjaman.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <?php } ?>

        <?php if ($level == 'peminjam') { ?>

        <div class="col-md-4 col-sm-6">
            <a href="alat.php" class="menu-card">
                <div class="menu-icon">🖥️</div>
                <div>
                    <h6>Daftar Alat</h6>
                    <p>Lihat alat yang tersedia di sekolah.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="peminjaman.php" class="menu-card">
                <div class="menu-icon">📝</div>
                <div>
                    <h6>Ajukan Peminjaman</h6>
                    <p>Ajukan peminjaman alat sekolah.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <div class="col-md-4 col-sm-6">
            <a href="pengembalian.php" class="menu-card">
                <div class="menu-icon">📦</div>
                <div>
                    <h6>Pengembalian Alat</h6>
                    <p>Kelola pengembalian alat pinjaman.</p>
                </div>
                <span class="menu-arrow">›</span>
            </a>
        </div>

        <?php } ?>

    </div>

    <div class="text-center mt-5 text-secondary">
        <small>© <?= date('Y') ?> Sistem Peminjaman Sarana dan Prasarana Sekolah</small>
    </div>

</div>

</body>
</html>
