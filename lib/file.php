<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function delete_file(string $file_id, PDO &$db = null)
{
    if ($db) {
        $db->prepare("DELETE FROM posts WHERE id = ?")
            ->execute([$file_id]);
    }

    array_map('unlink', array_filter((array) glob(FILE_UPLOAD_DIRECTORY . "/uploads/$file_id.*")));
    array_map('unlink', array_filter((array) glob(FILE_UPLOAD_DIRECTORY . "/thumbnails/$file_id.*")));
}