<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode non autorisee. POST requis.']);
    exit;
}

$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$api_key = $headers['x-api-key'] ?? $_POST['api_key'] ?? null;

$valid_keys = $config['api_keys'] ?? [];

if (!$api_key || !is_array($valid_keys) || !in_array($api_key, $valid_keys, true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Cle API invalide ou manquante.']);
    exit;
}

$key_hash = md5($api_key);
$cache_file = sys_get_temp_dir() . "/rate_limit_" . $key_hash . ".json";
$current_time = time();

$rate_data = ['start_time' => $current_time, 'count' => 0];
if (file_exists($cache_file)) {
    $rate_data = json_decode(file_get_contents($cache_file), true);
}

if (($current_time - $rate_data['start_time']) > $config['rate_limit_time']) {
    $rate_data = ['start_time' => $current_time, 'count' => 0];
}

if ($rate_data['count'] >= $config['rate_limit_max']) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit depasse. Reessayez plus tard.']);
    exit;
}

$rate_data['count']++;
file_put_contents($cache_file, json_encode($rate_data));

$content = $_POST['content'] ?? null;
$name = $_POST['name'] ?? null;

var_dump($_POST);

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Le champ "content" est requis.']);
    exit;
}

try {
    $payload = [
        'content'    => date('d/m/Y, H:i:s') . ' - ' . $content,
        'username'   => $name,
        'avatar_url' => 'https://cdn-avatars.huggingface.co/v1/production/uploads/68bd85e15271b9ac99cb2963/cEyVuEJrSO62SPVv8Zytb.png',
        "flags" => 4096,
    ];

    $ch = curl_init($config['webhook_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 204 || $http_code === 200) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Message envoye']);
    } else {
        http_response_code(502);
        echo json_encode(['error' => 'Echec de l\'envoi au webhook', 'code' => $http_code]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur']);
}