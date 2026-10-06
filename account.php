<?php
/**
 * account.php  —  the student's profile: their listings, incoming sale
 * requests, and their own purchases. Confirming handover and leaving reviews
 * happen on the product page (this page routes you there).
 */
require_once __DIR__ . '/config/functions.php';
require_login();
if (is_admin()) redirect('admin.php');
$me   = current_user($conn);
$myId = (int)$me['id'];
$pageTitle = 'My account';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';

    if ($act === 'request_verify') {
        mysqli_query($conn, "UPDATE users SET verify_requested=1 WHERE id=$myId");
        redirect('account.php?vr=1');
    }

    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM listings WHERE id=$id AND user_id=$myId LIMIT 1"));
    if ($row && $act === 'relist') {
        mysqli_query($conn, "UPDATE listings SET avail='available', reserved_by=0, reserved_until=NULL, seller_confirmed=0, buyer_confirmed=0 WHERE id=$id");
        $flash = 'Back on the market: ' . e($row['title']);
    } elseif ($row && $act === 'delete') {
        mysqli_query($conn, "DELETE FROM listings WHERE id=$id");
        mysqli_query($conn, "DELETE FROM listing_photos WHERE listing_id=$id");
        $flash = 'Removed: ' . e($row['title']);
    }
}

$myRating = user_rating($conn, $myId);

