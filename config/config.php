<?php
declare(strict_types=1);

// -----------------------------
// Basic site configuration
// -----------------------------

// Update these values to match your MySQL setup.
// You can also refactor this to read from environment variables.

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'job');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Job Recruitment');
// When running under a subfolder like http://localhost/dacn/
define('BASE_URL', '/dacn'); // e.g. '' for root; or '/subfolder' if hosted in a subfolder.

// File upload settings (keep student-friendly but safe).
define('UPLOAD_MAX_BYTES', 10 * 1024 * 1024); // 10MB default
define('UPLOAD_IMAGE_MAX_BYTES', 5 * 1024 * 1024); // 5MB for images

// ── Google reCAPTCHA v2 (checkbox) ───────────────────────────────────────────
// Tạo key tại: https://www.google.com/recaptcha/admin → reCAPTCHA v2 → "I'm not a robot"
// Domain: localhost (dev) và domain production của bạn.
//
// Cách nhanh: copy config/recaptcha.local.example.php → config/recaptcha.local.php rồi dán key.
if (is_file(__DIR__ . '/recaptcha.local.php')) {
    require_once __DIR__ . '/recaptcha.local.php';
} else {
    define('RECAPTCHA_SITE_KEY', '6Lcya-4sAAAAAMzJCB37-MdVFBIBORv0wME2zYeV');
    define('RECAPTCHA_SECRET_KEY', '6Lcya-4sAAAAANJtJIq9AAgmragKWKGdUM_GQMLY');
}
// config/config.php
$apiKey = $_ENV['GROQ_API_KEY'] ?? 'default_value_neu_khong_co';