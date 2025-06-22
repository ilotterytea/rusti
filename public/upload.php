<?php
include_once '../config.php';
include_once '../lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    exit(json_response(null, 'Method not allowed!', 403));
}

if (!isset($_FILES['file'])) {
    exit(json_response(null, 'Field "file" must be set!', 400));
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
$file_id_gen_start_time = time();

do {
    $file_id = "";
    if (time() - $file_id_gen_start_time >= FILE_ID_GENERATION_TIMEOUT_SEC) {
        break;
    }
    $file_id = FILE_ID_PREFIX . generate_random_chars(FILE_ID_LENGTH, FILE_ID_CHARPOOL);
} while (file_exists(FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_extension}"));

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

// saving in database
$db = new PDO(DB_URL);
$db->prepare('INSERT INTO posts (id, mime, extension, size, visibility) VALUES (?, ?, ?, ?, ?)')
    ->execute([$file_id, $file_mime, $file_extension, $file_size, 0]);

if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
    exit(json_response($data, null, 201));
} else {
    header("Location: {$download_url}");
}