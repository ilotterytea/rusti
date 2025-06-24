<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/image.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

authorize_user();

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    generate_alert('/', null, null, 200);
    exit;
}

$db = new PDO(DB_URL);

$stmt = $db->query("SELECT * FROM reports WHERE ban_id IS NULL ORDER BY sent_at DESC");
$stmt->execute();

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<html>

<head>
    <title>Reports - <?= INSTANCE_NAME ?></title>
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
                    <h1>Reports of <?= INSTANCE_NAME ?></h1>
                </section>

                <?php html_alert() ?>

                <?php if (empty($reports)): ?>
                    <p>No reports!</p>
                <?php else: ?>
                    <section class="column gap-8">
                        <p>Total: <?= count($reports) ?> reports</p>

                        <table>
                            <tr>
                                <th>#</th>
                                <th>File</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?= $report['id'] ?></td>
                                    <td><a href="/<?= $report['post_id'] ?>" target="_BLANK"><?= $report['post_id'] ?></a></td>
                                    <td><?= $report['message'] ?></td>
                                    <td class="row gap-8">
                                        <?php if (isset($report['feedback_email'])): ?>
                                            <a href="mailto:<?= $report['feedback_email'] ?>">
                                                <button>E-mail</button>
                                            </a>
                                        <?php endif; ?>
                                        <form action="/reports/delete.php" method="post">
                                            <input type="text" name="id" value="<?= $report['id'] ?>" style="display: none;">
                                            <button type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>

</html>