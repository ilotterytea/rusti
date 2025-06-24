<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';

authorize_user();

if (!isset($_SESSION['user'])) {
    generate_alert('/', null, 'Unauthorized', 401);
    exit;
}

$db = new PDO(DB_URL);

$secret_key = bin2hex(random_bytes(16));

$db->prepare('UPDATE users SET secret_key = ? WHERE id = ?')
    ->execute([bin2hex(random_bytes(16)), $_SESSION['user']['id']]);

setcookie('secret_key', '', time() - 1000, '/');
session_unset();
session_regenerate_id();

generate_alert('/', null, 'Logged out!', 200);