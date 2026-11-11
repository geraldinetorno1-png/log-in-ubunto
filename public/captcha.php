<?php
require __DIR__ . '/../src/bootstrap.php';
$text = issueCaptcha(time());
$image = imagecreatetruecolor(240, 80);
$bg = imagecolorallocate($image, 239, 242, 247);
imagefill($image, 0, 0, $bg);
for ($i = 0; $i < 18; $i++) {
    $color = imagecolorallocate($image, random_int(140, 200), random_int(150, 210), random_int(160, 220));
    imageline($image, random_int(0, 239), random_int(0, 79), random_int(0, 239), random_int(0, 79), $color);
}
$ink = imagecolorallocate($image, 28, 44, 66);
$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
for ($i = 0; $i < 6; $i++) {
    if (is_file($font) && function_exists('imagettftext')) {
        imagettftext($image, 25, random_int(-18, 18), 15 + $i * 36, random_int(48, 60), $ink, $font, $text[$i]);
    } else { imagestring($image, 5, 22 + $i * 34, random_int(25, 40), $text[$i], $ink); }
}
header('Content-Type: image/png');
imagepng($image);
