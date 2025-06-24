<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

function generate_alert(string $redirect, mixed $data, string|null $message = null, int $code = 200)
{
    if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
        echo json_response($data, $message, $code);
    } else {
        header("Location: {$redirect}?error={$message}&error_status={$code}");
    }
}

function html_alert()
{
    if (isset($_GET['error'], $_GET['error_status'])) {
        echo '' ?>
        <div class="box<?= intval($_GET['error_status'] > 299) ? " red" : "" ?>">
            <?= str_safe($_GET['error'], null) ?>
        </div>
        <?php ;
    }
}