    </main>
    <footer class="app-footer">
        <p>&copy; <?= date('Y') ?> <?= APP_NAME ?></p>
    </footer>
    <script src="<?= $baseUrl ?? '' ?>/assets/js/app.js"></script>
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= $baseUrl ?? '' ?>/assets/js/<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
