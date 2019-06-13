<?php

/*
 * ==========================================================
 * MESSENGER APP
 * ==========================================================
 *
 * Facebook Messenger app main file. © 2017-2024 board.support. All rights reserved.
 *
 * 1. Send a message to the Facebook user in Messenger
 * 2. Convert Support Board rich messages to Messenger rich messages
 * 3. Get the details of a Facebook user and add it to Support Board
 * 4. Get the details of a Facebook page
 * 5. Receive and process the messages from Facebook Messenger forwarded by board.support
 * 6. Set typing status
 * 7. Unsubscribe all FB pages from the app
 *
 */

define('SB_MESSENGER', '1.1.8');

function sb_messenger_send_message($psid, $facebook_page_id, $message = '', $attachments = [], $metadata = false, $message_id = false) {
    if (empty($message) && empty($attachments)) {
        return sb_error('missing-arguments', 'sb_messenger_send_message');
    }
    $response = [];
    $user = sb_get_user_by('facebook-id', $psid);
    if (!$user) {
        return sb_error('psid-not-found', 'sb_messenger_send_message', 'User with PSID ' . $psid . ' not found.');
    }
    $page = sb_messenger_get_page($facebook_page_id);
    $instagram = sb_isset(sb_db_get('SELECT source FROM sb_conversations WHERE user_id = ' . sb_db_escape($user['id'], true) . ' ORDER BY id DESC LIMIT 1'), 'source') == 'ig';
    if ($page) {

        // Message
        $data = ['messaging_type' => 'RESPONSE', 'recipient' => ['id' => $psid], 'message' => []];
        if (!empty($message)) {
            $message = sb_messaging_platforms_text_formatting($message);
            if ($instagram) {
                $message = sb_clear_text_formatting($message);
            }
            $message = sb_messenger_rich_messages($message, ['user_id' => $user['id'], 'instagram' => $instagram]);
            if ($message[0] || $message[1]) {
                if ($instagram) {
                    $message[0] = sb_clear_text_formatting($message[0]);
                }
                if (mb_strlen($message[0]) > 2000) {
                    $message[0] = mb_substr($message[0], 0, 2000);
                }
                $data['message']['text'] = str_replace(PHP_EOL . PHP_EOL . PHP_EOL, PHP_EOL . PHP_EOL, $message[0]);
                $data['message'] = array_merge($data['message'], $message[1]);
                $data['message']['metadata'] = $metadata;

                array_push($response, sb_curl('https://graph.facebook.com/me/messages?access_token=' . $page['messenger-page-token'], $data));
            } else if (isset($message[2]['attachments'])) {
                $attachments = $message[2]['attachments'];
            }
            if (!empty($message[2]['message'])) {
                sb_messenger_send_message($psid, $facebook_page_id, $message[2]['message'], [], $metadata, $message_id);
            }
        }

        // Attachments
        if (!empty($attachments) && is_array($attachments)) {
            for ($y = 0; $y < count($attachments); $y++) {
                $attachment = $attachments[$y];
                $attachment_type = false;
                $is_s3 = defined('SB_CLOUD_AWS_S3') || sb_get_multi_setting('amazon-s3', 'amazon-s3-active');
                switch (strtolower(pathinfo($attachment[1], PATHINFO_EXTENSION))) {
                    case 'gif':
                    case 'jpeg':
                    case 'jpg':
                    case 'png':
                        $attachment_type = 'image';
                        break;
                    case 'mp4':
                    case 'mov':
                    case 'avi':
                    case 'mkv':
                    case 'wmv':
                        $attachment_type = 'video';
                        break;
                    case 'mp3':
                    case 'aac':
                    case 'wav':
                    case 'flac':
                        $attachment_type = 'audio';
                        break;
                    default:
                        $attachment_type = 'file';
                }
                if ($is_s3) {
                    $attachment[1] = sb_download_file($attachment[1]);
                }
                $response_attchment = sb_curl('https://graph.facebook.com/me/messages?access_token=' . $page['messenger-page-token'], ['messaging_type' => 'RESPONSE', 'recipient' => ['id' => $psid], 'message' => ['attachment' => ['type' => $attachment_type, 'payload' => ['url' => str_replace(' ', '%20', $attachment[1]), 'is_reusable' => true]], 'metadata' => $metadata, 'text' => '']]);
                if (!isset($response_attchment['error']) || sb_isset($response_attchment['error'], 'error_subcode') != 2534015) {
                    array_push($response, $response_attchment);
                }
                if ($is_s3) {
                    sb_file_delete($attachment[1]);
                }
            }
        }
        if ($message_id) {
            for ($i = 0; $i < count($response); $i++) {
                if (isset($response[$i]['error']) && sb_isset($response[$i]['error'], 'error_subcode') != 2534015) {
                    sb_update_message($message_id, false, false, ['delivery_failed' => $instagram ? 'ig' : 'fb']);
                    break;
                }
            }
        }
        return $response;
    }
    return sb_error('facebook-page-not-found', 'sb_messenger_send_message');
}

