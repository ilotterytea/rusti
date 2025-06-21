<?php
include_once '../lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    exit(json_response(null, 'Method not allowed!', 403));
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
    exit(json_response(null, empty($file_mime) ? 'Corrupted file' : "Unsupported MIME type: {$file_mime}", 400));
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
    exit(json_response(null, 'Exceeded time to generate an ID for a file. Try again later.', 500));
}

if (!move_uploaded_file($file['tmp_name'], FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_extension}")) {
    exit(json_response(null, 'Failed to save a file! Try again later.', 500));
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

if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
    exit(json_response($data, null, 201));
} else {
    header("Location: {$download_url}");
}