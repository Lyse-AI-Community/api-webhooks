<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: image/png');

$data_file = __DIR__ . '/status.json';

if (!file_exists($data_file)) {
    render_error("Aucune donnée (status.json introuvable)");
    exit;
}

$raw_content = file_get_contents($data_file);
$records = json_decode($raw_content, true);

if (!is_array($records) || empty($records)) {
    render_error("data.json vide ou corrompu");
    exit;
}

$first_record = reset($records);
$type = $first_record['type'] ?? 'Training';

$points_raw = [];
foreach ($records as $row) {
    if (isset($row['loss'], $row['timestamp'])) {
        $loss_val = floatval($row['loss']);
        $time_val = strtotime($row['timestamp']);

        if ($time_val !== false && $loss_val > 0) {
            $points_raw[] = [
                'time' => $time_val,
                'loss' => $loss_val
            ];
        }
    }
}

if (count($points_raw) === 0) {
    render_error("Aucune donnee de loss valide dans le JSON");
    exit;
}

usort($points_raw, fn($a, $b) => $a['time'] <=> $b['time']);

$points_processed = [];
$bucket = [];
$bucket_start = $points_raw[0]['time'];
$interval = 30;

foreach ($points_raw as $p) {
    if (($p['time'] - $bucket_start) < $interval) {
        $bucket[] = $p;
    } else {
        if (!empty($bucket)) {
            $avg_time = array_sum(array_column($bucket, 'time')) / count($bucket);
            $avg_loss = array_sum(array_column($bucket, 'loss')) / count($bucket);
            $points_processed[] = ['time' => (int)$avg_time, 'loss' => $avg_loss];
        }
        $bucket = [$p];
        $bucket_start = $p['time'];
    }
}
if (!empty($bucket)) {
    $avg_time = array_sum(array_column($bucket, 'time')) / count($bucket);
    $avg_loss = array_sum(array_column($bucket, 'loss')) / count($bucket);
    $points_processed[] = ['time' => (int)$avg_time, 'loss' => $avg_loss];
}

$min_time   = $points_raw[0]['time'];
$max_time   = end($points_raw)['time'];
$time_range = max(1, $max_time - $min_time);

$all_losses = array_column($points_processed, 'loss');
$min_loss   = min($all_losses);
$max_loss   = max($all_losses);

if ($max_loss === $min_loss) {
    $max_loss += 0.0001;
}

$width   = 900;
$height  = 450;
$padding = 65;

$img = imagecreatetruecolor($width, $height);

$bg_color   = imagecolorallocate($img, 30, 33, 36);
$grid_color = imagecolorallocate($img, 50, 53, 59);
$text_color = imagecolorallocate($img, 220, 221, 222);
$sub_color  = imagecolorallocate($img, 142, 146, 151);
$line_color = imagecolorallocate($img, 88, 101, 242);
$min_color  = imagecolorallocate($img, 87, 242, 135);

imagefilledrectangle($img, 0, 0, $width, $height, $bg_color);

$chart_w = $width - (2 * $padding);
$chart_h = $height - (2 * $padding);

$grid_steps = 5;
for ($i = 0; $i <= $grid_steps; $i++) {
    $y = $height - $padding - ($i * ($chart_h / $grid_steps));
    imageline($img, $padding, (int)$y, $width - $padding, (int)$y, $grid_color);

    $val = $min_loss + ($i * ($max_loss - $min_loss) / $grid_steps);
    imagestring($img, 2, 5, (int)$y - 7, number_format($val, 4), $sub_color);
}

$x_steps = 6;
for ($i = 0; $i <= $x_steps; $i++) {
    $x = $padding + ($i * ($chart_w / $x_steps));
    imageline($img, (int)$x, $padding, (int)$x, $height - $padding, $grid_color);

    $t_label = $min_time + ($i * ($time_range / $x_steps));
    $format = ($time_range > 86400) ? 'd/m H:i' : 'H:i';
    imagestring($img, 2, (int)$x - 18, $height - $padding + 12, date($format, (int)$t_label), $sub_color);
}

imagestring($img, 5, $padding, 15, "Evolution de la Loss - " . $type, $text_color);

$points = [];
$min_point_coords = null;

foreach ($points_processed as $p) {
    $x = $padding + (($p['time'] - $min_time) / $time_range * $chart_w);
    $y = $height - $padding - (($p['loss'] - $min_loss) / ($max_loss - $min_loss) * $chart_h);

    $pt = ['x' => (int)$x, 'y' => (int)$y, 'time' => $p['time'], 'loss' => $p['loss']];
    $points[] = $pt;

    if ($p['loss'] == $min_loss && !$min_point_coords) {
        $min_point_coords = $pt;
    }
}

imagesetthickness($img, 2);
$max_gap = 100;

for ($i = 0; $i < count($points) - 1; $i++) {
    $p1 = $points[$i];
    $p2 = $points[$i + 1];

    if (($p2['time'] - $p1['time']) <= $max_gap) {
        imageline($img, $p1['x'], $p1['y'], $p2['x'], $p2['y'], $line_color);
    }
}

if ($min_point_coords) {
    imagefilledellipse($img, $min_point_coords['x'], $min_point_coords['y'], 8, 8, $min_color);
}

$duration_min = round($time_range / 60);
$info_str = "Duree: {$duration_min} min | Min loss: " . number_format($min_loss, 5);
imagestring($img, 3, $width - $padding - 280, 15, $info_str, $text_color);

imagepng($img);
imagedestroy($img);

function render_error($message) {
    $img = imagecreatetruecolor(700, 150);
    $bg  = imagecolorallocate($img, 30, 33, 36);
    $txt = imagecolorallocate($img, 237, 66, 69);
    imagefilledrectangle($img, 0, 0, 700, 150, $bg);
    imagestring($img, 4, 20, 60, "Erreur: " . $message, $txt);
    imagepng($img);
    imagedestroy($img);
}