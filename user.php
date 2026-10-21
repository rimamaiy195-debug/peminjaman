
<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id']) || $_SESSION['level'] != 'admin') {
    header("Location: login.php");
    exit;
}

$aksi = $_GET['aksi'] ?? '';
$pesan = '';

// TAMBAH PENGGUNA
if (isset($_POST['tambah'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = md5($_POST['password']);
    $level_baru = $_POST['level'];

    if (!in_array($level_baru, ['admin', 'petugas', 'peminjam'])) {
        $pesan = "Level pengguna tidak valid!";
        $aksi = 'tambah';
    } else {
        $cek = mysqli_query($koneksi, "SELECT * FROM user
            WHERE username='$username'");

        if (!$cek) {
            $pesan = "Gagal memeriksa username!";
            $aksi = 'tambah';
        } elseif (mysqli_num_rows($cek) > 0) {
            $pesan = "Username sudah digunakan!";
            $aksi = 'tambah';
        } else {
            $query = "INSERT INTO user
                (nama, username, password, level)
                VALUES
                ('$nama', '$username', '$password', '$level_baru')";

            if (mysqli_query($koneksi, $query)) {
                $id_baru = mysqli_insert_id($koneksi);

                catatAktivitas(
                    $koneksi,
                    $_SESSION['user_id'],
                    'Menambah pengguna',
                    'Menambahkan pengguna ' . $nama .
                    ' dengan username ' . $username .
                    ' dan level ' . $level_baru .
                    '. ID pengguna: ' . $id_baru
                );

                header("Location: user.php");
                exit;
            } else {
                $pesan = "Gagal menambahkan pengguna!";
                $aksi = 'tambah';
            }
        }
    }
}

// EDIT PENGGUNA
if (isset($_POST['update'])) {
    $id = (int) $_POST['user_id'];
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $level_baru = $_POST['level'];

    $aksi = 'edit';
    $_GET['id'] = $id;

    if (!in_array($level_baru, ['admin', 'petugas', 'peminjam'])) {
        $pesan = "Level pengguna tidak valid!";
    } else {
        $cek = mysqli_query($koneksi, "SELECT * FROM user
            WHERE username='$username'
            AND user_id!='$id'");

        if (!$cek) {
            $pesan = "Gagal memeriksa username!";
        } elseif (mysqli_num_rows($cek) > 0) {
            $pesan = "Username sudah digunakan!";
        } else {
            if (!empty($_POST['password'])) {
                $password = md5($_POST['password']);

                $query = "UPDATE user SET
                    nama='$nama',
                    username='$username',
                    password='$password',
                    level='$level_baru'
                    WHERE user_id='$id'";
            } else {
                $query = "UPDATE user SET
                    nama='$nama',
                    username='$username',
                    level='$level_baru'
                    WHERE user_id='$id'";
            }

            if (mysqli_query($koneksi, $query)) {
                catatAktivitas(
                    $koneksi,
                    $_SESSION['user_id'],
                    'Mengedit pengguna',
                    'Mengubah data pengguna ID ' . $id .
                    ' menjadi nama ' . $nama .
                    ', username ' . $username .
                    ', level ' . $level_baru
                );

                header("Location: user.php");
                exit;
            } else {
                $pesan = "Gagal mengubah data pengguna!";
            }
        }
    }
}

// HAPUS PENGGUNA
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    if ($id == $_SESSION['user_id']) {
        $pesan = "Akun sendiri tidak bisa dihapus!";
    } else {
        // Ambil data sebelum dihapus untuk dicatat ke log
        $cek = mysqli_query($koneksi, "SELECT nama, username
            FROM user WHERE user_id='$id'");

        $data_hapus = $cek ? mysqli_fetch_assoc($cek) : null;

        if (!$data_hapus) {
            $pesan = "Pengguna tidak ditemukan!";
        } else {
            $hapus = mysqli_query($koneksi, "DELETE FROM user
                WHERE user_id='$id'");

            if ($hapus && mysqli_affected_rows($koneksi) > 0) {
                catatAktivitas(
                    $koneksi,
                    $_SESSION['user_id'],
                    'Menghapus pengguna',
                    'Menghapus pengguna ' . $data_hapus['nama'] .
                    ' dengan username ' . $data_hapus['username'] .
                    '. ID pengguna: ' . $id
                );

                header("Location: user.php");
                exit;
            } else {
                $pesan = "Pengguna tidak bisa dihapus karena masih memiliki data terkait!";
            }
        }
    }

    $aksi = '';
}

// AMBIL DATA UNTUK EDIT
$data_edit = null;

if ($aksi == 'edit' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    $hasil = mysqli_query($koneksi, "SELECT * FROM user
        WHERE user_id='$id'");

    $data_edit = $hasil ? mysqli_fetch_assoc($hasil) : null;

    if (!$data_edit) {
        header("Location: user.php");
        exit;
    }
}

// AMBIL DATA TABEL
$user = mysqli_query($koneksi, "SELECT * FROM user
    ORDER BY user_id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

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

    <?php if ($aksi == 'tambah' || $aksi == 'edit') { ?>

        <div class="mb-4">
            <a href="user.php" class="btn btn-secondary btn-sm mb-3">
                &larr; Kembali ke Daftar
            </a>

            <h2>
                <?= $aksi == 'tambah' ? 'Tambah Pengguna' : 'Edit Pengguna' ?>
            </h2>

            <p class="page-subtitle">
                <?= $aksi == 'tambah'
                    ? 'Tambahkan akun baru ke aplikasi.'
                    : 'Ubah informasi akun pengguna.' ?>
            </p>
        </div>

        <?php if ($pesan != '') { ?>
            <div class="alert alert-warning">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php } ?>

        <div class="card">
            <div class="card-header">Form Pengguna</div>

            <div class="card-body">
                <form method="POST">

                    <?php if ($aksi == 'edit') { ?>
                        <input type="hidden" name="user_id"
                               value="<?= (int) $data_edit['user_id'] ?>">
                    <?php } ?>

                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control"
                               value="<?= htmlspecialchars(
                                   $data_edit['nama'] ?? $_POST['nama'] ?? ''
                               ) ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control"
                               value="<?= htmlspecialchars(
                                   $data_edit['username'] ?? $_POST['username'] ?? ''
                               ) ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Password
                            <?= $aksi == 'edit'
                                ? '(Kosongkan jika tidak diganti)'
                                : '' ?>
                        </label>

                        <input type="password" name="password"
                               class="form-control"
                               <?= $aksi == 'tambah' ? 'required' : '' ?>>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Level Pengguna</label>

                        <select name="level" class="form-select" required>
                            <option value="">-- Pilih Level --</option>

                            <?php
                            $levels = ['admin', 'petugas', 'peminjam'];
                            $level_data = $data_edit['level']
                                ?? $_POST['level']
                                ?? '';

                            foreach ($levels as $l) {
                                $selected = ($level_data == $l)
                                    ? 'selected'
                                    : '';

                                echo "<option value='$l' $selected>"
                                    . ucfirst($l)
                                    . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <?php if ($aksi == 'tambah') { ?>
                        <button type="submit" name="tambah"
                                class="btn btn-primary">
                            Simpan Pengguna
                        </button>
                    <?php } else { ?>
                        <button type="submit" name="update"
                                class="btn btn-primary">
                            Simpan Perubahan
                        </button>
                    <?php } ?>

                    <a href="user.php" class="btn btn-secondary">
                        Batal
                    </a>

                </form>
            </div>
        </div>

    <?php } else { ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
            <div>
                <h2>Kelola Pengguna</h2>
                <p class="page-subtitle mb-0">
                    Atur akun admin, petugas, dan peminjam.
                </p>
                <br>

            <a href="user.php?aksi=tambah" class="btn btn-primary">
                + Tambah Pengguna
            </a>
            </div>

            <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">
                &larr; Kembali ke Dashboard
            </a>
        </div>

        <?php if ($pesan != '') { ?>
            <div class="alert alert-warning mt-3">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php } ?>

        <div class="card mt-4">
            <div class="card-header">Daftar Pengguna</div>

            <div class="table-responsive">
                <input type="text" id="search" placeholder="🔍 Cari pengguna..." onkeyup="searchTable()">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Level</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $no = 1;

                        if ($user) {
                            while ($u = mysqli_fetch_assoc($user)) {
                                $warna = 'bg-secondary';

                                if ($u['level'] == 'admin') {
                                    $warna = 'bg-primary';
                                } elseif ($u['level'] == 'petugas') {
                                    $warna = 'bg-success';
                                } elseif ($u['level'] == 'peminjam') {
                                    $warna = 'bg-info text-dark';
                                }
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($u['nama']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>

                                <td>
                                    <span class="badge <?= $warna ?>">
                                        <?= htmlspecialchars(ucfirst($u['level'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <a href="user.php?aksi=edit&id=<?= (int) $u['user_id'] ?>" class="btn btn-warning btn-sm">
                                        Edit
                                    </a>

                                    <?php if ($u['user_id'] != $_SESSION['user_id']) { ?>
                                        <a href="user.php?hapus=<?= (int) $u['user_id'] ?>" class="btn btn-danger btn-sm"
                                           onclick="return confirm('Yakin ingin menghapus pengguna ini?')">
                                            Hapus
                                        </a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php
                            }
                        }
                        ?>

                        <?php if (!$user || mysqli_num_rows($user) == 0) { ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    Belum ada pengguna.
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
