<?php
include_once '../lib/utils.php';
include_once '../lib/alert.php';
include_once '../lib/account.php';

// creating a new config if it doesn't exist
if (!file_exists('../config.php')) {
    copy('../config.sample.php', '../config.php');
}

include_once '../config.php';

// redirect to file
if (str_starts_with($_SERVER['PHP_SELF'], '/index.php/')) {
    header('Location: /posts/?id=' . str_safe(substr($_SERVER['PHP_SELF'], 11), null) . (!empty($_SERVER['QUERY_STRING']) ? "&{$_SERVER['QUERY_STRING']}" : ""));
    exit;
}

$db = new PDO(DB_URL);

// creating database tables if they don't exist
$db->exec(file_get_contents('../database.sql'));

// file stats
$file_stats = $db->query("SELECT COUNT(*), SUM(size) FROM posts");
$file_stats->execute();
$file_stats = $file_stats->fetch();
$file_count = $file_stats[0];
$file_overall_size = $file_stats[1];

authorize_user();
?>
<html>

<head>
    <title><?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <noscript title="&gt; still no upload history and fancy upload button">JavaScript deniers <img
                        src="/static/img/icons/chad.png" width="20"></noscript>

                <section class="brand">
                    <img src="/static/img/brand.webp" alt="<?= INSTANCE_NAME ?>">
                    <h1><?= INSTANCE_NAME ?></h1>
                    <div class="row gap-8">
                        <?php if (FILES_LIST_ENABLED || (isset($_SESSION['user']) && $_SESSION['user']['is_admin'])): ?>
                            <a href="/posts/">[ Catalogue ]</a>
                        <?php endif; ?>
                    </div>
                    <div class="row-gap-8">
                        <?php if (isset($_SESSION['user'])): ?>
                            <p>Logged in as <span
                                    class="<?= $_SESSION['user']['is_admin'] ? "red" : "" ?>"><?= $_SESSION['user']['username'] ?>
                                </span>
                                | <a href="/account/logout.php">[ Log out ]</a></p>
                        <?php else: ?>
                            <a href="/account/login.php">[ Login ]</a>
                            <?php if (USER_REGISTRATION): ?>
                                <a href="/account/register.php">[ Register ]</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php html_alert() ?>

                <section class="box file-upload">
                    <div class="tab">
                        <p>File Upload</p>
                    </div>
                    <div class="content">
                        <?php if (!FILE_AUTHORIZED_UPLOAD || (FILE_AUTHORIZED_UPLOAD && isset($_SESSION['user']))): ?>
                            <form action="/posts/upload.php" method="post" enctype="multipart/form-data"
                                class="column gap-8 file-upload">

                                <input type="file" name="file" id="form-file">

                                <div class="column" id="form-file-upload" style="display: none;">
                                </div>

                                <div class="row gap-8" id="form-file-url">
                                    <p>Or: </p>
                                    <input type="url" name="url" id="form-url"
                                        placeholder="Enter video URL (YouTube, Instagram, etc.)" class="grow">
                                </div>

                                <table class="vertical" id="form-details">
                                    <tr>
                                        <th>Comment</th>
                                        <td><textarea name="comment" id="form-comment" placeholder="Empty"></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tags</th>
                                        <td><input type="text" name="tags" id="form-tags"
                                                placeholder="Space-separated tags">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Visibility</th>
                                        <td>
                                            <select name="visibility" id="form-visibility">
                                                <option value="0" <?= FILE_DEFAULT_VISIBILITY == 0 ? 'selected' : '' ?>>
                                                    Unlisted</option>
                                                <?php if (FILES_LIST_ENABLED): ?>
                                                    <option value="1" <?= FILE_DEFAULT_VISIBILITY == 1 ? 'selected' : '' ?>>
                                                        Public</option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Password <span class="hint"
                                                title="Password is used for file deletion">[?]</span></th>
                                        <td><input type="text" id="form-password" name="password"
                                                value="<?= generate_random_chars(FILE_ID_LENGTH * 2, FILE_ID_CHARPOOL) ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>File Expiration</th>
                                        <td>
                                            <select name="expires" id="form-expires">
                                                <?php foreach (FILE_EXPIRATION as $k => $v): ?>
                                                    <option value="<?= $k ?>" <?= FILE_DEFAULT_EXPIRATION == $k ? 'selected' : '' ?>><?= $v ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>

                                <div class="row">
                                    <button type="submit" id="form-submit-button">Upload</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>You need to log in to upload files</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="box" id="uploaded-files-wrapper" style="display:none">
                    <div class="tab">
                        <p>Uploaded Files<span title="Stored locally">*</span></p>
                    </div>
                    <div class="content uploaded-files" id="uploaded-files">
                    </div>
                </section>
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
    // saving account files locally
    function synchronizeFiles() {
        const accountFiles = <?= json_encode(isset($_SESSION['files']) ? $_SESSION['files'] : [], JSON_UNESCAPED_SLASHES) ?>;

        let storage = localStorage.getItem("uploaded_files");
        console.log(storage);


        if (!storage) {
            storage = '[]';
        }

        storage = JSON.parse(storage);

        accountFiles.forEach((v) => {
            if (!storage.some((x) => x.id == v.id)) {
                storage.push(v);
            }
        });

        console.log(storage);
        storage = storage.filter((x) => accountFiles.some((y) => x.id == y.id) || x.uploaded_by == null);

        storage.sort((a, b) => {
            const aDate = new Date(a.uploaded_at);
            const bDate = new Date(b.uploaded_at);

            if (aDate.getTime() > bDate.getTime()) {
                return -1;
            } else if (aDate.getTime() < bDate.getTime()) {
                return 1;
            } else {
                return 0;
            }
        });

        console.log(storage);

        localStorage.setItem("uploaded_files", JSON.stringify(storage));
    }

    let lastUrl = null;

    const uploadedFiles = document.getElementById("uploaded-files");

    const formFile = document.getElementById("form-file");
    const formURLWrapper = document.getElementById("form-file-url");
    const formURL = document.getElementById("form-url");
    const formDetails = document.getElementById("form-details");
    const formSubmitButton = document.getElementById("form-submit-button");

    // Decorating the form
    const form = document.getElementById("form-file-upload");
    formSubmitButton.style.display = 'none';
    formFile.style.display = 'none';
    formDetails.style.display = 'none';
    form.style.display = 'flex';
    form.innerHTML += '<div class="form-dropzone" id="form-dropzone"><h1>Click to select or drag & drop file here</h1></div>';

    const formDropzone = document.getElementById("form-dropzone");
    formDropzone.addEventListener("click", () => formFile.click());
    formDropzone.addEventListener("drop", (e) => {
        e.preventDefault();
        if (e.dataTransfer.items) {
            for (const item of e.dataTransfer.items) {
                if (item.kind === "file") {
                    const file = item.getAsFile();
                    uploadForm(file);
                    break;
                }
            }
        }
    });
    formDropzone.addEventListener("dragover", (e) => {
        e.preventDefault();
    });

    formFile.addEventListener("change", (e) => {
        const file = e.target.files[0];
        formDropzone.innerHTML = `<h1>${file.name}</h1>`;
        formURLWrapper.style.display = 'none';
        formDetails.style.display = 'flex';
        formSubmitButton.style.display = 'flex';
    });

    formURL.addEventListener("change", (e) => {
        const d = e.target.value.length > 0 ? 'flex' : 'none';
        formDetails.style.display = d;
        formSubmitButton.style.display = d;
        formDropzone.style.display = e.target.value.length > 0 ? 'none' : 'flex';
    });

    document.querySelector(".file-upload").addEventListener("submit", (e) => {
        e.preventDefault();
        uploadForm(formURL.value.length > 0 ? null : formFile.files[0]);
    }, true);

    function uploadForm(file) {
        lastUrl = null;
        formFile.setAttribute("disabled", true);
        formDropzone.style.display = 'flex';
        formDetails.style.display = 'none';
        formSubmitButton.style.display = 'none';
        formURLWrapper.style.display = 'none';
        formDropzone.innerHTML = '<h1>Uploading...</h1>' + (formURL.value.length > 0 ? '<p>This might take a while...</p>' : '');

        const form = new FormData();
        if (file) {
            form.append("file", file);
        } else {
            form.append("url", formURL.value);
        }
        form.append("comment", document.getElementById("form-comment").value);
        form.append("visibility", document.getElementById("form-visibility").value);
        form.append("password", document.getElementById("form-password").value);
        form.append("expires", document.getElementById("form-expires").value);
        form.append("tags", document.getElementById("form-tags").value);

        fetch("/posts/upload.php", {
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
                    formFile.removeAttribute("disabled");
                    formDropzone.innerHTML = '<h1>Click or drag files here</h1>';
                    formURLWrapper.style.display = 'flex';
                    formURL.value = null;
                    return;
                }

                addJsonFileToStorage(json.data);
                document.getElementById("uploaded-files-wrapper").style.display = 'grid';
                uploadedFiles.innerHTML = buildJsonFile(json.data, true) + uploadedFiles.innerHTML;
                formFile.removeAttribute("disabled");

                formDropzone.innerHTML = '<h1>Click or drag files here</h1>';
                formURLWrapper.style.display = 'flex';
                formURL.value = null;
            })
            .catch((err) => {
                alert("Something went wrong! More info in the console.");
                console.error(err);
                formFile.removeAttribute("disabled");
                formDropzone.innerHTML = '<h1>Click or drag files here</h1>';
                formURLWrapper.style.display = 'flex';
                formURL.value = null;
            });
    }

    function addJsonFileToStorage(json) {
        let files = localStorage.getItem("uploaded_files");

        if (files == null) {
            files = "[]";
        }

        files = JSON.parse(files);

        files.unshift(json);
        localStorage.setItem("uploaded_files", JSON.stringify(files));
    }

    function rebuildJsonFileStorage() {
        if (localStorage.getItem("uploaded_files") == null) {
            return;
        }

        document.getElementById("uploaded-files-wrapper").style.display = 'grid';

        const files = JSON.parse(localStorage.getItem("uploaded_files"));
        let htmlString = "";

        for (const file of files) {
            htmlString += buildJsonFile(file, false);
        }

        uploadedFiles.innerHTML = htmlString;
    }

    function buildJsonFile(file, highlight) {
        let htmlPreview = "<p><i>Non-displayable file.</i></p>";

        if (file.mime.startsWith("image/")) {
            htmlPreview = `<img src="/thumbnails/${file.id}.jpeg" alt="Missing thumbnail" />`;
        } else if (file.mime.startsWith("video/")) {
            htmlPreview = `<img src="/thumbnails/${file.id}.gif" alt="Missing thumbnail" />`;
        }

        return `
            <div class="box uploaded-file${highlight ? " highlight" : ""}">
                <div class="preview">
                    ${htmlPreview}
                </div>
                <div class="summary">
                    <h3>${file.id}.${file.extension}</h3>
                    <div class="info">
                        <p>${file.mime}</p>
                        <p>${(file.size / 1024 / 1024).toFixed(2)}MB</p>
                    </div>
                    <a href="${file.urls.download_url}" target="_BLANK">[ Open ]</a>
                </div>
            </div>
            `;
    }

    rebuildJsonFileStorage();
</script>

</html>