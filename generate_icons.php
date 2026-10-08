<?php
function createIcon($size, $path) {
    $im = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($im, 26, 62, 92); // #1a3e5c
    imagefill($im, 0, 0, $bg);
    
    // Teal accent circle
    $teal = imagecolorallocate($im, 13, 148, 136);
    imagefilledellipse($im, $size/2, $size/2, (int)($size*0.82), (int)($size*0.82), $teal);
    
    // Inner white circle
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledellipse($im, $size/2, $size/2, (int)($size*0.7), (int)($size*0.7), $white);
    
    // Medical Cross
    $crossCol = imagecolorallocate($im, 225, 29, 72); // Rose/Red
    $cWidth = (int)($size * 0.16);
    $cHeight = (int)($size * 0.44);
    imagefilledrectangle($im, (int)(($size - $cWidth)/2), (int)(($size - $cHeight)/2), (int)(($size + $cWidth)/2), (int)(($size + $cHeight)/2), $crossCol);
    imagefilledrectangle($im, (int)(($size - $cHeight)/2), (int)(($size - $cWidth)/2), (int)(($size + $cHeight)/2), (int)(($size + $cWidth)/2), $crossCol);
    
    // Pill capsule dot
    $pillCol = imagecolorallocate($im, 255, 255, 255);
    imagefilledellipse($im, $size/2, $size/2, (int)($size*0.14), (int)($size*0.14), $pillCol);

    imagepng($im, $path);
    imagedestroy($im);
}
@mkdir(__DIR__ . '/images', 0777, true);
createIcon(192, __DIR__ . '/images/icon-192.png');
createIcon(512, __DIR__ . '/images/icon-512.png');
echo "Icons created successfully at images/icon-192.png and images/icon-512.png\n";
?>
