<?php
require_once '../../core/auth.php';

if (isLogin()) {
    header("Location: ../../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Sistem KGB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header text-center">
                    <h4>Login Sistem KGB</h4>
                </div>
                <div class="card-body">

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            Email atau password salah
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login_process.php">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button class="btn btn-primary w-100">
                            Login
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
