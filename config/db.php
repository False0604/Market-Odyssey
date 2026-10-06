<?php
/**
 * db.php  —  Database connection + first-run auto setup + migrations
 * ------------------------------------------------------------------
 * Curriculum topics: connecting to MySQL, executing queries.
 *
 * Connects with the XAMPP MariaDB running on this PC, creates the database
 * and tables on first run, and upgrades older databases by adding any new
 * columns automatically (so you never have to edit tables by hand).
 */

// ---- 1. Connection settings --------------------------------------------
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';                // stock XAMPP default
$DB_NAME = 'market_odyssey';
$DB_PORT = 3306;

// Machine-specific overrides (e.g. a non-empty root password) live in a
// git-ignored file so credentials never get committed.
if (file_exists(__DIR__ . '/db.local.php')) {
    require __DIR__ . '/db.local.php';
}

// ---- 2. Connect --------------------------------------------------------
$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
if (!$conn) {
    die('Could not connect to MySQL. Is XAMPP\'s MySQL running? ' . mysqli_connect_error());
}

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$DB_NAME`
                     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($conn, $DB_NAME);
mysqli_set_charset($conn, 'utf8mb4');

// ---- 3. Tables ---------------------------------------------------------
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(80)  NOT NULL,
        email      VARCHAR(120) NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL,
        hostel     VARCHAR(40)  DEFAULT 'Rise',
        is_admin   TINYINT(1)   DEFAULT 0,
        banned     TINYINT(1)   DEFAULT 0,
        created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
    )
");
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS listings (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        user_id        INT NOT NULL,
        title          VARCHAR(120) NOT NULL,
        category       VARCHAR(40)  NOT NULL,
        custom_category VARCHAR(60) DEFAULT '',
        price          INT NOT NULL DEFAULT 0,
        item_condition VARCHAR(20)  NOT NULL DEFAULT 'Good',
        description    TEXT,
        pickup         VARCHAR(40)  DEFAULT 'Rise',
        photo          VARCHAR(255) DEFAULT '',
        qr             VARCHAR(255) DEFAULT '',
        pay_cod        TINYINT(1)   DEFAULT 1,
        pay_upi        TINYINT(1)   DEFAULT 1,
        status         VARCHAR(20)  DEFAULT 'pending',   -- pending | approved (admin review)
        avail          VARCHAR(20)  DEFAULT 'available',  -- available | reserved | sold
        reserved_by    INT          DEFAULT 0,
        reserved_until DATETIME     NULL,
        hidden         TINYINT(1)   DEFAULT 0,            -- admin can hide from the marketplace
        created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
    )
");
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        listing_id  INT NOT NULL,
        sender_id   INT NOT NULL,
        receiver_id INT NOT NULL,
        body        TEXT NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS listing_photos (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        listing_id INT NOT NULL,
        filename   VARCHAR(255) NOT NULL
    )
");
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS reviews (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        listing_id  INT NOT NULL,
        reviewer_id INT NOT NULL,          -- who wrote it
        reviewee_id INT NOT NULL,          -- who it's about
        role        VARCHAR(10) NOT NULL,  -- 'buyer' or 'seller' (the reviewer's role)
        rating      INT NOT NULL,          -- 1..5
        body        TEXT,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// ---- 4. Auto-migration: add any columns missing on older databases -----
function ensure_column($conn, $table, $col, $definition)
{
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $col);
    $r = mysqli_query($conn, "SELECT COUNT(*) n FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    if ((int)mysqli_fetch_assoc($r)['n'] === 0) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN $definition");
    }
}
ensure_column($conn, 'users', 'is_admin', "is_admin TINYINT(1) DEFAULT 0");
ensure_column($conn, 'users', 'banned',   "banned TINYINT(1) DEFAULT 0");
ensure_column($conn, 'users', 'email_verified',   "email_verified TINYINT(1) DEFAULT 0");   // via OTP at signup
ensure_column($conn, 'users', 'verified',         "verified TINYINT(1) DEFAULT 0");          // admin-verified (tick)
ensure_column($conn, 'users', 'verify_requested', "verify_requested TINYINT(1) DEFAULT 0");  // user asked admin
ensure_column($conn, 'listings', 'custom_category', "custom_category VARCHAR(60) DEFAULT ''");
ensure_column($conn, 'listings', 'qr',             "qr VARCHAR(255) DEFAULT ''");
ensure_column($conn, 'listings', 'avail',          "avail VARCHAR(20) DEFAULT 'available'");
ensure_column($conn, 'listings', 'reserved_by',    "reserved_by INT DEFAULT 0");
ensure_column($conn, 'listings', 'reserved_until', "reserved_until DATETIME NULL");
ensure_column($conn, 'listings', 'hidden',         "hidden TINYINT(1) DEFAULT 0");
ensure_column($conn, 'listings', 'seller_confirmed', "seller_confirmed TINYINT(1) DEFAULT 0");
ensure_column($conn, 'listings', 'buyer_confirmed',  "buyer_confirmed TINYINT(1) DEFAULT 0");
ensure_column($conn, 'listings', 'frozen',           "frozen TINYINT(1) DEFAULT 0");

// ---- 5. Make sure a campus-admin account always exists -----------------
// Separate admin login (see admin_login.php).  Email / password below.
$adminEmail = 'admin@woxsen.edu';
$safe = mysqli_real_escape_string($conn, $adminEmail);
$res  = mysqli_query($conn, "SELECT id FROM users WHERE email = '$safe' LIMIT 1");
if (mysqli_num_rows($res) === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (name, email, password, hostel, is_admin, email_verified, verified)
                         VALUES ('Campus Admin', '$safe', '$hash', 'iCreate', 1, 1, 1)");
} else {
    // ensure the flags are set even if the row predates these columns
    mysqli_query($conn, "UPDATE users SET is_admin = 1, email_verified = 1, verified = 1 WHERE email = '$safe'");
}

// ---- 6. Seed demo data the very first time -----------------------------
$result = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE is_admin = 0");
if ((int)mysqli_fetch_assoc($result)['c'] === 0) {
    require_once __DIR__ . '/seed.php';
    seed_sample_data($conn);
}
?>
