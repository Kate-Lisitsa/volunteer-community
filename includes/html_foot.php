    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <strong><?= escape(APP_NAME) ?></strong>
                <span class="footer-tagline">Волонтёрская помощь рядом с вами</span>
            </div>
            <p class="footer-copy">&copy; 2026 <?= escape(APP_NAME) ?>. Минск.</p>
        </div>
    </footer>
    <script src="<?= APP_URL ?>/assets/js/script.js" defer></script>
    <?= $footExtra ?? '' ?>
</body>
</html>
