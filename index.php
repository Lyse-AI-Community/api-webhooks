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

$type      = $_POST['type'] ?? 'Training';
$epoch      = $_POST['epoch'] ?? '0';
$completion = $_POST['completion'] ?? '0/0 (0%)';
$loss       = $_POST['loss'] ?? '1000000';
$tps        = $_POST['tps'] ?? '0 tokens/s';
$eta        = $_POST['eta'] ?? '0 h';

$message_param = $_POST['message_url'] ?? $_POST['message_id'] ?? null;

try {
    $payload = [
        'content' => null,
        'username' => $type,
        'avatar_url' => 'https://cdn-avatars.huggingface.co/v1/production/uploads/68bd85e15271b9ac99cb2963/cEyVuEJrSO62SPVv8Zytb.png',
        'embeds' => [
            [
                'title' => "{$type} (Epoch {$epoch})",
                'color' => 559629,
                'fields' => [
                    [
                        'name' => 'Completion :',
                        'value' => $completion
                    ],
                    [
                        'name' => 'Loss instantanée :',
                        'value' => $loss
                    ],
                    [
                        'name' => 'Token par seconde',
                        'value' => $tps
                    ],
                    [
                        'name' => 'ETA fin d\'epoch :',
                        'value' => $eta
                    ]
                ],
                'timestamp' => date(DATE_ATOM)
            ]
        ]
    ];

    $webhook_url = $config['webhook_url'];
    $is_edit = false;

    if (!empty($message_param)) {
        $message_id = null;
        if (filter_var($message_param, FILTER_VALIDATE_URL)) {
            $path_parts = explode('/', parse_url($message_param, PHP_URL_PATH));
            $message_id = end($path_parts);
        } else {
            $message_id = $message_param;
        }

        if (!empty($message_id)) {
            $webhook_url = rtrim($webhook_url, '/') . '/messages/' . $message_id;
            $is_edit = true;
        }
    }

    if (!$is_edit) {
        $webhook_url .= (parse_url($webhook_url, PHP_URL_QUERY) ? '&' : '?') . 'wait=true';
    }

    $ch = curl_init($webhook_url);
    $curl_options = [
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5
    ];

    if ($is_edit) {
        $curl_options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
    } else {
        $curl_options[CURLOPT_POST] = true;
    }

    curl_setopt_array($ch, $curl_options);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $discord_data = json_decode($response, true);

        $guild_id   = $discord_data['guild_id'] ?? '@me';
        $channel_id = $discord_data['channel_id'] ?? null;
        $message_id = $discord_data['id'] ?? null;

        $message_url = ($channel_id && $message_id) 
            ? "https://discord.com/channels/{$guild_id}/{$channel_id}/{$message_id}"
            : null;

        http_response_code(200);
        echo json_encode([
            'status'      => 'success',
            'action'      => $is_edit ? 'edited' : 'created',
            'message'     => $is_edit ? 'Message mis à jour' : 'Message envoyé',
            'message_url' => $message_url
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            'error'     => 'Echec de l\'opération auprès du webhook Discord',
            'code'      => $http_code,
            'response'  => json_decode($response, true) ?? $response
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur']);
}