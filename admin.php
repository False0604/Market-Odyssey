<?php
/**
 * admin.php  —  campus-admin control panel (separate admin login).
 * The admin can see ALL data — users, listings, transactions (buyings) and
 * reviews — and can approve/reject, hide/show or remove listings, and
 * block/unblock users. Actions are written to a plain-text audit log.
 */
require_once __DIR__ . '/config/functions.php';
require_admin();
$pageTitle = 'Admin panel';
$flash = '';

function admin_log($action, $detail)
{
    $fp = fopen(__DIR__ . '/uploads/admin_log.txt', 'a');
    if ($fp) { fwrite($fp, date('Y-m-d H:i:s') . "  " . strtoupper($action) . "  " . $detail . PHP_EOL); fclose($fp); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'approve') {
        mysqli_query($conn, "UPDATE listings SET status='approved' WHERE id=$id"); admin_log('approve', "listing #$id"); $flash = "Listing approved.";
    } elseif ($action === 'reject' || $action === 'remove_listing') {
        mysqli_query($conn, "DELETE FROM listings WHERE id=$id");
        mysqli_query($conn, "DELETE FROM listing_photos WHERE listing_id=$id");
        admin_log('remove', "listing #$id"); $flash = "Listing removed.";
    } elseif ($action === 'hide') {
        mysqli_query($conn, "UPDATE listings SET hidden=1 WHERE id=$id"); admin_log('hide', "listing #$id"); $flash = "Listing hidden.";
    } elseif ($action === 'show') {
        mysqli_query($conn, "UPDATE listings SET hidden=0 WHERE id=$id"); admin_log('show', "listing #$id"); $flash = "Listing visible again.";
    } elseif ($action === 'ban') {
        mysqli_query($conn, "UPDATE users SET banned=1 WHERE id=$id AND is_admin=0"); admin_log('ban', "user #$id"); $flash = "User blocked.";
    } elseif ($action === 'unban') {
        mysqli_query($conn, "UPDATE users SET banned=0 WHERE id=$id"); admin_log('unban', "user #$id"); $flash = "User unblocked.";
    } elseif ($action === 'delete_review') {
        mysqli_query($conn, "DELETE FROM reviews WHERE id=$id"); admin_log('delete_review', "review #$id"); $flash = "Review deleted.";
    } elseif ($action === 'verify_user') {
        mysqli_query($conn, "UPDATE users SET verified=1, verify_requested=0 WHERE id=$id AND is_admin=0"); admin_log('verify_user', "user #$id"); $flash = "Account verified.";
    } elseif ($action === 'unverify_user') {
        mysqli_query($conn, "UPDATE users SET verified=0 WHERE id=$id"); admin_log('unverify_user', "user #$id"); $flash = "Verification removed.";
    }
}

function scalar($conn, $sql) { return (int)(mysqli_fetch_assoc(mysqli_query($conn, $sql))['n'] ?? 0); }
$nPending = scalar($conn, "SELECT COUNT(*) n FROM listings WHERE status='pending'");
$nLive    = scalar($conn, "SELECT COUNT(*) n FROM listings WHERE status='approved' AND hidden=0 AND avail<>'sold'");
$nSold    = scalar($conn, "SELECT COUNT(*) n FROM listings WHERE avail='sold'");
$nUsers   = scalar($conn, "SELECT COUNT(*) n FROM users WHERE is_admin=0");
$nReviews = scalar($conn, "SELECT COUNT(*) n FROM reviews");
$nActive  = scalar($conn, "SELECT COUNT(*) n FROM listings WHERE avail='reserved'");
$nVerify  = scalar($conn, "SELECT COUNT(*) n FROM users WHERE is_admin=0 AND verified=0");
$verifyQ  = mysqli_query($conn, "SELECT * FROM users WHERE is_admin=0 AND verified=0 ORDER BY verify_requested DESC, id ASC");

