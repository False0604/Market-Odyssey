<?php
/**
 * header.php  —  two-tier top bar shown on every page (Amazon-inspired).
 */
require_once __DIR__ . '/../config/functions.php';
$me        = current_user($conn);
$msgCount  = message_thread_count($conn);
$pageTitle = $pageTitle ?? 'Market Odyssey';
$activeCat = $_GET['cat'] ?? 'All';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Market Odyssey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=8">
</head>
<body>

<header class="site-header">
    <div class="bar">
        <a class="brand" href="index.php" title="Market Odyssey home">
            <span class="logo" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                    <g stroke="#faf6f2" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M24 13 C 26 8 32 8 34 11" fill="none"/>
                        <path d="M22 13 C 19 7 12 8 12 13" fill="none"/>
                        <path d="M12 21 C 8 31 12 42 20 42 C 22 42.5 26 42.5 28 42 C 36 42 40 31 36 21 C 33 16 27 16 24 21 C 21 16 15 16 12 21 Z" fill="#faf6f2"/>
                    </g>
                </svg>
            </span>
            <span>
                <span class="name">Market <b>Odyssey</b></span><br>
                <span class="sub">WOXSEN CAMPUS</span>
            </span>
        </a>

        <form class="search" action="index.php" method="get" role="search">
            <input type="text" name="q" placeholder="Search the marketplace…" value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" aria-label="Search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            </button>
        </form>

        <div class="header-spacer"></div>

        <?php if (is_admin()): ?>
            <a class="h-link" href="admin.php"><span class="top">Signed in as</span><span class="big"><?= e($me['name']) ?></span></a>
            <a class="btn btn-primary btn-sm" href="admin.php">Admin panel</a>
            <a class="avatar" href="admin.php" title="Admin"><?= e(initials($me['name'])) ?></a>
        <?php elseif (is_logged_in()): ?>
            <a class="h-link" href="account.php"><span class="top">Hello,</span><span class="big"><?= e(strtok($me['name'], ' ')) ?></span></a>
            <a class="icon-btn" href="messages.php" title="Messages">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
                <?php if ($msgCount > 0): ?><span class="badge"><?= $msgCount ?></span><?php endif; ?>
            </a>
            <a class="btn btn-primary btn-sm" href="sell.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M12 5v14M5 12h14"/></svg>
                Sell
            </a>
            <a class="avatar" href="account.php" title="<?= e($me['name']) ?> — account"><?= e(initials($me['name'])) ?></a>
        <?php else: ?>
            <a class="h-link" href="login.php"><span class="top">Hello, sign in</span><span class="big">Account</span></a>
            <a class="btn btn-primary btn-sm" href="register.php">Join</a>
        <?php endif; ?>
    </div>

    <!-- Tier 2: category / department bar -->
    <nav class="catbar">
        <div class="inner">
            <a href="index.php?cat=All" class="<?= $activeCat === 'All' ? 'active' : '' ?>">All listings</a>
            <?php foreach (categories() as $c): ?>
                <a href="index.php?cat=<?= urlencode($c) ?>" class="<?= $activeCat === $c ? 'active' : '' ?>"><?= e($c) ?></a>
            <?php endforeach; ?>
            <?php if (is_logged_in() && !is_admin()): ?>
                <a class="sell-link" href="sell.php">+ Sell an item</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="wrap">
