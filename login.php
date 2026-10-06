<?php
/**
 * login.php  —  sign in with email + password.
 * Demonstrates: reading form controls, checking a hashed password,
 *               starting a SESSION, and an optional "remember me" COOKIE.
 */
require_once __DIR__ . '/config/functions.php';
if (is_logged_in()) redirect('index.php');

$pageTitle = 'Log in';
$error = '';
$email = '';
if (isset($_GET['err']) && $_GET['err'] === 'ms') {
    $error = 'Microsoft sign-in did not complete. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $safe = mysqli_real_escape_string($conn, $email);
    $res  = mysqli_query($conn, "SELECT * FROM users WHERE email = '$safe' LIMIT 1");
    $user = mysqli_fetch_assoc($res);

    if ($user && (int)$user['banned'] === 1) {
        $error = 'This account has been blocked by an admin.';
    } elseif ($user && password_verify($password, $user['password'])) {
        // Success: store the id in the SESSION so every page knows who we are.
        $_SESSION['user_id'] = (int)$user['id'];
        if ((int)$user['is_admin'] === 1) { $_SESSION['is_admin'] = 1; }

        // "Remember me" writes a COOKIE that lasts 30 days.
        if ($remember) {
            setcookie('mo_remember', $user['email'], time() + 60 * 60 * 24 * 30, '/');
        }
        redirect((int)$user['is_admin'] === 1 ? 'admin.php' : 'index.php');
    } else {
        $error = 'Wrong email or password. Try again.';
    }
}

// If a "remember me" cookie exists, pre-fill the email box.
if ($email === '' && isset($_COOKIE['mo_remember'])) {
    $email = $_COOKIE['mo_remember'];
}

require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="page-sub">Log in to buy, sell and message on campus.</p>

        <?php if ($error): ?><div class="flash bad"><?= e($error) ?></div><?php endif; ?>

        <form method="post" onsubmit="return validateLogin()">
            <div class="field">
                <label for="email">Woxsen email</label>
                <input type="email" name="email" id="email" value="<?= e($email) ?>" placeholder="you@woxsen.edu">
                <div class="error-text">Enter a valid email.</div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="••••••••">
                <div class="error-text">Enter your password.</div>
            </div>
            <label class="toggle" style="display:inline-flex;margin-bottom:18px">
                <input type="checkbox" name="remember">
                <span>Remember me</span>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>

        <div class="or-div">or</div>

        <!-- Continue with Microsoft (real OAuth when configured; demo otherwise) -->
        <a class="btn-ms" href="ms_login.php">
            <span class="ms-tiles"><i></i><i></i><i></i><i></i></span>
            Continue with Microsoft
        </a>

        <p class="auth-alt">New here? <a href="register.php">Create an account</a></p>

        <div class="demo-hint">
            <strong>Demo accounts:</strong> jhon@woxsen.edu · aarav@woxsen.edu · ishita@woxsen.edu
            — password <em>pass123</em>.
            <br>Or tap <strong>Continue with Microsoft</strong> to sign in as the demo account Riya Kapoor.
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
