<?php

/*
 * ==========================================================
 * ZALO APP
 * ==========================================================
 *
 * Zalo app main file. © 2017-2024 board.support. All rights reserved.
 *
 * 1. Send a message to Zalo
 * 2. Convert Support Board rich messages to Zalo rich messages
 * 3. Zalo curl
 *
 */

define('SB_ZALO', '1.0.1');

function sb_zalo_send_message($zalo_id, $message = '', $attachments = [], $conversation_id = false) {
    if (empty($message) && empty($attachments)) {
        return false;
    }
    $user_id = defined('SB_DIALOGFLOW') ? sb_get_user_by('zalo-id', $zalo_id)['id'] : false;
    $response = [];
    $base_url = 'https://openapi.zalo.me/v3.0/oa/message/';
    $query = ['recipient' => ['user_id' => $zalo_id], 'message' => []];
    $message = sb_zalo_rich_messages($message, ['user_id' => $user_id]);
    $attachments = array_merge($attachments, $message[1]);
    $headers = ['access_token: ' . sb_zalo_get_token(), 'Content-Type: application/json'];

    // Attachments
    $response['attachments'] = [];
    for ($i = 0; $i < count($attachments); $i++) {
        $url = $attachments[$i][1];
        $extension = substr($url, strripos($url, '.') + 1);
        $attachment_query = false;
        if (in_array($extension, ['jpg', 'png', 'gif'])) {
            $attachment_query = ['type' => 'template', 'payload' => ['template_type' => 'media', 'elements' => [['media_type' => 'image', 'url' => $url]]]];
        } else if (in_array($extension, ['pdf', 'doc', 'csv'])) {
            $response_attachment = sb_zalo_upload($url);
            if ($response_attachment[0]) {
                $attachment_query = ['type' => 'file', 'payload' => ['token' => $response_attachment[1]]];
            } else {
                $message[0] .= PHP_EOL . $url;
            }
        } else {
            $message[0] .= PHP_EOL . $url;
        }
        if ($attachment_query) {
            $query['message']['attachment'] = $attachment_query;
            $response_attachment = sb_curl($base_url . 'cs', json_encode($query), $headers);
            array_push($response['attachments'], $response_attachment[1]);
        }
    }
    unset($query['message']['attachment']);

    // Send the text message
    if ($message[0] || $message[2]) {
        if ($message[0]) {
            $query['message']['text'] = $message[0];
        }
        if ($message[2]) {
            $query['message']['attachment'] = $message[2];
        }
        $response = array_merge($response, sb_curl($base_url . ($message[2] ? 'promotion' : 'cs'), json_encode($query), $headers));
    }
    return ['error' => empty($response) || isset($response['sentMessages']) ? false : $response];
}

