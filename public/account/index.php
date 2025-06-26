<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';

authorize_user();
?>
<html>

<head>
    <title>Account - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <?php meta_opengraph(title: 'Account - ' . INSTANCE_NAME) ?>
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <?php html_header() ?>

                <?php if (isset($_SESSION['user'])): ?>
                    <section class="column">
                        <h1 <?= $_SESSION['user']['is_admin'] ? 'style="color:red"' : '' ?>>
                            <?= $_SESSION['user']['username'] ?>
                        </h1>
                        <hr>
                        <div class="row gap-8">
                            <a href="/account/delete.php">
                                <button>Delete account</button>
                            </a>
                            <a href="/account/logout.php?everywhere">
                                <button>Sign out everywhere</button>
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="row gap-8">
                        <a href="/account/login.php">
                            <button>Login</button>
                        </a>
                        <?php if (USER_REGISTRATION): ?>
                            <a href="/account/register.php">
                                <button>Register a new account</button>
                            </a>
                        <?php endif; ?>
                    </section>
                    <section>
                        <h1>Anonymous user</h1>
                        <hr>
                        <?php if (FILE_AUTHORIZED_UPLOAD): ?>
                            <p>
                                Account is required for file upload.
                                <?php if (USER_REGISTRATION): ?>
                                    <a href="/account/register.php">Register one today!</a>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p>These files are stored in your cookies.</p>
                            <p><b>You don't have to have a <?= INSTANCE_NAME ?> account</b> if you don't want your files linked
                                to you,
                                and don't
                                want other fancy features like albums.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="box">
                    <div class="tab">
                        <p>Uploaded files</p>
                    </div>
                    <div class="content uploaded-files" id="uploaded-files"
                        style="grid-template-columns: auto auto auto auto;">
                        <?php if (isset($_SESSION['files'])): ?>
                            <?php foreach ($_SESSION['files'] as $file): ?>
                                <div class="box uploaded-file">
                                    <div class="preview">
                                        <?php if (str_starts_with($file['mime'], 'image/')): ?>
                                            <img src="/thumbnails/<?= $file['id'] ?>.jpeg" alt="Missing thumbnail">
                                        <?php elseif (str_starts_with($file['mime'], 'video/')): ?>
                                            <img src="/thumbnails/<?= $file['id'] ?>.gif" alt="Missing thumbnail">
                                        <?php else: ?>
                                            <p><i>Non-displayable file.</i></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="summary">
                                        <h3><?= sprintf("%s.%s", $file['id'], $file['extension']) ?></h3>
                                        <div class="info">
                                            <p><?= $file['mime'] ?></p>
                                            <p><?= sprintf("%.2fMB", $file['size'] / 1024 / 1024) ?></p>
                                        </div>
                                        <div class="row gap-8">
                                            <a href="<?= $file['urls']['download_url'] ?>" target="_BLANK">
                                                <button>Open</button>
                                            </a>
                                            <?php if (isset($file['urls']['deletion_url'])): ?>
                                                <a href="<?= $file['urls']['deletion_url'] ?>">
                                                    <button>Delete</button>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <noscript><i>Even doe this feature requires JavaScript...</i></noscript>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>

<?php if (!isset($_SESSION['user'])): ?>
    <script>
        function loadUploadedFiles() {
            const uploadedFilesElement = document.getElementById("uploaded-files");
            uploadedFilesElement.innerHTML = '';

            let files = localStorage.getItem("uploaded_files");
            if (!files) {
                files = '[]';
            }
            files = JSON.parse(files);

            if (files.length == 0) {
                uploadedFilesElement.innerHTML = "<p><i>You haven't uploaded any file yet... <a href='/posts/upload.php'>Start uploading!</a>";
                return;
            }

            let elemOutput = '';

            for (const file of files) {
                let htmlPreview = "<p><i>Non-displayable file.</i></p>";

                if (file.mime.startsWith("image/")) {
                    htmlPreview = `<img src="/thumbnails/${file.id}.jpeg" alt="Missing thumbnail" />`;
                } else if (file.mime.startsWith("video/")) {
                    htmlPreview = `<img src="/thumbnails/${file.id}.gif" alt="Missing thumbnail" />`;
                }

                let delete_button = '';

                if (file.urls.deletion_url) {
                    delete_button = `
                    <a href="${file.urls.deletion_url}">
                        <button>Delete</button>
                    </a>
                    `;
                }

                elemOutput += `
                    <div class="box uploaded-file">
                        <div class="preview">
                            ${htmlPreview}
                        </div>
                        <div class="summary">
                            <h3>${file.id}.${file.extension}</h3>
                            <div class="info">
                                <p>${file.mime}</p>
                                <p>${(file.size / 1024 / 1024).toFixed(2)}MB</p>
                            </div>
                            <div class="row gap-8">
                                <a href="${file.urls.download_url}" target="_BLANK">
                                    <button>Open</button>
                                </a>
                                ${delete_button}
                            </div>
                        </div>
                    </div>
            `;
            }

            uploadedFilesElement.innerHTML = elemOutput;
        }

        loadUploadedFiles();
    </script>
<?php endif; ?>

</html>