<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';

function get_all_files(PDO &$db, bool $only_public = true, int|null $user_id = null): array
{
    // retrieving parameters
    $page = max(intval($_GET['p'] ?? '1'), 1) - 1;
    $limit = FILES_MAX_ITEMS;
    $offset = $limit * $page;
    $sort_by = $_GET['s'] ?? 'recent';

    $sql = "SELECT id, mime, extension FROM posts";

    if ($only_public) {
        $sql .= " WHERE visibility = 1";
    }

    $i = 0;

    if ($user_id) {
        $sql .= ($only_public ? " AND " : " WHERE ") . "uploaded_by = ?";
        $i++;
    }

    $sql .= " ORDER BY " . match ($sort_by) {
        'light' => 'size ASC',
        'heavy' => 'size DESC',
        'oldest' => 'uploaded_at ASC',
        default => 'uploaded_at DESC'
    };

    $sql .= " LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    if ($user_id) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    }
    $stmt->bindParam($i + 1, $limit, PDO::PARAM_INT);
    $stmt->bindParam($i + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

authorize_user();
$is_admin = isset($_SESSION['user']) && $_SESSION['user']['is_admin'];

$db = new PDO(DB_URL);

$posts = null;
$post = null;
$user = null;

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
    $posts = get_all_files($db, !$is_admin);
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
                        <?php if (isset($_SESSION['user']) && ($_SESSION['user']['is_admin'] || (isset($post['uploaded_by']) && $post['uploaded_by']['id'] == $_SESSION['user']['id']))): ?>
                            <a href="/posts/delete.php?id=<?= $post['id'] ?>">
                                <button>Delete</button>
                            </a>
                        <?php endif; ?>
                    </section>

                    <!-- File info -->
                    <section class="box">
                        <table class="vertical">
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

                        <?php if ((isset($user['is_same_user']) && $user['is_same_user']) || $is_admin): ?>
                            <p>Showing <u>all</u> <?= count($posts) ?> posts</p>
                        <?php else: ?>
                            <p>Showing <?= count($posts) ?> <u>public</u> posts</p>
                        <?php endif; ?>
                    </section>
                    <section class="files row flex-wrap gap-8">
                        <?php foreach ($posts as $post): ?>
                            <div class="file">
                                <a href="/<?= $post['id'] ?>" target="_BLANK">
                                    <?php if (str_starts_with($post['mime'], 'image/') || str_starts_with($post['mime'], 'video/')): ?>
                                        <img src="/thumbnails/<?= $post['id'] ?>.jpeg" alt="<?= $post['id'] ?>">
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