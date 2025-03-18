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
        </div>
    </div>
</body>

</html>