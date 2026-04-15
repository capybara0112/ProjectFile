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

