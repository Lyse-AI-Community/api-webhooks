<?php
header('Content-Type: image/png');

$data_file = __DIR__ . '/status.json';

if (!file_exists($data_file)) {
    render_error("Aucune donnée (status.json introuvable)");
    exit;
}

$records = json_decode(file_get_contents($data_file), true);

if (!is_array($records) || empty($records)) {
    render_error("Fichier status.json vide ou invalide");
    exit;
}

$losses = [];
$labels = [];

foreach ($records as $row) {
    if (isset($row['loss'])) {
        $val = floatval(preg_replace('/[^0-9.]/', '', $row['loss']));
        $losses[] = $val;
        $labels[] = $row['epoch'] ?? '';
    }
}

$count = count($losses);
if ($count === 0) {
    render_error("Aucune donnée de loss exploitable");
    exit;
}

$width  = 800;
$height = 400;
$padding = 50;

$img = imagecreatetruecolor($width, $height);

$bg_color     = imagecolorallocate($img, 30, 33, 36);
$grid_color   = imagecolorallocate($img, 50, 53, 59);
$text_color   = imagecolorallocate($img, 220, 221, 222);
$sub_color    = imagecolorallocate($img, 142, 146, 151);
$line_color   = imagecolorallocate($img, 88, 101, 242);
$point_color  = imagecolorallocate($img, 255, 255, 255);
$min_color    = imagecolorallocate($img, 87, 242, 135);

imagefilledrectangle($img, 0, 0, $width, $height, $bg_color);

$min_loss = min($losses);
$max_loss = max($losses);

if ($max_loss === $min_loss) {
    $max_loss += 1;
}

$chart_w = $width - (2 * $padding);
$chart_h = $height - (2 * $padding);

$grid_steps = 4;
for ($i = 0; $i <= $grid_steps; $i++) {
    $y = $height - $padding - ($i * ($chart_h / $grid_steps));
    imageline($img, $padding, (int)$y, $width - $padding, (int)$y, $grid_color);

    $val = $min_loss + ($i * ($max_loss - $min_loss) / $grid_steps);
    $val_str = number_format($val, 4);
    imagestring($img, 2, 5, (int)$y - 7, $val_str, $sub_color);
}

imagestring($img, 5, $padding, 15, "Graphique de Loss - Entrainement", $text_color);

$points = [];
for ($i = 0; $i < $count; $i++) {
    $x = ($count > 1) 
        ? $padding + ($i * ($chart_w / ($count - 1))) 
        : $padding + ($chart_w / 2);

    $y = $height - $padding - (($losses[$i] - $min_loss) / ($max_loss - $min_loss) * $chart_h);

    $points[] = ['x' => (int)$x, 'y' => (int)$y, 'val' => $losses[$i], 'epoch' => $labels[$i]];
}

for ($i = 0; $i < $count - 1; $i++) {
    imagesetthickness($img, 3);
    imageline($img, $points[$i]['x'], $points[$i]['y'], $points[$i + 1]['x'], $points[$i + 1]['y'], $line_color);
}

$step_label = max(1, (int)ceil($count / 10));

foreach ($points as $idx => $p) {
    $color = ($p['val'] === $min_loss) ? $min_color : $point_color;
    imagefilledellipse($img, $p['x'], $p['y'], 8, 8, $color);

    if ($idx % $step_label === 0 || $idx === $count - 1) {
        imagestring($img, 2, $p['x'] - 10, $height - $padding + 10, "E" . $p['epoch'], $sub_color);
    }
}

$last_val = end($losses);
$last_str = "Derniere loss: " . number_format($last_val, 5);
imagestring($img, 3, $width - $padding - 180, 15, $last_str, $text_color);

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