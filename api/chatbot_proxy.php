<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/layout.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Lấy Groq API key ──────────────────────────────────────────────────────────
// Tạo key miễn phí tại: https://console.groq.com/keys
// Key có dạng: gsk_xxxxxxxxxxxxxxxxxxxx
$apiKey = defined('GROQ_API_KEY') ? constant('GROQ_API_KEY') : '';
if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'GROQ_API_KEY chưa được cấu hình trong config/config.php']);
    exit;
}

$rawBody = file_get_contents('php://input');
if (!$rawBody) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$messages = $payload['messages'] ?? [];
if (!is_array($messages) || empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'Messages không hợp lệ']);
    exit;
}

// ── Build request body ────────────────────────────────────────────────────────
// Groq dùng đúng format OpenAI → không cần convert gì cả
// Model llama-3.3-70b: miễn phí, 30 RPM, thông minh
$requestBody = [
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $messages,
    'max_tokens'  => min((int)($payload['max_tokens'] ?? 1000), 4096),
    'temperature' => 0.7,
    'stream'      => false,
];

$url         = 'https://api.groq.com/openai/v1/chat/completions';
$encodedBody = json_encode($requestBody, JSON_UNESCAPED_UNICODE);

// ── Gọi API với retry ─────────────────────────────────────────────────────────
function callApiWithRetry(string $url, string $body, string $apiKey, int $maxRetries = 3): array
{
    $delay = 2;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            return ['response' => false, 'httpCode' => 0, 'curlError' => $curlError];
        }

        if (in_array($httpCode, [429, 503], true) && $attempt < $maxRetries) {
            sleep($delay);
            $delay *= 2;
            continue;
        }

        return ['response' => $response, 'httpCode' => $httpCode, 'curlError' => ''];
    }

    return ['response' => false, 'httpCode' => 429, 'curlError' => 'Đã thử ' . $maxRetries . ' lần nhưng API vẫn lỗi'];
}

$result    = callApiWithRetry($url, $encodedBody, $apiKey);
$response  = $result['response'];
$httpCode  = $result['httpCode'];
$curlError = $result['curlError'];

// ── Xử lý lỗi kết nối ────────────────────────────────────────────────────────
if ($response === false || $curlError !== '') {
    http_response_code(502);
    echo json_encode([
        'error'  => 'Không kết nối được tới Groq API',
        'detail' => $curlError,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Parse và trả về response ──────────────────────────────────────────────────
$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    http_response_code($httpCode ?: 500);

    $rawMsg  = $data['error']['message'] ?? '';
    $errCode = $data['error']['code']    ?? $httpCode;

    $friendlyMsg = match (true) {
        $httpCode === 429                        => 'Groq đang quá tải (30 req/phút). Chờ vài giây rồi thử lại.',
        $httpCode === 401                        => 'API key không hợp lệ. Kiểm tra lại GROQ_API_KEY trong config.php.',
        str_contains($rawMsg, 'model_not_found') => 'Model không tồn tại. Kiểm tra lại tên model.',
        default                                  => 'Groq API lỗi (' . $errCode . '): ' . ($rawMsg ?: 'Không xác định'),
    };

    echo json_encode(['error' => ['message' => $friendlyMsg]], JSON_UNESCAPED_UNICODE);
    exit;
}

// Groq trả về đúng format OpenAI → trả thẳng cho frontend, không cần sửa gì
echo json_encode($data, JSON_UNESCAPED_UNICODE);