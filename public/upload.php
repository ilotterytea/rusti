<?php
include_once '../lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(403);
    exit('Method not allowed!');
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    exit('Field "file" must be set!');
}

$upload_directory = './static/uploads';

$configpath = $_SERVER['DOCUMENT_ROOT'] . '/../tinyi.ini';

if (file_exists($configpath)) {
    $config = parse_ini_file($configpath, true);

    if (isset($config['files']['upload_directory'])) {
        $upload_directory = $config['files']['upload_directory'];
    }
}

if (!isset($config['files']['upload_directory'])) {
    http_response_code(500);
    exit('No files.upload_directory set in tinyi.ini!');
}

$file = $_FILES['file'];

$file_size = $file['size'];
$file_mime = $file['type'];
$file_name = pathinfo($file['name'], PATHINFO_FILENAME);

$file_extension = mime2ext($file_mime);

if (!$file_extension) {
    http_response_code(400);
    exit("Unsupported mime type: {$file_mime}");
}

$file_id = "";
$file_length = 5;
$file_chars = str_split("ABCDEFabcdef0123456789");

do {
    $file_id = "";
    for ($i = 0; $i < $file_length; $i++) {
        $file_id .= $file_chars[random_int(0, count($file_chars) - 1)];
    }
    error_log("asd");
} while (file_exists("{$upload_directory}/{$file_id}.{$file_extension}"));

if (empty($file_id)) {
    http_response_code(500);
    exit('Failed to generate an ID for a file');
}

if (!move_uploaded_file($file['tmp_name'], "{$upload_directory}/{$file_id}.{$file_extension}")) {
    http_response_code(500);
    exit('Failed to save a file! Try again.');
}

$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";

http_response_code(201);
header('Content-Type: application/json');
echo json_encode([
    'status_code' => 201,
    'message' => null,
    'data' => [
        'id' => $file_id,
        'mime' => $file_mime,
        'extension' => $file_extension,
        'size' => $file_size,
        'urls' => [
            'download_url' => "{$url}/{$file_id}.{$file_extension}"
        ]
    ]
], JSON_UNESCAPED_SLASHES);

