</main>

<footer class="site-footer">
    <div class="wrap">
        Market Odyssey · a student-run campus marketplace for Woxsen
        &nbsp;·&nbsp; built with HTML, CSS, PHP, MySQL &amp; JavaScript
        <?php if (!is_admin()): ?>
            &nbsp;·&nbsp; <a href="admin_login.php">Admin</a>
        <?php else: ?>
            &nbsp;·&nbsp; <a href="logout.php">Log out of admin</a>
        <?php endif; ?>
    </div>
</footer>

<script src="assets/js/main.js?v=8"></script>
</body>
</html>
