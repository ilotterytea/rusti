<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';

authorize_user();

if (!isset($_GET['id'])) {
    generate_alert("/", null, 'File ID must be set in query', 400);
    exit;
}

$db = new PDO(DB_URL);

$stmt = $db->prepare("SELECT id, extension, password, uploaded_by FROM posts WHERE id = ?");
$stmt->execute([$_GET['id']]);

$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    generate_alert("/", null, 'Post not found', 404);
    exit;
}

$delete_file =
    (
        isset($_SESSION['user']) &&
        (
            (isset($post['uploaded_by']) && $_SESSION['user']['id'] == $post['uploaded_by']) || /* SAME USER */
            $_SESSION['user']['is_admin'] /* ADMIN */
        ) /* USER */
    ) ||
    (
        isset($_GET['password'], $post['password']) && password_verify($_GET['password'], $post['password'])
    ) /* ANON */
;

if ($delete_file) {
    delete_file($post['id'], $db);
    generate_alert("/{$post['id']}", ['id' => $post['id']], "Deleted file ID: {$post['id']}", 200);
} else {
    generate_alert("/{$post['id']}", null, "You don't own this file.", 403);
}