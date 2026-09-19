<?php

declare(strict_types=1);

$allowedOrigin = 'http://localhost:4321';

header("Access-Control-Allow-Origin: {$allowedOrigin}");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Vary: Origin");


if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Method not allowed'
    ]);
    exit;
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Connection: keep-alive');

header('X-Accel-Buffering: no');
header('Content-Encoding: none');

while (ob_get_level() > 0) {
    ob_end_clean();
}

ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', '0');
ini_set('implicit_flush', '1');

ob_implicit_flush(true);

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    http_response_code(400);

    echo "data: " . json_encode([
        'error' => 'Invalid JSON'
    ]) . "\n\n";

    flush();
    exit;
}

$messages = $input['messages'] ?? [];

if (!is_array($messages)) {
    http_response_code(400);

    echo "data: " . json_encode([
        'error' => 'Invalid messages'
    ]) . "\n\n";

    flush();
    exit;
}

$openWebUIUrl = 'https://openwebui.marvideo.fr/api/chat/completions';
$config = require __DIR__ . '/config.php';

$apiKey = $config['openwebui_api_key'];

if (!$apiKey) {
    http_response_code(500);

    echo "data: " . json_encode([
        'error' => 'openwebui_api_key is not configured'
    ]) . "\n\n";

    flush();
    exit;
}

$payload = json_encode([
    'model' => 'openrouter.openrouter/free',
    'messages' => $messages,
    'stream' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init($openWebUIUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Accept: text/event-stream',
        'Cache-Control: no-cache',
    ],

    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_BUFFERSIZE => 1,

    CURLOPT_WRITEFUNCTION => function (
        $curl,
        string $data
    ): int {

        if ($data === '') {
            return 0;
        }
        echo $data;

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();

        return strlen($data);
    },

    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
]);

$result = curl_exec($ch);

if ($result === false) {

    echo "data: " . json_encode([
        'error' => curl_error($ch)
    ]) . "\n\n";

    echo "data: [DONE]\n\n";

    flush();
}

curl_close($ch);