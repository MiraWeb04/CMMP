<?php
declare(strict_types=1);
?>
		</div>
	</main>
</div>
<footer class="cmmp-footer">
	<div class="container-fluid px-3 px-lg-4 d-flex flex-column flex-md-row justify-content-between gap-2">
		<span>&copy; <?= date('Y') ?> <?= e(APP_TITLE) ?></span>
		<span class="text-muted">Serving the church community with care.</span>
	</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(APP_BASE_URL) ?>/assets/js/app.js"></script>
<?php if (isset($extraScripts)): ?><?= $extraScripts ?><?php endif; ?>
</body>
</html>
