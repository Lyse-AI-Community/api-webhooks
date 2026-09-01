<?php
$jsonPath = __DIR__ . '/data.json';

if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Fichier data.json introuvable.']);
    exit;
}

$jsonContent = file_get_contents($jsonPath);
$config = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE || !$config) {
    http_response_code(500);
    echo json_encode(['error' => 'Fichier data.json invalide ou corrompu.']);
    exit;
}

return $config;