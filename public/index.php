<?php
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
    <div class="container">
        <div class="wrapper">
            <main>
                <noscript title="&gt; still no upload history and fancy upload button">JavaScript deniers <img
                        src="/static/img/icons/chad.png" width="20"></noscript>

                <section class="brand">
                    <img src="/static/img/brand.webp" alt="<?= $instance_name ?>">
                    <h1><?= $instance_name ?></h1>
                </section>

                <section class="box file-upload">
                    <div class="tab">
                        <p>File Upload</p>
                    </div>
                    <div class="content">
                        <form action="/upload.php" method="post" enctype="multipart/form-data" id="form-upload">
                            <input type="file" name="file" id="form-file" required>
                            <button type="submit" id="form-submit-button">Upload</button>
                        </form>
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
    let lastUrl = null;

    const uploadedFiles = document.getElementById("uploaded-files");

    const formFile = document.getElementById("form-file");
    const formSubmitButton = document.getElementById("form-submit-button");

    // Decorating the form
    const form = document.getElementById("form-upload");
    formSubmitButton.style.display = 'none';
    formFile.style.display = 'none';
    form.innerHTML += '<div class="form-dropzone" id="form-dropzone"><h1>Click or drag files here</h1><p>The upload will start immediately after selection/drop</p></div>';

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

    formFile.addEventListener("change", (e) => uploadForm(e.target.files[0]));

    function uploadForm(file) {
        lastUrl = null;
        formFile.setAttribute("disabled", true);

        const form = new FormData();
        form.append("file", file);

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

                addJsonFileToStorage(json.data);
                document.getElementById("uploaded-files-wrapper").style.display = 'grid';
                uploadedFiles.innerHTML = buildJsonFile(json.data, true) + uploadedFiles.innerHTML;
                formFile.removeAttribute("disabled");
            })
            .catch((err) => {
                alert("Something went wrong! More info in the console.");
                console.error(err);
                formFile.removeAttribute("disabled");
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
            htmlPreview = `<img src="${file.urls.download_url}" alt="An image." />`;
        } else if (file.mime.startsWith("video")) {
            htmlPreview = `
                <video muted>
                <source src="${file.urls.download_url}" type="${file.mime}" alt="A video.">
                </video>
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
                    <a href="${file.urls.download_url}" target="_BLANK">[ Open ]</a>
                </div>
            </div>
            `;
    }

    rebuildJsonFileStorage();
</script>

</html>