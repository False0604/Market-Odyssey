<?php
/**
 * index.php  —  the marketplace home page.
 * Reads the search + category filters, lists approved & visible listings,
 * hides the current user's OWN listings (those live under their profile),
 * and shows a reserved ribbon when an item is blocked by a pending purchase.
 */
require_once __DIR__ . '/config/functions.php';
$pageTitle = 'Marketplace';
$me   = current_user($conn);
$myId = $me ? (int)$me['id'] : 0;

$q        = trim($_GET['q'] ?? '');
$category = $_GET['cat'] ?? 'All';

// Base rules: approved by admin, not hidden, not sold, seller not banned.
$where = "l.status = 'approved' AND l.hidden = 0 AND l.avail <> 'sold' AND u.banned = 0";
if ($myId > 0) {
    $where .= " AND l.user_id <> $myId";           // don't show me my own items here
}
if ($category !== 'All' && in_array($category, categories(), true)) {
    $safeCat = mysqli_real_escape_string($conn, $category);
    $where  .= " AND l.category = '$safeCat'";
}
if ($q !== '') {
    $s = mysqli_real_escape_string($conn, $q);
    $where .= " AND (l.title LIKE '%$s%' OR l.description LIKE '%$s%' OR l.custom_category LIKE '%$s%')";
}

$sql = "SELECT l.*, u.name AS seller, u.hostel AS seller_spot
        FROM listings l JOIN users u ON u.id = l.user_id
        WHERE $where ORDER BY l.created_at DESC, l.id DESC";
$listings = mysqli_query($conn, $sql);
$total    = mysqli_num_rows($listings);

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <span class="kicker">Woxsen Campus · Student-run</span>
    <h1>Everything students <span class="u">buy, sell &amp; swap.</span></h1>
    <p>List an item in a minute, message the seller, and meet up on campus — no endless WhatsApp scrolling.</p>
    <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="#listings">Browse listings</a>
        <a class="btn btn-ghost btn-lg" href="<?= is_logged_in() ? 'sell.php' : 'login.php' ?>">Sell an item</a>
    </div>
    <div class="hero-trust">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
        Every listing is admin-approved before it goes live.
    </div>
</section>

<div class="section-head" id="listings">
    <h2><?= $category === 'All' ? 'Fresh on campus' : e($category) ?></h2>
    <span class="count-line">
        <?php if ($q !== ''): ?>
            <?= $total ?> result<?= $total === 1 ? '' : 's' ?> for &ldquo;<?= e($q) ?>&rdquo;
        <?php else: ?>
            <?= $total ?> item<?= $total === 1 ? '' : 's' ?> available
        <?php endif; ?>
    </span>
</div>

<div class="grid">
    <?php if ($total === 0): ?>
        <div class="empty-market">
            <h3><?= $q !== '' ? 'No matches found' : 'Nothing here yet' ?></h3>
            <p><?= $q !== '' ? 'Try another search or category.' : 'Be the first to list something for your fellow students.' ?></p>
            <a class="btn btn-primary" href="<?= is_logged_in() ? 'sell.php' : 'login.php' ?>">Sell an item</a>
        </div>
    <?php endif; ?>

    <?php while ($item = mysqli_fetch_assoc($listings)):
        $isFree = ((int)$item['price'] === 0);
        $photo  = $item['photo'] ? 'uploads/' . rawurlencode($item['photo']) : '';
        $state  = listing_state($item);
    ?>
        <a class="card" href="product.php?id=<?= (int)$item['id'] ?>">
            <?php if ($state === 'reserved'): ?><span class="ribbon reserved">Reserved</span><?php endif; ?>
            <div class="photo">
                <span class="cat-tag"><?= e(category_label($item)) ?></span>
                <?php if ($photo): ?>
                    <img src="<?= e($photo) ?>" alt="<?= e($item['title']) ?>">
                <?php else: ?>
                    <span class="ph">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        No photo
                    </span>
                <?php endif; ?>
            </div>
            <div class="body">
                <div class="title"><?= e($item['title']) ?></div>
                <div class="price <?= $isFree ? 'free' : '' ?>">
                    <?php if ($isFree): ?>Free<?php else: ?><span class="cur">₹</span><?= number_format((int)$item['price']) ?><?php endif; ?>
                </div>
                <div class="seller">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <?= e($item['seller']) ?> · <?= e($item['pickup']) ?>
                </div>
                <div class="pay">
                    <?php if ((int)$item['pay_cod']): ?><span class="pill">COD</span><?php endif; ?>
                    <?php if ((int)$item['pay_upi']): ?><span class="pill green">UPI</span><?php endif; ?>
                </div>
            </div>
        </a>
    <?php endwhile; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
