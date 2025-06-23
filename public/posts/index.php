<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

$db = new PDO(DB_URL);

$posts = null;
$post = null;

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
// all files
else if (FILES_LIST_ENABLED) {
    // retrieving parameters
    $page = max(intval($_GET['p'] ?? '1'), 1) - 1;
    $limit = FILES_MAX_ITEMS;
    $offset = $limit * $page;
    $sort_by = $_GET['s'] ?? 'recent';

    $sql = "SELECT id, mime, extension FROM posts";

    $sql .= " ORDER BY " . match ($sort_by) {
        'light' => 'size ASC',
        'heavy' => 'size DESC',
        'oldest' => 'uploaded_at ASC',
        default => 'uploaded_at DESC'
    };

    $sql .= " LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <h1>Public catalog of <?= INSTANCE_NAME ?></h1>
                        <p>Here are only files with <u>public</u> visibility, don&apos;t worry.</p>
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
                <?php else: ?>
                    Public file catalog is disabled on this instance.
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>

<?php if (isset($post)): ?>
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