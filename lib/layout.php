<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once __DIR__ . '/helpers.php';

/**
 * Public URL for static assets under /assets/
 */
function asset_url(string $path): string
{
    return rtrim(BASE_URL, '/') . '/assets/' . ltrim($path, '/');
}

function render_header(string $title, array $options = []): void
{
    $pageTitle  = $title;
    $user       = current_user();
    $flash      = get_flash();
    $role       = $user['role'] ?? null;
    $userName   = $user['name'] ?? null;
    $extraHead  = $options['extra_head'] ?? '';

    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
}

function render_footer(array $options = []): void
{
    $extraFooter = $options['extra_footer'] ?? '';
    require dirname(__DIR__) . '/includes/footer.php';
}

/**
 * Standalone auth layout (login split-screen). Closes body/html in auth-foot.php.
 */
function render_auth_footer(): void
{
    ?>
<script>
function onRecaptchaExpired() {
    if (typeof grecaptcha !== 'undefined') {
        try { grecaptcha.reset(); } catch (e) { /* ignore */ }
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
