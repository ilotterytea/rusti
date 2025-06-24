<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';

authorize_user();

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    generate_alert('/', null, null, 200);
    exit;
}

if (!isset($_POST['id'])) {
    generate_alert("/", null, 'File ID must be set in your request', 400);
    exit;
}

$db = new PDO(DB_URL);

// fetching post
$id = $_POST['id'];
$stmt = $db->prepare("SELECT id, extension FROM posts WHERE id = ? AND id NOT IN (SELECT post_id FROM post_bans)");
$stmt->execute([$id]);

$post = $stmt->fetch();

if (!$post) {
    generate_alert('/posts/report.php', null, 'File ID not found!', 404);
    exit;
}

$ban_reason = str_safe($_POST['reason'] ?? 'TOS violation', null);
if (empty($ban_reason)) {
    $ban_reason = null;
}

// posting ban
$db->prepare("INSERT INTO post_bans(post_id, reason) VALUES (?, ?)")
    ->execute([$id, $ban_reason]);

$ban_id = $db->lastInsertId();

$db->prepare("UPDATE reports SET ban_id = ? WHERE post_id = ?")
    ->execute([$ban_id, $id]);

// fetching ban
$stmt = $db->prepare("SELECT * FROM post_bans WHERE id = ?");
$stmt->execute([$ban_id]);
$ban = $stmt->fetch(PDO::FETCH_ASSOC);

// deleting the file
unlink(FILE_UPLOAD_DIRECTORY . "/uploads/{$post['id']}.{$post['extension']}");
unlink(FILE_UPLOAD_DIRECTORY . "/thumbnails/{$post['id']}.jpeg");

generate_alert("/{$post['id']}", $ban, "Post ID {$post['id']} has been banned");