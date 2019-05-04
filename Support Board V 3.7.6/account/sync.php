<?php require('functions.php') ?>
<html lang="en-US">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />
    <title>
        <?php echo sb_('Synchronization completed.')  . ' | ' . SB_CLOUD_BRAND_NAME ?>
    </title>
    <link rel="stylesheet" href="<?php echo CLOUD_URL . '/script/css/admin.css' ?>" media="all" />
    <link rel="shortcut icon" href="<?php echo SB_CLOUD_BRAND_ICON_PNG ?>" />
    <style>
    .sb-admin {
        padding: 60px !important;
        width: auto;
        max-width: 800px;
        position: static;
        height: auto;
    }

    p {
        font-size: 16px;
        line-height: 22px;
        letter-spacing: 0.3px;
        margin: 5px 0 0 0;
        color: #788692;
    }

    .sb-setting {
        display: block;
    }

    .sb-setting + .sb-setting {
        margin-top: 15px;
        padding: 0;
        border-top: none;
    }

    </style>
</head>
<body>
    <main>
        <section class="section-base">
            <div class="container">
                <?php show_info() ?>
            </div>
        </section>
    </main>
</body>
</html>

<?php

function show_info() {
    $code = '<div class="sb-admin"><h2>' . sb_('Synchronization completed.') . '</h2><p>' . sb_('You completed the synchronization. Please copy and paste the information below in the admin area.') . '</p>';
    $parameters = explode('&', $_SERVER['QUERY_STRING']);
    foreach ($parameters as $value)  {
        if (strpos($value, '=') !== false) {
            $item = explode('=', $value);
            $code .= '<div class="sb-setting"><h4>' . strtoupper(sb_(sb_string_slug(urldecode($item[0]), 'string'))) . '</h4><input value="' . urldecode($item[1]) . '" type="text" readonly /></div>';
        }
    }
    echo $code . '</div>';
}

?>