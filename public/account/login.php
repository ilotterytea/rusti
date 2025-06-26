<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../partials.php';

$db = new PDO(DB_URL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['username'], $_POST['password'])) {
        exit(json_response(null, 'Username and password must be set in request', 400));
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT password, secret_key FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (password_verify($password, $row['password'])) {
            setcookie('secret_key', $row['secret_key'], time() + USER_COOKIE_TIME, '/');
            generate_alert('/', ['secret_key' => $row['secret_key']], "Welcome back, $username");
            exit;
        }

        generate_alert('/account/login.php', null, "Passwords don't match", 403);
    } else {
        generate_alert('/account/login.php', null, "Requested user doesn't exist", 404);
    }
}
?>
<html>

<head>
    <title>Log in - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <?php meta_opengraph(title: "Login") ?>
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

                <form action="/account/login.php" method="post" enctype="multipart/form-data">
                    <h3>Log in to account</h3>
                    <hr>
                    <table class="vertical left">
                        <tr>
                            <th>Username:</th>
                            <td><input type="text" name="username" required></td>
                        </tr>
                        <tr>
                            <th>Password:</th>
                            <td><input type="password" name="password" required></td>
                        </tr>
                    </table>
                    <div class="row gap-8">
                        <button type="submit">Login</button>
                        <?php if (USER_REGISTRATION): ?>
                            <a href="/account/register.php">Register a new account</a>
                        <?php endif; ?>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>

</html>