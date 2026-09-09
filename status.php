<?php
header('Content-Type: image/png');

$data_file = __DIR__ . '/status.json';

if (!file_exists($data_file)) {
    render_error("Aucune donnee (status.json introuvable)");
    exit;
}

$records = json_decode(file_get_contents($data_file), true);

if (!is_array($records) || empty($records)) {
    render_error("Fichier status.json vide ou invalide");
    exit;
}

$first_record = reset($records);
$type = $first_record['type'] ?? 'Training';

$points_raw = [];
foreach ($records as $row) {
    if (isset($row['loss'], $row['timestamp'])) {
        $loss_val = floatval(preg_replace('/[^0-9.]/', '', $row['loss']));
        $time_val = is_numeric($row['timestamp']) ? (int)$row['timestamp'] : strtotime($row['timestamp']);

        if ($time_val !== false) {
            $points_raw[] = [
                'time' => $time_val,
                'loss' => $loss_val
            ];
        }
    }
}

$count_raw = count($points_raw);
if ($count_raw === 0) {
    render_error("Aucune donnee de loss/temps exploitable");
    exit;
}

usort($points_raw, fn($a, $b) => $a['time'] <=> $b['time']);

$latest_time = end($points_raw)['time'];
$cutoff_time = $latest_time - (3 * 3600);

$points_raw = array_values(array_filter($points_raw, function ($p) use ($cutoff_time) {
    return $p['time'] >= $cutoff_time;
}));

$count_raw = count($points_raw);
if ($count_raw === 0) {
    render_error("Aucune donnee dans les 3 dernieres heures");
    exit;
}

$max_display_points = 200;

if ($count_raw > $max_display_points) {
    $points_processed = [];
    $chunk_size = ceil($count_raw / $max_display_points);
    $chunks = array_chunk($points_raw, $chunk_size);

    foreach ($chunks as $chunk) {
        $avg_time = array_sum(array_column($chunk, 'time')) / count($chunk);
        $avg_loss = array_sum(array_column($chunk, 'loss')) / count($chunk);
        $points_processed[] = [
            'time' => (int)$avg_time,
            'loss' => $avg_loss
        ];
    }
} else {
    $points_processed = $points_raw;
}

$count = count($points_processed);

$min_time   = $points_raw[0]['time'];
$max_time   = end($points_raw)['time'];
$time_range = $max_time - $min_time;

$all_losses = array_column($points_raw, 'loss');
$min_loss   = min($all_losses);
$max_loss   = max($all_losses);

if ($max_loss === $min_loss) {
    $max_loss += 0.0001;
}

$width   = 900;
$height  = 450;
$padding = 65;

$img = imagecreatetruecolor($width, $height);

$bg_color    = imagecolorallocate($img, 30, 33, 36);
$grid_color  = imagecolorallocate($img, 50, 53, 59);
$text_color  = imagecolorallocate($img, 220, 221, 222);
$sub_color   = imagecolorallocate($img, 142, 146, 151);
$line_color  = imagecolorallocate($img, 88, 101, 242);
$point_color = imagecolorallocate($img, 255, 255, 255);
$min_color   = imagecolorallocate($img, 87, 242, 135);

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
    $format = ($time_range > 86400) ? 'd/m H:i' : (($time_range > 3600) ? 'H:i' : 'H:i:s');
    $time_str = date($format, (int)$t_label);

    imagestring($img, 2, (int)$x - 22, $height - $padding + 12, $time_str, $sub_color);
}

$title = "Evolution de la Loss - " . $type;
imagestring($img, 5, $padding, 15, $title, $text_color);

$points = [];
$min_point_coords = null;

foreach ($points_processed as $p) {
    $x = ($time_range > 0)
        ? $padding + (($p['time'] - $min_time) / $time_range * $chart_w)
        : $padding + ($chart_w / 2);

    $y = $height - $padding - (($p['loss'] - $min_loss) / ($max_loss - $min_loss) * $chart_h);

    $pt = ['x' => (int)$x, 'y' => (int)$y, 'loss' => $p['loss']];
    $points[] = $pt;

    if ($p['loss'] == $min_loss && !$min_point_coords) {
        $min_point_coords = $pt;
    }
}

imagesetthickness($img, 2);
for ($i = 0; $i < count($points) - 1; $i++) {
    imageline(
        $img,
        $points[$i]['x'],
        $points[$i]['y'],
        $points[$i + 1]['x'],
        $points[$i + 1]['y'],
        $line_color
    );
}

$show_dots = ($count <= 50);

foreach ($points as $p) {
    if ($show_dots) {
        imagefilledellipse($img, $p['x'], $p['y'], 4, 4, $point_color);
    }
}

if ($min_point_coords) {
    imagefilledellipse($img, $min_point_coords['x'], $min_point_coords['y'], 8, 8, $min_color);
}

$duration_min = round($time_range / 60);
$info_str = "Points: {$count_raw} | Duree: {$duration_min}m | Min: " . number_format($min_loss, 5);
imagestring($img, 3, $width - $padding - 310, 15, $info_str, $text_color);

imagepng($img);
imagedestroy($img);

function render_error($message) {
    $img = imagecreatetruecolor(600, 150);
    $bg  = imagecolorallocate($img, 30, 33, 36);
    $txt = imagecolorallocate($img, 237, 66, 69);
    imagefilledrectangle($img, 0, 0, 600, 150, $bg);
    imagestring($img, 4, 20, 60, "Erreur: " . $message, $txt);
    imagepng($img);
    imagedestroy($img);
}