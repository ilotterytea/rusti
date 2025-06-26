<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';

$db = new PDO(DB_URL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['username'], $_POST['password'])) {
        exit(json_response(null, 'Username and password must be set in request', 400));
    }

    $username = str_safe($_POST['username'], USER_NAME_LENGTH[1]);
    if (strlen($username) < USER_NAME_LENGTH[0] || strlen($username) > USER_NAME_LENGTH[1]) {
        generate_alert('/account/register.php', null, sprintf('Your username length must be between %s and %s characters', USER_NAME_LENGTH[0], USER_NAME_LENGTH[1]), 400);
        exit;
    }

    $password = $_POST['password'];
    if (strlen($password) < USER_PASSWORD_LENGTH) {
        generate_alert('/account/register.php', null, sprintf('Your password must be at least %s characters', USER_PASSWORD_LENGTH), 400);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->rowCount() != 0) {
        generate_alert('/account/register.php', null, 'The username has already been taken', 409);
        exit;
    }

    $secret_key = bin2hex(random_bytes(16));

    $db->prepare("INSERT INTO users(username, password, secret_key) VALUES (?, ?, ?)")
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $secret_key]);

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$db->lastInsertId()]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    session_start();
    $_SESSION['user'] = $user;

    unset($user['password']);
    unset($user['is_admin']);

    setcookie('secret_key', $secret_key, time() + USER_COOKIE_TIME, '/');

    generate_alert('/', $user, "Created a new account: $username", 201);
    exit;
}
?>
<html>

<head>
    <title>Register - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <?php meta_opengraph(title: 'Register') ?>
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <?php html_header() ?>

                <form action="/account/register.php" method="post" enctype="multipart/form-data">
                    <h3>Register an account</h3>
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
                        <button type="submit">Register</button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>

</html>