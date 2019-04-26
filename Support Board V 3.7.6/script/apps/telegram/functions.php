<?php

/*
 * ==========================================================
 * TELEGRAM APP
 * ==========================================================
 *
 * Telegram app main file. © 2017-2024 board.support. All rights reserved.
 *
 * 1. Send a message to Telegram
 * 2. Get attachment type
 * 3. Convert Support Board rich messages to Telegram rich messages
 * 4. Synchronize Telegram with Support Board
 * 5. Download a Telegram file
 * 6. Set typing status in Telegram
 *
 */

define('SB_TELEGRAM', '1.0.7');

function sb_telegram_send_message($chat_id, $message = '', $attachments = [], $conversation_id = false) {
    if (empty($message) && empty($attachments)) {
        return false;
    }
    if ($attachments === false || $attachments === '') {
        $attachments = [];
    }
    $token = sb_isset(sb_db_get('SELECT extra_2 FROM sb_conversations WHERE ' . ($conversation_id ? 'id = ' . sb_db_escape($conversation_id) : 'extra = "' . sb_db_escape($chat_id) . '"')), 'extra_2'); // Deprecated.
    if (empty($token) || !strpos($token, ':')) { // Deprecated. Remove if.
        $token = sb_isset(sb_db_get('SELECT extra_3 FROM sb_conversations WHERE ' . ($conversation_id ? 'id = ' . sb_db_escape($conversation_id) : 'extra = "' . sb_db_escape($chat_id) . '"')), 'extra_3');
    } // Deprecated. Remove if.
    $token = $token ? $token : sb_get_multi_setting('telegram', 'telegram-token'); // Deprecated
    $user_id = sb_isset(sb_db_get('SELECT A.id FROM sb_users A, sb_conversations B WHERE A.id = B.user_id AND B.extra = "' . sb_db_escape($chat_id) . '"'), 'id');
    if (!$user_id) {
        return sb_error('chat-id-not-found', 'sb_telegram_send_message', 'User with chat ID  ' . $chat_id . ' not found.');
    }

    // Send the message
    $business_connection_id = sb_get_user_extra($user_id, 'telegram_bcid');
    $query = ['chat_id' => $chat_id, 'parse_mode' => 'MarkdownV2'];
    $method = 'sendMessage';
    $message = sb_telegram_rich_messages($message, ['user_id' => $user_id]);
    $attachments = array_merge($attachments, $message[1]);
    $count = count($attachments);
    $query = array_merge($query, $message[2]);
    $message = str_replace(['[', ']', '(', ')', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'], ['\\[', '\\]', '\\(', '\\)', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'], $message[0]);
    $special_chars = ['*', '~', '__', '_'];
    for ($i = 0; $i < count($special_chars); $i++) {
        if (substr_count($message, $special_chars[$i]) === 1) {
            $message = str_replace($special_chars[$i], '\\' . $special_chars[$i], $message);
        }
    }
    preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $message, $match);
    if ($match[0]) {
        $match = $match[0];
        for ($i = 0; $i < count($match); $i++) {
            $message = str_replace($match[$i], '[' . $match[$i] . '](' . str_replace(['\\.', '\\-'], ['.', '-'], $match[$i]) . ')', $message);
        }
    }
    $query[$count ? 'caption' : 'text'] = $message;
    if ($count) {
        $query['caption'] = $message;
        $attachment_type = sb_telegram_get_attachment_type($attachments[0][1]);
        $method = $attachment_type[0];
        $query[$attachment_type[1]] = $attachments[0][1];
    } else {
        $query['text'] = $message;
    }
    if ($business_connection_id) {
        $query['business_connection_id'] = $business_connection_id;
    }
    $response = sb_curl('https://api.telegram.org/bot' . $token . '/' . $method, $query);

    // Attachments
    if ($count > 1) {
        $responses = [];
        for ($i = 1; $i < $count; $i++) {
            $query = ['chat_id' => $chat_id];
            if ($business_connection_id) {
                $query['business_connection_id'] = $business_connection_id;
            }
            $attachment_type = sb_telegram_get_attachment_type($attachments[$i][1]);
            $method = $attachment_type[0];
            $query[$attachment_type[1]] = $attachments[$i][1];
            array_push($responses, sb_curl('https://api.telegram.org/bot' . $token . '/' . $method, $query));
        }
        $response['attachments'] = $responses;
    }

    return $response;
}

function sb_telegram_get_attachment_type($url) {
    $extension = substr($url, strripos($url, '.') + 1);
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
        case 'png':
            return ['sendPhoto', 'photo'];
        case 'gif':
            return ['sendAnimation', 'animation'];
        case 'm4a':
        case 'mp3':
            return ['sendAudio', 'audio'];
        case 'mp4':
            return ['sendVideo', 'video'];
    }
    return ['sendDocument', 'document'];
}

