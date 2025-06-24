<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

authorize_user();

$db = new PDO(DB_URL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id'], $_POST['message'])) {
        generate_alert('/posts/report.php', null, 'ID and message must be set!', 400);
        exit;
    }

    $message = str_safe($_POST['message'], null, false);
    if (empty($message)) {
        generate_alert('/posts/report.php', null, 'Message must not be empty!', 400);
        exit;
    }

    $id = $_POST['id'];

    $stmt = $db->prepare("SELECT id FROM posts
        WHERE id = ? AND id NOT IN (SELECT post_id FROM post_bans)
    ");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        generate_alert('/posts/report.php', null, 'File ID not found!', 404);
        exit;
    }

    $email = str_safe($_POST['feedback_email'] ?? '', null);
    if (empty($email)) {
        $email = null;
    }

    $db->prepare('INSERT INTO reports(post_id, message, feedback_email) VALUES (?, ?, ?)')
        ->execute([$id, $message, $email]);

    generate_alert('/', $report, 'Your report has been submitted!', 201);
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        $id = null;
    }
}

?>
<html>

<head>
    <title>Report abuse - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <section class="row align-center gap-8">
                    <a href="/"><img src="/static/img/brand.webp" alt="<?= INSTANCE_NAME ?>" height="20px"></a>
                    <h1><?= INSTANCE_NAME ?></h1>
                </section>

                <?php html_alert() ?>

                <form action="/posts/report.php" method="post" class="column gap-8">
                    <h3>Report abuse</h3>
                    <hr>
                    <table class="vertical left">
                        <tr>
                            <th>File ID:</th>
                            <td>
                                <?php if ($id): ?>
                                    <input type="text" name="id" value="<?= $id ?>" required>
                                <?php else: ?>
                                    <input type="text" name="id" required>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Feedback E-Mail:</th>
                            <td><input type="email" name="feedback_email"
                                    placeholder="Leave empty if you wish to stay anonymous"></td>
                        </tr>
                    </table>

                    <div class="column gap-8">
                        <p>Please describe what&apos;s wrong with this post:</p>
                        <textarea style="width: unset;" name="message" required></textarea>
                    </div>

                    <button type="submit">Send</button>
                </form>
            </main>
        </div>
    </div>
</body>

</html>