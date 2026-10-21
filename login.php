
<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

// Kalau sudah login, langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$pesan = "";

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = md5($_POST['password']);

    $query = mysqli_query($koneksi, "SELECT * FROM user
        WHERE username='$username' AND password='$password'");

    if (!$query) {
        die("Terjadi kesalahan: " . mysqli_error($koneksi));
    }

    $data = mysqli_fetch_assoc($query);

    if ($data) {
        $_SESSION['user_id'] = $data['user_id'];
        $_SESSION['nama'] = $data['nama'];
        $_SESSION['level'] = $data['level'];

        // Catat aktivitas login
        catatAktivitas($koneksi, $data['user_id'], 'Login', 'Pengguna ' . $data['nama'] . ' berhasil login ke sistem');
        header("Location: dashboard.php");
        exit;
    } else {
        $pesan = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Peminjaman Sarpras</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="login-page">
    <div class="login-box">

        <div class="login-logo">
            &#128203;
        </div>

        <h3 class="text-center fw-bold mb-2">
            Peminjaman Sarpras
        </h3>

        <p class="text-center text-secondary mb-4">
            Silakan login untuk melanjutkan
        </p>

        <?php if ($pesan != "") { ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php } ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username"
                       class="form-control"
                       placeholder="Masukkan username"
                       autocomplete="username"
                       required>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password"
                       class="form-control"
                       placeholder="Masukkan password"
                       autocomplete="current-password"
                       required>
            </div>

            <button type="submit" name="login"
                    class="btn btn-primary w-100">
                Login
            </button>

        </form>

        <p class="text-center text-secondary mt-4 mb-0">
            Sistem Peminjaman Sarana dan Prasarana Sekolah
        </p>

    </div>
</div>

</body>
</html>
