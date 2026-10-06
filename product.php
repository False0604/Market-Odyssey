<?php
/**
 * product.php  —  a single listing + the buy / reserve / confirm / review flow.
 *
 * Flow:
 *   1. Buyer clicks Purchase  -> item reserved 20 min, seller notified.
 *   2. Seller confirms "delivered", buyer confirms "received" (a window each).
 *   3. When BOTH confirm      -> item marked sold, and each party gets a
 *      window to leave a review of the other.
 */
require_once __DIR__ . '/config/functions.php';
$me   = current_user($conn);
$myId = $me ? (int)$me['id'] : 0;
$id   = (int)($_GET['id'] ?? 0);

function load_listing($conn, $id)
{
    $res = mysqli_query($conn, "
        SELECT l.*, u.name AS seller, u.hostel AS seller_spot, u.id AS seller_id, u.verified AS seller_verified
        FROM listings l JOIN users u ON u.id = l.user_id WHERE l.id = $id LIMIT 1");
    return mysqli_fetch_assoc($res);
}
function notify($conn, $listingId, $from, $to, $text)
{
    $t = mysqli_real_escape_string($conn, $text);
    mysqli_query($conn, "INSERT INTO messages (listing_id, sender_id, receiver_id, body) VALUES ($listingId, $from, $to, '$t')");
}

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    require_login();
    $action = $_POST['action'] ?? '';
    $item   = load_listing($conn, $id);
    $isSeller = $item && ((int)$item['seller_id'] === $myId);
    $isBuyer  = $item && ((int)$item['reserved_by'] === $myId);

    if ($item && $action === 'buy') {
        $state = listing_state($item);
        if ($isSeller)              $flash = "You can't buy your own listing.";
        elseif ($state !== 'available') $flash = 'Sorry — this item is no longer available to buy.';
        else {
            $until = date('Y-m-d H:i:s', time() + RESERVE_MINUTES * 60);
            mysqli_query($conn, "UPDATE listings SET avail='reserved', reserved_by=$myId, reserved_until='$until',
                                 seller_confirmed=0, buyer_confirmed=0 WHERE id=$id AND avail <> 'sold'");
            if (mysqli_affected_rows($conn) === 1) {
                notify($conn, $id, $myId, (int)$item['seller_id'],
                    "🛒 " . $me['name'] . " wants to buy \"" . $item['title'] . "\". Reserved for you for "
                    . RESERVE_MINUTES . " min — arrange the handover, then confirm delivery.");
                redirect("product.php?id=$id&bought=1");
            } else $flash = 'Someone just reserved this item.';
        }
    } elseif ($item && $action === 'cancel' && $isBuyer) {
        mysqli_query($conn, "UPDATE listings SET avail='available', reserved_by=0, reserved_until=NULL, seller_confirmed=0, buyer_confirmed=0, frozen=0 WHERE id=$id");
        redirect("product.php?id=$id&cancelled=1");

    } elseif ($item && $action === 'freeze' && ($isSeller || $isBuyer) && $item['avail'] === 'reserved') {
        // Freeze the hold so it won't expire while the handover is arranged.
        mysqli_query($conn, "UPDATE listings SET frozen=1 WHERE id=$id");
        redirect("product.php?id=$id&frozen=1");

    } elseif ($item && $action === 'unfreeze' && ($isSeller || $isBuyer) && $item['avail'] === 'reserved') {
        // Resume the countdown from now.
        $until = date('Y-m-d H:i:s', time() + RESERVE_MINUTES * 60);
        mysqli_query($conn, "UPDATE listings SET frozen=0, reserved_until='$until' WHERE id=$id");
        redirect("product.php?id=$id&unfrozen=1");

    } elseif ($item && $action === 'confirm_delivered' && $isSeller && $item['avail'] === 'reserved') {
        mysqli_query($conn, "UPDATE listings SET seller_confirmed=1 WHERE id=$id");
        maybe_complete($conn, $id, $me['name']);
        redirect("product.php?id=$id&confirmed=1");

    } elseif ($item && $action === 'confirm_received' && $isBuyer && $item['avail'] === 'reserved') {
        mysqli_query($conn, "UPDATE listings SET buyer_confirmed=1 WHERE id=$id");
        maybe_complete($conn, $id, $me['name']);
        redirect("product.php?id=$id&confirmed=1");

    } elseif ($item && $action === 'leave_review' && ($isSeller || $isBuyer)) {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $body   = mysqli_real_escape_string($conn, trim($_POST['body'] ?? ''));
        $role   = $isSeller ? 'seller' : 'buyer';
        $reviewee = $isSeller ? (int)$item['reserved_by'] : (int)$item['seller_id'];
        if ((int)$item['seller_confirmed'] === 1 && (int)$item['buyer_confirmed'] === 1
            && $reviewee > 0 && !has_reviewed($conn, $id, $myId)) {
            mysqli_query($conn, "INSERT INTO reviews (listing_id, reviewer_id, reviewee_id, role, rating, body)
                                 VALUES ($id, $myId, $reviewee, '$role', $rating, '$body')");
        }
        redirect("product.php?id=$id&reviewed=1");
    }
}

/** If both sides have confirmed, mark the item sold and tell them. */
function maybe_complete($conn, $id, $byName)
{
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM listings WHERE id=$id"));
    if ($r && (int)$r['seller_confirmed'] === 1 && (int)$r['buyer_confirmed'] === 1 && $r['avail'] !== 'sold') {
        mysqli_query($conn, "UPDATE listings SET avail='sold' WHERE id=$id");
        $seller = (int)$r['user_id']; $buyer = (int)$r['reserved_by'];
        $msg = "✅ Both sides confirmed \"" . $r['title'] . "\" — the deal is complete. Leave a review!";
        notify($conn, $id, $seller, $buyer, $msg);
        notify($conn, $id, $buyer, $seller, $msg);
    }
}

$item = load_listing($conn, $id);
if (!$item || ((int)$item['hidden'] === 1 || $item['status'] !== 'approved') && !is_admin()) {
    $pageTitle = 'Not found';
    require __DIR__ . '/includes/header.php';
    echo '<a class="back" href="index.php">‹ Back to marketplace</a>';
    echo '<h1 class="page-title">Listing not available</h1><p class="page-sub">It may have been sold, hidden or removed.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle    = $item['title'];
$isFree       = ((int)$item['price'] === 0);
$photo        = $item['photo'] ? 'uploads/' . rawurlencode($item['photo']) : '';
$gallery      = listing_gallery($conn, $item);   // all photos (primary first)
$qr           = $item['qr'] ? 'uploads/' . rawurlencode($item['qr']) : '';
$state        = listing_state($item);
$isSeller     = ($myId === (int)$item['seller_id']);
$isBuyer      = ($myId === (int)$item['reserved_by'] && $myId > 0);
$reservedByMe = ($state === 'reserved' && $isBuyer);
$minsLeft     = reserve_minutes_left($item);
$frozen       = is_frozen($item);
$chatUrl      = 'messages.php?to=' . (int)$item['seller_id'] . '&listing=' . (int)$item['id'];
$sc = (int)$item['seller_confirmed']; $bc = (int)$item['buyer_confirmed'];

// The other party's name (for the confirm/review windows).
$otherName = '';
if ($isSeller && (int)$item['reserved_by'] > 0) {
    $otherName = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM users WHERE id=" . (int)$item['reserved_by']))['name'] ?? 'the buyer';
} elseif ($isBuyer) { $otherName = $item['seller']; }

// Does a window need to pop for me right now?
$needConfirm = ($state === 'reserved') && (($isSeller && !$sc) || ($isBuyer && !$bc));
$completed   = ($sc === 1 && $bc === 1 && (int)$item['reserved_by'] > 0);
$needReview  = $completed && ($isSeller || $isBuyer) && !has_reviewed($conn, $id, $myId);

$sellerRating = user_rating($conn, (int)$item['seller_id']);

require __DIR__ . '/includes/header.php';
?>

<a class="back" href="index.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    Back to marketplace
</a>

<?php if (isset($_GET['bought'])): ?>
    <div class="flash ok">✅ Reserved for you for <?= RESERVE_MINUTES ?> minutes. Meet the seller, then confirm you received it below.</div>
<?php elseif (isset($_GET['cancelled'])): ?>
    <div class="flash info">Your reservation was released. The item is available again.</div>
<?php elseif (isset($_GET['frozen'])): ?>
    <div class="flash ok">❄ Hold frozen — this item stays reserved for you until you resume the timer or complete the deal.</div>
<?php elseif (isset($_GET['unfrozen'])): ?>
    <div class="flash info">Timer resumed — <?= RESERVE_MINUTES ?> minutes on the clock again.</div>
<?php elseif (isset($_GET['confirmed'])): ?>
    <div class="flash ok">Confirmation saved.<?= $completed ? ' The deal is complete — leave a review!' : ' Waiting for the other person to confirm too.' ?></div>
<?php elseif (isset($_GET['reviewed'])): ?>
    <div class="flash ok">🙏 Thanks for your review.</div>
<?php elseif ($flash): ?>
    <div class="flash bad"><?= e($flash) ?></div>
<?php endif; ?>

<section class="detail">
    <div>
        <div class="big-photo">
            <?php if (!empty($gallery)): ?>
                <img id="galleryMain" src="uploads/<?= e(rawurlencode($gallery[0])) ?>" alt="<?= e($item['title']) ?>">
            <?php else: ?>
                <div style="display:grid;justify-items:center;gap:10px"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>No photo provided</div>
            <?php endif; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
            <div class="gallery-thumbs">
                <?php foreach ($gallery as $i => $g): $src = 'uploads/' . rawurlencode($g); ?>
                    <button type="button" class="g-thumb <?= $i === 0 ? 'active' : '' ?>" onclick="swapGalleryPhoto('<?= e($src) ?>', this)">
                        <img src="<?= e($src) ?>" alt="photo <?= $i + 1 ?>">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <span class="cat-tag" style="position:static;display:inline-block"><?= e(category_label($item)) ?></span>
        <h1><?= e($item['title']) ?></h1>
        <div class="price <?= $isFree ? 'free' : '' ?>"><?php if ($isFree): ?>Free<?php else: ?><span class="cur">₹</span><?= number_format((int)$item['price']) ?><?php endif; ?></div>

        <div class="state-line">
            <?php if ($state === 'available'): ?><span class="chip-state available">Available</span>
            <?php elseif ($state === 'reserved' && $frozen): ?><span class="chip-state frozen">❄ Reserved · on hold</span>
            <?php elseif ($state === 'reserved'): ?><span class="chip-state reserved">Reserved · <?= $minsLeft ?> min left</span>
            <?php else: ?><span class="chip-state sold">Sold</span><?php endif; ?>
            <span class="verified"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Admin-approved</span>
        </div>

        <div class="seller-card">
            <span class="avatar"><?= e(initials($item['seller'])) ?></span>
            <div>
                <div class="who"><?= e($item['seller']) ?><?= verified_tick(['verified' => $item['seller_verified']], 16) ?></div>
                <div class="meta">
                    Pickup near <?= e($item['pickup']) ?> · <?= e($item['item_condition']) ?>
                    <?php if ($sellerRating['count'] > 0): ?>
                        &nbsp;·&nbsp;<span class="rating-badge"><span class="s">★</span><?= e($sellerRating['avg']) ?> (<?= $sellerRating['count'] ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="label-tiny">Payment accepted</div>
        <div class="pay" style="margin-bottom:16px">
            <?php if ((int)$item['pay_cod']): ?><span class="pill">Cash on delivery</span><?php endif; ?>
            <?php if ((int)$item['pay_upi']): ?><span class="pill green">UPI</span><?php endif; ?>
        </div>
        <?php if ((int)$item['pay_upi'] && $qr): ?>
            <div class="label-tiny">Scan to pay (UPI)</div><div class="qr-box"><img src="<?= e($qr) ?>" alt="Seller UPI QR"></div>
        <?php endif; ?>

        <!-- ================= actions ================= -->
        <div style="margin-top:22px">
        <?php if ($state === 'reserved' && ($isSeller || $isBuyer)): ?>
            <!-- both parties see the handover progress -->
            <div class="confirm-steps">
                <div class="confirm-step <?= $sc ? 'done' : '' ?>"><span class="tick"><?= $sc ? '✓' : '' ?></span>Seller delivered</div>
                <div class="confirm-step <?= $bc ? 'done' : '' ?>"><span class="tick"><?= $bc ? '✓' : '' ?></span>Buyer received</div>
            </div>
            <!-- Freeze the hold for delivery convenience -->
            <div style="display:flex;align-items:center;gap:12px;margin:0 0 16px">
                <?php if ($frozen): ?>
                    <form method="post"><input type="hidden" name="action" value="unfreeze"><button class="btn btn-ghost btn-sm">❄ Frozen — resume timer</button></form>
                    <span class="count-line">On hold — it won't be released until you resume.</span>
                <?php else: ?>
                    <form method="post"><input type="hidden" name="action" value="freeze"><button class="btn btn-ghost btn-sm">❄ Freeze hold</button></form>
                    <span class="count-line">Keep it reserved past the <?= RESERVE_MINUTES ?>-min timer while you arrange delivery.</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isSeller && $state !== 'reserved'): ?>
            <div class="notice-inline"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>This is your listing. Manage it from <a href="account.php">your profile</a>.</div>

        <?php elseif ($needReview): ?>
            <div class="detail-actions">
                <button class="btn btn-buy btn-lg" onclick="document.getElementById('reviewDialog').showModal()">Leave a review</button>
                <a class="btn btn-ghost btn-lg" href="<?= e($chatUrl) ?>">Message</a>
            </div>

        <?php elseif ($state === 'sold'): ?>
            <div class="notice-inline">This item has been sold.<?= $completed ? ' Deal completed.' : '' ?></div>

        <?php elseif ($needConfirm): ?>
            <div class="detail-actions">
                <?php if ($isSeller): ?>
                    <button class="btn btn-buy btn-lg" onclick="document.getElementById('confirmDialog').showModal()">Confirm delivery</button>
                <?php else: ?>
                    <button class="btn btn-buy btn-lg" onclick="document.getElementById('confirmDialog').showModal()">Confirm you received it</button>
                <?php endif; ?>
                <a class="btn btn-ghost btn-lg" href="<?= e($chatUrl) ?>">Message</a>
                <?php if ($isBuyer): ?><form method="post"><input type="hidden" name="action" value="cancel"><button class="btn btn-ghost btn-lg">Release</button></form><?php endif; ?>
            </div>

        <?php elseif ($state === 'reserved' && ($isSeller || $isBuyer)): ?>
            <div class="notice-inline">You've confirmed your side. Waiting for <?= e($otherName ?: 'the other person') ?> to confirm too.</div>
            <a class="btn btn-ghost btn-lg" href="<?= e($chatUrl) ?>">Message</a>

        <?php elseif ($state === 'reserved'): ?>
            <div class="notice-inline">Reserved by another buyer (<?= e(hold_label($item)) ?>). You can still message the seller.</div>
            <a class="btn btn-ghost btn-lg" href="<?= is_logged_in() ? e($chatUrl) : 'login.php' ?>">Message seller</a>

        <?php else: /* available */ ?>
            <div class="detail-actions">
                <?php if (is_logged_in()): ?>
                    <form method="post"><input type="hidden" name="action" value="buy">
                        <button class="btn btn-buy btn-lg" onclick="return confirm('Reserve and buy this item? It will be held for you for <?= RESERVE_MINUTES ?> minutes.')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2 3h3l2.4 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L23 7H6"/></svg>Purchase
                        </button>
                    </form>
                    <a class="btn btn-ghost btn-lg" href="<?= e($chatUrl) ?>">Message seller</a>
                <?php else: ?>
                    <a class="btn btn-buy btn-lg" href="login.php">Log in to buy</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>

        <h3>Description</h3>
        <p class="desc"><?= nl2br(e($item['description'])) ?></p>
    </div>
</section>

<?php
// Reviews written about this seller (social proof).
$rv = mysqli_query($conn, "SELECT r.*, u.name reviewer FROM reviews r JOIN users u ON u.id=r.reviewer_id
                           WHERE r.reviewee_id=" . (int)$item['seller_id'] . " AND r.role='buyer' ORDER BY r.id DESC LIMIT 5");
if (mysqli_num_rows($rv) > 0): ?>
    <div class="section-head" style="margin-top:10px"><h2>What buyers say about <?= e(strtok($item['seller'], ' ')) ?></h2></div>
    <div style="max-width:640px;margin-bottom:50px">
    <?php while ($r = mysqli_fetch_assoc($rv)): ?>
        <div class="review-card">
            <div class="head"><span class="avatar"><?= e(initials($r['reviewer'])) ?></span>
                <div><div style="font-weight:700;font-size:14px"><?= e($r['reviewer']) ?></div><div class="stars-static"><?= stars($r['rating']) ?></div></div>
            </div>
            <?php if (trim($r['body']) !== ''): ?><div class="body"><?= nl2br(e($r['body'])) ?></div><?php endif; ?>
        </div>
    <?php endwhile; ?>
    </div>
<?php endif; ?>

<!-- ================= Confirmation window ================= -->
<?php if ($needConfirm): ?>
<dialog class="modal" id="confirmDialog">
    <div class="modal-inner">
        <?php if ($isSeller): ?>
            <h3>Confirm delivery</h3>
            <p>Have you handed this item over to <strong><?= e($otherName ?: 'the buyer') ?></strong>?</p>
        <?php else: ?>
            <h3>Confirm you received it</h3>
            <p>Did you receive this item from <strong><?= e($otherName ?: 'the seller') ?></strong>?</p>
        <?php endif; ?>
        <div class="modal-item">
            <span class="thumb"><?php if ($photo): ?><img src="<?= e($photo) ?>" alt=""><?php endif; ?></span>
            <div><div class="who"><?= e($item['title']) ?></div><div class="sub"><?= e(money($item['price'])) ?> · <?= e($item['pickup']) ?></div></div>
        </div>
        <form method="post" class="modal-actions">
            <input type="hidden" name="action" value="<?= $isSeller ? 'confirm_delivered' : 'confirm_received' ?>">
            <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Not yet</button>
            <button class="btn btn-buy"><?= $isSeller ? 'Yes, I delivered it' : 'Yes, I received it' ?></button>
        </form>
    </div>
</dialog>
<script>window.addEventListener('DOMContentLoaded',function(){var d=document.getElementById('confirmDialog'); if(d&&!d.open) d.showModal();});</script>
<?php endif; ?>

<!-- ================= Review window ================= -->
<?php if ($needReview): ?>
<dialog class="modal" id="reviewDialog">
    <div class="modal-inner">
        <h3>Leave a review</h3>
        <p>How was your deal with <strong><?= e($otherName ?: 'them') ?></strong>?</p>
        <form method="post">
            <input type="hidden" name="action" value="leave_review">
            <div class="stars-input">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                    <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" <?= $s === 5 ? 'checked' : '' ?>>
                    <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</label>
                <?php endfor; ?>
            </div>
            <div class="field"><textarea name="body" placeholder="A line about how it went (optional)…" style="min-height:90px"></textarea></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Later</button>
                <button class="btn btn-buy">Post review</button>
            </div>
        </form>
    </div>
</dialog>
<script>window.addEventListener('DOMContentLoaded',function(){var d=document.getElementById('reviewDialog'); if(d&&!d.open) d.showModal();});</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
