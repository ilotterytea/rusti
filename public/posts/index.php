<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';

function get_all_files(PDO &$db, bool $only_public = true, int|null $user_id = null, string|null $tags = null): array
{
    // retrieving parameters
    $page = max(intval($_GET['p'] ?? '1'), 1) - 1;
    $limit = FILES_MAX_ITEMS;
    $offset = $limit * $page;
    $sort_by = $_GET['s'] ?? 'recent';

    $sql = "SELECT p.id, p.mime, p.extension FROM posts p";
    $i = 0;
    $where = false;
    $params = [];

    if ($tags) {
        $sql .= " INNER JOIN tags t ON t.name = ?
            INNER JOIN tag_posts tp ON tp.tag_id = t.id
            WHERE tp.post_id = p.id";
        array_push($params, [1, $tags, PDO::PARAM_STR]);
        $where = true;
        $i++;
    }

    if ($only_public) {
        $sql .= ($where ? " AND" : " WHERE") . " p.visibility = 1";
    }

    if ($user_id) {
        $sql .= ($where ? " AND " : " WHERE ") . "p.uploaded_by = ?";
        array_push($params, [$i + 1, $user_id, PDO::PARAM_INT]);
        $i++;
    }

    $sql .= " ORDER BY " . match ($sort_by) {
        'light' => 'p.size ASC',
        'heavy' => 'p.size DESC',
        'oldest' => 'p.uploaded_at ASC',
        default => 'p.uploaded_at DESC'
    };

    $sql .= " LIMIT ? OFFSET ?";

    array_push($params, [$i + 1, $limit, PDO::PARAM_INT]);
    array_push($params, [$i + 2, $offset, PDO::PARAM_INT]);

    $stmt = $db->prepare($sql);
    foreach ($params as $p) {
        $stmt->bindParam($p[0], $p[1], $p[2]);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

authorize_user();
$is_admin = isset($_SESSION['user']) && $_SESSION['user']['is_admin'];

$db = new PDO(DB_URL);

$posts = null;
$post = null;
$user = null;
$tags = str_safe($_GET['q'] ?? '', null);
if (empty($tags)) {
    $tags = null;
}

$file_name = null;

// single file
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$_GET['id']]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $post = $row;
        unset($post['password']);

        if (isset($post['uploaded_by'])) {
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$post['uploaded_by']]);
            $post['uploaded_by'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $file_name = "/{$post['id']}.{$post['extension']}";

        // fetching ban
        $stmt = $db->prepare("SELECT * FROM post_bans WHERE post_id = ?");
        $stmt->execute([$post['id']]);
        $post['ban'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // fetching tags
        $stmt = $db->prepare("SELECT t.name FROM tags t
            INNER JOIN tag_posts tp ON tp.post_id = ?
            WHERE t.id = tp.tag_id
        ");
        $stmt->execute([$post['id']]);
        $post['tags'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');

        // count views
        if (!in_array($post['id'], $_SESSION['viewed_files'])) {
            array_push($_SESSION['viewed_files'], $post['id']);
            $post['views']++;
            $db->prepare("UPDATE posts SET views = ? WHERE id = ?")
                ->execute([$post['views'], $post['id']]);
        }
    }
}
// user files
else if (isset($_GET['by'])) {
    $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$_GET['by']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user['is_same_user'] = isset($_SESSION['user']) ? ($user['id'] == $_SESSION['user']['id']) : false;

        $posts = get_all_files($db, !($user['is_same_user'] || $is_admin), $user['id']);
    }
}
// all files
else if (FILES_LIST_ENABLED || $is_admin) {
    $posts = get_all_files($db, !$is_admin, null, $tags);
}

if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
    if ($post) {
        exit(json_response($post));
    } else if ($posts) {
        exit(json_response($posts));
    } else {
        exit(json_response(null, 'Not found', 404));
    }
}
?>
<html>

