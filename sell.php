<?php
/**
 * sell.php  —  "Sell an item" form + handler.
 * Demonstrates: reading form controls (text, list, radio, checkbox),
 *               file uploads (product photo + optional UPI QR),
 *               inserting into MySQL. New listings await admin review.
 */
require_once __DIR__ . '/config/functions.php';
require_login();
if (is_admin()) redirect('admin.php');       // admins manage, they don't sell
$me = current_user($conn);

$pageTitle = 'Sell an item';
$errors = [];
$done   = false;

$title = $price = $description = $customCat = '';
$category  = 'Electronic';
$pickup    = in_array($me['hostel'], locations(), true) ? $me['hostel'] : 'Rise';
$condition = 'Like new';
$payCod = true; $payUpi = true;

/** Save one uploaded image field into /uploads; returns [filename, error]. */
function save_upload($field, $prefix)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return ['', ''];   // nothing uploaded — that's allowed
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return ['', 'Images must be JPG, PNG, GIF or WEBP.'];
    }
    if ($_FILES[$field]['size'] > 4 * 1024 * 1024) {
        return ['', 'Each image must be under 4 MB.'];
    }
    $name = $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], __DIR__ . '/uploads/' . $name)) {
        return ['', 'Could not save an uploaded image (check the uploads folder).'];
    }
    return [$name, ''];
}

/** Save several uploaded images from one multiple-file field. Returns
 *  [array of filenames, error]. Skips empty slots; caps at $max files. */
