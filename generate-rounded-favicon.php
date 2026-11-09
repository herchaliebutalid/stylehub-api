<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| STYLEHUB ROUNDED FAVICON GENERATOR
|--------------------------------------------------------------------------
|
| Input:
| public/stylehub-icon.png
|
| Output:
| public/stylehub-favicon-rounded.png
|
| This script creates a rounded-square favicon
| with four mathematically identical corners.
|
| The original logo is not modified.
|
*/

/*
|--------------------------------------------------------------------------
| FILE LOCATIONS
|--------------------------------------------------------------------------
*/

$sourcePath = __DIR__
    . '/public/stylehub-icon.png';

$outputPath = __DIR__
    . '/public/stylehub-favicon-rounded.png';

/*
|--------------------------------------------------------------------------
| IMAGE CONFIGURATION
|--------------------------------------------------------------------------
*/

$size = 256;

$radius = 68;

$margin = 13;

/*
|--------------------------------------------------------------------------
| VALIDATE REQUIREMENTS
|--------------------------------------------------------------------------
*/

if (!extension_loaded('gd')) {

    exit(
        "ERROR: PHP GD extension is not enabled.\n"
    );

}

if (!is_file($sourcePath)) {

    exit(
        "ERROR: Original StyleHub icon was not found.\n"
        . $sourcePath
        . "\n"
    );

}

/*
|--------------------------------------------------------------------------
| LOAD ORIGINAL IMAGE
|--------------------------------------------------------------------------
*/

$source = @imagecreatefrompng(
    $sourcePath
);

if ($source === false) {

    exit(
        "ERROR: Unable to load original PNG.\n"
    );

}

$sourceWidth = imagesx($source);

$sourceHeight = imagesy($source);

/*
|--------------------------------------------------------------------------
| CREATE WHITE IMAGE BACKGROUND
|--------------------------------------------------------------------------
*/

$background = imagecreatetruecolor(
    $size,
    $size
);

$white = imagecolorallocate(
    $background,
    255,
    255,
    255
);

imagefill(
    $background,
    0,
    0,
    $white
);

/*
|--------------------------------------------------------------------------
| RESIZE AND CENTER ORIGINAL LOGO
|--------------------------------------------------------------------------
*/

$logoSize = $size - ($margin * 2);

imagecopyresampled(

    $background,

    $source,

    $margin,

    $margin,

    0,

    0,

    $logoSize,

    $logoSize,

    $sourceWidth,

    $sourceHeight

);

/*
|--------------------------------------------------------------------------
| CREATE TRANSPARENT OUTPUT
|--------------------------------------------------------------------------
*/

$output = imagecreatetruecolor(
    $size,
    $size
);

imagealphablending(
    $output,
    false
);

imagesavealpha(
    $output,
    true
);

$transparent = imagecolorallocatealpha(
    $output,
    255,
    255,
    255,
    127
);

imagefill(
    $output,
    0,
    0,
    $transparent
);

/*
|--------------------------------------------------------------------------
| APPLY SYMMETRICAL ROUNDED CORNERS
|--------------------------------------------------------------------------
|
| Every corner uses the same mathematical mask.
|
| This ensures:
| - Identical left and right corners
| - Identical top and bottom corners
| - Smooth transparent edges
|
*/

for ($y = 0; $y < $size; $y++) {

    for ($x = 0; $x < $size; $x++) {

        /*
        |------------------------------------------
        | MIRRORED PIXEL DISTANCES
        |------------------------------------------
        */

        $dx = min(
            $x + 0.5,
            $size - $x - 0.5
        );

        $dy = min(
            $y + 0.5,
            $size - $y - 0.5
        );

        $coverage = 1.0;

        /*
        |------------------------------------------
        | CALCULATE CORNER TRANSPARENCY
        |------------------------------------------
        */

        if (
            $dx < $radius
            &&
            $dy < $radius
        ) {

            $distance = hypot(
                $radius - $dx,
                $radius - $dy
            );

            $coverage = max(
                0.0,
                min(
                    1.0,
                    $radius + 0.5 - $distance
                )
            );

        }

        /*
        |------------------------------------------
        | GET ORIGINAL PIXEL COLOR
        |------------------------------------------
        */

        $pixel = imagecolorat(
            $background,
            $x,
            $y
        );

        $red = ($pixel >> 16) & 255;

        $green = ($pixel >> 8) & 255;

        $blue = $pixel & 255;

        /*
        |------------------------------------------
        | COMPUTE ALPHA VALUE
        |------------------------------------------
        */

        $alpha = 127 - (int) round(
            $coverage * 127
        );

        /*
        |------------------------------------------
        | APPLY FINAL PIXEL
        |------------------------------------------
        */

        $color = imagecolorallocatealpha(
            $output,
            $red,
            $green,
            $blue,
            $alpha
        );

        imagesetpixel(
            $output,
            $x,
            $y,
            $color
        );

    }

}

/*
|--------------------------------------------------------------------------
| SAVE ROUNDED FAVICON
|--------------------------------------------------------------------------
*/

$saved = imagepng(
    $output,
    $outputPath,
    9
);

/*
|--------------------------------------------------------------------------
| RELEASE MEMORY
|--------------------------------------------------------------------------
*/

imagedestroy($source);

imagedestroy($background);

imagedestroy($output);

/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

if (!$saved) {

    exit(
        "ERROR: Failed to save rounded favicon.\n"
    );

}

echo "\n";

echo "SUCCESS: StyleHub rounded favicon generated!\n";

echo "Output: "
    . $outputPath
    . "\n";

echo "Size: 256 x 256 pixels\n";

echo "Corner radius: 68 pixels\n";

echo "Background: White\n";

echo "Corners: Transparent and symmetrical\n";

echo "\n";
