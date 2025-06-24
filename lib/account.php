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

        $stmt = $db->prepare('SELECT * FROM posts WHERE uploaded_by = ?');
        $stmt->execute([$_SESSION['user']['id']]);

        $_SESSION['files'] = [];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";

        foreach ($rows as $row) {
            $file = $row;

            $file['urls'] = [
                'download_url' => "$url/{$row['id']}",
                'deletion_url' => "$url/posts/delete.php?id={$row['id']}",
            ];

            unset($file['password']);

            array_push($_SESSION['files'], $file);
        }
    } else {
        setcookie('secret_key', '', time() - 1000, '/');

        session_unset();
        session_destroy();
    }

    $stmt = null;
    $db = null;
}