<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function authorize_user()
{
    session_start();

    if (!isset($_COOKIE['secret_key']) && !isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return;
    }

    $db = new PDO(DB_URL);

    $stmt = $db->prepare('SELECT * FROM users WHERE secret_key = ?');
    $stmt->execute([$_COOKIE['secret_key'] ?? $_SERVER['HTTP_AUTHORIZATION']]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['user'] = $row;
    } else {
        setcookie('secret_key', '', time() - 1000, '/');

        session_unset();
        session_destroy();
    }

    $stmt = null;
    $db = null;
}