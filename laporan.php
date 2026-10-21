<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION['level'], ['admin', 'petugas'])) {
    header("Location: dashboard.php");
    exit;
}

$tanggal_mulai = $_GET['mulai'] ?? '';
$tanggal_selesai = $_GET['selesai'] ?? '';

$where = "";

if ($tanggal_mulai != "" && $tanggal_selesai != "") {
    $mulai = mysqli_real_escape_string($koneksi, $tanggal_mulai);
    $selesai = mysqli_real_escape_string($koneksi, $tanggal_selesai);

    $where = "WHERE DATE(p.tanggal_pengajuan) BETWEEN '$mulai' AND '$selesai'";
}

// Catat aktivitas membuka laporan
$detail_log = 'Melihat laporan peminjaman';

if ($tanggal_mulai != "" && $tanggal_selesai != "") {
    $detail_log .= " periode $tanggal_mulai sampai $tanggal_selesai";
}

catatAktivitas($koneksi, $_SESSION['user_id'], 'Melihat laporan', $detail_log);

$query = mysqli_query($koneksi, "SELECT
    p.peminjaman_id,
    u.nama AS nama_peminjam,
    p.tanggal_pengajuan,
    p.tanggal_pinjam,
    p.tanggal_rencana_kembali,
    p.status AS status_peminjaman,
    p.keperluan,
    k.tanggal_kembali,
    k.kondisi_sesudah,
    k.status AS status_pengembalian,
    k.denda,
    GROUP_CONCAT(
        CONCAT(a.nama_alat, ' (', d.jumlah, ')')
        SEPARATOR ', '
    ) AS daftar_alat
    FROM peminjaman p
    JOIN user u ON p.user_id = u.user_id
    LEFT JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
    LEFT JOIN alat a ON d.alat_id = a.alat_id
    LEFT JOIN pengembalian k ON p.peminjaman_id = k.peminjaman_id
    $where GROUP BY p.peminjaman_id ORDER BY p.peminjaman_id DESC");

if (!$query) {
    die("Gagal mengambil laporan: " . mysqli_error($koneksi));
}

$total_data = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Peminjaman</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            body {
                background: white !important;
                font-size: 10px;
            }

            .no-print,
            .navbar {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
            }

            .main-content {
                padding: 0 !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .card-body {
                padding: 0 !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            .table {
                font-size: 9px;
                width: 100% !important;
            }

            .table th,
            .table td {
                padding: 5px !important;
                border: 1px solid #777 !important;
                white-space: normal !important;
            }

            .table thead th {
                background: #eee !important;
                color: black !important;
                text-transform: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            h2, h5, p {
                color: black !important;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark no-print">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Peminjaman Sarpras</a>

        <div class="d-flex align-items-center gap-3">
            <span class="text-white">
                <?= htmlspecialchars($_SESSION['nama']) ?>
            </span>

            <a href="logout.php" class="btn btn-outline-light btn-sm"
               onclick="return confirm('Yakin ingin keluar?')">
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="container main-content">

    <!-- JUDUL -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
        <div>
            <h2>Laporan Peminjaman</h2>
            <p class="page-subtitle mb-0">
                Lihat dan cetak data peminjaman serta pengembalian alat.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary no-print">
            Kembali ke Dashboard
        </a>
    </div>

    <!-- FILTER TANGGAL -->
    <div class="card mt-4 mb-4 no-print">
        <div class="card-header">Filter Laporan</div>

        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">

                <div class="col-md-4">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="mulai" class="form-control"
                           value="<?= htmlspecialchars($tanggal_mulai) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" name="selesai" class="form-control"
                           value="<?= htmlspecialchars($tanggal_selesai) ?>">
                </div>

                <div class="col-md-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        Tampilkan
                    </button>

                    <a href="laporan.php" class="btn btn-secondary">
                        Reset
                    </a>

                    <button type="button" onclick="window.print()"
                            class="btn btn-success">
                        Cetak Laporan
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- TABEL LAPORAN -->
    <div class="card">
        <div class="card-header">
            <span>Data Peminjaman dan Pengembalian</span>
        </div>

        <div class="card-body">

            <h5 class="text-center mb-2">
                LAPORAN PEMINJAMAN DAN PENGEMBALIAN ALAT
            </h5>

            <?php if ($tanggal_mulai != "" && $tanggal_selesai != "") { ?>
                <p class="text-center mb-4">
                    Periode:
                    <?= htmlspecialchars($tanggal_mulai) ?>
                    sampai
                    <?= htmlspecialchars($tanggal_selesai) ?>
                </p>
            <?php } else { ?>
                <p class="text-center mb-4">
                    Semua Periode
                </p>
            <?php } ?>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Peminjam</th>
                            <th>Daftar Alat</th>
                            <th>Tgl Pengajuan</th>
                            <th>Tgl Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Tgl Kembali</th>
                            <th>Status Pinjam</th>
                            <th>Status Kembali</th>
                            <th>Keperluan</th>
                            <th>Denda</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $no = 1;

                        while ($data = mysqli_fetch_assoc($query)) {
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <?= htmlspecialchars($data['nama_peminjam']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['daftar_alat'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['tanggal_pengajuan'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['tanggal_pinjam'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['tanggal_rencana_kembali'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['tanggal_kembali'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['status_peminjaman']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['status_pengembalian'] ?? 'Belum ada') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['keperluan'] ?? '-') ?>
                                </td>

                                <td>
                                    Rp <?= number_format((float)($data['denda'] ?? 0), 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php } ?>

                        <?php if ($total_data == 0) { ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    Tidak ada data laporan.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 mb-0">
                <strong>Jumlah data:</strong>
                <?= $total_data ?> peminjaman
            </p>

        </div>
    </div>

</div>

</body>
</html>
