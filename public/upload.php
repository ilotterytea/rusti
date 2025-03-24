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
$config = [];

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
$file_length = intval($config['files']['file_id_length'] ?? '5');
$file_chars = str_split($config['files']['file_id_char_pool'] ?? 'ABCabc123');

do {
    $file_id = "";
    for ($i = 0; $i < $file_length; $i++) {
        $file_id .= $file_chars[random_int(0, count($file_chars) - 1)];
    }
    if (isset($config['files']['upload_prefix'])) {
        $file_id = $config['files']['upload_prefix'] . $file_id;
    }
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

$download_url = "{$url}/{$file_id}.{$file_extension}";

$data = [
    'id' => $file_id,
    'mime' => $file_mime,
    'extension' => $file_extension,
    'size' => $file_size,
    'urls' => [
        'download_url' => $download_url
    ]
];

// saving locally saved file
$uploaded_files_cookies = json_decode($_COOKIE['UPLOADED_FILES'] ?? '[]') ?? [];

if (!is_array($uploaded_files_cookies)) {
    $uploaded_files_cookies = [];
}

array_unshift($uploaded_files_cookies, $data);

$cookie_body = json_encode($uploaded_files_cookies, JSON_UNESCAPED_SLASHES);

while (strlen($cookie_body) >= 4096) {
    array_pop($uploaded_files_cookies);
    $cookie_body = json_encode($uploaded_files_cookies, JSON_UNESCAPED_SLASHES);
}

setcookie('UPLOADED_FILES', $cookie_body, time() + 60 * 60 * 24 * 365, '/');

if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
    http_response_code(201);
    header(header: 'Content-Type: application/json');
    echo json_encode([
        'status_code' => 201,
        'message' => null,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES);
} else {
    header("Location: {$download_url}");
}