<?php
/**
 * admin_login.php  —  a SEPARATE login just for campus admins.
 * Authenticates against an account whose is_admin flag is set.
 */
require_once __DIR__ . '/config/functions.php';
if (is_admin()) redirect('admin.php');

$pageTitle = 'Admin login';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $safe = mysqli_real_escape_string($conn, $email);
    $res  = mysqli_query($conn, "SELECT * FROM users WHERE email='$safe' LIMIT 1");
    $user = mysqli_fetch_assoc($res);

    if ($user && (int)$user['is_admin'] === 1 && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['is_admin'] = 1;
        redirect('admin.php');
    } else {
        $error = 'Invalid admin credentials.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card admin">
        <h1>Admin login</h1>
        <p class="page-sub">Campus administrators only.</p>

        <?php if ($error): ?><div class="flash bad"><?= e($error) ?></div><?php endif; ?>

        <form method="post" onsubmit="return validateLogin()">
            <div class="field">
                <label for="email">Admin email</label>
                <input type="email" name="email" id="email" value="<?= e($email) ?>" placeholder="admin@woxsen.edu">
                <div class="error-text">Enter a valid email.</div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="••••••••">
                <div class="error-text">Enter your password.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Enter admin panel</button>
        </form>

        <p class="auth-alt"><a href="index.php">← Back to marketplace</a></p>
        <div class="demo-hint"><strong>Demo admin:</strong> admin@woxsen.edu&nbsp;/&nbsp;admin123</div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
