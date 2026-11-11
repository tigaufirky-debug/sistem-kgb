<?php
require_once __DIR__ . '/../core/auth.php';
$user = user() ?? ['nama' => 'User', 'role' => ''];
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

function isMenuActive($requestPath, $target)
{
    return strpos($requestPath, $target) !== false;
}

$adminMenus = [
    ['label' => 'Dashboard', 'path' => '/modules/admin/dashboard.php'],
    ['label' => 'User Pegawai', 'path' => '/modules/admin/user_list.php'],
    ['label' => 'Data Pegawai', 'path' => '/modules/admin/pegawai_list.php'],
    ['label' => 'Verifikasi', 'path' => '/modules/admin/verifikasi.php'],
    ['label' => 'SK KGB', 'path' => '/modules/admin/sk_kgb.php'],
    ['label' => 'Laporan', 'path' => '/modules/admin/laporan.php'],
    ['label' => 'Riwayat Final', 'path' => '/modules/admin/riwayat_kgb.php'],
    ['label' => 'Reminder Email', 'path' => '/modules/admin/reminder.php'],
];

$pegawaiMenus = [
    ['label' => 'Dashboard', 'path' => '/modules/pegawai/dashboard.php'],
    ['label' => 'Pengajuan KGB', 'path' => '/modules/pegawai/pengajuan.php'],
    ['label' => 'Riwayat KGB Final', 'path' => '/modules/pegawai/riwayat_kgb.php'],
];

$menus = $user['role'] === 'admin' ? $adminMenus : $pegawaiMenus;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sistem KGB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #eef3f8;
            --surface: #ffffff;
            --surface-soft: #f6f9fc;
            --text: #0f172a;
            --muted: #64748b;
            --border: #d9e2ec;
            --primary: #0f766e;
            --primary-strong: #0b5f59;
            --sidebar-a: #0b132b;
            --sidebar-b: #12263a;
            --sidebar-text: #dbe7f4;
            --sidebar-muted: #96a9bf;
            --sidebar-active: #14b8a6;
        }
        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background:
                radial-gradient(1200px 500px at -20% -20%, #d8f3ff 0%, rgba(216,243,255,0) 60%),
                radial-gradient(900px 400px at 120% -10%, #d9ffe8 0%, rgba(217,255,232,0) 55%),
                var(--bg);
            color: var(--text);
        }
        .app-shell {
            min-height: 100vh;
            display: flex;
        }
        .app-sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--sidebar-a), var(--sidebar-b));
            color: var(--sidebar-text);
            padding: 18px 14px;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.03) inset;
        }
        .app-brand {
            font-weight: 800;
            font-size: 1.08rem;
            letter-spacing: 0.2px;
            margin-bottom: 6px;
        }
        .app-subtitle {
            font-size: 0.8rem;
            color: var(--sidebar-muted);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }
        .app-menu a {
            display: block;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 8px;
            padding: 10px 11px;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all .18s ease;
        }
        .app-menu a:hover {
            background: rgba(255, 255, 255, 0.09);
            color: #fff;
            transform: translateX(2px);
        }
        .app-menu a.active {
            background: var(--sidebar-active);
            color: #062926;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(20, 184, 166, 0.3);
        }
        .app-sidebar-bottom {
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,.12);
        }
        .app-user {
            font-size: .83rem;
            color: var(--sidebar-muted);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .btn-logout {
            width: 100%;
            border: 1px solid rgba(255,255,255,.22);
            color: #ffe2e2;
            background: rgba(220, 38, 38, .12);
            border-radius: 8px;
            padding: 8px 12px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: 700;
        }
        .btn-logout:hover {
            color: #fff;
            background: rgba(220, 38, 38, .28);
        }
        .app-main {
            flex: 1;
            min-width: 0;
        }
        .app-topbar {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(5px);
            border-bottom: 1px solid var(--border);
            padding: 12px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar-title {
            font-weight: 700;
            color: var(--text);
        }
        .topbar-meta {
            color: var(--muted);
            font-size: .88rem;
            font-weight: 600;
        }
        .app-content {
            padding: 20px 22px 30px;
        }
        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(2, 8, 23, 0.05);
            background: var(--surface);
        }
        .card-header {
            background: var(--surface-soft);
            border-bottom: 1px solid var(--border);
            font-weight: 700;
        }
        .table {
            margin-bottom: 0;
        }
        .table > :not(caption) > * > * {
            border-bottom-color: #e7eef6;
            vertical-align: middle;
        }
        .table-dark {
            --bs-table-bg: #1f2937;
            --bs-table-border-color: #1f2937;
        }
        .btn {
            border-radius: 9px;
            font-weight: 600;
        }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: var(--primary-strong);
            border-color: var(--primary-strong);
        }
        .form-control, .form-select {
            border-radius: 9px;
            border-color: #cfd9e4;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6db8b1;
            box-shadow: 0 0 0 .2rem rgba(20, 184, 166, .18);
        }
        .alert {
            border-radius: 10px;
            border: 1px solid transparent;
        }
        h3, h4, h5 {
            font-weight: 800;
            letter-spacing: .1px;
        }
        @media (max-width: 991.98px) {
            .app-shell {
                display: block;
            }
            .app-sidebar {
                width: 100%;
                height: auto;
                position: static;
            }
            .app-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand">Sistem KGB</div>
        <div class="app-subtitle"><?= htmlspecialchars(strtoupper($user['role'] ?: 'user')) ?></div>

        <nav class="app-menu">
            <?php foreach ($menus as $menu): ?>
                <?php
                $isActive = isMenuActive($requestPath, $menu['path']);
                $href = appUrl($menu['path']);
                ?>
                <a href="<?= htmlspecialchars($href) ?>" class="<?= $isActive ? 'active' : '' ?>">
                    <?= htmlspecialchars($menu['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="app-sidebar-bottom">
            <div class="app-user">
                Login sebagai<br><strong><?= htmlspecialchars($user['nama']) ?></strong>
            </div>
            <a href="<?= htmlspecialchars(appUrl('/modules/auth/logout.php')) ?>" class="btn-logout">Logout</a>
        </div>
    </aside>

    <div class="app-main">
        <div class="app-topbar">
            <div class="topbar-title">Sistem KGB</div>
            <div class="topbar-meta"><?= htmlspecialchars(strtoupper($user['role'] ?: 'user')) ?></div>
        </div>

        <main class="app-content">
