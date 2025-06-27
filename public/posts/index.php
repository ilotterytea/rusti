<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';

function get_all_files(
    PDO &$db,
    bool $only_public = true,
    int|null $user_id = null,
    string|null $tags = null,
    int $page,
    int &$max_pages,
    bool $preview_only = false
): array {
    // retrieving parameters
    $limit = FILES_MAX_ITEMS;
    $offset = $limit * $page;
    $sort_by = $_GET['s'] ?? 'recent';

    $sql = "FROM posts p";
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

    if ($preview_only) {
        $sql .= ($where ? " AND" : " WHERE") . " p.mime LIKE 'image/%' OR p.mime LIKE 'video/%'";
        $where = true;
    }

    if ($only_public) {
        $sql .= ($where ? " AND" : " WHERE") . " p.visibility = 1";
        $where = true;
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

    $countStmt = $db->prepare("SELECT COUNT(*) $sql");
    foreach ($params as $p) {
        $countStmt->bindParam($p[0], $p[1], $p[2]);
    }

    $sql .= " LIMIT ? OFFSET ?";

    array_push($params, [$i + 1, $limit, PDO::PARAM_INT]);
    array_push($params, [$i + 2, $offset, PDO::PARAM_INT]);

    $stmt = $db->prepare('SELECT p.id, p.mime, p.extension, p.uploaded_at, p.size ' . $sql);

    foreach ($params as $p) {
        $stmt->bindParam($p[0], $p[1], $p[2]);
    }
    $stmt->execute();
    $countStmt->execute();
    $max_pages = ceil(intval($countStmt->fetch()[0]) / FILES_MAX_ITEMS);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_file(PDO &$db, mixed $row): mixed
{

    // checking expiration
    if (
        isset($row['expires_at']) &&
        (
            ($row['expires_at'] == $row['uploaded_at'] && $row['views'] != 0) ||
            ($row['expires_at'] != $row['uploaded_at'] && strtotime($row['expires_at']) - time() <= 0)
        )
    ) {
        delete_file($row['id'], $db);
        return null;
    }

    $post = $row;
    unset($post['password']);

    if (isset($post['uploaded_by'])) {
        $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$post['uploaded_by']]);
        $post['uploaded_by'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

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
    if (!in_array($post['id'], $_SESSION['viewed_files']) && !in_array($post['id'], $_SESSION['uploaded_files'] ?? [])) {
        array_push($_SESSION['viewed_files'], $post['id']);
        $post['views']++;
        $db->prepare("UPDATE posts SET views = ? WHERE id = ?")
            ->execute([$post['views'], $post['id']]);
    }

    return $post;
}

authorize_user();
$is_admin = isset($_SESSION['user']) && $_SESSION['user']['is_admin'];

$db = new PDO(DB_URL);

$popular_tags = null;
$random_post = null;

$posts = null;
$page = max(intval($_GET['p'] ?? '1'), 1) - 1;
$max_pages = 0;

$grid_mode = ($_GET['m'] ?? 'grid') == 'grid';
$infinite_mode = ($_GET['wall'] ?? 'finite') == 'infinite';
$tags = str_safe($_GET['q'] ?? '', null);
$preview_files = ($_GET['pf'] ?? 'true') == 'true';
if (empty($tags)) {
    $tags = null;
}
$redirect = [
    'q' => $tags,
    'p' => $page + 1,
    'by' => $_GET['by'] ?? '',
    'm' => $_GET['m'] ?? 'grid',
    'pf' => $preview_files ? 'true' : 'false'
];
$redirect = http_build_query($redirect);

$post = null;
$user = null;

$file_name = null;

// single file
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$_GET['id']]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $post = get_file($db, $row);
        $file_name = "/{$post['id']}.{$post['extension']}";
    }
}
// user files
else if (isset($_GET['by']) && !empty($_GET['by'])) {
    $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$_GET['by']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user['is_same_user'] = isset($_SESSION['user']) ? ($user['id'] == $_SESSION['user']['id']) : false;

        $posts = get_all_files($db, !($user['is_same_user'] || $is_admin), $user['id'], $page, $max_pages, $preview_files);
    }
}
// all files
else if (FILES_LIST_ENABLED || $is_admin) {
    $posts = get_all_files($db, !$is_admin, null, $tags, $page, $max_pages, $preview_files);

    // fetching popular tags
    $stmt = $db->query("SELECT t.name, COUNT(p.id) AS usage FROM tags t
        INNER JOIN tag_posts tp ON tp.tag_id = t.id
        INNER JOIN posts p ON p.id = tp.post_id
        GROUP BY t.name
        ORDER BY usage DESC
        LIMIT 10
    ");
    $stmt->execute();
    $popular_tags = $stmt->fetchAll();

    // fetching a random post
    $stmt = $db->query("SELECT * FROM posts WHERE mime LIKE 'image/%' OR mime LIKE 'video/%' ORDER BY RANDOM() LIMIT 1");
    $stmt->execute();
    $random_post = $stmt->fetch() ?: null;
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
    <?php meta_opengraph($post, isset($posts) ? 'Posts' : null) ?>
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <?php if (isset($post)): ?>
                    <?php html_header() ?>

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
                                    <?php if ($post['expires_at'] == $post['uploaded_at']): ?>
                                        <th>Expires</th>
                                        <td style="color:red;font-weight:bold;">after viewing</td>
                                    <?php else: ?>
                                        <th>Expires in</th>
                                        <td style="color: red">about
                                            <?= format_timestamp(strtotime($post['expires_at']) - time()) ?>
                                        </td>
                                    <?php endif; ?>
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
                                <?php if ($post['size'] == 0): ?>
                                    <td>N/A</td>
                                <?php else: ?>
                                    <td><?= sprintf("%.2fMB", $post['size'] / 1024 / 1024) ?></td>
                                <?php endif; ?>
                            </tr>
                            <tr>
                                <th>MIME (Extension)</th>
                                <td><?= sprintf("%s (%s)", $post['mime'], $post['extension']) ?></td>
                            </tr>
                        </table>
                    </section>
                <?php elseif (isset($posts)): ?>
                    <?php
                    if ((isset($user['is_same_user']) && $user['is_same_user']) || $is_admin) {
                        $subtitle = 'Showing <u>all</u> ' . count($posts) . ' posts';
                    } else {
                        $subtitle = 'Showing ' . count($posts) . ' <u>public</u> posts';
                    }

                    if (isset($tags)) {
                        $subtitle .= " with tag <u>$tags</u>";
                    }

                    if ($max_pages > 0) {
                        $subtitle .= ' (Page ' . $page + 1 . "/$max_pages)";
                    }

                    html_header(
                        title: "Library of " . INSTANCE_NAME,
                        subtitle: $subtitle
                    );
                    ?>

                    <section class="row gap-8">
                        <!-- SIDE BAR -->
                        <section class="column gap-8" style="max-width: 20%;">
                            <!-- SEARCH -->
                            <form action="/posts/" method="get">
                                <div class="box">
                                    <div class="tab">
                                        <p>Search</p>
                                    </div>
                                    <div class="content column gap-8">
                                        <input type="text" name="q" value="<?= $tags ?>" required>
                                        <button type="submit">Find</button>
                                    </div>
                                </div>
                            </form>

                            <?php if (!empty($popular_tags)): ?>
                                <!-- POPULAR TAGS -->
                                <div class="box">
                                    <div class="tab">
                                        <p>Popular tags</p>
                                    </div>
                                    <div class="content column gap-8">
                                        <ul>
                                            <?php foreach ($popular_tags as $tag): ?>
                                                <ol>
                                                    <a
                                                        href="/posts/?q=<?= $tag[0] ?>"><?= sprintf("%s (%d)", $tag[0], $tag[1]) ?></a>
                                                </ol>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($random_post)): ?>
                                <!-- RANDOM POST -->
                                <div class="box random-post">
                                    <div class="tab">
                                        <p>Random post</p>
                                    </div>
                                    <div class="content justify-center align-center">
                                        <a href="/posts/?id=<?= $random_post['id'] ?>"
                                            class="column justify-center align-center" target="_blank">
                                            <?php if (str_starts_with($random_post['mime'], 'image/')): ?>
                                                <img src="/thumbnails/<?= $random_post['id'] ?>.jpeg"
                                                    alt="<?= $random_post['id'] ?>">
                                            <?php elseif (str_starts_with($random_post['mime'], 'video/')): ?>
                                                <img src="/thumbnails/<?= $random_post['id'] ?>.gif"
                                                    alt="<?= $random_post['id'] ?>">
                                            <?php else: ?>
                                                <p><?= $random_post['id'] ?></p>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- OTHER -->
                            <div class="box row gap-8 flex-wrap">
                                <?php if ($grid_mode): ?>
                                    <a href="/posts/?<?= "$redirect&m=row" ?>">
                                        <button>List view</button>
                                    </a>
                                <?php else: ?>
                                    <a href="/posts/?<?= "$redirect&m=grid" ?>">
                                        <button>Grid view</button>
                                    </a>
                                <?php endif; ?>

                                <?php if ($infinite_mode): ?>
                                    <a href="/posts/?<?= "$redirect&wall=finite" ?>">
                                        <button>Finite wall</button>
                                    </a>
                                <?php else: ?>
                                    <a href="/posts/?<?= "$redirect&wall=infinite" ?>">
                                        <button>Infinite wall</button>
                                    </a>
                                <?php endif; ?>

                                <?php if ($preview_files): ?>
                                    <a href="/posts/?<?= "$redirect&pf=false" ?>">
                                        <button>All files</button>
                                    </a>
                                <?php else: ?>
                                    <a href="/posts/?<?= "$redirect&pf=true" ?>">
                                        <button>Files with thumbnails</button>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if (($_GET['wall'] ?? 'finite') != 'infinite' && $max_pages > 1): ?>
                                <!-- PAGINATION -->
                                <div class="box row">
                                    <?php
                                    $query = [];
                                    parse_str($redirect, $query);
                                    unset($query['p']);
                                    $redirect = http_build_query($query);
                                    ?>

                                    <?php if ($page > 0): ?>
                                        <a href="/posts/?<?= $redirect . '&p=' . $page - 1 ?>">
                                            <button>Previous page</button>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($page < $max_pages - 1): ?>
                                        <a href="/posts/?<?= $redirect . '&p=' . $page + 2 ?>">
                                            <button>Next page</button>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                        <!-- FILES -->
                        <?php if ($grid_mode): ?>
                            <div class="files row grow flex-wrap gap-8" id="file-list">
                                <?php foreach ($posts as $post): ?>
                                    <div class="file">
                                        <a href="/<?= $post['id'] ?>" target="_blank">
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
                            </div>
                        <?php else: ?>
                            <table class="column grow gap-8" style="text-align:left">
                                <thead class="row">
                                    <tr class="row grow gap-8">
                                        <th class="grow">File name</th>
                                        <th><a
                                                href="/posts/?<?= $redirect . '&s=' . ($_GET['s'] ?? 'light') == 'heavy' ? 'light' : 'heavy' ?>">
                                                Size
                                            </a>
                                        </th>
                                        <th><a
                                                href="/posts/?<?= $redirect . '&s=' . ($_GET['s'] ?? 'recent') == 'oldest' ? 'recent' : 'oldest' ?>">
                                                Uploaded
                                            </a></th>
                                    </tr>
                                </thead>
                                <tbody class="column gap-8" id="file-list">
                                    <?php foreach ($posts as $post): ?>
                                        <tr class="row gap-8 file-row">
                                            <td class="row gap-8 align-center grow">
                                                <div style="width:32px;height:32px;">
                                                    <?php if (str_starts_with($post['mime'], 'image/')): ?>
                                                        <img src="/userdata/thumbnails/<?= $post['id'] ?>.jpeg" alt="<?= $post['id'] ?>"
                                                            height="32" width="32">
                                                    <?php elseif (str_starts_with($post['mime'], 'video/')): ?>
                                                        <img src="/userdata/thumbnails/<?= $post['id'] ?>.gif" alt="<?= $post['id'] ?>"
                                                            height="32" width="32">
                                                    <?php endif; ?>
                                                </div>
                                                <a href="/<?= $post['id'] ?>" target="_blank">
                                                    <?= sprintf("%s.%s", $post['id'], $post['extension']) ?>
                                                </a>
                                            </td>
                                            <?php if ($post['size'] == 0): ?>
                                                <td>N/A</td>
                                            <?php else: ?>
                                                <td><?= sprintf("%.2fMB", $post['size'] / 1024 / 1024) ?></td>
                                            <?php endif; ?>
                                            <td><?= format_timestamp(time() - strtotime($post['uploaded_at'])) ?>
                                                ago</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </section>
                <?php elseif (!FILES_LIST_ENABLED): ?>
                    <?php html_header(); ?>
                    Public file catalog is disabled on this instance.
                <?php else: ?>
                    <?php html_header(title: "Library of " . INSTANCE_NAME); ?>
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

<?php if ($infinite_mode): ?>
    <script>
        function formatTimestamp(timestampSecs) {
            const days = Math.floor(timestampSecs / (60 * 60 * 24));
            const hours = Math.floor((timestampSecs / (60 * 60)) % 24);
            const minutes = Math.floor((timestampSecs % (60 * 60)) / 60);
            const seconds = Math.floor(timestampSecs % 60);

            if (days === 0 && hours === 0 && minutes === 0) {
                return `${seconds} second${seconds !== 1 ? 's' : ''}`;
            } else if (days === 0 && hours === 0) {
                return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
            } else if (days === 0) {
                return `${hours} hour${hours !== 1 ? 's' : ''}`;
            } else {
                return `${days} day${days !== 1 ? 's' : ''}`;
            }
        }

        let loading = false;
        let page = <?= $page + 1 ?>;
        let startTimestamp = <?= time() ?>;
        const wall = document.getElementById("file-list");

        window.onscroll = () => {
            console.log("xd");
            if (wall.getBoundingClientRect().bottom <= window.innerHeight && !loading) {
                if (!loading) page++;
                loading = true;

                fetch(`/posts/?p=${page}&st=${startTimestamp}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then((r) => r.json())
                    .then((json) => {
                        if (json.status_code != 200) {
                            return;
                        }

                        let images = '';

                        for (const file of json.data) {
                            <?php if ($grid_mode): ?>
                                let htmlPreview = `<p>${file.id}</p>`;

                                if (file.mime.startsWith('image/')) {
                                    htmlPreview = `<img src="/thumbnails/${file.id}.jpeg" alt="${file.id}" />`;
                                } else if (file.mime.startsWith('video/')) {
                                    htmlPreview = `<img src="/thumbnails/${file.id}.gif" alt="${file.id}" />`;
                                }

                                images += `
                                <div class="file">
                                    <a href="/<?= $post['id'] ?>" target="_blank">
                                        ${htmlPreview}
                                    </a>
                                </div>
                                `;
                            <?php else: ?>
                                let htmlPreview = `<p>${file.id}</p>`;

                                if (file.mime.startsWith('image/')) {
                                    htmlPreview = `<img src="/thumbnails/${file.id}.jpeg" alt="${file.id}" width="32" height="32" />`;
                                } else if (file.mime.startsWith('video/')) {
                                    htmlPreview = `<img src="/thumbnails/${file.id}.gif" alt="${file.id}" width="32" height="32" />`;
                                }

                                const size = file.size == 0 ? "N/A" : ((file.size / 1024 / 1024).toFixed(2) + "MB");

                                images += `
                                <tr class="row gap-8 file-row">
                                    <td class="row gap-8 align-center grow">
                                        <div style="width:32px;height:32px;">
                                            ${htmlPreview}
                                        </div>
                                        <a href="/${file.id}" target="_blank">
                                            ${file.id}.${file.extension}
                                        </a>
                                        </td>
                                        <td>${size}</td>
                                        <td>${formatTimestamp((Date.now() / 1000) - Date.parse(file.uploaded_at))}
                                        ago</td>
                                </tr>
                                `;
                            <?php endif; ?>
                        }

                        wall.innerHTML += images;
                        loading = false;
                    });
            }
        };
    </script>
<?php endif; ?>

</html>