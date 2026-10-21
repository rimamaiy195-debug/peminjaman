<?php
function catatAktivitas($koneksi, $user_id, $aktivitas, $detail = '') {
    $user_id = (int) $user_id;
    $aktivitas = mysqli_real_escape_string($koneksi, $aktivitas);
    $detail = mysqli_real_escape_string($koneksi, $detail);

    $query = "INSERT INTO log_aktivitas
        (user_id, aktivitas, detail, waktu)
        VALUES
        ('$user_id', '$aktivitas', '$detail', NOW())";

    return mysqli_query($koneksi, $query);
}
?>