function sb_zalo_rich_messages($message, $extra = false) {
    $attachement_query = false;
    $message = sb_clear_text_formatting($message);
    $shortcodes = sb_get_shortcode($message);
    $attachments = [];
    for ($j = 0; $j < count($shortcodes); $j++) {
        $shortcode = $shortcodes[$j];
        $shortcode_id = sb_isset($shortcode, 'id', '');
        $shortcode_name = $shortcode['shortcode_name'];
        $message = trim(str_replace($shortcode['shortcode'], '', $message) . sb_isset($shortcode, 'title', '') . PHP_EOL . sb_(sb_isset($shortcode, 'message', '')));
        switch ($shortcode_name) {
            case 'slider-images':
                $images = explode(',', $shortcode['images']);
                for ($i = 0; $i < count($images); $i++) {
                    array_push($attachments, [$images[$i], $images[$i]]);
                }
                break;
            case 'slider':
            case 'card':
                $index = $shortcode_name == 'slider' ? 1 : 0;
                $suffix = $index ? '-' . $index : '';
                $text = $shortcode['description' . $suffix] . PHP_EOL . sb_isset($shortcode, 'extra' . $suffix, '');
                $image = sb_isset($shortcode, 'image' . $suffix);
                $elements = [];
                if ($image) {
                    $image = sb_download_file($image);
                    $response_attachment = sb_zalo_upload($image, true);
                    if ($response_attachment[0]) {
                        array_push($elements, ['attachment_id' => $response_attachment[1], 'type' => 'banner']);
                    }
                }
                if (!empty($shortcode['header' . $suffix])) {
                    array_push($elements, ['type' => 'header', 'content' => $shortcode['header' . $suffix]]);
                }
                if ($text) {
                    array_push($elements, ['type' => 'text', 'align' => 'left', 'content' => $text]);
                }
                $attachement_query = [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'promotion',
                        'elements' => $elements,
                        'buttons' => [
                            [
                                'title' => substr(sb_isset($shortcode, 'link-text' . $suffix), 0, 100),
                                'image_icon' => '',
                                'type' => 'oa.open.url',
                                'payload' => [
                                    'url' => sb_isset($shortcode, 'link' . $suffix)
                                ]
                            ]
                        ]
                    ]
                ];
                break;
            case 'list-image':
            case 'list':
                $index = 0;
                if ($shortcode_name == 'list-image') {
                    $shortcode['values'] = str_replace('://', '', $shortcode['values']);
                    $index = 1;
                }
                $values = explode(',', $shortcode['values']);
                if (strpos($values[0], ':')) {
                    for ($i = 0; $i < count($values); $i++) {
                        $value = explode(':', $values[$i]);
                        $message .= PHP_EOL . '• ' . trim($value[$index]) . ' ' . trim($value[$index + 1]);
                    }
                } else {
                    for ($i = 0; $i < count($values); $i++) {
                        $message .= PHP_EOL . '• ' . trim($values[$i]);
                    }
                }
                $message = trim($message);
                break;
            case 'rating':
            case 'select':
            case 'buttons':
            case 'chips':
                $is_rating = $shortcode_name == 'rating';
                $values = $is_rating ? [sb_isset($shortcode, 'label-positive'), sb_isset($shortcode, 'label-negative')] : explode(',', $shortcode['options']);
                $count = count($values);
                if ($count < 5) {
                    $elements = [];
                    $buttons = [];
                    if (!empty($shortcode['title'])) {
                        array_push($elements, ['type' => 'header', 'content' => $shortcode['title']]);
                    }
                    if (!empty($shortcode['message'])) {
                        array_push($elements, ['type' => 'text', 'align' => 'left', 'content' => $shortcode['message']]);
                    }
                    for ($i = 0; $i < $count; $i++) {
                        array_push($buttons, [
                            'title' => substr($values[$i], 0, 100),
                            'image_icon' => '',
                            'type' => 'oa.query.show',
                            'payload' => $values[$i]
                        ]);
                    }
                    $attachement_query = [
                        'type' => 'template',
                        'payload' => [
                            'template_type' => 'promotion',
                            'elements' => $elements,
                            'buttons' => $buttons
                        ]
                    ];
                    $message = '';
                } else {
                    if ($message) {
                        $message .= PHP_EOL;
                    }
                    for ($i = 0; $i < $count; $i++) {
                        $message .= PHP_EOL . '• ' . trim($values[$i]);
                    }
                }
                if ($shortcode_id == 'sb-human-takeover' && defined('SB_DIALOGFLOW')) {
                    sb_dialogflow_set_active_context('human-takeover', [], 2, false, sb_isset($extra, 'user_id'));
                }
                if ($is_rating) {
                    sb_dialogflow_set_active_context('rating', [], 2, false, sb_isset($extra, 'user_id'));
                }
                break;
            case 'share':
            case 'button':
                $message .= ($message ? PHP_EOL : '') . $shortcode['link'];
                break;
            case 'video':
                $message .= ($message ? PHP_EOL : '') . ($shortcode['type'] == 'youtube' ? 'https://www.youtube.com/embed/' : 'https://player.vimeo.com/video/') . $shortcode['id'];
                break;
            case 'image':
                $attachments = [[$shortcode['url'], $shortcode['url']]];
                break;
            case 'articles':
                if (isset($shortcode['link'])) {
                    $message = $shortcode['link'];
                }
                break;
        }
    }
    return [$message, $attachments, $attachement_query];
}

function sb_zalo_upload($url, $is_image = false) {
    $path = substr($url, strrpos(substr($url, 0, strrpos($url, '/')), '/'));
    $response = sb_curl('https://openapi.zalo.me/v2.0/oa/upload/' . ($is_image ? 'image' : 'file'), ['file' => new CURLFile(sb_upload_path() . $path)], ['access_token: ' . sb_zalo_get_token()], 'UPLOAD');
    $token = sb_isset(sb_isset($response, 'data'), $is_image ? 'attachment_id' : 'token');
    sb_file_delete($path);
    return $token ? [true, $token] : [false, $response];
}

function sb_zalo_get_token() {
    global $ZALO_TOKEN;
    if (isset($ZALO_TOKEN)) {
        return $ZALO_TOKEN;
    }
    $token = sb_get_external_setting('zalo-token');
    if ($token) {
        $token = explode('|', $token);
        if (time() < $token[2]) {
            $ZALO_TOKEN = $token[1];
            return $token[1];
        }
    }
    $data = sb_curl('https://oauth.zaloapp.com/v4/oa/access_token?refresh_token=' . ($token ? $token[0] : sb_get_multi_setting('zalo', 'zalo-token')) . '&app_id=' . sb_get_multi_setting('zalo', 'zalo-app-id') . '&grant_type=refresh_token', '', ['secret_key: ' . sb_get_multi_setting('zalo', 'zalo-app-secret-key')]);
    $access_token = sb_isset($data, 'access_token');
    $refresh_token = sb_isset($data, 'refresh_token');
    if ($access_token && $refresh_token) {
        sb_save_external_setting('zalo-token', $refresh_token . '|' . $access_token . '|' . (time() + $data['expires_in']));
        $ZALO_TOKEN = $access_token;
        return $access_token;
    } else if (sb_isset($data, 'error_name') == 'Invalid refresh token.' && $token) {
        sb_save_external_setting('zalo-token', '');
        return sb_zalo_get_token();
    }
    return false;
}

?>