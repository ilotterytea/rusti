<?php
$uploaded_files_cookies = json_decode(stripslashes($_COOKIE['UPLOADED_FILES'] ?? '[]'), true) ?? [];
if (!is_array($uploaded_files_cookies)) {
    $uploaded_files_cookies = [];
}
?>
<html>

<head>
    <title>tinyi</title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <section class="brand">
                <img src="/static/img/brand.webp" alt="tinyi">
            </section>

            <section class="box file-upload">
                <div class="tab">
                    <p>File Upload</p>
                </div>
                <div class="content">
                    <form action="/upload.php" method="post" enctype="multipart/form-data">
                        <input type="file" name="file" id="file" required>
                        <button type="submit">Upload</button>
                    </form>
                </div>
            </section>

            <?php if (!empty($uploaded_files_cookies)): ?>
                <section class="box">
                    <div class="tab">
                        <p>Uploaded Files<span title="Stored locally in your cookies">*</span></p>
                    </div>
                    <div class="content uploaded-files">
                        <?php foreach ($uploaded_files_cookies as $f): ?>
                            <div class="box uploaded-file">
                                <div class="preview">
                                    <?php if (str_starts_with($f['mime'], 'image/')): ?>
                                        <img src="<?= $f['urls']['download_url'] ?>" alt="An image.">
                                    <?php elseif (str_starts_with($f['mime'], 'video/')): ?>
                                        <video muted>
                                            <source src="<?= $f['urls']['download_url'] ?>" type="<?= $f['mime'] ?>" alt="A video.">
                                        </video>
                                    <?php else: ?>
                                        <p><i>Non-displayable file.</i></p>
                                    <?php endif; ?>
                                </div>
                                <div class="summary">
                                    <h3><?= "{$f['id']}.{$f['extension']}" ?></h3>
                                    <div class="info">
                                        <p><?= $f['mime'] ?></p>
                                        <p><?= sprintf('%.2f', $f['size'] / 1024.0 / 1024.0) . 'MB' ?></p>
                                    </div>
                                    <a href="<?= $f['urls']['download_url'] ?>" target="_BLANK">[ Open ]</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>