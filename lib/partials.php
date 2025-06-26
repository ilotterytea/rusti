<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function meta_opengraph($post = null, $title = null)
{
    $url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";

    echo '' ?>
    <meta property="og:type" content="website" />
    <meta property="og:description" content="A simple file uploader." />
    <meta property="og:url" content="<?= $url ?>" />
    <?php ;

    if (isset($post)) {
        $file_name = "{$post['id']}.{$post['extension']}";

        if (str_starts_with($post['mime'], 'image/')) {
            echo '' ?>
            <meta property="og:type" content="image" />
            <meta property="og:image" content="<?= "$url/{$post['id']}.jpeg" ?>" />
            <?php ;
        } else if (str_starts_with($post['mime'], 'video/')) {
            echo '' ?>
                <meta property="og:type" content="video" />
                <meta property="og:video" content="<?= "$url/{$post['id']}.gif" ?>" />
            <?php ;
        } else {
            echo '<meta property="og:type" content="file" />';
        }

        echo '<meta property="og:title" content="Post ' . $post['id'] . ' - ' . INSTANCE_NAME . '" />';

        echo '' ?>
        <meta property="og:file:description" content="<?= $post['comment'] ?: '' ?>" />
        <meta property="og:file:mime_type" content="<?= $post['mime'] ?>" />
        <meta property="og:file:url" content="<?= "$url/$file_name" ?>" />
        <meta property="og:file:size" content="<?= $post['size'] ?>" />
        <meta property="og:file:name" content="<?= $file_name ?>" />
        <?php ;
    } else {
        echo '<meta property="og:title" content="' . (isset($title) ? "$title - " : "") . INSTANCE_NAME . '" />';
    }
}