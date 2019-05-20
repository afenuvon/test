<?php

/*
 * ==========================================================
 * ZALO POST.PHP
 * ==========================================================
 *
 * ZALO response listener. This file receive the messages sent to the Line bot. This file requires the Zalo App.
 * © 2017-2024 board.support. All rights reserved.
 *
 */

$raw = file_get_contents('php://input');
$response = json_decode($raw, true);
$signature = isset($_SERVER['HTTP_X_ZEVENT_SIGNATURE']) ? $_SERVER['HTTP_X_ZEVENT_SIGNATURE'] : false;
if (!$signature) {
    die();
}
require('../../include/functions.php');
sb_cloud_load_by_url();
$hash = 'mac=' . hash('sha256', $response['app_id'] . $raw . $response['timestamp'] . trim(sb_get_multi_setting('zalo', 'zalo-oa-secret-key')));
if (strcmp($hash, $_SERVER['HTTP_X_ZEVENT_SIGNATURE']) === 0 && isset($response['event_name'])) {
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    $event_name = sb_isset($response, 'event_name');
    $GLOBALS['SB_FORCE_ADMIN'] = true;
    if ($event_name) {
        $user = false;
        if (in_array($event_name, ['user_send_text', 'user_send_sticker', 'user_send_image', 'user_send_file', 'user_send_video', 'user_send_location', 'user_send_link', 'user_send_gif', 'user_send_audio', 'oa_send_text', 'oa_send_image', 'oa_send_list', 'oa_send_gif'])) {
            $is_admin_message = strpos($event_name, 'oa_send_') === 0;
            $zalo_id = $response[$is_admin_message ? 'recipient' : 'sender']['id'];
            $message = $response['message'];
            $message_text = sb_isset($message, 'text', '');
            $attachments = [];

            // User and conversation
            $user = sb_get_user_by('zalo-id', $zalo_id);
            $department = sb_get_multi_setting('zalo', 'zalo-department-id');
            if (!$user) {
                $extra = ['zalo-id' => [$zalo_id, 'ZALO ID']];
                if ($message_text && defined('SB_DIALOGFLOW')) {
                    $extra['language'] = sb_google_language_detection_get_user_extra($message_text);
                }
                $user_data = json_decode(sb_curl('https://openapi.zalo.me/v3.0/oa/user/detail?data={user_id:' . $zalo_id . '}', '', ['access_token: ' . sb_zalo_get_token()], 'GET'), true);
                $name = ['', ''];
                $profile_image = false;
                if ($user_data) {
                    $name = sb_split_name($user_data['data']['display_name']);
                    $profile_image = $user_data['data']['avatars']['240'];
                }
                $user_id = sb_add_user(['first_name' => $name[0], 'last_name' => $name[1], 'profile_image' => $profile_image, 'user_type' => 'lead'], $extra);
                $user = sb_get_user($user_id);
            } else {
                $user_id = $user['id'];
                $conversation_id = sb_isset(sb_db_get('SELECT id FROM sb_conversations WHERE source = "za" AND user_id = ' . $user_id . ' ORDER BY id DESC LIMIT 1'), 'id');
            }
            $GLOBALS['SB_LOGIN'] = $user;
            if (!$conversation_id) {
                $conversation_id = sb_isset(sb_new_conversation($user_id, 2, '', $department, -1, 'za', false, false, $tags), 'details', [])['id'];
            }

            // Attachments
            $message_attachments = sb_isset($message, 'attachments', []);
            foreach ($message_attachments as $attachment) {
                $type = sb_isset($attachment, 'type');
                $payload = sb_isset($attachment, 'payload', []);
                $url = sb_isset($payload, 'url');
                if ($url) {
                    if ($type == 'file') {
                        $url = sb_download_file($url, rand(9999, 999999999) . '_' . $payload['name']);
                    }
                    array_push($attachments, [($type == 'sticker' ? 'sticker_' . $payload['id'] . '.gif' : basename($url)), $url]);
                }
            }

            // Send message
            $response = sb_send_message($is_admin_message ? $user_id : sb_get_bot_ID(), $conversation_id, $message_text, $attachments);
            if (!$is_admin_message) {

                // Dialogflow, Notifications, Bot messages
                $response_external = sb_messaging_platforms_functions($conversation_id, $message_text, $attachments, $user, ['source' => 'za', 'zalo_id' => $zalo_id]);

                // Queue
                if (sb_get_multi_setting('queue', 'queue-active')) {
                    sb_queue($conversation_id, $department);
                }
            }
        } else if ($event_name == 'user_received_message') {

            // Update message status and set it as received
            $user = sb_get_user_by('zalo-id', $response['recipient']['id']);
            if ($user) {
                $message_id = sb_db_get('SELECT id FROM sb_messages WHERE conversation_id = (SELECT id FROM sb_conversations WHERE source = "za" AND user_id = ' . $user['id'] . ' ORDER BY id DESC LIMIT 1) AND user_id <> ' . $user['id'] . ' AND status_code = 0 ORDER BY id DESC LIMIT 1');
                if ($message_id) {
                    sb_update_messages_status([$message_id['id']], $user['id']);
                }
            }
        }

        // Online status
        sb_update_users_last_activity($user['id']);

        $GLOBALS['SB_FORCE_ADMIN'] = false;
    }
}

die('Invalid signature');

?>