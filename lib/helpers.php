<?php
declare(strict_types=1);

// Shared helper functions (HTML escaping, redirect, auth checks, etc).

require_once __DIR__ . '/../config/db.php';

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

ensure_session();

function format_money_vnd(?float $value): string
{
    if ($value === null) {
        return '';
    }

    return number_format($value, 0, ',', '.') . ' VNĐ';
}

function job_salary_label(array $job): string
{
    $salaryMin = isset($job['salary_min']) && $job['salary_min'] !== null ? (float)$job['salary_min'] : null;
    $salaryMax = isset($job['salary_max']) && $job['salary_max'] !== null ? (float)$job['salary_max'] : null;
    $salaryType = (string)($job['salary_type'] ?? 'month');

    $suffix = match ($salaryType) {
        'year' => '/năm',
        'hour' => '/giờ',
        default => '/tháng',
    };

    if ($salaryMin !== null && $salaryMax !== null) {
        return format_money_vnd($salaryMin) . ' - ' . format_money_vnd($salaryMax) . $suffix;
    }
    if ($salaryMin !== null) {
        return 'Từ ' . format_money_vnd($salaryMin) . $suffix;
    }
    if ($salaryMax !== null) {
        return 'Đến ' . format_money_vnd($salaryMax) . $suffix;
    }

    return 'Thương lượng';
}

function branch_location_label(array $branch): string
{
    $candidates = [
        $branch['location_label']    ?? null,
        $branch['province_display']  ?? null,   // set by Phase 2 JOIN queries
        $branch['full_address']      ?? null,
        $branch['address_detail']    ?? null,
        $branch['province']          ?? null,   // legacy key — keep for safety
        $branch['legacy_province']   ?? null,   // renamed column (Phase 1)
        $branch['branch_name']       ?? null,
    ];
 
    foreach ($candidates as $value) {
        $value = trim((string)($value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
 
    return 'Chưa cập nhật';
}

function job_location_label(array $job): string
{
    $candidates = [
        $job['location_label']    ?? null,
        $job['branch_province']   ?? null,   // p.name alias from Phase 2 JOINs
        $job['full_address']      ?? null,
        $job['branch_full_address'] ?? null,
        $job['branch_address']    ?? null,
        $job['address_detail']    ?? null,
        $job['province']          ?? null,   // legacy key — keep for safety
        $job['legacy_province']   ?? null,   // renamed column (Phase 1)
        $job['company_address']   ?? null,
    ];
 
    foreach ($candidates as $value) {
        $value = trim((string)($value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
 
    return 'Chưa cập nhật';
}

function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    if ($url !== '' && $url[0] === '/') {
        $base = rtrim(BASE_URL, '/');
        $url = $base . $url;
    }

    if (headers_sent()) {
        echo '<script>window.location.href=' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . e($url) . '"></noscript>';
        exit;
    }

    header('Location: ' . $url);
    exit;
}

/**
 * Phân trang: ?page=jobs (route) và ?p=2 (số trang) là hai tham số khác nhau.
 *
 * @return array{page: int, total_pages: int, offset: int}
 */
function pagination_meta(int $totalItems, int $perPage, int $requestedPage): array
{
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $page = max(1, min($requestedPage, $totalPages));

    return [
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
    ];
}

function notify_user(PDO $pdo, int $userId, string $content): void
{
    if ($userId <= 0 || trim($content) === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, content) VALUES (:uid, :content)'
    );
    $stmt->execute([
        ':uid'     => $userId,
        ':content' => $content,
    ]);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(?string $role = null): void
{
    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }

    if ($role !== null && ($user['role'] ?? null) !== $role) {
        // If forbidden, just go back to home for student simplicity.
        redirect('/');
    }
}

function flash(string $message, string $type = 'success'): void
{
    ensure_session();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    ensure_session();
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        flash('CSRF token không hợp lệ. Vui lòng thử lại.', 'danger');
        redirect('/');
    }
}