<head>
    <title>
        <?php if (isset($post)): ?>
            Post <?= $post['id'] ?>
        <?php else: ?>
            Posts
        <?php endif; ?>
        - <?= INSTANCE_NAME ?>
    </title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <?php if (isset($post)): ?>
                    <section class="row align-center gap-8">
                        <a href="/"><img src="/static/img/brand.webp" alt="<?= INSTANCE_NAME ?>" height="20px"></a>
                        <h1><?= INSTANCE_NAME ?></h1>
                    </section>

                    <?php html_alert() ?>

                    <!-- File preview -->
                    <?php if (isset($post['ban'])): ?>
                        <div class="box column red">
                            <p><b>This post has been banned
                                    <?= format_timestamp(time() - strtotime($post['ban']['banned_at'])) ?> ago</b>
                            </p>
                            <?php if (isset($post['ban']['reason'])): ?>
                                <p>Reason: <u><?= $post['ban']['reason'] ?></u></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <section class="file-preview box column justify-center align-center">
                            <?php if (str_starts_with($post['mime'], 'image/')): ?>
                                <img src="/userdata<?= $file_name ?>" alt="An image.">
                            <?php elseif (str_starts_with($post['mime'], 'video/')): ?>
                                <video autoplay controls>
                                    <source src="/userdata<?= $file_name ?>" type="<?= $post['mime'] ?>">
                                </video>
                            <?php elseif (str_starts_with($post['mime'], 'audio/')): ?>
                                <audio autoplay controls>
                                    <source src="/userdata<?= $file_name ?>" type="<?= $post['mime'] ?>">
                                </audio>
                            <?php elseif (str_starts_with($post['mime'], 'text/')): ?>
                                <pre><?= file_get_contents(FILE_UPLOAD_DIRECTORY . $file_name) ?></pre>
                            <?php else: ?>
                                <p><i>No preview</i></p>
                            <?php endif; ?>
                        </section>

                        <!-- File actions -->
                        <section class="box row-reverse gap-8" id="file-actions">
                            <a href="<?= $file_name ?>">
                                <button>Raw</button>
                            </a>
                            <a href="<?= $file_name ?>" download>
                                <button>Download</button>
                            </a>
                            <a href="/posts/report.php?id=<?= $post['id'] ?>">
                                <button>Report</button>
                            </a>
                            <?php if ($is_admin && !isset($post['ban'])): ?>
                                <form action="/posts/ban.php" method="post">
                                    <input type="text" name="id" value="<?= $post['id'] ?>" style="display: none;">
                                    <input type="text" name="reason" placeholder="Ban reason">
                                    <button type="submit">Ban</button>
                                </form>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['user']) && ($_SESSION['user']['is_admin'] || (isset($post['uploaded_by']) && $post['uploaded_by']['id'] == $_SESSION['user']['id']))): ?>
                                <a href="/posts/delete.php?id=<?= $post['id'] ?>">
                                    <button>Delete</button>
                                </a>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <!-- File info -->
                    <section class="box">
                        <table class="vertical">
                            <?php if (!empty($post['tags'])): ?>
                                <tr>
                                    <th>Tags</th>
                                    <td>
                                        <?php foreach ($post['tags'] as $tag): ?>
                                            <a href="/posts/?q=<?= $tag ?>"><?= $tag ?></a>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Uploader</th>
                                <td>
                                    <?php if (isset($post['uploaded_by'])): ?>
                                        <a
                                            href="/posts/?by=<?= $post['uploaded_by']['id'] ?>"><?= $post['uploaded_by']['username'] ?></a>
                                    <?php else: ?>
                                        <i>Anonymous</i><?php endif; ?>,
                                    about <?= format_timestamp(time() - strtotime($post['uploaded_at'])) ?> ago
                                </td>
                            </tr>
                            <?php if (isset($post['expires_at'])): ?>
                                <tr>
                                    <th>Expires in</th>
                                    <td style="color: red">about
                                        <?= format_timestamp(strtotime($post['expires_at']) - time()) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Comment</th>
                                <td>
                                    <p>
                                        <?php if (isset($post['comment'])): ?>
                                            <?= $post['comment'] ?>
                                        <?php else: ?>
                                            <i>Empty</i>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th>Visibility</th>
                                <td>
                                    <?php switch ($post['visibility']):
                                        case 0: ?>
                                            Unlisted
                                            <?php break;
                                        case 1: ?>
                                            Public
                                            <?php break;
                                        default: ?>
                                            N/A
                                    <?php endswitch; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Views</th>
                                <td><?= $post['views'] ?></td>
                            </tr>
                            <tr>
                                <th>Size</th>
                                <td><?= sprintf("%.2fMB", $post['size'] / 1024 / 1024) ?></td>
                            </tr>
                            <tr>
                                <th>MIME (Extension)</th>
                                <td><?= sprintf("%s (%s)", $post['mime'], $post['extension']) ?></td>
                            </tr>
                        </table>
                    </section>
                <?php elseif (isset($posts)): ?>
                    <section class="brand">
                        <a href="/"><img src="/static/img/brand.webp" alt="<?= INSTANCE_NAME ?>"></a>
                        <?php if (isset($user)): ?>
                            <h1>File catalog of <?= $user['username'] ?></h1>
                        <?php else: ?>
                            <h1>File catalog of <?= INSTANCE_NAME ?></h1>
                        <?php endif; ?>

                        <p>
                            <?php if ((isset($user['is_same_user']) && $user['is_same_user']) || $is_admin): ?>
                                Showing <u>all</u> <?= count($posts) ?> posts
                            <?php else: ?>
                                Showing <?= count($posts) ?> <u>public</u> posts
                            <?php endif; ?>

                            <?php if (isset($tags)): ?>
                                with tag <u><?= $tags ?></u>
                            <?php endif; ?>
                        </p>
                    </section>
                    <section class="files row flex-wrap gap-8">
                        <?php foreach ($posts as $post): ?>
                            <div class="file">
                                <a href="/<?= $post['id'] ?>" target="_BLANK">
                                    <?php if (str_starts_with($post['mime'], 'image/')): ?>
                                        <img src="/thumbnails/<?= $post['id'] ?>.jpeg" alt="<?= $post['id'] ?>">
                                    <?php elseif (str_starts_with($post['mime'], 'video/')): ?>
                                        <img src="/thumbnails/<?= $post['id'] ?>.gif" alt="<?= $post['id'] ?>">
                                    <?php else: ?>
                                        <p><?= $post['id'] ?></p>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php elseif (!FILES_LIST_ENABLED): ?>
                    Public file catalog is disabled on this instance.
                <?php else: ?>
                    Nothing found.
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>

<?php if (isset($post) && !isset($_SESSION['user'])): ?>
    <script>
        function get_deletion_button() {
            const storage = localStorage.getItem("uploaded_files");

            if (storage) {
                const files = JSON.parse(storage);
                const file = files.find((v) => v.id == '<?= $post['id'] ?>');

                if (file && file.password) {
                    document.getElementById("file-actions").innerHTML += `
                    <a href="/posts/delete.php?id=<?= $post['id'] ?>&password=${file.password}">
                    <button>Delete</button>
                    </a>`;
                }
            }
        }

        get_deletion_button();
    </script>
<?php endif; ?>

</html>