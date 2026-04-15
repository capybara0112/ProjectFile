<?php
declare(strict_types=1);

// Shared helper functions (HTML escaping, redirect, auth checks, etc).

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        $branch['location_label'] ?? null,
        $branch['full_address'] ?? null,
        $branch['address_detail'] ?? null,
        $branch['province'] ?? null,
        $branch['branch_name'] ?? null,
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
        $job['location_label'] ?? null,
        $job['full_address'] ?? null,
        $job['branch_address'] ?? null,
        $job['address_detail'] ?? null,
        $job['province'] ?? null,
        $job['company_address'] ?? null,
    ];

    foreach ($candidates as $value) {
        $value = trim((string)($value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return 'Chưa cập nhật';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    // If redirect target is an absolute path (starts with "/"),
    // prefix it with BASE_URL so it works from a subfolder like `/dacn`.
    if ($url !== '' && $url[0] === '/') {
        $base = rtrim(BASE_URL, '/');
        $url = $base . $url; // '/' => '/dacn/' ; '/login.php' => '/dacn/login.php'
    }
    header('Location: ' . $url);
    exit;
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
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!$token || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(400);
        exit('CSRF token không hợp lệ');
    }
}