function sb_telegram_rich_messages($message, $extra = false) {
    $shortcodes = sb_get_shortcode($message);
    $attachments = [];
    $telegram = [];
    for ($j = 0; $j < count($shortcodes); $j++) {
        $shortcode = $shortcodes[$j];
        $shortcode_id = sb_isset($shortcode, 'id', '');
        $shortcode_name = $shortcode['shortcode_name'];
        $message = trim((isset($shortcode['title']) ? ' *' . sb_($shortcode['title']) . '*' : '') . PHP_EOL . sb_(sb_isset($shortcode, 'message', '')) . str_replace($shortcode['shortcode'], '{R}', $message));
        $message_inner = '';
        switch ($shortcode_name) {
            case 'slider-images':
                $attachments = explode(',', $shortcode['images']);
                for ($i = 0; $i < count($attachments); $i++) {
                    $attachments[$i] = [$attachments[$i], $attachments[$i]];
                }
                $message = '';
                break;
            case 'slider':
            case 'card':
                $suffix = $shortcode_name == 'slider' ? '-1' : '';
                $message = '*' . sb_($shortcode['header' . $suffix]) . '*' . (isset($shortcode['description' . $suffix]) ? (PHP_EOL . $shortcode['description' . $suffix]) : '') . (isset($shortcode['extra' . $suffix]) ? (PHP_EOL . '`' . $shortcode['extra' . $suffix] . '`') : '') . (isset($shortcode['link' . $suffix]) ? (PHP_EOL . PHP_EOL . $shortcode['link' . $suffix]) : '');
                $attachments = [[$shortcode['image' . $suffix], $shortcode['image' . $suffix]]];
                break;
            case 'list-image':
            case 'list':
                $index = $shortcode_name == 'list-image' ? 1 : 0;
                $shortcode['values'] = str_replace(['://', '\:', "\n,-"], ['{R2}', '{R4}', ' '], $shortcode['values']);
                $values = explode(',', str_replace('\,', '{R3}', $shortcode['values']));
                if (strpos($values[0], ':')) {
                    for ($i = 0; $i < count($values); $i++) {
                        $value = explode(':', str_replace('{R3}', ',', $values[$i]));
                        $message_inner .= PHP_EOL . '• *' . trim($value[$index]) . '* ' . trim($value[$index + 1]);
                    }
                } else {
                    for ($i = 0; $i < count($values); $i++) {
                        $message_inner .= PHP_EOL . '• ' . trim(str_replace('{R3}', ',', $values[$i]));
                    }
                }
                $message = trim(str_replace(['{R2}', '{R}', "\r\n\r\n\r\n", '{R4}'], ['://', $message_inner . PHP_EOL . PHP_EOL, "\r\n\r\n", ':'], $message));
                break;
            case 'select':
            case 'buttons':
            case 'chips':
                $values = explode(',', $shortcode['options']);
                for ($i = 0; $i < count($values); $i++) {
                    array_push($telegram, sb_($values[$i]));
                }
                $telegram = ['reply_markup' => json_encode(['keyboard' => [$telegram], 'one_time_keyboard' => true])];
                if ($shortcode_id == 'sb-human-takeover' && defined('SB_DIALOGFLOW')) {
                    sb_dialogflow_set_active_context('human-takeover', [], 2, false, sb_isset($extra, 'user_id'));
                }
                break;
            case 'button':
                $message = $shortcode['link'];
                break;
            case 'video':
                $message = ($shortcode['type'] == 'youtube' ? 'https://www.youtube.com/embed/' : 'https://player.vimeo.com/video/') . $shortcode['id'];
                break;
            case 'image':
                $attachments = [[$shortcode['url'], $shortcode['url']]];
                $message = str_replace('{R}', '', $message);
                break;
            case 'rating':
                if (defined('SB_DIALOGFLOW')) {
                    sb_dialogflow_set_active_context('rating', [], 2, false, sb_isset($extra, 'user_id'));
                }
                break;
            case 'articles':
                if (isset($shortcode['link'])) {
                    $message = $shortcode['link'];
                }
                break;
        }
    }
    return [str_replace('{R}', '', $message), $attachments, $telegram];
}

function sb_telegram_synchronization($token, $cloud = '', $is_additional_number = false) {
    return json_decode(sb_get('https://api.telegram.org/bot' . $token . '/setWebhook?url=' . SB_URL . '/apps/telegram/post.php' . ($is_additional_number ? '%3Ftg_token%3D' . $token : '') . str_replace(['&', '='], [$is_additional_number ? '%26' : '%3F', '%3D'], $cloud)), true);
}

function sb_telegram_download_file($file_id, $token) {
    $file = sb_get('https://api.telegram.org/bot' . $token . '/getFile?file_id=' . $file_id, true);
    $path = $file['result']['file_path'];
    if (!empty($file['ok'])) {
        return sb_download_file('https://api.telegram.org/file/bot' . $token . '/' . $path, rand(1000, 99999) . '_' . (strpos($path, '/') ? substr($path, strripos($path, '/') + 1) : $path));
    }
    return false;
}

function sb_telegram_set_typing($chat_id, $token) {
    return sb_get('https://api.telegram.org/bot' . $token . '/sendChatAction?action=typing&chat_id=' . $chat_id);
}

?>