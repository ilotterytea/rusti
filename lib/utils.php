<?php
define("MIME_MAP", [
    'video/3gpp2' => '3g2',
    'video/3gp' => '3gp',
    'video/3gpp' => '3gp',
    'application/x-compressed' => '7zip',
    'audio/x-acc' => 'aac',
    'audio/ac3' => 'ac3',
    'application/postscript' => 'ai',
    'audio/x-aiff' => 'aif',
    'audio/aiff' => 'aif',
    'audio/x-au' => 'au',
    'video/x-msvideo' => 'avi',
    'video/msvideo' => 'avi',
    'video/avi' => 'avi',
    'application/x-troff-msvideo' => 'avi',
    'application/macbinary' => 'bin',
    'application/mac-binary' => 'bin',
    'application/x-binary' => 'bin',
    'application/x-macbinary' => 'bin',
    'image/bmp' => 'bmp',
    'image/x-bmp' => 'bmp',
    'image/x-bitmap' => 'bmp',
    'image/x-xbitmap' => 'bmp',
    'image/x-win-bitmap' => 'bmp',
    'image/x-windows-bmp' => 'bmp',
    'image/ms-bmp' => 'bmp',
    'image/x-ms-bmp' => 'bmp',
    'application/bmp' => 'bmp',
    'application/x-bmp' => 'bmp',
    'application/x-win-bitmap' => 'bmp',
    'application/cdr' => 'cdr',
    'application/coreldraw' => 'cdr',
    'application/x-cdr' => 'cdr',
    'application/x-coreldraw' => 'cdr',
    'image/cdr' => 'cdr',
    'image/x-cdr' => 'cdr',
    'zz-application/zz-winassoc-cdr' => 'cdr',
    'application/mac-compactpro' => 'cpt',
    'application/pkix-crl' => 'crl',
    'application/pkcs-crl' => 'crl',
    'application/x-x509-ca-cert' => 'crt',
    'application/pkix-cert' => 'crt',
    'text/css' => 'css',
    'text/x-comma-separated-values' => 'csv',
    'text/comma-separated-values' => 'csv',
    'application/vnd.msexcel' => 'csv',
    'application/x-director' => 'dcr',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/x-dvi' => 'dvi',
    'message/rfc822' => 'eml',
    'application/x-msdownload' => 'exe',
    'video/x-f4v' => 'f4v',
    'audio/x-flac' => 'flac',
    'video/x-flv' => 'flv',
    'image/gif' => 'gif',
    'application/gpg-keys' => 'gpg',
    'application/x-gtar' => 'gtar',
    'application/x-gzip' => 'gzip',
    'application/mac-binhex40' => 'hqx',
    'application/mac-binhex' => 'hqx',
    'application/x-binhex40' => 'hqx',
    'application/x-mac-binhex40' => 'hqx',
    'text/html' => 'html',
    'image/x-icon' => 'ico',
    'image/x-ico' => 'ico',
    'image/vnd.microsoft.icon' => 'ico',
    'text/calendar' => 'ics',
    'application/java-archive' => 'jar',
    'application/x-java-application' => 'jar',
    'application/x-jar' => 'jar',
    'image/jp2' => 'jp2',
    'video/mj2' => 'jp2',
    'image/jpx' => 'jp2',
    'image/jpm' => 'jp2',
    'image/jpeg' => 'jpeg',
    'image/pjpeg' => 'jpeg',
    'application/x-javascript' => 'js',
    'application/json' => 'json',
    'text/json' => 'json',
    'application/vnd.google-earth.kml+xml' => 'kml',
    'application/vnd.google-earth.kmz' => 'kmz',
    'text/x-log' => 'log',
    'audio/x-m4a' => 'm4a',
    'audio/mp4' => 'm4a',
    'application/vnd.mpegurl' => 'm4u',
    'audio/midi' => 'mid',
    'application/vnd.mif' => 'mif',
    'video/quicktime' => 'mov',
    'video/x-sgi-movie' => 'movie',
    'audio/mpeg' => 'mp3',
    'audio/mpg' => 'mp3',
    'audio/mpeg3' => 'mp3',
    'audio/mp3' => 'mp3',
    'video/mp4' => 'mp4',
    'video/mpeg' => 'mpeg',
    'application/oda' => 'oda',
    'audio/ogg' => 'ogg',
    'video/ogg' => 'ogg',
    'application/ogg' => 'ogg',
    'font/otf' => 'otf',
    'application/x-pkcs10' => 'p10',
    'application/pkcs10' => 'p10',
    'application/x-pkcs12' => 'p12',
    'application/x-pkcs7-signature' => 'p7a',
    'application/pkcs7-mime' => 'p7c',
    'application/x-pkcs7-mime' => 'p7c',
    'application/x-pkcs7-certreqresp' => 'p7r',
    'application/pkcs7-signature' => 'p7s',
    'application/pdf' => 'pdf',
    'application/octet-stream' => 'pdf',
    'application/x-x509-user-cert' => 'pem',
    'application/x-pem-file' => 'pem',
    'application/pgp' => 'pgp',
    'application/x-httpd-php' => 'php',
    'application/php' => 'php',
    'application/x-php' => 'php',
    'text/php' => 'php',
    'text/x-php' => 'php',
    'application/x-httpd-php-source' => 'php',
    'image/png' => 'png',
    'image/x-png' => 'png',
    'application/powerpoint' => 'ppt',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.ms-office' => 'ppt',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'application/x-photoshop' => 'psd',
    'image/vnd.adobe.photoshop' => 'psd',
    'audio/x-realaudio' => 'ra',
    'audio/x-pn-realaudio' => 'ram',
    'application/x-rar' => 'rar',
    'application/rar' => 'rar',
    'application/x-rar-compressed' => 'rar',
    'audio/x-pn-realaudio-plugin' => 'rpm',
    'application/x-pkcs7' => 'rsa',
    'text/rtf' => 'rtf',
    'text/richtext' => 'rtx',
    'video/vnd.rn-realvideo' => 'rv',
    'application/x-stuffit' => 'sit',
    'application/smil' => 'smil',
    'text/srt' => 'srt',
    'image/svg+xml' => 'svg',
    'application/x-shockwave-flash' => 'swf',
    'application/x-tar' => 'tar',
    'application/x-gzip-compressed' => 'tgz',
    'image/tiff' => 'tiff',
    'font/ttf' => 'ttf',
    'text/plain' => 'txt',
    'text/x-vcard' => 'vcf',
    'application/videolan' => 'vlc',
    'text/vtt' => 'vtt',
    'audio/x-wav' => 'wav',
    'audio/wave' => 'wav',
    'audio/wav' => 'wav',
    'application/wbxml' => 'wbxml',
    'video/webm' => 'webm',
    'image/webp' => 'webp',
    'audio/x-ms-wma' => 'wma',
    'application/wmlc' => 'wmlc',
    'video/x-ms-wmv' => 'wmv',
    'video/x-ms-asf' => 'wmv',
    'font/woff' => 'woff',
    'font/woff2' => 'woff2',
    'application/xhtml+xml' => 'xhtml',
    'application/excel' => 'xl',
    'application/msexcel' => 'xls',
    'application/x-msexcel' => 'xls',
    'application/x-ms-excel' => 'xls',
    'application/x-excel' => 'xls',
    'application/x-dos_ms_excel' => 'xls',
    'application/xls' => 'xls',
    'application/x-xls' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-excel' => 'xlsx',
    'application/xml' => 'xml',
    'text/xml' => 'xml',
    'text/xsl' => 'xsl',
    'application/xspf+xml' => 'xspf',
    'application/x-compress' => 'z',
    'application/x-zip' => 'zip',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip',
    'application/s-compressed' => 'zip',
    'multipart/x-zip' => 'zip',
    'text/x-scriptzsh' => 'zsh',
]);

