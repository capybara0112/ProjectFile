<?php
declare(strict_types=1);

/**
 * Authentication helpers (reCAPTCHA, password verify, session hardening).
 */

function recaptcha_is_configured(): bool
{
    if (!defined('RECAPTCHA_SITE_KEY') || !defined('RECAPTCHA_SECRET_KEY')) {
        return false;
    }

    $site   = (string)RECAPTCHA_SITE_KEY;
    $secret = (string)RECAPTCHA_SECRET_KEY;

    return $site !== ''
        && $secret !== ''
        && $site !== 'YOUR_RECAPTCHA_SITE_KEY'
        && $secret !== 'YOUR_RECAPTCHA_SECRET_KEY';
}

function curl_extension_available(): bool
{
    return function_exists('curl_init');
}

function verify_recaptcha(string $response, string $secretKey): bool
{
    if ($response === '' || !recaptcha_is_configured()) {
        return false;
    }

    if (!curl_extension_available()) {
        return false;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secretKey,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || $raw === false || !is_string($raw)) {
        return false;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }

    return is_array($data) && !empty($data['success']);
}

function authenticate_user(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role, avatar FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    $stored = (string)($user['password'] ?? '');
    if ($stored === '') {
        return null;
    }

    $ok = false;

    // Mật khẩu đã hash (bcrypt/argon — chuỗi bắt đầu bằng $)
    if (str_starts_with($stored, '$')) {
        $ok = password_verify($password, $stored);
        if ($ok && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = :hash WHERE id = :id')
                ->execute([':hash' => $newHash, ':id' => (int)$user['id']]);
        }
    } else {
        // Dữ liệu cũ / seed: mật khẩu lưu plain text trong DB (vd: 123456)
        $ok = hash_equals($stored, $password);
        if ($ok) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = :hash WHERE id = :id')
                ->execute([':hash' => $newHash, ':id' => (int)$user['id']]);
        }
    }

    return $ok ? $user : null;
}

/**
 * Gán phiên sau đăng nhập thành công — chống session fixation.
 */
function login_establish_session(array $user): void
{
    ensure_session();
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'     => (int)$user['id'],
        'name'   => (string)$user['name'],
        'email'  => (string)$user['email'],
        'role'   => (string)$user['role'],
        'avatar' => $user['avatar'] ?? null,
    ];
}