function save_multi($field, $prefix, $max)
{
    $names = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) return [$names, ''];
    $count = count($_FILES[$field]['name']);
    for ($i = 0; $i < $count && count($names) < $max; $i++) {
        if ($_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES[$field]['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return [$names, 'Images must be JPG, PNG, GIF or WEBP.'];
        }
        if ($_FILES[$field]['size'][$i] > 4 * 1024 * 1024) {
            return [$names, 'Each image must be under 4 MB.'];
        }
        $name = $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES[$field]['tmp_name'][$i], __DIR__ . '/uploads/' . $name)) {
            $names[] = $name;
        }
    }
    return [$names, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $category    = $_POST['category'] ?? 'Electronic';
    $customCat   = trim($_POST['custom_category'] ?? '');
    $pickup      = $_POST['pickup'] ?? 'Rise';
    $price       = trim($_POST['price'] ?? '0');
    $condition   = $_POST['condition'] ?? 'Like new';
    $description = trim($_POST['description'] ?? '');
    $payCod      = isset($_POST['pay_cod']);
    $payUpi      = isset($_POST['pay_upi']);

    if ($title === '')                             $errors[] = 'Please give your item a title.';
    if (!in_array($category, categories(), true))  $errors[] = 'Please choose a valid category.';
    if ($category === 'Other' && $customCat === '') $errors[] = 'Please name your category (you chose "Other").';
    if (!is_numeric($price) || (int)$price < 0)    $errors[] = 'Price must be 0 or more.';
    if ($description === '')                        $errors[] = 'Add a short description.';
    if (!$payCod && !$payUpi)                       $errors[] = 'Pick at least one payment method.';

    [$photoList, $photoErr] = save_multi('photos', 'item', 6);   // up to 6 photos
    if ($photoErr) $errors[] = $photoErr;
    $photoName = $photoList[0] ?? '';                            // first = card/primary

    $qrName = '';
    if ($payUpi) {
        [$qrName, $qrErr] = save_upload('qr', 'qr');
        if ($qrErr) $errors[] = $qrErr;
    }

    if (empty($errors)) {
        $uid  = (int)$me['id'];
        $t    = mysqli_real_escape_string($conn, $title);
        $cat  = mysqli_real_escape_string($conn, $category);
        $cc   = mysqli_real_escape_string($conn, $category === 'Other' ? $customCat : '');
        $pick = mysqli_real_escape_string($conn, $pickup);
        $cond = mysqli_real_escape_string($conn, $condition);
        $desc = mysqli_real_escape_string($conn, $description);
        $ph   = mysqli_real_escape_string($conn, $photoName);
        $qr   = mysqli_real_escape_string($conn, $qrName);
        $pr   = (int)$price;
        $cod  = $payCod ? 1 : 0;
        $upi  = $payUpi ? 1 : 0;

        $ok = mysqli_query($conn, "INSERT INTO listings
            (user_id, title, category, custom_category, price, item_condition, pickup, description, photo, qr, pay_cod, pay_upi, status, avail)
            VALUES ($uid, '$t', '$cat', '$cc', $pr, '$cond', '$pick', '$desc', '$ph', '$qr', $cod, $upi, 'pending', 'available')");
        if ($ok) {
            // Store any additional photos (the first is already the primary).
            $lid = mysqli_insert_id($conn);
            foreach (array_slice($photoList, 1) as $extra) {
                $ex = mysqli_real_escape_string($conn, $extra);
                mysqli_query($conn, "INSERT INTO listing_photos (listing_id, filename) VALUES ($lid, '$ex')");
            }
            $done = true;
        } else { $errors[] = 'Database error: ' . mysqli_error($conn); }
    }
}

require __DIR__ . '/includes/header.php';
?>

<a class="back" href="index.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    Back to marketplace
</a>

<h1 class="page-title">Sell an item</h1>
<p class="page-sub">List it once — a campus admin reviews it, then all of Woxsen can see it.</p>

<?php if ($done): ?>
    <div class="flash ok">✅ Submitted! Your listing is <strong>pending admin review</strong>. Track it under <a href="account.php" style="color:var(--link);font-weight:700">My listings</a>.</div>
    <a class="btn btn-primary btn-lg" href="account.php">Go to my listings</a>

<?php else: ?>
    <?php if (!empty($errors)): ?>
        <div class="flash bad">Please fix:<ul style="margin:8px 0 0 18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form class="form-card" method="post" enctype="multipart/form-data" id="sellForm" onsubmit="return validateSellForm()">
        <div class="field">
            <label>Photos <span style="color:var(--muted);font-weight:500">(up to 6 — the first is the cover)</span></label>
            <label class="dropzone" id="dropzone" for="photoInput">
                <div class="hint" id="dropHint">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    Drop or click to add product photos
                </div>
                <div id="photoPreviews" class="photo-previews" style="display:none"></div>
            </label>
            <input type="file" name="photos[]" id="photoInput" accept="image/*" multiple style="display:none" onchange="previewPhotos(this)">
        </div>

        <div class="field">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" placeholder="e.g. Sony WH-1000XM4 Headphones" value="<?= e($title) ?>">
            <div class="error-text">Please enter a title.</div>
        </div>

        <div class="two-col">
            <div class="field">
                <label for="category">Category</label>
                <select name="category" id="category" onchange="toggleCustomCat()">
                    <?php foreach (categories() as $c): ?>
                        <option value="<?= e($c) ?>" <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="pickup">Pickup near</label>
                <select name="pickup" id="pickup">
                    <?php foreach (locations() as $l): ?>
                        <option value="<?= e($l) ?>" <?= $l === $pickup ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Shows only when category = Other -->
        <div class="field" id="customCatField" style="<?= $category === 'Other' ? '' : 'display:none' ?>">
            <label for="custom_category">Name your category</label>
            <input type="text" name="custom_category" id="custom_category" placeholder="e.g. Sports gear, Books, Cycle…" value="<?= e($customCat) ?>">
            <div class="error-text">Please name your category.</div>
        </div>

        <div class="field">
            <label for="price">Price (₹) — put 0 for a free giveaway</label>
            <input type="number" name="price" id="price" min="0" step="1" value="<?= e($price === '' ? '0' : $price) ?>">
            <div class="error-text">Enter a price of 0 or more.</div>
        </div>

        <div class="field">
            <label>Condition</label>
            <div class="toggle-row">
                <?php foreach (['Like new', 'Good', 'Fair'] as $opt): ?>
                    <label class="toggle"><input type="radio" name="condition" value="<?= e($opt) ?>" <?= $opt === $condition ? 'checked' : '' ?>><span><?= e($opt) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea name="description" id="description" placeholder="Condition, how long you've used it, what's included, why you're selling…"><?= e($description) ?></textarea>
            <div class="error-text">Please describe your item.</div>
        </div>

        <div class="field">
            <label>Payment accepted</label>
            <div class="toggle-row">
                <label class="toggle"><input type="checkbox" name="pay_cod" <?= $payCod ? 'checked' : '' ?>><span>Cash on delivery</span></label>
                <label class="toggle"><input type="checkbox" name="pay_upi" id="payUpi" <?= $payUpi ? 'checked' : '' ?> onchange="toggleQr()"><span>UPI</span></label>
            </div>
        </div>

        <!-- UPI QR upload — shows only when UPI is ticked -->
        <div class="field" id="qrField" style="<?= $payUpi ? '' : 'display:none' ?>">
            <label>Your UPI QR code <span style="color:var(--muted);font-weight:500">(optional — buyers scan it to pay)</span></label>
            <label class="dropzone sm" id="qrDrop" for="qrInput">
                <div class="hint" id="qrHint">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 14v.01M14 20v.01M20 20v.01M17 17v.01"/></svg>
                    Click to upload your UPI QR image
                </div>
                <img id="qrPreview" alt="QR preview" style="display:none">
            </label>
            <input type="file" name="qr" id="qrInput" accept="image/*" style="display:none" onchange="previewQr(this)">
        </div>

        <div class="notice-inline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7a5b23" stroke-width="2"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
            Your listing is reviewed by a campus admin before it goes live.
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>
            Submit for review
        </button>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