function mime2ext($mime)
{
    return isset(MIME_MAP[$mime]) ? MIME_MAP[$mime] : false;
}

function ext2mime($ext)
{
    $map = array_flip(MIME_MAP);
    return isset($map[$ext]) ? $map[$ext] : false;
}

function json_response($data, $message = null, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    return json_encode([
        'status_code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES);
}

function generate_random_chars($length, $charpool)
{
    $output = "";
    for ($i = 0; $i < $length; $i++) {
        $output .= $charpool[random_int(0, count($charpool) - 1)];
    }
    return $output;
}

function clamp(int $current, int $min, int $max): int
{
    return max($min, min($max, $current));
}

function str_safe(string $s, int|null $max_length, bool $remove_new_lines = true): string
{
    $output = $s;

    if ($remove_new_lines) {
        $output = str_replace(PHP_EOL, "", $output);
    }

    $output = htmlspecialchars($output);
    $output = strip_tags($output);

    if ($max_length) {
        $output = substr($output, 0, $max_length);
    }

    $output = trim($output);

    return $output;
}

function format_timestamp(int $timestamp_secs)
{
    $days = (int) floor($timestamp_secs / (60.0 * 60.0 * 24.0));
    $hours = (int) floor(round($timestamp_secs / (60 * 60)) % 24);
    $minutes = (int) floor(round($timestamp_secs % (60 * 60)) / 60);
    $seconds = (int) floor($timestamp_secs % 60);

    if ($days == 0 && $hours == 0 && $minutes == 0) {
        return "$seconds second" . ($seconds > 1 ? "s" : "");
    } else if ($days == 0 && $hours == 0) {
        return "$minutes minute" . ($minutes > 1 ? "s" : "");
    } else if ($days == 0) {
        return "$hours hour" . ($hours > 1 ? "s" : "");
    } else {
        return "$days day" . ($days > 1 ? "s" : "");
    }
}