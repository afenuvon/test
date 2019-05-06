<?php

/*
 * ==========================================================
 * LINE POST.PHP
 * ==========================================================
 *
 * Line response listener. This file receive the messages sent to the Line bot. This file requires the Line App.
 * © 2017-2024 board.support. All rights reserved.
 *
 */

$raw = file_get_contents('php://input');
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
$response = json_decode($raw, true);
if ($response && isset($response['events']) && isset($_SERVER['HTTP_X_LINE_SIGNATURE'])) {
    require('../../include/functions.php');
    sb_cloud_load_by_url();
    $get_line_secret = sb_isset($_GET, 'line_secret');
    $line_secret = $get_line_secret ? $get_line_secret : sb_get_multi_setting('line', 'line-channel-secret'); // Deprecated
    if ($_SERVER['HTTP_X_LINE_SIGNATURE'] === base64_encode(hash_hmac('sha256', $raw, $line_secret, true))) {
        $GLOBALS['SB_FORCE_ADMIN'] = true;
        $response = $response['events'][0];
        if (!isset($response['source']) || $response['source']['type'] !== 'user') {
            die('Source is not user');
        }
        $line_id = $response['source']['userId'];
        $message = $response['message'];
        $message_text = sb_isset($message, 'text', '');
        $attachments = [];
        $token = false;
        $user_id = false;
        $department = false;
        $tags = false;
        $numbers = sb_get_setting('line');
        if (is_array($numbers)) {
            for ($i = 0; $i < count($numbers); $i++) {
                if ($numbers[$i]['line-secret'] == $get_line_secret) {
                    $token = trim($numbers[$i]['line-token']);
                    $department = $numbers[$i]['line-department-id'];
                    $tags = sb_isset($numbers[$i], 'line-tags');
                }
            }
        }
        if (!$token) {
            die('Line token not found');
        }

        // User and conversation
        $user = sb_get_user_by('line-id', $line_id);
        if (!$user) {
            $extra = ['line-id' => [$line_id, 'LINE ID']];
            $sender = sb_line_curl('profile/' . $line_id, $token, '', 'GET');
            if (!empty($sender['language'])) {
                $extra['language'] = [sb_language_code($sender['language']), 'Language'];
            } else if ($message_text && defined('SB_DIALOGFLOW')) {
                $extra['language'] = sb_google_language_detection_get_user_extra($message_text);
            }
            $name = sb_split_name($sender['displayName']);
            $user_id = sb_add_user(['first_name' => $name[0], 'last_name' => $name[1], 'profile_image' => empty($sender['pictureUrl']) ? '' : sb_download_file($sender['pictureUrl']), 'user_type' => 'lead'], $extra);
            $user = sb_get_user($user_id);
        } else {
            $user_id = $user['id'];
            $conversation_id = sb_isset(sb_db_get('SELECT id FROM sb_conversations WHERE source = "ln" AND user_id = ' . $user_id . ' AND (extra_2 = "' . $token . '" OR extra_3 = "' . $token . '") ORDER BY id DESC LIMIT 1'), 'id'); // Deprecated. Remove extra_2. Use only extra_3 = "' . $token . '"
        }
        $GLOBALS['SB_LOGIN'] = $user;
        if (!$conversation_id) {
            $conversation_id = sb_isset(sb_new_conversation($user_id, 2, '', $department, -1, 'ln', $line_id, $token, $tags), 'details', [])['id'];
        }

        // Attachments
        switch ($message['type']) {
            case 'image':
            case 'file':
            case 'audio':
            case 'video':
                $url = sb_download_file('https://api-data.line.me/v2/bot/message/' . $message['id'] . '/content', $message['id'], true, ['Authorization: Bearer ' . $token]);
                array_push($attachments, [basename($url), $url]);
                break;
            case 'sticker':
                $message_text .= ($message_text ? PHP_EOL : '') . '[sticker]';
                break;
            case 'location':
                $message_text .= ($message_text ? PHP_EOL : '') . 'https://www.google.com/maps/search/?api=1&query=' . $message['latitude'] . ',' . $message['longitude'];
                break;
        }

        // Send message
        $response = sb_send_message($user_id, $conversation_id, $message_text, $attachments, false, json_encode(['message_token' => $response['replyToken']]));

        // Dialogflow, Notifications, Bot messages
        $response_external = sb_messaging_platforms_functions($conversation_id, $message_text, $attachments, $user, ['source' => 'ln', 'line_id' => $line_id]);

        // Queue
        if (sb_get_multi_setting('queue', 'queue-active')) {
            sb_queue($conversation_id, $department);
        }

        // Online status
        sb_update_users_last_activity($user_id);

        $GLOBALS['SB_FORCE_ADMIN'] = false;
    }
    die('Invalid signature');
}
?>