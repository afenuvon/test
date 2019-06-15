<?php

/*
 * ==========================================================
 * MESSENGER CLOUD POST FILE
 * ==========================================================
 *
 * Messenger cloud post file to forward WhatsApp messages to the right account. © 2017-2024 board.support. All rights reserved.
 *
 */

if (isset($_GET['hub_mode']) && $_GET['hub_mode'] == 'subscribe') {
    require('../script/config.php');
    if ($_GET['hub_verify_token'] == MESSENGER_VERIFY_TOKEN) {
        echo $_GET['hub_challenge'];
    }
    die();
}
$raw = file_get_contents('php://input');
$response = json_decode($raw, true);

flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$response_messaging = false;
if (isset($response['messaging'])) {
    $response_messaging = $response['messaging'];
} else if (isset($response['object'])) {
    if (isset($response['entry'][0]['messaging'])) {
        $response_messaging = $response['entry'][0]['messaging'];
    } else if (isset($response['entry'][0]['standby'])) {
        $response_messaging = $response['entry'][0]['standby'];
    }
}
if (isset($response['object']) && isset($response['entry'])) {
    $page_id = $response['entry'][0]['id'];
} else if (isset($response['recipient'])) {
    $page_id = $response['recipient']['id'];
} else if ($response_messaging) {
    $page_id = $response_messaging[0]['recipient']['id'];
}

if ($page_id) {
    require('functions.php');
    $token = db_get('SELECT token FROM messenger WHERE page_id = "' . db_escape($page_id) . '"');
    if ($token && isset($token['token'])) {
        $ch = curl_init(CLOUD_URL . '/script/apps/messenger/post.php?cloud=' . $token['token']);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

?>