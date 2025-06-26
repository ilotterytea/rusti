<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

function html_header(bool $big = false, string $title = INSTANCE_NAME, string|null $subtitle = null)
{
    echo '<noscript title="&gt; still no upload history and fancy upload button">JavaScript deniers <img
            src="/static/img/icons/chad.png" width="20"></noscript>';

    echo '<header class="';
    echo $big ? 'column gap-8">' : 'row align-bottom gap-8">';


    $links = [
        '/posts/' => 'Posts',
        '/posts/upload.php' => 'Upload',
        '/account/' => 'Account'
    ];

    $is_admin = isset($_SESSION['user']) && $_SESSION['user']['is_admin'];

    if ($is_admin) {
        $links['/reports/'] = 'Reports';
    }

    if (str_starts_with($_SERVER['PHP_SELF'], '/index.php')) {
        unset($links['/posts/upload.php']);
    }

    if ($big) {
        echo '' ?>
        <section class="column justify-center align-center">
            <a href="/"><img src="/static/img/brand.webp" alt=""></a>
            <h1><?= $title ?></h1>
            <?php if (isset($subtitle)): ?>
                <p title="<?= strip_tags($subtitle) ?>"><?= $subtitle ?></p>
            <?php endif; ?>
        </section>
        <section class="row gap-8 align-center justify-center">
            <?php foreach ($links as $k => $v): ?>
                <a href="<?= $k ?>">
                    <button><?= $v ?></button>
                </a>
            <?php endforeach; ?>
        </section>
        <?php ;
    } else {
        echo '' ?>
        <section class="row align-bottom gap-8">
            <a href="/"><img src="/static/img/brand.webp" alt="" height="24"></a>
            <div class="column">
                <?php if (isset($subtitle)): ?>
                    <p class="font-tiny align-bottom" title="<?= strip_tags($subtitle) ?>"><?= $subtitle ?></p>
                <?php endif; ?>
                <h1 class="grow align-bottom"><?= $title ?></h1>
            </div>
        </section>
        <section class="row grow align-bottom gap-8">
            <?php foreach ($links as $k => $v): ?>
                <a href="<?= $k ?>">
                    <button><?= $v ?></button>
                </a>
            <?php endforeach; ?>
        </section>
        <?php ;
    }

    if (isset($_SESSION['user'])) {
        echo '' ?>
        <section class="row <?= $big ? 'justify-center' : '' ?> gap-8">
            <p>
                Logged in as
                <?php if ($_SESSION['user']['is_admin']): ?>
                    <span class="red">
                    <?php endif; ?>

                    <?= $_SESSION['user']['username'] ?>

                    <?php if ($_SESSION['user']['is_admin']): ?>
                    </span>
                <?php endif; ?>
            </p>
            <a href="/account/logout.php">
                <img src="/static/img/icons/door_out.png" alt="[Logout]">
            </a>
        </section>
        <?php ;
    }

    echo '</header>';

    html_alert();
}

function html_big_footer(PDO &$db)
{
    $url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";

    // counting files
    $file_stats = $db->query("SELECT COUNT(*), SUM(size) FROM posts");
    $file_stats->execute();
    $file_stats = $file_stats->fetch();
    $file_count = $file_stats[0];
    $file_overall_size = $file_stats[1];

    echo '' ?>
    <footer>
        <?php if (array_key_exists($url, INSTANCE_MIRRORS)): ?>
            <p>You're using a mirror (<?= INSTANCE_MIRRORS[$url] ?>). <a href="<?= INSTANCE_ORIGINAL_WEBSITE ?>">Check out the
                    original website!</a></p>
        <?php endif; ?>
        <?php
        $mirrors = array_filter(
            INSTANCE_MIRRORS,
            fn($k) => $k != $url,
            ARRAY_FILTER_USE_KEY
        );
        if (!empty($mirrors)): ?>
            <div class="row gap-8 justify-center align-center">
                <p>Mirrors:</p>
                <div class="row justify-between">
                    <?php foreach ($mirrors as $k => $v): ?>
                        <p><a href="<?= $k ?>"><?= $v ?></a></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <p>Serving <?= $file_count ?> files and <?= sprintf("%.2f", $file_overall_size / 1024 / 1024) ?>MB of
            active
            content</p>
    </footer>
    <?php ;
}

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