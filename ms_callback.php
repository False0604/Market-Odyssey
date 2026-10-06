<?php
/**
 * ms_callback.php  —  Microsoft sends the student back here after they sign
 * in (REAL flow only). We swap the one-time code for an access token, read
 * their profile from Microsoft Graph, then find-or-create their local
 * account and log them in.  (Only runs when ms_config.php has credentials.)
 */
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/ms_config.php';

if (!ms_enabled()) redirect('login.php');

// Validate the response (code present + state matches what we sent).
$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
if ($code === '' || $state === '' || $state !== ($_SESSION['ms_state'] ?? '__none__')) {
    redirect('login.php?err=ms');
}

// ---- 1. Exchange the authorization code for an access token -----------
$body = http_build_query([
    'client_id'     => MS_CLIENT_ID,
    'client_secret' => MS_CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => MS_REDIRECT,
    'scope'         => 'openid profile email User.Read',
]);
$ch = curl_init('https://login.microsoftonline.com/' . MS_TENANT . '/oauth2/v2.0/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$token = json_decode(curl_exec($ch), true);
curl_close($ch);
if (empty($token['access_token'])) redirect('login.php?err=ms');

// ---- 2. Fetch the user's Microsoft profile ----------------------------
$ch = curl_init('https://graph.microsoft.com/v1.0/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']],
]);
$profile = json_decode(curl_exec($ch), true);
curl_close($ch);

$email = $profile['mail'] ?? $profile['userPrincipalName'] ?? '';
$name  = $profile['displayName'] ?? 'Microsoft user';
if ($email === '') redirect('login.php?err=ms');

// ---- 3. Find or create the local account, then log in -----------------
$safe = mysqli_real_escape_string($conn, $email);
$res  = mysqli_query($conn, "SELECT id FROM users WHERE email = '$safe' LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
    $uid = (int)$row['id'];
} else {
    $n    = mysqli_real_escape_string($conn, $name);
    $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (name, email, password, hostel)
                         VALUES ('$n', '$safe', '$hash', 'Hostel A')");
    $uid = mysqli_insert_id($conn);
}

$_SESSION['user_id']  = $uid;
$_SESSION['login_via'] = 'microsoft';
unset($_SESSION['ms_state']);
redirect('index.php');
?>