function sb_messenger_rich_messages($message, $extra = false) {
    $shortcodes = sb_get_shortcode($message);
    $facebook = [];
    $extra_values = [];
    $instagram = sb_isset($extra, 'instagram');
    for ($j = 0; $j < count($shortcodes); $j++) {
        $shortcode = $shortcodes[$j];
        $shortcode_id = sb_isset($shortcode, 'id', '');
        $shortcode_name = $shortcode['shortcode_name'];
        $message = trim(str_replace($shortcode['shortcode'], '{R}', $message) . (isset($shortcode['title']) ? ' *' . sb_($shortcode['title']) . '*' : '') . PHP_EOL . sb_(sb_isset($shortcode, 'message', '')));
        $message_inner = '';
        switch ($shortcode_name) {
            case 'slider-images':
                $extra_values = explode(',', $shortcode['images']);
                for ($i = 0; $i < count($extra_values); $i++) {
                    $extra_values[$i] = [$extra_values[$i], $extra_values[$i]];
                }
                $extra_values = ['attachments' => $extra_values];
                $facebook = false;
                $message = '';
                break;
            case 'slider':
            case 'card':
                $elements = [];
                if ($shortcode_name == 'card') {
                    $elements = [['title' => sb_($shortcode['header']), 'subtitle' => sb_(sb_isset($shortcode, 'description', '')) . (isset($shortcode['extra']) ? (PHP_EOL . $shortcode['extra']) : ''), 'image_url' => $shortcode['image'], 'buttons' => [['type' => 'web_url', 'url' => $shortcode['link'], 'title' => sb_($shortcode['link-text'])]]]];
                } else {
                    $index = 1;
                    while ($index) {
                        if (isset($shortcode['header-' . $index])) {
                            array_push($elements, ['title' => sb_($shortcode['header-' . $index]), 'subtitle' => sb_(sb_isset($shortcode, 'description-' . $index, '')) . (isset($shortcode['extra-' . $index]) ? (PHP_EOL . $shortcode['extra-' . $index]) : ''), 'image_url' => $shortcode['image-' . $index], 'buttons' => [['type' => 'web_url', 'url' => $shortcode['link-' . $index], 'title' => sb_($shortcode['link-text-' . $index])]]]);
                            $index++;
                        } else
                            $index = false;
                    }
                }
                $facebook = ['attachment' => ['type' => 'template', 'payload' => ['template_type' => 'generic', 'elements' => $elements]]];
                $message = '';
                break;
            case 'select':
            case 'buttons':
            case 'chips':
                $values = explode(',', $shortcode['options']);
                if ($instagram) {
                    for ($i = 0; $i < count($values); $i++) {
                        $shortcode_message .= PHP_EOL . '• ' . sb_($values[$i]);
                    }
                } else {
                    $facebook = ['quick_replies' => []];
                    for ($i = 0; $i < count($values); $i++) {
                        array_push($facebook['quick_replies'], ['content_type' => 'text', 'title' => sb_($values[$i]), 'payload' => $shortcode_id]);
                    }
                }
                if ($shortcode_id == 'sb-human-takeover' && defined('SB_DIALOGFLOW')) {
                    sb_dialogflow_set_active_context('human-takeover', [], 2, false, sb_isset($extra, 'user_id'));
                }
                break;
            case 'inputs':
                $values = explode(',', $shortcode['values']);
                for ($i = 0; $i < count($values); $i++) {
                    $message .= PHP_EOL . '• ' . sb_($values[$i]);
                }
                break;
            case 'email':
                $facebook = ['quick_replies' => [['content_type' => 'user_email', 'payload' => $shortcode_id]]];
                if (sb_isset($shortcode, 'phone')) {
                    $extra_values = 'phone';
                }
                break;
            case 'phone':
                $facebook = ['quick_replies' => [['content_type' => 'user_phone_number', 'payload' => $shortcode_id]]];
                break;
            case 'button':
                $facebook = ['attachment' => ['type' => 'template', 'payload' => ['template_type' => 'button', 'text' => $message, 'buttons' => [['type' => 'web_url', 'url' => $shortcode['link'], 'title' => sb_($shortcode['name'])]]]]];
                $message = '';
                break;
            case 'video':
                $message = ($shortcode['type'] == 'youtube' ? 'https://www.youtube.com/embed/' : 'https://player.vimeo.com/video/') . $shortcode['id'];
                break;
            case 'image':
                $extra_values = ['attachments' => [[$shortcode['url'], $shortcode['url']]], 'message' => $message];
                $facebook = false;
                $message = '';
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
            case 'rating':
                if (!$instagram) {
                    $facebook = ['attachment' => ['type' => 'template', 'payload' => ['template_type' => 'button', 'text' => $message, 'buttons' => [['type' => 'postback', 'title' => sb_($shortcode['label-positive']), 'payload' => 'rating-positive'], ['type' => 'postback', 'title' => sb_($shortcode['label-negative']), 'payload' => 'rating-negative']]]]];
                    $message = '';
                }
                if (defined('SB_DIALOGFLOW')) {
                    sb_dialogflow_set_active_context('rating', [], 2, false, sb_isset($extra, 'user_id'));
                }
                break;
            case 'articles':
                if (isset($shortcode['link'])) {
                    $message = $shortcode['link'];
                } else {
                    $facebook = false;
                    $message = '';
                }
                break;
            default:
                $facebook = false;
                $message = '';
        }
    }
    return [str_replace('{R}', '', $message), $facebook, $extra_values];
}