// Incoming sale requests: my listings currently reserved (filtered live in PHP).
$reqRaw = mysqli_query($conn, "SELECT l.*, b.name AS buyer_name FROM listings l LEFT JOIN users b ON b.id=l.reserved_by
                               WHERE l.user_id=$myId AND l.avail='reserved' ORDER BY l.reserved_until ASC");
$reqs = [];
while ($r = mysqli_fetch_assoc($reqRaw)) { if (listing_state($r) === 'reserved') $reqs[] = $r; }

// My purchases: items I've reserved or bought.
$buys = mysqli_query($conn, "SELECT l.*, s.name AS seller_name FROM listings l JOIN users s ON s.id=l.user_id
                             WHERE l.reserved_by=$myId ORDER BY l.id DESC");

$mine = mysqli_query($conn, "SELECT * FROM listings WHERE user_id=$myId ORDER BY created_at DESC, id DESC");

require __DIR__ . '/includes/header.php';
?>

<div class="profile-head">
    <span class="avatar"><?= e(initials($me['name'])) ?></span>
    <div>
        <h1 class="page-title" style="margin:0"><?= e($me['name']) ?><?= verified_tick($me, 20) ?></h1>
        <p class="page-sub" style="margin:2px 0 0"><?= e($me['email']) ?> · <?= e($me['hostel']) ?>
            <?php if ($myRating['count'] > 0): ?> · <span class="rating-badge"><span class="s">★</span><?= e($myRating['avg']) ?> (<?= $myRating['count'] ?>)</span><?php endif; ?>
        </p>
    </div>
    <div style="margin-left:auto;display:flex;gap:10px">
        <a class="btn btn-primary" href="sell.php">+ Sell an item</a>
        <a class="btn btn-ghost" href="logout.php">Log out</a>
    </div>
</div>

<?php if ($flash): ?><div class="flash ok" style="margin-top:18px"><?= $flash ?></div><?php endif; ?>

<!-- Verification status -->
<?php if ((int)$me['verified'] === 1): ?>
    <div class="flash ok" style="margin-top:18px;display:flex;align-items:center;gap:8px"><?= verified_tick($me, 16) ?> Your account is verified by the campus admin.</div>
<?php elseif (isset($_GET['vr']) || (int)$me['verify_requested'] === 1): ?>
    <div class="flash info" style="margin-top:18px">⏳ Verification requested — an admin will review your name and email shortly.</div>
<?php else: ?>
    <div class="flash info" style="margin-top:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <span>Your account isn't verified yet. Verified accounts get a blue tick that builds buyer trust.</span>
        <form method="post"><input type="hidden" name="action" value="request_verify"><button class="btn btn-primary btn-sm">Request verification</button></form>
    </div>
<?php endif; ?>

<?php if (count($reqs) > 0): ?>
    <div class="section-head" style="margin-top:26px"><h2>Sale requests</h2><span class="count-line">Buyers waiting on you</span></div>
    <?php foreach ($reqs as $r):
        $sc = (int)$r['seller_confirmed']; $bc = (int)$r['buyer_confirmed']; ?>
        <div class="row">
            <div class="thumb"><?php if ($r['photo']): ?><img src="uploads/<?= e(rawurlencode($r['photo'])) ?>" alt=""><?php endif; ?></div>
            <div class="grow">
                <div class="title"><?= e($r['title']) ?></div>
                <div class="meta"><strong><?= e($r['buyer_name'] ?? 'A buyer') ?></strong> · <?= e(money($r['price'])) ?> · <?= e(hold_label($r)) ?>
                    · <?= $sc ? 'you confirmed ✓' : 'confirm delivery' ?><?= $bc ? ' · buyer received ✓' : '' ?></div>
            </div>
            <div class="actions">
                <a class="btn btn-primary btn-sm" href="product.php?id=<?= (int)$r['id'] ?>"><?= $sc ? 'Open' : 'Confirm delivery' ?></a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (mysqli_num_rows($buys) > 0): ?>
    <div class="section-head" style="margin-top:30px"><h2>My purchases</h2><span class="count-line">Items you reserved or bought</span></div>
    <?php while ($b = mysqli_fetch_assoc($buys)):
        $st = listing_state($b); $sc = (int)$b['seller_confirmed']; $bc = (int)$b['buyer_confirmed'];
        $done = ($sc && $bc); ?>
        <div class="row">
            <div class="thumb"><?php if ($b['photo']): ?><img src="uploads/<?= e(rawurlencode($b['photo'])) ?>" alt=""><?php endif; ?></div>
            <div class="grow">
                <div class="title"><?= e($b['title']) ?></div>
                <div class="meta">from <strong><?= e($b['seller_name']) ?></strong> · <?= e(money($b['price'])) ?> · <?= $bc ? 'you received ✓' : 'confirm receipt' ?><?= $sc ? ' · seller delivered ✓' : '' ?></div>
            </div>
            <div class="actions">
                <span class="status <?= $st ?>"><?= $done ? 'Completed' : ucfirst($st) ?></span>
                <a class="btn btn-primary btn-sm" href="product.php?id=<?= (int)$b['id'] ?>"><?= $done ? (has_reviewed($conn,(int)$b['id'],$myId) ? 'View' : 'Leave review') : 'Confirm receipt' ?></a>
            </div>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

<div class="section-head" style="margin-top:30px"><h2>My listings</h2><span class="count-line">Only you can see this list</span></div>
<?php if (mysqli_num_rows($mine) === 0): ?>
    <div class="empty-market"><h3>You haven't listed anything yet</h3><p>Put your first item up for the campus.</p><a class="btn btn-primary" href="sell.php">Sell an item</a></div>
<?php endif; ?>
<?php while ($l = mysqli_fetch_assoc($mine)):
    $state = listing_state($l); ?>
    <div class="row">
        <div class="thumb"><?php if ($l['photo']): ?><img src="uploads/<?= e(rawurlencode($l['photo'])) ?>" alt=""><?php endif; ?></div>
        <div class="grow">
            <div class="title"><?= e($l['title']) ?></div>
            <div class="meta"><?= e(category_label($l)) ?> · <?= e(money($l['price'])) ?> · <?= e($l['pickup']) ?></div>
        </div>
        <div class="actions">
            <?php if ($l['status'] === 'pending'): ?><span class="status pending">Pending review</span>
            <?php elseif ((int)$l['hidden'] === 1): ?><span class="status hidden">Hidden by admin</span>
            <?php else: ?><span class="status <?= $state ?>"><?= ucfirst($state) ?></span><?php endif; ?>

            <?php if ($l['status'] === 'approved' && (int)$l['hidden'] === 0 && $state !== 'sold'): ?>
                <a class="btn btn-ghost btn-sm" href="product.php?id=<?= (int)$l['id'] ?>">View</a>
            <?php elseif ($state === 'sold'): ?>
                <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="relist" onclick="return confirm('Put this item back on the market?')">Relist</button></form>
            <?php endif; ?>
            <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="delete" onclick="return confirm('Remove this listing permanently?')">Remove</button></form>
        </div>
    </div>
<?php endwhile; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
