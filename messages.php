<?php
/**
 * messages.php  —  the buyer/seller chat.
 * Demonstrates: sessions (who am I), MySQL queries, inserting a sent message,
 *               reading a text box, arrays & loops to render the conversation.
 */
require_once __DIR__ . '/config/functions.php';
require_login();
$me    = current_user($conn);
$myId  = (int)$me['id'];
$pageTitle = 'Messages';

// --- Sending a new message (form POST) ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = (int)($_POST['to'] ?? 0);
    $listing = (int)($_POST['listing'] ?? 0);
    $body    = trim($_POST['body'] ?? '');
    if ($to > 0 && $body !== '') {
        $safe = mysqli_real_escape_string($conn, $body);
        mysqli_query($conn, "INSERT INTO messages (listing_id, sender_id, receiver_id, body)
                             VALUES ($listing, $myId, $to, '$safe')");
    }
    // Redirect after POST so a refresh does not resend (good practice).
    redirect('messages.php?to=' . $to . '&listing=' . $listing);
}

// --- Build the list of conversation threads for the sidebar -------------
// A "thread" = the most recent message with each other person.
$threadsRes = mysqli_query($conn, "
    SELECT m.*,
           CASE WHEN m.sender_id = $myId THEN m.receiver_id ELSE m.sender_id END AS other_id
    FROM messages m
    WHERE m.sender_id = $myId OR m.receiver_id = $myId
    ORDER BY m.created_at DESC, m.id DESC");

$threads = [];        // other_id => [name, hostel, last message, listing title]
while ($row = mysqli_fetch_assoc($threadsRes)) {
    $oid = (int)$row['other_id'];
    if (!isset($threads[$oid])) {
        $threads[$oid] = $row;    // first seen = most recent (query is sorted)
    }
}

// --- Which conversation is open? ----------------------------------------
$openId = (int)($_GET['to'] ?? 0);
if ($openId === 0 && !empty($threads)) {
    $openId = (int)array_key_first($threads);   // default to newest thread
}
$listingId = (int)($_GET['listing'] ?? 0);

// --- Look up the other person + the chat messages -----------------------
$other = null;
$chat  = [];
$listingTitle = '';
if ($openId > 0) {
    $r = mysqli_query($conn, "SELECT * FROM users WHERE id = $openId LIMIT 1");
    $other = mysqli_fetch_assoc($r);

    $cRes = mysqli_query($conn, "
        SELECT * FROM messages
        WHERE (sender_id = $myId AND receiver_id = $openId)
           OR (sender_id = $openId AND receiver_id = $myId)
        ORDER BY created_at ASC, id ASC");
    while ($m = mysqli_fetch_assoc($cRes)) {
        $chat[] = $m;
        if ($listingId === 0 && (int)$m['listing_id'] > 0) {
            $listingId = (int)$m['listing_id'];
        }
    }
}
if ($listingId > 0) {
    $lr = mysqli_query($conn, "SELECT title FROM listings WHERE id = $listingId LIMIT 1");
    if ($lrow = mysqli_fetch_assoc($lr)) { $listingTitle = $lrow['title']; }
}

require __DIR__ . '/includes/header.php';
?>

<a class="back" href="index.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    Back to marketplace
</a>

<section class="messages">
    <!-- Left: list of conversations -->
    <aside class="thread-list">
        <h2>Messages</h2>
        <?php if (empty($threads)): ?>
            <p style="color:var(--muted)">No conversations yet. Open a listing and tap <em>Message seller</em> to start one.</p>
        <?php endif; ?>

        <?php foreach ($threads as $oid => $t):
            $ures = mysqli_query($conn, "SELECT name, hostel FROM users WHERE id = $oid LIMIT 1");
            $u = mysqli_fetch_assoc($ures);
            if (!$u) continue;
            $ltitle = '';
            if ((int)$t['listing_id'] > 0) {
                $lr = mysqli_query($conn, "SELECT title FROM listings WHERE id=".(int)$t['listing_id']." LIMIT 1");
                if ($lrow = mysqli_fetch_assoc($lr)) $ltitle = $lrow['title'];
            }
            $href = 'messages.php?to=' . $oid . '&listing=' . (int)$t['listing_id'];
        ?>
            <a class="thread <?= $oid === $openId ? 'active' : '' ?>" href="<?= e($href) ?>">
                <span class="avatar"><?= e(initials($u['name'])) ?></span>
                <span class="t-meta">
                    <span class="t-name"><?= e($u['name']) ?></span>
                    <?php if ($ltitle): ?><span class="t-item"><?= e($ltitle) ?></span><?php endif; ?>
                    <span class="t-last"><?= e($t['body']) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </aside>

    <!-- Right: the open conversation -->
    <div class="chat">
        <?php if (!$other): ?>
            <div class="empty-state">Pick a conversation on the left to start chatting.</div>
        <?php else: ?>
            <div class="chat-head">
                <span class="avatar"><?= e(initials($other['name'])) ?></span>
                <div>
                    <div class="who"><?= e($other['name']) ?></div>
                    <?php if ($listingTitle): ?><div class="item">Re: <?= e($listingTitle) ?></div><?php endif; ?>
                </div>
                <span class="verified">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                    Verified student
                </span>
            </div>

            <div class="chat-body" id="chatBody">
                <?php if (empty($chat)): ?>
                    <div class="empty-state">No messages yet — say hello and ask about pickup or price.</div>
                <?php endif; ?>
                <?php foreach ($chat as $m):
                    $mine = ((int)$m['sender_id'] === $myId);
                    $time = date('g:i A', strtotime($m['created_at']));
                    // Auto-generated system notices (buy / sold / frozen / thanks)
                    // render as a centred system note, not a chat bubble.
                    $first = function_exists('mb_substr') ? mb_substr($m['body'], 0, 1) : $m['body'][0];
                    $isSystem = in_array($first, ['🛒', '✅', '🙏', '❄'], true);
                ?>
                    <?php if ($isSystem): ?>
                        <div class="bubble system"><?= e($m['body']) ?></div>
                    <?php else: ?>
                        <div class="bubble <?= $mine ? 'me' : 'them' ?>">
                            <?= nl2br(e($m['body'])) ?>
                            <span class="time"><?= e($time) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- The message box -->
            <form class="chat-input" method="post" onsubmit="return validateChat()">
                <input type="hidden" name="to" value="<?= $openId ?>">
                <input type="hidden" name="listing" value="<?= $listingId ?>">
                <input type="text" name="body" id="chatBox" autocomplete="off"
                       placeholder="Message about pickup, price, bargain…">
                <button type="submit" class="chat-send" title="Send">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
