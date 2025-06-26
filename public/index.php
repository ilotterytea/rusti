<?php
include_once '../lib/utils.php';
include_once '../lib/alert.php';
include_once '../lib/account.php';
include_once '../lib/partials.php';

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
    <?php meta_opengraph() ?>
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <main>
                <?php html_header(true) ?>

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
                                    <?php if (!FILE_AUTHORIZED_TAGS || (FILE_AUTHORIZED_TAGS && isset($_SESSION['user']))): ?>
                                    <tr>
                                        <th>Tags</th>
                                        <td><input type="text" name="tags" id="form-tags"
                                                placeholder="Space-separated tags">
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Visibility</th>
                                        <td>
                                            <select name="visibility" id="form-visibility">
                                                <option value="0" <?= FILE_DEFAULT_VISIBILITY == 0 ? 'selected' : '' ?>>
                                                    Unlisted</option>
                                                <?php if ((!FILE_AUTHORIZED_PUBLIC && FILES_LIST_ENABLED) || (FILE_AUTHORIZED_PUBLIC && isset($_SESSION['user']))): ?>
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

                <section class="box">
                    <div class="tab">
                        <p>Where are all my uploaded files?</p>
                    </div>
                    <div class="content">
                        <p>They&apos;re still saved in your cookies and you can view them in the <a
                                href="/account/">'Account'</a> tab.</p>
                    </div>
                </section>

                <section class="box column" style="display: none;">
                    <div class="tab">
                        <p>Recently uploaded files<span title="Stored locally">*</span></p>
                    </div>
                    <div class="content uploaded-files" id="recently-uploaded-files">
                    </div>
                </section>
            </main>
            <?php html_big_footer($db) ?>
        </div>
    </div>
</body>

<script>
    let lastUrl = null;

    const recentlyUploadedFilesElement = document.getElementById("recently-uploaded-files");

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
        <?php if (!FILE_AUTHORIZED_TAGS || (FILE_AUTHORIZED_TAGS && isset($_SESSION['user']))): ?>
        form.append("tags", document.getElementById("form-tags").value);
        <?php endif; ?>

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
                if (recentlyUploadedFilesElement.parentElement.style.display == 'none') {
                    recentlyUploadedFilesElement.parentElement.style.display = 'flex';
                }
                recentlyUploadedFilesElement.innerHTML = buildJsonFile(json.data, true) + recentlyUploadedFilesElement.innerHTML;
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

    function buildJsonFile(file, highlight) {
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
</script>

</html>