$pending  = mysqli_query($conn, "SELECT l.*, u.name seller FROM listings l JOIN users u ON u.id=l.user_id WHERE l.status='pending' ORDER BY l.created_at ASC");
$all      = mysqli_query($conn, "SELECT l.*, u.name seller FROM listings l JOIN users u ON u.id=l.user_id WHERE l.status='approved' ORDER BY l.created_at DESC");
// Transactions (buyings): anything reserved or sold.
$txns     = mysqli_query($conn, "SELECT l.*, s.name seller, b.name buyer FROM listings l
                                 JOIN users s ON s.id=l.user_id LEFT JOIN users b ON b.id=l.reserved_by
                                 WHERE l.avail IN ('reserved','sold') ORDER BY l.id DESC");
$reviews  = mysqli_query($conn, "SELECT r.*, rr.name reviewer, re.name reviewee, l.title FROM reviews r
                                 JOIN users rr ON rr.id=r.reviewer_id JOIN users re ON re.id=r.reviewee_id
                                 LEFT JOIN listings l ON l.id=r.listing_id ORDER BY r.id DESC");
$users    = mysqli_query($conn, "SELECT * FROM users WHERE is_admin=0 ORDER BY banned DESC, id ASC");

require __DIR__ . '/includes/header.php';
?>

<div class="section-head" style="margin-top:26px">
    <h1 class="page-title" style="margin:0">Admin panel</h1>
    <a class="btn btn-ghost" href="logout.php">Log out of admin</a>
</div>
<p class="page-sub">Full visibility over users, listings, transactions and reviews.</p>

<?php if ($flash): ?><div class="flash ok"><?= $flash ?></div><?php endif; ?>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr))">
    <div class="stat"><div class="n"><?= $nPending ?></div><div class="l">Pending</div></div>
    <div class="stat"><div class="n"><?= $nVerify ?></div><div class="l">To verify</div></div>
    <div class="stat"><div class="n"><?= $nLive ?></div><div class="l">Live</div></div>
    <div class="stat"><div class="n"><?= $nActive ?></div><div class="l">In progress</div></div>
    <div class="stat"><div class="n"><?= $nSold ?></div><div class="l">Sold</div></div>
    <div class="stat"><div class="n"><?= $nUsers ?></div><div class="l">Students</div></div>
    <div class="stat"><div class="n"><?= $nReviews ?></div><div class="l">Reviews</div></div>
</div>

<!-- Account verifications -->
<div class="section-head"><h2>Account verifications (<?= $nVerify ?>)</h2><span class="count-line">New accounts — check name &amp; email</span></div>
<?php if ($nVerify === 0): ?><p style="color:var(--muted);margin-bottom:26px">Every account is verified.</p><?php endif; ?>
<?php while ($u = mysqli_fetch_assoc($verifyQ)): ?>
    <div class="row">
        <span class="avatar" style="width:44px;height:44px;font-size:14px"><?= e(initials($u['name'])) ?></span>
        <div class="grow">
            <div class="title"><?= e($u['name']) ?>
                <?php if ((int)$u['verify_requested'] === 1): ?><span class="status pending" style="margin-left:6px">Requested</span><?php endif; ?></div>
            <div class="meta"><?= e($u['email']) ?> ·
                <?= (int)$u['email_verified'] === 1 ? 'email verified ✓' : 'email not verified' ?> ·
                <?= e($u['hostel']) ?> · joined <?= e(date('d M Y', strtotime($u['created_at']))) ?></div>
        </div>
        <div class="actions">
            <form method="post"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-primary btn-sm" name="action" value="verify_user">Verify account</button></form>
        </div>
    </div>
<?php endwhile; ?>

<!-- Pending -->
<div class="section-head"><h2>Pending review (<?= $nPending ?>)</h2></div>
<?php if ($nPending === 0): ?><p style="color:var(--muted);margin-bottom:26px">Queue is clear.</p><?php endif; ?>
<?php while ($l = mysqli_fetch_assoc($pending)): ?>
    <div class="row">
        <div class="thumb"><?php if ($l['photo']): ?><img src="uploads/<?= e(rawurlencode($l['photo'])) ?>" alt=""><?php endif; ?></div>
        <div class="grow"><div class="title"><?= e($l['title']) ?></div><div class="meta">by <?= e($l['seller']) ?> · <?= e(category_label($l)) ?> · <?= e(money($l['price'])) ?></div></div>
        <div class="actions">
            <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-primary btn-sm" name="action" value="approve">Approve</button></form>
            <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="reject" onclick="return confirm('Reject and delete?')">Reject</button></form>
        </div>
    </div>
<?php endwhile; ?>

<!-- Transactions -->
<div class="section-head" style="margin-top:30px"><h2>Transactions</h2><span class="count-line">Every reservation &amp; sale</span></div>
<?php if (mysqli_num_rows($txns) === 0): ?><p style="color:var(--muted);margin-bottom:26px">No purchases yet.</p><?php endif; ?>
<?php while ($t = mysqli_fetch_assoc($txns)):
    $st = listing_state($t); $done = ((int)$t['seller_confirmed'] && (int)$t['buyer_confirmed']); ?>
    <div class="row">
        <div class="grow">
            <div class="title"><?= e($t['title']) ?> — <?= e(money($t['price'])) ?></div>
            <div class="meta"><strong><?= e($t['buyer'] ?? '—') ?></strong> ⭢ buying from <strong><?= e($t['seller']) ?></strong>
                · delivered <?= (int)$t['seller_confirmed'] ? '✓' : '—' ?> · received <?= (int)$t['buyer_confirmed'] ? '✓' : '—' ?></div>
        </div>
        <div class="actions"><span class="status <?= $st ?>"><?= $done ? 'Completed' : ucfirst($st) ?></span>
            <a class="btn btn-ghost btn-sm" href="product.php?id=<?= (int)$t['id'] ?>">Open</a></div>
    </div>
