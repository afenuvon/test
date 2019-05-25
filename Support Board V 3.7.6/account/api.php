<?php

/*
 * ==========================================================
 * API.PHP
 * ==========================================================
 *
 * Support Board Cloud API file. © 2022-2024 board.support. All rights reserved.
 *
 */

define('SB_CLOUD_API', true);

if (!isset($_GET['action'])) {
    die();
}
require_once('functions.php');
if (in_array($_GET['action'], ['create-account', 'update-account', 'delete-account', 'update-account-membership', 'magic-link']) && sb_isset($_GET, 'key') !== SB_CLOUD_KEY) {
    die(json_encode(sb_error('security-error', $_GET['action'], 'invalid-or-missing-cloud-key')));
}
switch ($_GET['action']) {
    case 'magic-link':
        die(sb_json_response(account_magic_link($_GET['email'])));
    case 'create-account':
        die(sb_json_response(account_registration($_POST)));
    case 'update-account':
        $GLOBALS['SUPER_ACTIVE_ACCOUNT'] = true;
        $GLOBALS['ACTIVE_ACCOUNT'] = db_get('SELECT * FROM users WHERE id = ' . db_escape($_POST['customer_id'], true), true);
        $GLOBALS['ACTIVE_ACCOUNT']['user_id'] = $GLOBALS['ACTIVE_ACCOUNT']['id'];
        die(sb_json_response(account_save($_POST)));
    case 'delete-account':
        die(sb_json_response(super_delete_customer($_GET['customer_id'])));
    case 'update-account-membership':
        db_query('DELETE FROM users_data WHERE slug = "active_membership_cache" AND user_id = ' . db_escape($_GET['customer_id'], true));
        die(sb_json_response(db_query('UPDATE users SET membership = "' . $_GET['membership_id'] . '" WHERE id = ' . db_escape($_GET['customer_id'], true))));
    case 'account':
        die(api_encrypt(sb_encryption('decrypt', $_GET['value'])));
    case 'login':
        die(json_encode(account_login_get_user($_GET['email'], urldecode($_GET['password']))));
    case 'verify':
        die(file_exists(SB_PATH . '/config/config_' . $_GET['value'] . '.php') ? 'success' : 'error');
    case 'cron':
        die(cloud_cron(!isset($_GET['skip_backup'])));
    case 'mysql-kill':
        $processes = db_get('SHOW FULL PROCESSLIST', false);
        for ($i = 0; $i < count($processes); $i++) {
            if (sb_isset($processes[$i], 'Time') > 0 && sb_isset($processes[$i], 'State') == 'Sending data' && strpos(sb_isset($processes[$i], 'Info'), 'sb_')) {
                db_query('KILL ' . $processes[$i]['id']);
            }
        }
        die();
    case 'zapier-subscribe':
        $api_token = sb_isset($_GET, 'api_token');
        if ($api_token) {
            $data = json_decode(file_get_contents('php://input'), true);
            $_POST['token'] = $api_token;
            cloud_api();
            $zapier = sb_get_external_setting('zapier', []);
            $zapier[$_GET['webhook']] = $data['hookUrl'];
            sb_save_external_setting('zapier', $zapier);
        }
        die('[{"subscribed": "' . $_GET['webhook'] . '"}]');
    case 'zapier-test':
        $api_token = sb_isset($_GET, 'api_token');
        if ($api_token) {
            $webhook_sample_data = [
                'SBMessageSent' => '{ "function": "message-sent", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "user_id": "947", "conversation_user_id": "947", "conversation_id": "1057", "conversation_status_code": "2", "conversation_source": "wa", "message_id": "2574", "message": "Hello! How are you?", "attachments": [["name","https://example.com/image.png"],["name","https://example.com/file.txt"]] }}',
                'SBEmailSent' => '{ "function": "email-sent", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "recipient_id": "957", "message": "Hello! How can I help you?", "attachments": [["name","https://example.com/image.png"],["name","https://example.com/file.txt"]] } }',
                'SBSMSSent' => '{ "function": "sms-sent", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "recipent_id": "947", "message": "Hello! How are you?", "Body": "Hello! How are you?", "From": "+15104564545", "To": "+15305431221", "response": { "sid": "SM1f0e8ae6ade43cb3c0ce4525424e404f", "date_created": "Fri, 13 Aug 2010 01:16:24 +0000", "date_updated": "Fri, 13 Aug 2010 01:16:24 +0000", "date_sent": null, "account_sid": "ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX", "to": "+15305431221", "from": "+15104564545", "body": "A Test Message", "status": "queued", "flags":["outbound"], "api_version": "2010-04-01", "price": null, "uri": "/2010-04-01/Accounts/ACXXXX/Messages/SM1f004f.json" } } }',
                'SBNewMessagesReceived' => '{ "function": "new-messages", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "details": { "message": "Hello world!", "message_id": "10231", "attachments": "", "payload": "", "message_status_code": "0", "last_update_time": "2024-04-02 16:57:19", "message_user_id": "1964", "message_first_name": "Don", "message_last_name": "John", "message_profile_image": "https://example.com/image.jpg", "message_user_type": "admin", "department": null, "agent_id": null, "title": "", "source": null, "extra": null, "tags": null, "id": "4607", "user_id": "4747", "creation_time": "2024-04-02 16:57:17", "status_code": "1" }, "messages": [ { "details": { "message": "dasda", "attachments": "", "payload": {}, "status_code": "0", "id": "10231", "profile_image": "https://example.com/image.jpg", "first_name": "Don", "last_name": "John", "user_id": "1964", "user_type": "admin", "full_name": "Don John", "creation_time": "2024-04-02 16:57:19" } } ] } }',
                'SBDialogflowMessage' => '{ "function": "dialogflow-message", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "response": { "token": "ya29.a0Afkjh8MADFYeT4BgCy3917xqSDdVvw4mgVHrgrLDcgRk9ajWoQAgdjv5e...", "messages": [ { "message": "Hi! How are you doing?" } ], "response": { "responseId": "46d2c208-2a7f-4ca2-bd7d-6636982b8bee-0f0e27e1", "queryResult": { "queryText": "hi", "action": "input.welcome", "allRequiredParamsPresent": "true", "fulfillmentText": "Hi! How are you doing?", "fulfillmentMessages": [ { "text": { "text": [ "Hi! How are you doing?" ] } } ], "outputContexts": [ { "name": "projects/api-project-655517752147/agent/sessions...", "lifespanCount": "1", "parameters": { "no-input": "0", "no-match": "0" } } ], "intent": { "name": "projects/api-project-655517752147/agent/intents...", "displayName": "Default Welcome Intent" }, "intentDetectionConfidence": "1", "languageCode": "en" }, "alternativeQueryResults": [ { "queryText": "hi", "outputContexts": [ { "name": "projects/api-project-655517752147/agent...", "parameters": { "no-match": "1", "no-input": "0" } } ], "languageCode": "en" } ] } }, "message": "Hello", "conversation_id": 123456 } }',
                'SBMessageDeleted' => '{ "function": "message-deleted", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": "2595" }',
                'SBRichMessageSubmit' => '{"function":"rich-message","key":"xxxxxxxx","sender-url":"https:\/\/example.com","data":{"result":true,"data":{"type":"inputs","result":{"name":["Don Jhon","Name"],"your-email":["example@gmail.com","Your Email"],"site-url":["www.example.com","Site URL"]}},"id":"example"}}',
                'SBNewConversationReceived' => '{ "function": "new-conversation-received", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "messages": [ { "id": "10222", "user_id": "4746", "message": "Hi there!", "creation_time": "2024-04-02 07:26:18", "attachments": "", "status_code": "0", "payload": "", "conversation_id": "4605", "first_name": "User", "last_name": "#19332", "profile_image": "https://example.com/image.png", "user_type": "lead" }, { "id": "10223", "user_id": "377", "message": "How are you?", "creation_time": "2024-04-02 07:26:22", "attachments": "", "status_code": "2", "payload": "{\"follow_up_message\":true,\"preview\":\"Preview text!\"]\"}", "conversation_id": "4605", "first_name": "Smart Assistant", "last_name": "", "profile_image": "https://example.com/image.png", "user_type": "bot" } ], "details": { "user_id": "4746", "first_name": "User", "last_name": "#19332", "profile_image": "https://example.com/image.png", "user_type": "lead", "id": "4605", "title": "", "status_code": "0", "creation_time": "2024-04-02 07:26:17", "department": null, "agent_id": null, "source": null, "extra": null, "extra_2": null, "extra_3": null, "tags": null, "busy": false } } }',
                'SBNewConversationCreated' => '{ "function": "new-conversation-created", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "messages": [ { "id": "10222", "user_id": "4746", "message": "Hi there!", "creation_time": "2024-04-02 07:26:18", "attachments": "", "status_code": "0", "payload": "", "conversation_id": "4605", "first_name": "User", "last_name": "#19332", "profile_image": "https://example.com/image.png", "user_type": "lead" }, { "id": "10223", "user_id": "377", "message": "How are you?", "creation_time": "2024-04-02 07:26:22", "attachments": "", "status_code": "2", "payload": "{\"follow_up_message\":true,\"preview\":\"Preview text!\"]\"}", "conversation_id": "4605", "first_name": "Smart Assistant", "last_name": "", "profile_image": "https://example.com/image.png", "user_type": "bot" } ], "details": { "user_id": "4746", "first_name": "User", "last_name": "#19332", "profile_image": "https://example.com/image.png", "user_type": "lead", "id": "4605", "title": "", "status_code": "0", "creation_time": "2024-04-02 07:26:17", "department": null, "agent_id": null, "source": null, "extra": null, "extra_2": null, "extra_3": null, "tags": null, "busy": false } } }',
                'SBActiveConversationStatusUpdated' => '{ "function": "conversation-status-updated", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "conversation_id": "1057", "status_code": "0" } }',
                'SBLoginForm' => '{ "function":"login","key":"","data":{"details":{"id":"18","profile_image":"https:\/\/schiocco.s3.amazonaws.com\/3045506.png","first_name":"Fede","last_name":"","email":"fede@fede.com","user_type":"user","token":"ec83c134e5d53be98abd0025145473eec0ff814e","url":"https:\/\/sandbox.cloud.board.support\/script","password":"$2y$10$vYtwWDEqOt7jMSBcCmPigOrqw06tdD8ZSFm70L6c1PLEQ8j938l2W","conversation_id":"false"}},"sender-url":"https://example.com/image.png"}',
                'SBRegistrationForm' => '{ "function": "registration", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "user": { "profile_image": [ "https://board.support/user.svg", "Profile image" ], "first_name": [ "Don", "First name" ], "last_name": [ "John", "Last name" ], "email": [ "example@email.com", "Email" ], "password": [ "12345678", "Password" ], "password-check": [ "12345678", "Repeat password" ], "user_type": [ "user", "" ] }, "extra": { "phone": [ "+02123456789", "Phone" ], "city": [ "London", "City" ] } } }',
                'SBUserDeleted' => '{ "function": "user-deleted", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": "951" }',
                'SBNewEmailAddress' => '{ "function": "new-email-address", "key": "xxxxxxxx", "sender-url": "https://example.com", "data": { "name": "John Doe", "email": "email@example.com" } }'
            ];
            die('[' . $webhook_sample_data[$_GET['webhook']] . ']');
        }
    case 'get-articles':
        $cloud_user_id = (intval($_GET['chat_id']) + 153) / 95675;
        $token = cloud_get_token_by_id($cloud_user_id);
        $cloud_user = db_get('SELECT * FROM users WHERE token = "' . $token . '"');
        $_POST['cloud'] = sb_encryption(json_encode($cloud_user));
        ob_start();
        echo '<div id="sb-articles">';
        require('../script/include/articles.php');
        echo '</div>';
        die(ob_get_clean());
}

function api_encrypt($value) {
    return base64_encode(openssl_encrypt($value, 'AES-256-CBC', hash('sha256', 'ksdn_dsmkl87M'), 0, hash('sha256', '872785493jmndKds9')));
}

function sb_json_response($result) {
    $response = ['success' => true, 'response' => $result];
    if (sb_is_error($result)) {
        $response = ['success' => false, 'response' => $result->code()];
        if ($result->message()) {
            $response['message'] = $result->message();
        }
    }
    die(json_encode($response));
}
?>