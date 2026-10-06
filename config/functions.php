<?php
/**
 * functions.php  —  helper functions used across the site.
 * Demonstrates: PHP functions, arrays, strings, sessions & cookies.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

/** How long a "buy" reservation blocks an item, in minutes. */
const RESERVE_MINUTES = 20;

/** Make text safe to print inside HTML. */
function e($text) { return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'); }

/** The categories a listing can belong to. "Other" allows a custom label. */
function categories() { return ['Electronic', 'Food', 'Posters/Decor', 'Tickets', 'Other']; }

/** Campus pickup spots. */
function locations() { return ['Rise', 'Pool area', 'Fountain', 'iCreate', 'Library']; }

/** Redirect and stop. */
function redirect($url) { header("Location: $url"); exit; }

/* ---- Sessions / current user --------------------------------------- */
function is_logged_in() { return isset($_SESSION['user_id']); }
function is_admin()     { return !empty($_SESSION['is_admin']); }

function current_user($conn)
{
    if (!is_logged_in()) return null;
    $id  = (int)$_SESSION['user_id'];
    $res = mysqli_query($conn, "SELECT * FROM users WHERE id = $id LIMIT 1");
    return mysqli_fetch_assoc($res);
}

function require_login()
{
    if (!is_logged_in()) redirect('login.php');
}

/** Protect the admin pages — only a signed-in admin may pass. */
function require_admin()
{
    if (!is_admin()) redirect('admin_login.php');
}

/* ---- Small display helpers ----------------------------------------- */
function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : '';
    $last  = '';
    if (count($parts) > 1) {
        $lw = $parts[count($parts) - 1];
        $last = isset($lw[0]) ? strtoupper($lw[0]) : '';
    }
    return $first . $last;
}

function money($price)
{
    return (int)$price === 0 ? 'Free' : '₹' . number_format((int)$price);
}

/** The label to show for a listing's category (handles the custom "Other"). */
function category_label($row)
{
    if (($row['category'] ?? '') === 'Other' && !empty($row['custom_category'])) {
        return $row['custom_category'];
    }
    return $row['category'] ?? '';
}

/**
 * The live availability of a listing: 'available', 'reserved' or 'sold'.
 * A reservation that is older than RESERVE_MINUTES is treated as expired.
 */
function listing_state($row)
{
    if (($row['avail'] ?? '') === 'sold') return 'sold';
    if (($row['avail'] ?? '') === 'reserved') {
        // A frozen hold never expires; otherwise it lapses after the timer.
        if (!empty($row['frozen'])) return 'reserved';
        if (!empty($row['reserved_until']) && strtotime($row['reserved_until']) > time()) return 'reserved';
    }
    return 'available';
}

/** Is this reservation frozen (held indefinitely for delivery)? */
function is_frozen($row) { return !empty($row['frozen']); }

/** Minutes left on a reservation (0 if none / expired; -1 if frozen). */
function reserve_minutes_left($row)
{
    if (!empty($row['frozen'])) return -1;            // frozen = on hold, no countdown
    if (empty($row['reserved_until'])) return 0;
    $left = strtotime($row['reserved_until']) - time();
    return $left > 0 ? (int)ceil($left / 60) : 0;
}

/** A short label for a reservation's remaining time. */
function hold_label($row)
{
    if (!empty($row['frozen'])) return 'On hold';
    $m = reserve_minutes_left($row);
    return $m . ' min left';
}

/** Has this user already left a review for this listing/transaction? */
function has_reviewed($conn, $listingId, $reviewerId)
{
    $lid = (int)$listingId; $rid = (int)$reviewerId;
    $res = mysqli_query($conn, "SELECT COUNT(*) n FROM reviews WHERE listing_id=$lid AND reviewer_id=$rid");
    return (int)mysqli_fetch_assoc($res)['n'] > 0;
}

/** A user's average star rating and how many reviews they've received. */
function user_rating($conn, $userId)
{
    $uid = (int)$userId;
    $r = mysqli_query($conn, "SELECT ROUND(AVG(rating),1) avg, COUNT(*) n FROM reviews WHERE reviewee_id=$uid");
    $row = mysqli_fetch_assoc($r);
    return ['avg' => $row['avg'], 'count' => (int)$row['n']];
}

/** Render a row of ★ / ☆ for a 1–5 rating. */
function stars($rating)
{
    $rating = (int)$rating; $out = '';
    for ($i = 1; $i <= 5; $i++) $out .= $i <= $rating ? '★' : '☆';
    return $out;
}

/** A small blue "verified by admin" tick, shown only for verified accounts. */
function verified_tick($user, $size = 15)
{
    if (empty($user['verified'])) return '';
    return '<span class="vtick" title="Verified by campus admin" aria-label="Verified">'
        . '<svg width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none">'
        . '<path d="M12 2l2.4 1.8 3 .1 .9 2.9 2.4 1.8-.9 2.9.9 2.9-2.4 1.8-.9 2.9-3 .1L12 22l-2.4-1.8-3-.1-.9-2.9L3.3 15.4l.9-2.9-.9-2.9 2.4-1.8.9-2.9 3-.1z" fill="currentColor"/>'
        . '<path d="M8.5 12.2l2.3 2.3 4.7-4.9" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
}

/** All photo filenames for a listing: the primary (listings.photo) first,
 *  then any extras from listing_photos. */
function listing_gallery($conn, $listing)
{
    $photos = [];
    if (!empty($listing['photo'])) $photos[] = $listing['photo'];
    $lid = (int)$listing['id'];
    $res = mysqli_query($conn, "SELECT filename FROM listing_photos WHERE listing_id=$lid ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($res)) $photos[] = $row['filename'];
    return $photos;
}

/** Count of conversation threads for the header badge. */
function message_thread_count($conn)
{
    if (!is_logged_in()) return 0;
    $uid = (int)$_SESSION['user_id'];
    $res = mysqli_query($conn, "
        SELECT COUNT(DISTINCT other) AS c FROM (
            SELECT CASE WHEN sender_id = $uid THEN receiver_id ELSE sender_id END AS other
            FROM messages WHERE sender_id = $uid OR receiver_id = $uid
        ) t");
    return (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
}
?>