<?php endwhile; ?>

<!-- Reviews -->
<div class="section-head" style="margin-top:30px"><h2>Reviews (<?= $nReviews ?>)</h2></div>
<?php if ($nReviews === 0): ?><p style="color:var(--muted);margin-bottom:26px">No reviews yet.</p><?php endif; ?>
<?php while ($r = mysqli_fetch_assoc($reviews)): ?>
    <div class="row">
        <div class="grow">
            <div class="title"><span class="stars-static"><?= stars($r['rating']) ?></span> &nbsp;<?= e($r['reviewer']) ?> → <?= e($r['reviewee']) ?> <span class="meta">(as <?= e($r['role']) ?>)</span></div>
            <div class="meta"><?= e($r['title'] ?? 'listing') ?><?= trim($r['body']) !== '' ? ' — “' . e($r['body']) . '”' : '' ?></div>
        </div>
        <div class="actions"><form method="post"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="delete_review" onclick="return confirm('Delete this review?')">Delete</button></form></div>
    </div>
<?php endwhile; ?>

<!-- All listings -->
<div class="section-head" style="margin-top:30px"><h2>All listings</h2><span class="count-line">Hide, show or remove</span></div>
<?php while ($l = mysqli_fetch_assoc($all)):
    $state = listing_state($l); ?>
    <div class="row">
        <div class="thumb"><?php if ($l['photo']): ?><img src="uploads/<?= e(rawurlencode($l['photo'])) ?>" alt=""><?php endif; ?></div>
        <div class="grow"><div class="title"><?= e($l['title']) ?></div><div class="meta">by <?= e($l['seller']) ?> · <?= e(category_label($l)) ?> · <?= e(money($l['price'])) ?></div></div>
        <div class="actions">
            <?php if ((int)$l['hidden'] === 1): ?><span class="status hidden">Hidden</span><?php else: ?><span class="status <?= $state ?>"><?= ucfirst($state) ?></span><?php endif; ?>
            <a class="btn btn-ghost btn-sm" href="product.php?id=<?= (int)$l['id'] ?>">View</a>
            <?php if ((int)$l['hidden'] === 1): ?>
                <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-dark btn-sm" name="action" value="show">Show</button></form>
            <?php else: ?>
                <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="hide">Hide</button></form>
            <?php endif; ?>
            <form method="post"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="remove_listing" onclick="return confirm('Remove permanently?')">Remove</button></form>
        </div>
    </div>
<?php endwhile; ?>

<!-- Users (full data) -->
<div class="section-head" style="margin-top:30px"><h2>Students (<?= $nUsers ?>)</h2><span class="count-line">All user data</span></div>
<?php while ($u = mysqli_fetch_assoc($users)):
    $uid = (int)$u['id'];
    $nL = scalar($conn, "SELECT COUNT(*) n FROM listings WHERE user_id=$uid");
    $nB = scalar($conn, "SELECT COUNT(*) n FROM listings WHERE reserved_by=$uid");
    $rt = user_rating($conn, $uid); ?>
    <div class="row">
        <span class="avatar" style="width:44px;height:44px;font-size:14px"><?= e(initials($u['name'])) ?></span>
        <div class="grow">
            <div class="title"><?= e($u['name']) ?><?= verified_tick($u, 15) ?>
                <?php if ($rt['count'] > 0): ?><span class="rating-badge" style="margin-left:6px"><span class="s">★</span><?= e($rt['avg']) ?></span><?php endif; ?></div>
            <div class="meta"><?= e($u['email']) ?> · <?= e($u['hostel']) ?> · joined <?= e(date('d M Y', strtotime($u['created_at']))) ?> · <?= $nL ?> listing<?= $nL === 1 ? '' : 's' ?> · <?= $nB ?> purchase<?= $nB === 1 ? '' : 's' ?></div>
        </div>
        <div class="actions">
            <?php if ((int)$u['verified'] === 1): ?>
                <form method="post"><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-ghost btn-sm" name="action" value="unverify_user">Unverify</button></form>
            <?php else: ?>
                <form method="post"><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-primary btn-sm" name="action" value="verify_user">Verify</button></form>
            <?php endif; ?>
            <?php if ((int)$u['banned'] === 1): ?>
                <span class="status banned">Blocked</span>
                <form method="post"><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-dark btn-sm" name="action" value="unban">Unblock</button></form>
            <?php else: ?>
                <form method="post"><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-danger btn-sm" name="action" value="ban" onclick="return confirm('Block this user?')">Block</button></form>
            <?php endif; ?>
        </div>
    </div>
<?php endwhile; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
