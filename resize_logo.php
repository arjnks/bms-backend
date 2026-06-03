<?php
$src = imagecreatefrompng("public/leo_logo.png");
$width = imagesx($src);
$height = imagesy($src);
$new_width = 150;
$new_height = intval($height * ($new_width / $width));
$dst = imagecreatetruecolor($new_width, $new_height);
// preserve transparency as white background
$white = imagecolorallocate($dst, 255, 255, 255);
imagefill($dst, 0, 0, $white);
imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
imagejpeg($dst, "public/leo_logo.jpg", 90);
echo "Resized logo created.";

