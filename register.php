<?php
/**
 * register.php  —  create an account with email OTP verification.
 * Step 1: details (name, email, location — pick or type your own, password).
 * Step 2: enter the 6-digit code we generate for the email, then the account
 *         is created with its email marked verified.
 *
 * Sending real email needs an SMTP server (not configured on a stock XAMPP),
 * so in this build the code is also shown on screen (clearly marked "demo").
 * To go live, wire PHP mail()/PHPMailer where noted below.
 */
require_once __DIR__ . '/config/functions.php';
if (is_logged_in()) redirect('index.php');

$pageTitle = 'Join';
$errors = [];
$step    = 1;
$otpDemo = '';                       // shown in the demo banner
$name = $email = ''; $hostel = 'Rise'; $customLoc = '';

/** Make + "send" a fresh code, store the pending signup in the session. */
function issue_otp(&$signup)
{
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $signup['otp']     = $otp;
    $signup['expires'] = time() + 600;              // 10 minutes
    $_SESSION['pending_signup'] = $signup;
    // --- Real email would go here ---------------------------------------
    // @mail($signup['email'], 'Your Market Odyssey code', "Your code is $otp");
    return $otp;
}

$action = $_POST['action'] ?? '';

if ($action === 'signup') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $hostel    = $_POST['hostel'] ?? 'Rise';
    $customLoc = trim($_POST['custom_location'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';

    // "Other" means the student typed their own spot.
    $location = ($hostel === 'Other') ? $customLoc : $hostel;

    if ($name === '')                                $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Please enter a valid email.';
    if ($hostel === 'Other' && $customLoc === '')    $errors[] = 'Please type your location.';
    if (strlen($password) < 6)                       $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                       $errors[] = 'The two passwords do not match.';

    if (empty($errors)) {
        $safe = mysqli_real_escape_string($conn, $email);
        if (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE email='$safe' LIMIT 1")) > 0) {
            $errors[] = 'That email is already registered. Try logging in.';
        }
    }

    if (empty($errors)) {
        $signup = [
            'name'     => $name,
            'email'    => $email,
            'location' => $location,
            'hash'     => password_hash($password, PASSWORD_DEFAULT),
        ];
        $otpDemo = issue_otp($signup);
        $step = 2;
    }

} elseif ($action === 'resend') {
    $signup = $_SESSION['pending_signup'] ?? null;
    if ($signup) { $otpDemo = issue_otp($signup); $email = $signup['email']; $step = 2; }
    else $errors[] = 'Please start again.';

} elseif ($action === 'verify_otp') {
    $entered = trim($_POST['otp'] ?? '');
    $signup  = $_SESSION['pending_signup'] ?? null;
    $email   = $signup['email'] ?? '';
    if (!$signup) {
        $errors[] = 'Your session expired — please sign up again.';
    } elseif (time() > $signup['expires']) {
        $errors[] = 'That code expired. Tap resend for a new one.'; $step = 2;
    } elseif ($entered !== $signup['otp']) {
        $errors[] = 'That code is not right. Try again.'; $step = 2;
    } else {
        // Verified — create the account (email_verified = 1, admin verified = 0).
        $n = mysqli_real_escape_string($conn, $signup['name']);
        $e = mysqli_real_escape_string($conn, $signup['email']);
        $h = mysqli_real_escape_string($conn, $signup['location']);
        $hash = mysqli_real_escape_string($conn, $signup['hash']);
        mysqli_query($conn, "INSERT INTO users (name, email, password, hostel, email_verified, verified)
                             VALUES ('$n', '$e', '$hash', '$h', 1, 0)");
        $_SESSION['user_id'] = mysqli_insert_id($conn);
        unset($_SESSION['pending_signup']);
        redirect('index.php');
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card">
    <?php if ($step === 1): ?>
        <h1>Join Market Odyssey</h1>
        <p class="page-sub">One account to buy, sell and chat across campus.</p>

        <?php if (!empty($errors)): ?>
            <div class="flash bad"><ul style="margin:0 0 0 18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" onsubmit="return validateRegister()">
            <input type="hidden" name="action" value="signup">
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" name="name" id="name" value="<?= e($name) ?>" placeholder="e.g. Aarav Sharma">
                <div class="error-text">Please enter your name.</div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?= e($email) ?>" placeholder="you@woxsen.edu">
                <div class="error-text">Enter a valid email.</div>
            </div>
            <div class="field">
                <label for="hostel">Usual campus spot</label>
                <select name="hostel" id="hostel" onchange="toggleCustomLoc()">
                    <?php foreach (locations() as $l): ?>
                        <option value="<?= e($l) ?>" <?= $l === $hostel ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                    <option value="Other" <?= $hostel === 'Other' ? 'selected' : '' ?>>Other (type it)…</option>
                </select>
            </div>
            <div class="field" id="customLocField" style="<?= $hostel === 'Other' ? '' : 'display:none' ?>">
                <label for="custom_location">Type your location</label>
                <input type="text" name="custom_location" id="custom_location" value="<?= e($customLoc) ?>" placeholder="e.g. Block C, Sports complex…">
                <div class="error-text">Please type your location.</div>
            </div>
            <div class="two-col">
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="min 6 characters">
                    <div class="error-text">At least 6 characters.</div>
                </div>
                <div class="field">
                    <label for="confirm">Confirm</label>
                    <input type="password" name="confirm" id="confirm" placeholder="repeat password">
                    <div class="error-text">Passwords must match.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send verification code</button>
        </form>

        <div class="or-div">or</div>
        <a class="btn-ms" href="ms_login.php"><span class="ms-tiles"><i></i><i></i><i></i><i></i></span>Continue with Microsoft</a>
        <p class="auth-alt">Already a member? <a href="login.php">Log in</a></p>

    <?php else: /* step 2 — OTP */ ?>
        <h1>Verify your email</h1>
        <p class="page-sub">Enter the 6-digit code we sent to <strong><?= e($email) ?></strong>.</p>

        <?php if (!empty($errors)): ?>
            <div class="flash bad"><ul style="margin:0 0 0 18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if ($otpDemo !== ''): ?>
            <div class="flash info"><strong>Demo mode:</strong> email isn't wired to a mail server here, so your code is <strong style="letter-spacing:2px"><?= e($otpDemo) ?></strong>.</div>
        <?php endif; ?>

        <form method="post" onsubmit="return validateOtp()">
            <input type="hidden" name="action" value="verify_otp">
            <div class="field">
                <label for="otp">Verification code</label>
                <input type="text" name="otp" id="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                       placeholder="••••••" style="letter-spacing:8px;text-align:center;font-size:22px">
                <div class="error-text">Enter the 6-digit code.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify &amp; create account</button>
        </form>
        <form method="post" style="text-align:center;margin-top:14px">
            <input type="hidden" name="action" value="resend">
            <button class="btn btn-ghost btn-sm" type="submit">Resend code</button>
        </form>
    <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
