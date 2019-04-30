<?php

/*
 * ==========================================================
 * WHATSAPP CLOUD POST FILE
 * ==========================================================
 *
 * WhatsApp cloud post file to forward WhatsApp messages to the right account. © 2017-2024 board.support. All rights reserved.
 *
 */

if (isset($_GET['hub_mode']) && $_GET['hub_mode'] == 'subscribe') {
    require('../script/config.php');
    if ($_GET['hub_verify_token'] == WHATSAPP_VERIFY_TOKEN) {
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
if (isset($response['entry']) && isset($response['entry'][0]['changes'])) {
    $response = $response['entry'][0]['changes'][0]['value'];
    if (isset($response['metadata']) && isset($response['metadata']['phone_number_id'])) {
        $phone_number_id = $response['metadata']['phone_number_id'];
        require('functions.php');
        $token = db_get('SELECT token FROM whatsapp WHERE phone_number_id = "' . db_escape($phone_number_id) . '"');
        if ($token && isset($token['token'])) {
            $ch = curl_init(CLOUD_URL . '/script/apps/whatsapp/post.php?cloud=' . $token['token']);
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
}

function debug($value) {
    $path = 'debug.txt';
    $value = is_string($value) ? $value : json_encode($value);
    if (file_exists($path)) {
        $value = file_get_contents($path) . PHP_EOL . $value;
    }
    $file = fopen($path, 'w');
    fwrite($file, "\xEF\xBB\xBF" . $value);
    fclose($file);
}

?>