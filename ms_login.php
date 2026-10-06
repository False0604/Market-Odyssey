<?php
/**
 * ms_login.php  —  starts the "Continue with Microsoft" sign-in.
 * If real Azure credentials are set (ms_config.php) it redirects to the
 * genuine Microsoft login page (OAuth 2.0 authorization-code flow).
 * Otherwise it performs a clearly-labelled DEMO sign-in so the button works.
 */
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/ms_config.php';
if (is_logged_in()) redirect('index.php');

if (ms_enabled()) {
    // ---- REAL flow: send the student to Microsoft to authenticate ------
    $state = bin2hex(random_bytes(8));
    $_SESSION['ms_state'] = $state;                 // guards against CSRF
    $query = http_build_query([
        'client_id'     => MS_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri'  => MS_REDIRECT,
        'response_mode' => 'query',
        'scope'         => 'openid profile email User.Read',
        'state'         => $state,
    ]);
    redirect('https://login.microsoftonline.com/' . MS_TENANT . '/oauth2/v2.0/authorize?' . $query);
}

// ---- DEMO flow (default): no Azure app configured ---------------------
// Sign into a demo Microsoft-style account so you can experience the flow.
$demoEmail = 'riya@outlook.com';
$safe = mysqli_real_escape_string($conn, $demoEmail);
$res  = mysqli_query($conn, "SELECT id FROM users WHERE email = '$safe' LIMIT 1");

if ($row = mysqli_fetch_assoc($res)) {
    $uid = (int)$row['id'];
} else {
    // Create the demo account if it is missing.
    $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (name, email, password, hostel)
                         VALUES ('Riya Kapoor', '$safe', '$hash', 'Hostel C')");
    $uid = mysqli_insert_id($conn);
}

$_SESSION['user_id']  = $uid;
$_SESSION['login_via'] = 'microsoft-demo';
redirect('index.php');
?>
