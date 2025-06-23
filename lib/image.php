<?php
function generate_image_thumbnail(string $src_path, string $dst_path, int $width, int $height)
{
    if ($src_path == "") {
        return -2;
    }

    $input_path = escapeshellarg($src_path);
    $output_path = escapeshellarg($dst_path);

    $result_code = null;

    exec(command: "magick $input_path -resize {$width}x{$height} -loop 0 $output_path", result_code: $result_code);

    return $result_code;
}