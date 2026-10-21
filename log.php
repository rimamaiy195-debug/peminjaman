
<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['level'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

// FILTER TANGGAL
$mulai = $_GET['mulai'] ?? '';
$selesai = $_GET['selesai'] ?? '';

$where = "";

if ($mulai != "" && $selesai != "") {
    $tanggal_mulai = mysqli_real_escape_string($koneksi, $mulai);
    $tanggal_selesai = mysqli_real_escape_string($koneksi, $selesai);

    $where = "WHERE DATE(l.waktu) BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'";
}

// AMBIL DATA LOG
$query = mysqli_query($koneksi, "SELECT
    l.*, u.nama, u.username
    FROM log_aktivitas l
    LEFT JOIN user u ON l.user_id = u.user_id
    $where
    ORDER BY l.log_id DESC");

if (!$query) {
    die("Gagal mengambil data log: " . mysqli_error($koneksi));
}

$total_data = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            Peminjaman Sarpras
        </a>

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

    <!-- JUDUL HALAMAN -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
        <div>
            <h2>Log Aktivitas</h2>
            <p class="page-subtitle mb-0">
                Pantau aktivitas pengguna dalam aplikasi.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

    <!-- FILTER TANGGAL -->
    <div class="card mt-4 mb-4">
        <div class="card-header">
            Filter Aktivitas
        </div>

        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">

                <div class="col-md-4">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="mulai" class="form-control"
                           value="<?= htmlspecialchars($mulai) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" name="selesai" class="form-control"
                           value="<?= htmlspecialchars($selesai) ?>">
                </div>

                <div class="col-md-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a href="log.php" class="btn btn-secondary">
                        Reset
                    </a>
                </div>

            </form>
        </div>
    </div>

    <!-- TABEL LOG -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Riwayat Aktivitas</span>

            <span class="badge bg-primary">
                <?= $total_data ?> data
            </span>
        </div>

        <div class="table-responsive">
            <input type="text" id="search" placeholder="🔍 Cari aktivitas..." onkeyup="searchTable()">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Pengguna</th>
                        <th>Aktivitas</th>
                        <th>Detail</th>
                        <th>Waktu</th>
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
                                <strong>
                                    <?= htmlspecialchars($data['nama'] ?? 'Pengguna dihapus') ?>
                                </strong>
                                <br>
                                <small class="text-secondary">
                                    @<?= htmlspecialchars($data['username'] ?? '-') ?>
                                </small>
                            </td>

                            <td>
                                <span class="badge bg-primary">
                                    <?= htmlspecialchars($data['aktivitas']) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($data['detail'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($data['waktu']) ?>
                            </td>
                        </tr>
                    <?php } ?>

                    <?php if ($total_data == 0) { ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="mb-2">
                                    <strong>Belum ada aktivitas.</strong>
                                </div>
                                <span class="text-secondary">
                                    Data aktivitas akan tampil jika sudah tercatat.
                                </span>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function searchTable() {
    let input = document
        .getElementById("search")
        .value
        .toLowerCase();
    let rows = document.querySelectorAll("tbody tr");
    rows.forEach(function(row) {
        let text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

</body>
</html>
