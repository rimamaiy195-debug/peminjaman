<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id']) || $_SESSION['level'] != 'admin') {
    header("Location: login.php");
    exit;
}

$aksi = $_GET['aksi'] ?? '';
$pesan = "";

// TAMBAH KATEGORI
if (isset($_POST['tambah'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $query = "INSERT INTO kategori (nama_kategori, deskripsi)VALUES ('$nama', '$deskripsi')";

    if (mysqli_query($koneksi, $query)) {
        $id = mysqli_insert_id($koneksi);

        catatAktivitas($koneksi, $_SESSION['user_id'], 'Menambahkan kategori', "Menambahkan kategori $nama (ID: $id)");
        header("Location: kategori.php");
        exit;
    } else {
        $pesan = "Gagal menambahkan kategori!";
        $aksi = 'tambah';
    }
}

// EDIT KATEGORI
if (isset($_POST['update'])) {
    $id = (int) $_POST['kategori_id'];
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $query = "UPDATE kategori SET nama_kategori='$nama', deskripsi='$deskripsi' WHERE kategori_id='$id'";

    if (mysqli_query($koneksi, $query)) {
        catatAktivitas($koneksi, $_SESSION['user_id'], 'Mengedit kategori', "Mengubah kategori menjadi $nama (ID: $id)");

        header("Location: kategori.php");
        exit;
    } else {
        $pesan = "Gagal mengubah kategori!";
        $aksi = 'edit';
        $_GET['id'] = $id;
    }
}

// HAPUS KATEGORI
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    // Ambil nama kategori sebelum dihapus
    $data_hapus = mysqli_query($koneksi, "SELECT nama_kategori FROM kategori WHERE kategori_id='$id'");

    $kategori_hapus = mysqli_fetch_assoc($data_hapus);

    if (mysqli_query($koneksi, "DELETE FROM kategori WHERE kategori_id='$id'")) {
        if ($kategori_hapus) {
            catatAktivitas($koneksi, $_SESSION['user_id'], 'Menghapus kategori', "Menghapus kategori {$kategori_hapus['nama_kategori']} (ID: $id)");
        }
        header("Location: kategori.php");
        exit;
    } else {
        $pesan = "Kategori tidak bisa dihapus karena masih digunakan oleh data alat!";
    }
}

// DATA KATEGORI UNTUK EDIT
$data_edit = null;

if ($aksi == 'edit' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $hasil = mysqli_query($koneksi, "SELECT * FROM kategori WHERE kategori_id='$id'");
    $data_edit = mysqli_fetch_assoc($hasil);

    if (!$data_edit) {
        header("Location: kategori.php");
        exit;
    }
}

// DATA TABEL
$kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY kategori_id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark">
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

    <?php if ($aksi == 'tambah' || $aksi == 'edit') { ?>

        <!-- FORM TAMBAH / EDIT -->
        <h2><?= $aksi == 'tambah' ? 'Tambah Kategori' : 'Edit Kategori' ?></h2>
        <p class="page-subtitle">
            <?= $aksi == 'tambah' ? 'Tambahkan kategori alat baru.' : 'Ubah informasi kategori alat.' ?>
        </p>

        <?php if ($pesan != "") { ?>
            <div class="alert alert-warning">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php } ?>

        <div class="card">
            <div class="card-header">Form Kategori</div>
            <div class="card-body">
                <form method="POST">

                    <?php if ($aksi == 'edit') { ?>
                        <input type="hidden" name="kategori_id"
                               value="<?= $data_edit['kategori_id'] ?>">
                    <?php } ?>

                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="nama_kategori"
                               class="form-control"
                               placeholder="Masukkan nama kategori"
                               value="<?= htmlspecialchars($data_edit['nama_kategori'] ?? $_POST['nama_kategori'] ?? '') ?>"
                               required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control"
                                  rows="4"
                                  placeholder="Masukkan deskripsi kategori"><?= htmlspecialchars($data_edit['deskripsi'] ?? $_POST['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <?php if ($aksi == 'tambah') { ?>
                        <button type="submit" name="tambah" class="btn btn-primary">
                            Simpan
                        </button>
                    <?php } else { ?>
                        <button type="submit" name="update" class="btn btn-warning">
                            Simpan Perubahan
                        </button>
                    <?php } ?>

                    <a href="kategori.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>

    <?php } else { ?>

        <!-- TABEL KATEGORI -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
            <div>
                <h2>Kelola Kategori</h2>
                <p class="page-subtitle mb-0">
                    Atur kategori untuk mengelompokkan alat.
                </p>
            </div>

            <a href="dashboard.php" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>
        </div>

        <?php if ($pesan != "") { ?>
            <div class="alert alert-warning mt-3">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php } ?>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Daftar Kategori</span>
                <a href="kategori.php?aksi=tambah" class="btn btn-primary btn-sm">
                    + Tambah Kategori
                </a>
            </div>

            <div class="table-responsive">
                <input type="text" id="search" placeholder="🔍 Cari kategori..." onkeyup="searchTable()">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $no = 1;
                        while ($k = mysqli_fetch_assoc($kategori)) {
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($k['nama_kategori']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($k['deskripsi'] ?? '') ?: '-' ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="kategori.php?aksi=edit&id=<?= $k['kategori_id'] ?>"
                                           class="btn btn-warning btn-sm">
                                            Edit
                                        </a>

                                        <a href="kategori.php?hapus=<?= $k['kategori_id'] ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Yakin ingin menghapus kategori ini?')">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>

                        <?php if (mysqli_num_rows($kategori) == 0) { ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    Belum ada kategori alat.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php } ?>

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