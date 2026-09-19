<?php

declare(strict_types=1);

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");

header(
    "Access-Control-Allow-Origin: http://localhost:4321/"
);
header(
    "Access-Control-Allow-Headers: Content-Type"
);
header(
    "Access-Control-Allow-Methods: POST, OPTIONS"
);

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit;
}

$input = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($input)) {
    http_response_code(400);
    exit;
}

$messages = $input["messages"] ?? [];

if (!is_array($messages)) {
    http_response_code(400);
    exit;
}

$openWebUIUrl =
    "https://openwebui.marvideo.fr/api/chat/completions";

$config = require __DIR__ . '/config.php';

$apiKey = $config['openwebui_api_key'];

if (!$apiKey) {
    http_response_code(500);
    echo "data: " . json_encode([
        "error" => "API key non configurée"
    ]) . "\n\n";
    exit;
}

$payload = json_encode([
    "model" => "openrouter.openrouter/free",
    "messages" => $messages,
    "stream" => true,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($openWebUIUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
        "Accept: text/event-stream",
    ],

    CURLOPT_POSTFIELDS => $payload,

    CURLOPT_RETURNTRANSFER => false,

    CURLOPT_WRITEFUNCTION => function (
        $curl,
        string $data
    ): int {
        echo $data;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();

        return strlen($data);
    },
]);

$result = curl_exec($ch);

if ($result === false) {
    echo "data: " . json_encode([
        "error" => curl_error($ch)
    ]) . "\n\n";

    echo "data: [DONE]\n\n";
}

curl_close($ch);