<?php
$uploaded_files_cookies = json_decode(stripslashes($_COOKIE['UPLOADED_FILES'] ?? '[]'), true) ?? [];
if (!is_array($uploaded_files_cookies)) {
    $uploaded_files_cookies = [];
}

// cv pasted from https://gist.github.com/eusonlito/5099936
function size($dir): array
{
    $size = 0;
    $count = 0;

    foreach (glob(rtrim($dir, '/') . '/*') as $each) {
        $size += is_file($each) ? filesize($each) : size($each);
        $count++;
    }

    return [$size, $count];
}

$upload_directory = './static/uploads';
$configpath = $_SERVER['DOCUMENT_ROOT'] . '/../tinyi.ini';
$config = null;

if (file_exists($configpath)) {
    $config = parse_ini_file($configpath, true);

    if (isset($config['files']['upload_directory'])) {
        $upload_directory = $config['files']['upload_directory'];
    }
}

[$file_overall_size, $file_count] = size($upload_directory);

$instance_name = $config['instance']['name'] ?? $_SERVER['HTTP_HOST'];
?>
<html>

<head>
    <title><?= $instance_name ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
</head>

<body>
    <noscript>Enabled limited functionality mode</noscript>
    <div class="container">
        <div class="wrapper">
            <main>
                <section class="brand">
                    <img src="/static/img/brand.png" alt="<?= $instance_name ?>">
                    <h1><?= $instance_name ?></h1>
                </section>

                <section class="box file-upload">
                    <div class="tab">
                        <div class="grow">
                            <p>File Upload</p>
                        </div>
                        <button onclick="resetUpload()" id="form-reset-button" style="display: none;"><img
                                src="/static/img/icons/cancel.png" alt="X"></button>
                    </div>
                    <div class="content">
                        <form action="/upload.php" method="post" enctype="multipart/form-data" id="form-upload">
                            <input type="file" name="file" id="form-file" required>
                            <button type="submit" id="form-submit-button">Upload</button>
                        </form>
                        <div class="form-upload-result row" id="form-upload-summary" style="display: none;">
                            <div class="column grow gap-8">
                                <h1 id="form-upload-filename"></h1>
                                <p class="small-font text-gray" id="form-url" style="display: none"></p>
                            </div>
                            <div class="row gap-8" id="form-actions" style="display: none;">
                                <button onclick="copyLink()">Copy</button>
                                <button onclick="openLink()">Open</button>
                            </div>
                            <button onclick="uploadForm()" id="form-upload-button">Upload</button>
                        </div>
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
                                                <source src="<?= $f['urls']['download_url'] ?>" type="<?= $f['mime'] ?>"
                                                    alt="A video.">
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
            </main>
            <footer>
                <?php
                $mirror = explode(';', $config['instance']['mirror'] ?? '');
                if (count($mirror) > 1): ?>
                    <p style="margin-bottom: 12px">You're looking in the mirror for <?= $mirror[1] ?>. <a
                            href="<?= $mirror[0] ?>">[ Check out the
                            origin website
                            ]</a></p>
                <?php endif; ?>
                <p>a part of</p>
                <a href="https://alright.party" target="_blank"><img src="/static/img/alrightparty.png"
                        alt="alright.party"></a>
                <p>Serving <?= $file_count ?> files and <?= sprintf("%.2f", $file_overall_size / 1024 / 1024) ?>MB of
                    active
                    content</p>
            </footer>
        </div>
    </div>
</body>

<script>
    let lastUrl = null;

    const formFile = document.getElementById("form-file");
    const formSubmitButton = document.getElementById("form-submit-button");

    // Decorating the form
    const form = document.getElementById("form-upload");
    formSubmitButton.style.display = 'none';
    formFile.style.display = 'none';
    form.innerHTML += '<div class="form-dropzone" id="form-dropzone"><h1>Click to select file</h1><p>The upload will start immediately after selection</p></div>';

    document.getElementById("form-dropzone").addEventListener("click", () => formFile.click());
    formFile.addEventListener("change", () => previewFile());

    function previewFile() {
        const file = formFile.files[0];
        document.getElementById("form-dropzone").style.display = 'none';
        document.getElementById("form-upload-summary").style.display = 'flex';
        document.getElementById("form-upload-button").style.display = 'flex';
        document.getElementById("form-upload-filename").innerHTML = file.name;
        document.getElementById("form-reset-button").style.display = 'flex';
    }

    function uploadForm() {
        lastUrl = null;
        const form = new FormData();
        form.append("file", formFile.files[0]);

        fetch("/upload.php", {
            "method": "POST",
            "headers": {
                "Accept": "application/json"
            },
            "body": form
        })
            .then((r) => r.json())
            .then((json) => {
                if (json.status_code != 201) {
                    alert(json.message);
                    return;
                }

                const url = document.getElementById("form-url");
                url.innerHTML = json.data.urls.download_url;
                lastUrl = json.data.urls.download_url;
                url.style.display = 'flex';

                document.getElementById("form-actions").style.display = 'flex';
                document.getElementById("form-upload-button").style.display = 'none';
            })
            .catch((err) => {
                alert("Something went wrong! More info in the console.");
                console.error(err);
            });
    }

    function resetUpload() {
        document.getElementById("form-upload-summary").style.display = 'none';
        document.getElementById("form-actions").style.display = 'none';
        document.getElementById("form-url").style.display = 'none';
        document.getElementById("form-reset-button").style.display = 'none';
        document.getElementById("form-dropzone").style.display = 'flex';
    }

    function copyLink() {
        if (lastUrl) navigator.clipboard.writeText(lastUrl);
    }

    function openLink() {
        if (lastUrl) window.location.href = lastUrl;
    }

</script>

</html>