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

$max_timestamp = 0;
foreach ($records as $row) {
    if (isset($row['timestamp'])) {
        $t = is_numeric($row['timestamp']) ? (int)$row['timestamp'] : strtotime($row['timestamp']);
        if ($t > $max_timestamp) {
            $max_timestamp = $t;
        }
    }
}

if ($max_timestamp === 0) {
    render_error("Aucune donnee de temps exploitable");
    exit;
}

$cutoff_time = $max_timestamp - 10800;

$points_raw = [];
$min_loss = PHP_FLOAT_MAX;
$max_loss = -PHP_FLOAT_MAX;

foreach ($records as $row) {
    if (isset($row['loss'], $row['timestamp'])) {
        $t = is_numeric($row['timestamp']) ? (int)$row['timestamp'] : strtotime($row['timestamp']);
        if ($t >= $cutoff_time) {
            $loss = (float)preg_replace('/[^0-9.]/', '', $row['loss']);
            $points_raw[] = ['time' => $t, 'loss' => $loss];

            if ($loss < $min_loss) $min_loss = $loss;
            if ($loss > $max_loss) $max_loss = $loss;
        }
    }
}

$count_raw = count($points_raw);
if ($count_raw === 0) {
    render_error("Aucune donnee dans les 3 dernieres heures");
    exit;
}

usort($points_raw, fn($a, $b) => $a['time'] <=> $b['time']);

$min_time = $points_raw[0]['time'];
$max_time = $points_raw[$count_raw - 1]['time'];
$time_range = $max_time - $min_time;

if ($max_loss === $min_loss) {
    $max_loss += 0.0001;
}

$max_display_points = 200;
if ($count_raw > $max_display_points) {
    $points_processed = [];
    $chunk_size = (int)ceil($count_raw / $max_display_points);

    for ($i = 0; $i < $count_raw; $i += $chunk_size) {
        $sum_time = 0;
        $sum_loss = 0;
        $actual_chunk_count = 0;

        for ($j = $i; $j < $i + $chunk_size && $j < $count_raw; $j++) {
            $sum_time += $points_raw[$j]['time'];
            $sum_loss += $points_raw[$j]['loss'];
            $actual_chunk_count++;
        }

        $points_processed[] = [
            'time' => (int)($sum_time / $actual_chunk_count),
            'loss' => $sum_loss / $actual_chunk_count
        ];
    }
} else {
    $points_processed = $points_raw;
}

$count = count($points_processed);

$width   = 900;
$height  = 450;
$padding = 65;
$chart_w = $width - (2 * $padding);
$chart_h = $height - (2 * $padding);

$img = imagecreatetruecolor($width, $height);

$bg_color    = imagecolorallocate($img, 30, 33, 36);
$grid_color  = imagecolorallocate($img, 50, 53, 59);
$text_color  = imagecolorallocate($img, 220, 221, 222);
$sub_color   = imagecolorallocate($img, 142, 146, 151);
$line_color  = imagecolorallocate($img, 88, 101, 242);
$point_color = imagecolorallocate($img, 255, 255, 255);
$min_color   = imagecolorallocate($img, 87, 242, 135);

imagefilledrectangle($img, 0, 0, $width, $height, $bg_color);

$grid_steps = 5;
$loss_step = ($max_loss - $min_loss) / $grid_steps;
$y_step = $chart_h / $grid_steps;

for ($i = 0; $i <= $grid_steps; $i++) {
    $y = (int)($height - $padding - ($i * $y_step));
    imageline($img, $padding, $y, $width - $padding, $y, $grid_color);

    $val = $min_loss + ($i * $loss_step);
    imagestring($img, 2, 5, $y - 7, number_format($val, 4), $sub_color);
}

$x_steps = 6;
$x_step = $chart_w / $x_steps;
$time_step = $time_range / $x_steps;
$format = ($time_range > 86400) ? 'd/m H:i' : (($time_range > 3600) ? 'H:i' : 'H:i:s');

for ($i = 0; $i <= $x_steps; $i++) {
    $x = (int)($padding + ($i * $x_step));
    imageline($img, $x, $padding, $x, $height - $padding, $grid_color);

    $t_label = $min_time + ($i * $time_step);
    imagestring($img, 2, $x - 22, $height - $padding + 12, date($format, (int)$t_label), $sub_color);
}

$title = "Evolution de la Loss - " . $type;
imagestring($img, 5, $padding, 15, $title, $text_color);

$duration_min = round($time_range / 60);
$info_str = "Points: {$count_raw} | Duree: {$duration_min}m | Min: " . number_format($min_loss, 5);
imagestring($img, 3, $width - $padding - 310, 15, $info_str, $text_color);

$scale_x = $time_range > 0 ? $chart_w / $time_range : 0;
$scale_y = $chart_h / ($max_loss - $min_loss);

$prev_x = null;
$prev_y = null;
$min_coords = null;
$show_dots = ($count <= 50);

imagesetthickness($img, 2);

foreach ($points_processed as $p) {
    $x = (int)($time_range > 0 ? $padding + (($p['time'] - $min_time) * $scale_x) : $padding + ($chart_w / 2));
    $y = (int)($height - $padding - (($p['loss'] - $min_loss) * $scale_y));

    if ($prev_x !== null) {
        imageline($img, $prev_x, $prev_y, $x, $y, $line_color);
    }

    if ($show_dots) {
        imagefilledellipse($img, $x, $y, 4, 4, $point_color);
    }

    if ($min_coords === null && $p['loss'] == $min_loss) {
        $min_coords = [$x, $y];
    }

    $prev_x = $x;
    $prev_y = $y;
}

if ($min_coords) {
    imagefilledellipse($img, $min_coords[0], $min_coords[1], 8, 8, $min_color);
}

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