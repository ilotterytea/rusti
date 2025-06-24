<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';

authorize_user();

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    generate_alert("/", null, null, 403);
    exit;
}

if (!isset($_POST['id'])) {
    generate_alert("/", null, 'File ID must be set in query', 400);
    exit;
}

$id = $_POST['id'];

$db = new PDO(DB_URL);

$stmt = $db->prepare("SELECT id FROM reports WHERE id = ?");
$stmt->execute([$_POST['id']]);

if (!$stmt->fetch()) {
    generate_alert("/reports/", null, 'Report not found', 404);
    exit;
}

$db->prepare("DELETE FROM reports WHERE id = ?")
    ->execute([$id]);

generate_alert("/reports/", null, "Report ID $id has been deleted", 200);