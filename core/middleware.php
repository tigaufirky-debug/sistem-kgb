<?php
require_once __DIR__ . '/auth.php';

function requireAdmin()
{
    requireLogin();

    if ($_SESSION['user']['role'] !== 'admin') {
        die("Akses ditolak (Admin only)");
    }
}

function requirePegawai()
{
    requireLogin();

    if ($_SESSION['user']['role'] !== 'pegawai') {
        die("Akses ditolak (Pegawai only)");
    }
}
