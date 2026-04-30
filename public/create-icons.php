<?php
/**
 * Generador de iconos PWA — The Place 818
 * Ejecutar UNA VEZ visitando: https://theplace818app.gastroredes.com/public/create-icons.php
 * Eliminar este archivo después de ejecutarlo.
 *
 * Requiere la extensión GD (disponible en Hostinger Business).
 */

if (!function_exists('imagecreatetruecolor')) {
    die('❌ La extensión GD no está disponible.');
}

$dir = __DIR__ . '/assets/icons/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    die('❌ No se pudo crear el directorio: ' . $dir);
}

$sizes = [192, 512];
$created = [];

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);

    // Habilitar transparencia
    imagesavealpha($img, true);

    // Colores
    $bg     = imagecolorallocate($img, 26, 26, 46);    // #1a1a2e — fondo oscuro
    $accent = imagecolorallocate($img, 233, 69, 96);   // #e94560 — rojo accent
    $white  = imagecolorallocate($img, 255, 255, 255); // #ffffff

    // Fondo
    imagefill($img, 0, 0, $bg);

    // Rectángulo redondeado simulado con un cuadrado + círculos en esquinas
    $margin  = (int)($size * 0.08);
    $radius  = (int)($size * 0.18);
    $x1 = $margin;
    $y1 = $margin;
    $x2 = $size - $margin;
    $y2 = $size - $margin;

    // Cuerpo principal
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $accent);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $accent);

    // Esquinas redondeadas
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $accent);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $accent);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $accent);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $accent);

    // Texto "818" centrado usando font built-in escalado
    // Para tamaños grandes, repetimos el dibujo de font 5 ampliado con imageresolution
    $font     = 5;
    $charW    = imagefontwidth($font);
    $charH    = imagefontheight($font);
    $text     = '818';
    $textW    = strlen($text) * $charW;

    // Escala aproximada: queremos que el texto ocupe ~40% del ancho del ícono
    $scale    = (int)(($size * 0.38) / $textW);
    if ($scale < 1) $scale = 1;

    // Creamos un canvas pequeño con el texto y lo escalamos
    $tmpW = $textW + 4;
    $tmpH = $charH + 4;
    $tmp  = imagecreatetruecolor($tmpW, $tmpH);
    $tmpBg  = imagecolorallocate($tmp, 233, 69, 96);
    $tmpWht = imagecolorallocate($tmp, 255, 255, 255);
    imagefill($tmp, 0, 0, $tmpBg);
    imagestring($tmp, $font, 2, 2, $text, $tmpWht);

    $scaledW = $tmpW * $scale;
    $scaledH = $tmpH * $scale;
    $scaled  = imagecreatetruecolor($scaledW, $scaledH);
    imagecopyresized($scaled, $tmp, 0, 0, 0, 0, $scaledW, $scaledH, $tmpW, $tmpH);

    $destX = (int)(($size - $scaledW) / 2);
    $destY = (int)(($size - $scaledH) / 2);
    imagecopy($img, $scaled, $destX, $destY, 0, 0, $scaledW, $scaledH);

    imagedestroy($tmp);
    imagedestroy($scaled);

    // Guardar
    $path = $dir . "icon-{$size}.png";
    imagepng($img, $path);
    imagedestroy($img);

    $created[] = "icon-{$size}.png";
}

echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Iconos generados</title></head><body style="font-family:sans-serif;padding:2rem">';
echo '<h2>✅ Iconos generados correctamente</h2>';
echo '<ul>';
foreach ($created as $f) {
    echo "<li>{$f}</li>";
}
echo '</ul>';
echo '<p style="color:red"><strong>Elimina este archivo del servidor ahora.</strong></p>';
echo '</body></html>';