function sb_messenger_add_user($fb_user_id, $token, $user_type = 'lead', $instagram = false, $message = false) {
    $extra = ['facebook-id' => [$fb_user_id, 'Facebook ID']];
    $user_id = sb_add_user([], $extra);
    $user_details = sb_get('https://graph.facebook.com/' . $fb_user_id . ($instagram ? '?fields=name,profile_pic' : '?fields=first_name,last_name') . '&access_token=' . $token, true);
    if (sb_is_error($user_details)) {
        return $user_details;
    }
    $profile_image = $instagram ? $user_details['profile_pic'] : sb_isset(sb_isset(sb_get('https://graph.facebook.com/' . $fb_user_id . '/picture?redirect=false&width=600&height=600&access_token=' . $token, true), 'data'), 'url');
    if ($profile_image) {
        $profile_image = sb_download_file($profile_image, $fb_user_id . '.jpg');
        $user_details['profile_image'] = sb_is_error($profile_image) || empty($profile_image) ? '' : $profile_image;
    }
    if (isset($user_details['name'])) {
        $name = sb_split_name($user_details['name']);
        $user_details['first_name'] = $name[0];
        $user_details['last_name'] = $name[1];
    }
    $user_details['user_type'] = $user_type;
    if (defined('SB_DIALOGFLOW')) {
        $extra['language'] = sb_google_language_detection_get_user_extra($message);
    }
    sb_update_user($user_id, $user_details, $extra);
    return $user_id;
}

function sb_messenger_get_page($page_id = false) {
    $facebook_pages = sb_get_setting('messenger-pages', []);
    if (is_array($facebook_pages)) {
        for ($i = 0; $i < count($facebook_pages); $i++) {
            if ($facebook_pages[$i]['messenger-page-id'] == $page_id || sb_isset($facebook_pages[$i], 'messenger-instagram-id') == $page_id) {
                return $facebook_pages[$i];
            }
        }
    }
    return $page_id ? false : $facebook_pages;
}

function sb_messenger_set_typing($user_id, $page_id = false, $token = false) {
    if ($page_id) {
        $token = sb_isset(sb_messenger_get_page($page_id), 'messenger-page-token');
    }
    return $token ? sb_curl('https://graph.facebook.com/me/messages?access_token=' . $token, ['recipient' => ['id' => $user_id], 'sender_action' => 'typing_on']) : false;
}

function sb_messenger_unsubscribe() {
    if (sb_is_cloud()) {
        require_once(SB_CLOUD_PATH . '/account/functions.php');
        cloud_messenger_unsubscribe();
    }
    $envato_purchase_code = sb_get_setting('envato-purchase-code');
    if ($envato_purchase_code) {
        return sb_curl('https://board.support/synch/?fb_unsubscribe&purchase-code=' . $envato_purchase_code, '', [], 'GET');
    }
    $fb_pages = sb_messenger_get_page();
    if (!empty($fb_pages)) {
        return sb_curl('https://board.support/synch/?fb_unsubscribe&page-id=' . $fb_pages[0]['messenger-page-id'], '', [], 'GET');
    }
}

?>