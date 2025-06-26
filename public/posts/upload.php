<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/image.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

authorize_user();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    exit(json_response(null, 'Method not allowed!', 405));
}

if (FILE_AUTHORIZED_UPLOAD && !isset($_SESSION['user'])) {
    exit(json_response(null, 'You must be authorized to upload files', 401));
}

$file_data = null;

// using yt-dlp to download external content
if (FILE_EXTSRC && isset($_POST['url']) && !empty($_POST['url'])) {
    $url = $_POST['url'];
    $output = [];
    exec('yt-dlp -f "worst" --get-filename -o "%(filesize_approx)s %(ext)s %(duration)s" "' . escapeshellarg($url) . '"', $output);
    if (empty($output)) {
        generate_alert('/', null, 'Bad URL', 400);
        exit;
    }

    $output = explode(' ', $output[0]);

    $duration = intval($output[2]);
    if ($duration == 0 || $duration > FILE_EXTSRC_DURATION) {
        generate_alert('/', null, sprintf("File must be under %d minutes", FILE_EXTSRC_DURATION / 60), 400);
        exit;
    }

    $file_data = [
        'size' => intval($output[0]),
        'mime' => ext2mime($output[1]),
        'extension' => $output[1]
    ];
} else if (isset($_FILES['file'])) {
    $file = $_FILES['file'];

    $file_data = [
        'size' => $file['size'],
        'mime' => $file['type'],
        'extension' => mime2ext($file['type'])
    ];
}

if (!$file_data) {
    generate_alert('/', null, 'URL or file must be sent', 400);
    exit;
}

if (!$file_data['extension'] || !$file_data['mime']) {
    exit(json_response(null, empty($file_data['mime']) ? 'Corrupted file' : "Unsupported MIME type: {$file_data['mime']}", 400));
}

$file_id = "";
$file_id_gen_start_time = time();

do {
    $file_id = "";
    if (time() - $file_id_gen_start_time >= FILE_ID_GENERATION_TIMEOUT_SEC) {
        break;
    }
    $file_id = FILE_ID_PREFIX . generate_random_chars(FILE_ID_LENGTH, FILE_ID_CHARPOOL);
} while (file_exists(FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_data['extension']}"));

if (empty($file_id)) {
    exit(json_response(null, 'Exceeded time to generate an ID for a file. Try again later.', 500));
}

if (!is_dir(FILE_UPLOAD_DIRECTORY . "/uploads") && !mkdir(FILE_UPLOAD_DIRECTORY . "/uploads", 0777, true)) {
    exit(json_response(null, 'Failed to create upload directory', 500));
}

if (isset($url)) {
    $result = 0;
    $output = [];
    exec(sprintf(
        'yt-dlp -f "worst" -o "%s/uploads/%s.%s" "%s" 2>&1',
        FILE_UPLOAD_DIRECTORY,
        $file_id,
        $file_data['extension'],
        escapeshellarg($url)
    ), $output, $result);

    if ($result != 0) {
        error_log(sprintf("Failed to download a file (URL: %s): %s", $url, implode('\n', $output)));
        generate_alert('/', null, 'Failed to download a file! Try again later.', 500);
        exit;
    }
} else if (!move_uploaded_file($file['tmp_name'], FILE_UPLOAD_DIRECTORY . "/uploads/{$file_id}.{$file_data['extension']}")) {
    exit(json_response(null, 'Failed to save a file! Try again later.', 500));
}

// creating a thumbnail
if (!is_dir(FILE_UPLOAD_DIRECTORY . "/thumbnails") && !mkdir(FILE_UPLOAD_DIRECTORY . "/thumbnails", 0777, true)) {
    exit(json_response(null, 'Failed to create upload directory', 500));
}

$is_video = str_starts_with($file_data['mime'], 'video/');
$thumbnail_folder = FILE_UPLOAD_DIRECTORY . "/thumbnails/$file_id";

if ($is_video && !mkdir($thumbnail_folder, 0777, true)) {
    generate_alert('/', null, 'Failed to create temporary folder for future thumbnail', 500);
    exit;
}

if (
    (
        str_starts_with($file_data['mime'], 'image/') &&
        $thumbnail_error = generate_image_thumbnail(
            FILE_UPLOAD_DIRECTORY . "/uploads/{$file_id}.{$file_data['extension']}[0]",
            FILE_UPLOAD_DIRECTORY . "/thumbnails/{$file_id}.jpeg",
            128,
            128
        )
    ) ||
    (
        $is_video &&
        $thumbnail_error = generate_video_thumbnail(
            FILE_UPLOAD_DIRECTORY . "/uploads/{$file_id}.{$file_data['extension']}",
            $thumbnail_folder,
            FILE_UPLOAD_DIRECTORY . "/thumbnails/{$file_id}.gif",
            128,
            128
        )
    )
) {
    exit(json_response(null, "Failed to create a thumbnail (Error code {$thumbnail_error})", 500));
}

if (
    $is_video &&
    array_map('unlink', array_filter((array) glob("$thumbnail_folder/*")))
    && !rmdir($thumbnail_folder)
) {
    generate_alert('/', null, 'Failed to remove temporary folder for a thumbnail', 500);
    exit;
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
$db->prepare('INSERT INTO posts (id, mime, extension, size, visibility, comment, password, expires_at, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$file_id, $file_data['mime'], $file_data['extension'], $file_data['size'], $visibility, $comment, $password, $expires_at, $_SESSION['user']['id'] ?? null]);

// getting data from database
$stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$file_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);
$data['password'] = $_POST['password'];
$data['urls'] = [
    'download_url' => $download_url
];

// parsing tags
$tags = str_safe($_POST['tags'] ?? '', null);

if (!empty($tags)) {
    $tags = explode(' ', $tags);
    $tag_ids = [];
    $data['tags'] = [];

    foreach ($tags as $tag) {
        $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->execute([$tag]);

        $tag_id = null;

        if ($row = $stmt->fetch()) {
            $tag_id = $row['id'];
        } else {
            $db->prepare("INSERT INTO tags(name) VALUES (?)")
                ->execute([$tag]);
            $tag_id = $db->lastInsertId();
        }

        array_push($tag_ids, $tag_id);
        array_push($data['tags'], $tag);
    }

    foreach ($tag_ids as $tag_id) {
        $db->prepare("INSERT INTO tag_posts(tag_id, post_id) VALUES (?, ?)")
            ->execute([$tag_id, $file_id]);
    }
}

if (isset($password)) {
    $data['urls']['deletion_url'] = "{$url}/posts/delete.php?id={$file_id}&key={$_POST['password']}";
}

if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
    exit(json_response($data, null, 201));
} else {
    header("Location: {$download_url}");
}