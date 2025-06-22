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

$download_url = "{$url}/{$file_id}";

// setting other metadata
$visibility = clamp(intval($_POST['visibility'] ?? '0'), 0, 1);
$comment = str_safe($_POST['comment'] ?? '', null, false);
if (empty($comment)) {
    $comment = null;
}

$password = null;

if (isset($_POST['password'])) {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// parsing expires_at
$expires_at = null;

if (isset($_POST['expires']) && array_key_exists($_POST['expires'], FILE_EXPIRATION)) {
    $e = $_POST['expires'];
    $v = intval(substr($e, 0, strlen($e) - 1));
    $m = substr($e, strlen($e) - 1);

    $secs = match ($m) {
        'd' => 86400,
        'h' => 3600,
        default => 0
    };

    $t = time() + $v * $secs;
    $expires_at = date("Y-m-d H:i:s", $t);
}

// saving in database
$db = new PDO(DB_URL);
$db->prepare('INSERT INTO posts (id, mime, extension, size, visibility, comment, password, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$file_id, $file_mime, $file_extension, $file_size, $visibility, $comment, $password, $expires_at]);

// getting data from database
$stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$file_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);
unset($data['password']);
$data['urls'] = [
    'download_url' => $download_url
];

if (isset($password)) {
    $data['urls']['deletion_url'] = "{$url}/file/delete.php?id={$file_id}&key={$_POST['password']}";
}

if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
    exit(json_response($data, null, 201));
} else {
    header("Location: {$download_url}");
}