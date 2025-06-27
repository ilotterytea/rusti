<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/account.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';

authorize_user();
$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";

$file_types = [];

foreach (MIME_MAP as $k => $v) {
    $type = ucfirst(explode('/', $k)[0]);
    if (!array_key_exists($type, $file_types)) {
        $file_types[$type] = [];
    }

    if (!in_array($v, $file_types[$type])) {
        array_push($file_types[$type], $v);
    }
}
?>
<html>

<head>
    <title>Uploaders - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <?php meta_opengraph(title: 'Uploaders') ?>
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <?php html_header() ?>
                <section class="column gap-16">
                    <div>
                        <h1>File Uploaders</h1>
                        <p>Configure your software to work with <?= INSTANCE_NAME ?></p>
                    </div>

                    <!-- SOFTWARE -->
                    <div class="row gap-8">
                        <!-- SHAREX -->
                        <section class="column">
                            <div class="column">
                                <h2>ShareX</h2>
                                <p class="small-font">(Destinations &rarr; Custom uploader settings &rarr; New)</p>
                            </div>
                            <table class="vertical">
                                <tr>
                                    <th>Name:</th>
                                    <td><code><?= INSTANCE_NAME ?></code></td>
                                </tr>
                                <tr>
                                    <th>Request URL:</th>
                                    <td><code class="copy"><?= $url ?>/posts/upload.php</code></td>
                                </tr>
                                <tr>
                                    <th>Destination type:</th>
                                    <td><code>Image uploader</code></td>
                                </tr>
                                <tr>
                                    <th>Method:</th>
                                    <td><code>POST</code></td>
                                </tr>
                                <tr>
                                    <th>Body:</th>
                                    <td><code>Form data (multipart/form-data)</code></td>
                                </tr>
                                <tr>
                                    <th>Headers:</th>
                                    <td><code>Accept: application/json</code></td>
                                </tr>
                                <tr>
                                    <th>File form name:</th>
                                    <td><code class="copy">file</code></td>
                                </tr>
                                <tr>
                                    <th>URL:</th>
                                    <td><code class="copy">{json:data.urls.download_url}</code></td>
                                </tr>
                                <tr>
                                    <th>Deletion URL:</th>
                                    <td><code class="copy">{json:data.urls.deletion_url}</code></td>
                                </tr>
                            </table>
                            <p>Then, select it via <b>Destinations &rarr; Image uploader &rarr; Custom image
                                    uploader</b></p>
                        </section>

                        <!-- CHATTERINO -->
                        <section class="column">
                            <div class="column">
                                <h2>Chatterino</h2>
                                <p class="small-font">(Settings &rarr; External tools &rarr; Image Uploader)</p>
                            </div>
                            <table class="vertical">
                                <tr>
                                    <th>Request URL:</th>
                                    <td><code class="copy"><?= $url ?>/posts/upload.php</code></td>
                                </tr>
                                <tr>
                                    <th>Form field:</th>
                                    <td><code class="copy">file</code></td>
                                </tr>
                                <tr>
                                    <th>Extra headers:</th>
                                    <td><code class="copy">Accept: application/json</code></td>
                                </tr>
                                <tr>
                                    <th>Image link:</th>
                                    <td><code class="copy">{data.urls.download_url}</code></td>
                                </tr>
                                <tr>
                                    <th>Deletion link:</th>
                                    <td><code class="copy">{data.urls.deletion_url}</code></td>
                                </tr>
                            </table>
                        </section>
                    </div>

                    <!-- API -->
                    <section class="column gap-16">
                        <h2>API</h2>
                        <div class="column">
                            <h3>Endpoint</h3>
                            <hr>
                            <p><code>POST <span class="copy"><?= $url ?>/posts/upload.php</span></code></p>
                        </div>

                        <div>
                            <h3>Request Format</h3>
                            <hr>
                            <table class="vertical">
                                <tr>
                                    <th>Method:</th>
                                    <td><code>POST</code></td>
                                </tr>
                                <tr>
                                    <th>Content-Type:</th>
                                    <td><code>multipart/form-data</code></td>
                                </tr>
                                <tr>
                                    <th>Headers:</th>
                                    <td><code>Accept: application/json</code></td>
                                </tr>
                                <tr>
                                    <th>File field:</th>
                                    <td><code>file</code></td>
                                </tr>
                                <tr>
                                    <th>Max file size:</th>
                                    <td><code><?= ini_get("upload_max_filesize") ?></code></td>
                                </tr>
                            </table>
                        </div>

                        <div class="column">
                            <h3>Supported file extensions</h3>
                            <hr>
                            <table class="vertical">
                                <?php foreach ($file_types as $type => $exts): ?>
                                    <tr>
                                        <th><?= $type ?>:</th>
                                        <td style="text-align: justify"><?= implode(' ', $exts) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </section>
                </section>
            </main>
        </div>
    </div>
</body>

<script>
    const copyButtons = document.querySelectorAll(".copy");
    for (const copyButton of copyButtons) {
        const content = copyButton.innerHTML;
        const button = document.createElement("button");

        button.innerHTML = '<img src="/static/img/icons/paste_plain.png" alt="Copy" />';
        button.addEventListener("click", () => {
            navigator.clipboard.writeText(content);
        });

        copyButton.parentElement.appendChild(button);
    }
</script>

</html>