<?php
declare(strict_types=1);
?>
</main>

<footer class="app-footer mt-auto">
    <div class="container app-footer-inner">
        <div class="footer-brand">
            <i class="fa-solid fa-briefcase me-2 text-accent"></i><?= e(SITE_NAME) ?>
        </div>
        <div class="muted small text-center text-md-end">
            Nền tảng tuyển dụng · PHP + MySQL + Bootstrap 5
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const KEY  = 'darkMode';
    const body = document.body;
    const btn  = document.getElementById('darkModeToggle');
    const icon = document.getElementById('dmIcon');

    function apply(dark) {
        if (dark) {
            body.classList.add('dark-mode');
            if (icon) icon.className = 'fa-solid fa-sun';
        } else {
            body.classList.remove('dark-mode');
            if (icon) icon.className = 'fa-solid fa-moon';
        }
    }

    apply(localStorage.getItem(KEY) === '1');
    document.documentElement.classList.remove('dm-preload');

    if (btn) {
        btn.addEventListener('click', function () {
            const isDark = body.classList.toggle('dark-mode');
            localStorage.setItem(KEY, isDark ? '1' : '0');
            if (icon) icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        });
    }
})();
</script>
<?php if (!empty($extraFooter ?? '')): ?>
<?= $extraFooter ?>
<?php endif; ?>
</body>
</html>
