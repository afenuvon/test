<?php

/*
 * ==========================================================
 * AI APP
 * ==========================================================
 *
 * Artificial Intelligence App main file. © 2017-2024 board.support. All rights reserved.
 *
 */

define('SB_DIALOGFLOW', '1.4.7');

/*
 * -----------------------------------------------------------
 * SYNC
 * -----------------------------------------------------------
 *
 */

if (isset($_GET['code']) && file_exists('../../include/functions.php')) {
    require('../../include/functions.php');
    sb_cloud_load();
    $info = sb_google_key();
    $query = '{ code: "' . $_GET['code'] . '", grant_type: "authorization_code", client_id: "' . $info[0] . '", client_secret: "' . $info[1] . '", redirect_uri: "' . SB_URL . '/apps/dialogflow/functions.php" }';
    $response = sb_curl('https://accounts.google.com/o/oauth2/token', $query, ['Content-Type: application/json', 'Content-Length: ' . strlen($query)]);
    die($response && isset($response['refresh_token']) ? '<script>document.location = "' . (sb_is_cloud() ? str_replace('/script', '', SB_URL) : SB_URL . '/admin.php') . '?setting=dialogflow&refresh_token=' . $response['refresh_token'] . '";</script>' : 'Error while trying to get Dialogflow token. Dialogflow code: ' . $_GET['code'] . '. Response: ' . json_encode($response));
}

/*
 * -----------------------------------------------------------
 * OBJECTS
 * -----------------------------------------------------------
 *
 * Dialogflow objects
 *
 */

class SBDialogflowEntity {
    public $data;

    function __construct($id, $values, $prompts = []) {
        $this->data = ['displayName' => $id, 'entities' => $values, 'kind' => 'KIND_MAP', 'enableFuzzyExtraction' => true];
    }

    public function __toString() {
        return $this->json();
    }

    function json() {
        return json_encode($this->data);
    }

    function data() {
        return $this->data;
    }
}

class SBDialogflowIntent {
    public $data;

    function __construct($name, $training_phrases, $bot_responses, $entities = [], $entities_values = [], $payload = false, $input_contexts = [], $output_contexts = [], $prompts = [], $id = false) {
        $training_phrases_api = [];
        $parameters = [];
        $parameters_checks = [];
        $messages = [];
        $json = json_decode(file_get_contents(SB_PATH . '/apps/dialogflow/data.json'), true);
        $entities = array_merge($entities, $json['entities']);
        $entities_values = array_merge($entities_values, $json['entities-values']);
        $project_id = false;
        if (is_string($bot_responses)) {
            $bot_responses = [$bot_responses];
        }
        if (is_string($training_phrases)) {
            $training_phrases = [$training_phrases];
        }
        for ($i = 0; $i < count($training_phrases); $i++) {
            $parts_temp = explode('@', $training_phrases[$i]);
            $parts = [];
            $parts_after = false;
            for ($j = 0; $j < count($parts_temp); $j++) {
                $part = ['text' => ($j == 0 ? '' : '@') . $parts_temp[$j]];
                for ($y = 0; $y < count($entities); $y++) {
                    $entity = is_string($entities[$y]) ? $entities[$y] : $entities[$y]['displayName'];
                    $entity_type = '@' . $entity;
                    $entity_name = str_replace('.', '-', $entity);
                    $entity_value = empty($entities_values[$entity]) ? $entity_type : $entities_values[$entity][array_rand($entities_values[$entity])];
                    if (strpos($part['text'], $entity_type) !== false) {
                        $mandatory = true;
                        if (strpos($part['text'], $entity_type . '*') !== false) {
                            $mandatory = false;
                            $part['text'] = str_replace($entity_type . '*', $entity_type, $part['text']);
                        }
                        $parts_after = explode($entity_type, $part['text']);
                        $part = ['text' => $entity_value, 'entityType' => $entity_type, 'alias' => $entity_name, 'userDefined' => true];
                        if (count($parts_after) > 1) {
                            $parts_after = ['text' => $parts_after[1]];
                        } else {
                            $parts_after = false;
                        }
                        if (!in_array($entity, $parameters_checks)) {
                            array_push($parameters, ['displayName' => $entity_name, 'value' => '$' . $entity, 'mandatory' => $mandatory, 'entityTypeDisplayName' => '@' . $entity, 'prompts' => sb_isset($prompts, $entity_name, [])]);
                            array_push($parameters_checks, $entity);
                        }
                        break;
                    }
                }
                array_push($parts, $part);
                if ($parts_after)
                    array_push($parts, $parts_after);
            }
            array_push($training_phrases_api, ['type' => 'EXAMPLE', 'parts' => $parts]);
        }
        for ($i = 0; $i < count($bot_responses); $i++) {
            array_push($messages, ['text' => ['text' => $bot_responses[$i]]]);
        }
        if (!empty($payload)) {
            $std = new stdClass;
            $std->payload = $payload;
            array_push($messages, $std);
        }
        if (!empty($input_contexts) && is_array($input_contexts)) {
            $project_id = sb_get_multi_setting('google', 'google-project-id');
            for ($i = 0; $i < count($input_contexts); $i++) {
                $input_contexts[$i] = 'projects/' . $project_id . '/agent/sessions/-/contexts/' . $input_contexts[$i];
            }
        }
        if (!empty($output_contexts) && is_array($output_contexts)) {
            $project_id = $project_id ? $project_id : sb_get_multi_setting('google', 'google-project-id');
            for ($i = 0; $i < count($output_contexts); $i++) {
                $is_array = is_array($output_contexts[$i]);
                $output_contexts[$i] = ['name' => 'projects/' . $project_id . '/agent/sessions/-/contexts/' . ($is_array ? $output_contexts[$i][0] : $output_contexts[$i]), 'lifespanCount' => ($is_array ? $output_contexts[$i][1] : 3)];
            }
        }
        $t = ['displayName' => $name, 'trainingPhrases' => $training_phrases_api, 'parameters' => $parameters, 'messages' => $messages, 'inputContextNames' => $input_contexts, 'outputContexts' => $output_contexts];
        if ($id) {
            $t['name'] = $id;
        }
        $this->data = $t;
    }

    public function __toString() {
        return $this->json();
    }

    function json() {
        return json_encode($this->data);
    }

    function data() {
        return $this->data;
    }
}

/*
 * -----------------------------------------------------------
 * DIALOGFLOW MESSAGE
 * -----------------------------------------------------------
 *
 * Send the user message to the bot and return the reply
 *
 */

$sb_recursion_dialogflow = [true, true, true, true, true];
function sb_dialogflow_message($conversation_id = false, $message = '', $token = -1, $language = false, $attachments = [], $event = '', $parameters = false, $project_id = false, $session_id = false, $audio = false) {
    global $sb_recursion_dialogflow;
    if (sb_is_cloud()) {
        sb_cloud_membership_validation(true);
    }
    $smart_reply = $event == 'smart-reply';
    $user_id = $conversation_id && !$smart_reply && sb_is_agent() ? sb_db_get('SELECT user_id FROM sb_conversations WHERE id = ' . sb_db_escape($conversation_id, true))['user_id'] : sb_get_active_user_ID();
    if (!sb_cloud_membership_has_credits('google')) {
        return sb_error('no-credits', 'sb_dialogflow_message');
    }
    $cx = sb_get_multi_setting('google', 'dialogflow-edition', sb_get_setting('dialogflow-edition')) == 'cx'; // Deprecated: sb_get_setting('dialogflow-edition', 'es')
    $query = ['queryInput' => [], 'queryParams' => $cx ? ['parameters' => ['user_id' => $user_id, 'conversation_id' => $conversation_id]] : ['payload' => ['support_board' => ['conversation_id' => $conversation_id, 'user_id' => $user_id]]]];
    $bot_id = sb_get_bot_id();
    $human_takeover = sb_dialogflow_get_human_takeover_settings();
    $human_takeover = $human_takeover['active'] ? $human_takeover : false;
    $response_success = [];
    $multilingual = sb_get_setting('dialogflow-multilingual') || sb_get_multi_setting('google', 'google-multilingual'); // Deprecated: sb_get_setting('dialogflow-multilingual')
    $multilingual_translation = sb_get_setting('dialogflow-multilingual-translation') || sb_get_multi_setting('google', 'google-multilingual-translation'); // Deprecated: sb_get_setting('dialogflow-multilingual-translation')
    $user_language = $multilingual_translation ? sb_get_user_extra($user_id, 'language') : false;
    $unknow_language_message = false;
    $dialogflow_agent = false;
    $is_human_takeover = $conversation_id && !$smart_reply && !sb_dialogflow_is_human_takeover($conversation_id);
    $message_id = false;
    $translations = false;
    $payload = [];
    if ($human_takeover && sb_dialogflow_is_human_takeover($conversation_id) && sb_isset($human_takeover, 'disable-chatbot')) {
        return false;
    }
    if ($event == 'translations') {
        unset($GLOBALS['SB_LANGUAGE']);
        $translations = sb_get_current_translations();
    }
    if ($parameters) {
        $query['queryParams'][$cx ? 'parameters' : 'payload'] = array_merge($query['queryParams'][$cx ? 'parameters' : 'payload'], $parameters);
    }
    if (empty($bot_id)) {
        return new SBValidationError('bot-id-not-found');
    }
    if (!$language || empty($language[0])) {
        $language = $multilingual ? ($user_language ? $user_language : sb_get_user_language($user_id)) : false;
        $language = $language ? [$language] : ['en'];
    } else {
        $language[0] = sb_dialogflow_language_code($language[0]);
        if (count($language) > 1 && $language[1] == 'language-detection') {
            $response_success['language_detection'] = $language[0];
        }
    }
    $query['queryInput']['languageCode'] = $language[0];

    // Retrive token
    if ($token == -1 || $token === false) {
        $token = sb_dialogflow_get_token();
        if (sb_is_error($token)) {
            return $token;
        }
    }

    // Attachments
    $attachments = sb_json_array($attachments);
    for ($i = 0; $i < count($attachments); $i++) {
        $message .= ' ' . $attachments[$i][1];
    }

    if (!empty($audio)) {

        // Audio
        if (pathinfo($audio, PATHINFO_EXTENSION) == 'ogg' && sb_get_multi_setting('open-ai', 'open-ai-speech-recognition')) {
            $message .= sb_open_ai_audio_to_text($audio);
            $audio = false;
        } else {
            $audio = strpos($audio, 'http') === 0 ? sb_get($audio) : file_get_contents($audio);
            if ($cx) {
                $query['queryInput']['audio'] = ['config' => ['SampleRateHertz' => 16000, 'audioEncoding' => 'AUDIO_ENCODING_OGG_OPUS', 'languageCode' => $language[0]], 'audio' => base64_encode($audio)];
            } else {
                $query['queryInput']['audioConfig'] = ['audioEncoding' => 'AUDIO_ENCODING_UNSPECIFIED', 'languageCode' => $language[0]];
                $query['inputAudio'] = base64_encode($audio);
            }
        }
    }
    if (empty($audio) && !empty($message)) {

        // Message
        $query['queryInput']['text'] = ['text' => $message, 'languageCode' => $language[0]];
    } else if (!empty($event)) {

        // Events
        $query['queryInput']['event'] = $cx ? ['event' => $event] : ['name' => $event, 'languageCode' => $language[0]];
    }

    // Department linking
    if (!$project_id && $conversation_id && !$smart_reply) {
        $departments = sb_get_setting('dialogflow-departments');
        if ($departments && is_array($departments)) {
            $department = sb_db_get('SELECT department FROM sb_conversations WHERE id = ' . sb_db_escape($conversation_id, true))['department'];
            for ($i = 0; $i < count($departments); $i++) {
                if ($departments[$i]['dialogflow-departments-id'] == $department) {
                    $project_id = $departments[$i]['dialogflow-departments-agent'];
                    break;
                }
            }
        }
    }

    // Dialogflow response
    $session_id = $session_id ? $session_id : ($user_id ? $user_id : 'sb');
    $response = sb_dialogflow_curl('/agent/sessions/' . $session_id . ':detectIntent', $query, false, 'POST', $token, $project_id);
    sb_cloud_membership_use_credits(($cx ? 'cx' : 'es') . (empty($audio) ? '' : '-audio'), 'google', strlen($audio));
    sb_webhooks('SBDialogflowMessage', ['response' => $response, 'message' => $message, 'conversation_id' => $conversation_id]);
    if (is_string($response)) {
        if (strpos($response, 'Error 404')) {
            return ['response' => ['error' => 'Error 404. Dialogflow Project ID or Agent Name not found.']];
        }
        $response = [];
    }
    if (sb_is_error($response)) {
        return $response;
    }
    if (isset($response['error']) && (sb_isset($response['error'], 'code') == 403 || in_array($response['error']['status'], ['PERMISSION_DENIED', 'UNAUTHENTICATED']))) {
        if ($sb_recursion_dialogflow[0]) {
            $sb_recursion_dialogflow[0] = false;
            $token = sb_dialogflow_get_token(false);
            return sb_dialogflow_message($conversation_id, $message, $token, $language, [], $event);
        } else {
            sb_error('dialogflow-access-token', 'sb_dialogflow_message', $response);
        }
    }
    if ($event == 'Welcome') {
        sb_dialogflow_set_active_context('welcome', [], 2, $token, $user_id, $language[0]);
    }
    if (isset($response['error'])) {
        return ['response' => $response];
    }
    $response_query = sb_isset($response, 'queryResult', []);
    $messages = sb_isset($response_query, 'fulfillmentMessages', sb_isset($response_query, 'responseMessages', []));
    $unknow_answer = sb_dialogflow_is_unknow($response);
    $results = [];
    $message_length = strlen($message);
    if (!$messages && isset($response_query['knowledgeAnswers'])) {
        $messages = sb_isset($response_query['knowledgeAnswers'], 'answers', []);
        for ($i = 0; $i < count($messages); $i++) {
            $messages[$i] = ['text' => ['text' => [$messages[$i]['answer']]]];
        }
    }
    if (isset($messages[0]) && isset($messages[0]['text']) && $messages[0]['text']['text'][0] == 'skip-intent') {
        $unknow_answer = true;
        $messages = [];
    }
    if (isset($response_query['webhookPayload'])) {
        array_push($messages, ['payload' => $response_query['webhookPayload']]);
    }

    // Parameters
    $parameters = isset($response_query['parameters']) && count($response_query['parameters']) ? $response_query['parameters'] : [];
    if (isset($response_query['outputContexts']) && count($response_query['outputContexts']) && isset($response_query['outputContexts'][0]['parameters'])) {
        for ($i = 0; $i < count($response_query['outputContexts']); $i++) {
            if (isset($response_query['outputContexts'][$i]['parameters'])) {
                $parameters = array_merge($response_query['outputContexts'][$i]['parameters'], $parameters);
            }
        }
    }

    // Google search, spelling correction
    if ($unknow_answer && !sb_is_agent()) {
        if ($message_length > 2) {
            if ($sb_recursion_dialogflow[1] && sb_get_multi_setting('open-ai', 'open-ai-spelling-correction-dialogflow') && empty(sb_get_shortcode($message))) {
                $spelling_correction = sb_open_ai_spelling_correction($message);
                $sb_recursion_dialogflow[1] = false;
                if ($spelling_correction != $message) {
                    return sb_dialogflow_message($conversation_id, $spelling_correction, $token, $language, $attachments, $event, $parameters);
                }
            }
            $google_search_settings = sb_get_setting('dialogflow-google-search');
            if ($google_search_settings) {
                $spelling_correction = $google_search_settings['dialogflow-google-search-spelling-active'];
                $continue = $google_search_settings['dialogflow-google-search-active'] && $message_length > 4 && !sb_get_multi_setting('open-ai', 'open-ai-active');
                if ($continue) {
                    $entities = sb_isset($google_search_settings, 'dialogflow-google-search-entities');
                    if (!empty($entities) && is_array($entities)) {
                        $continue = false;
                        $entities_response = sb_isset(sb_google_analyze_entities($message, $language[0], $token), 'entities', []);
                        for ($i = 0; $i < count($entities_response); $i++) {
                            if (in_array($entities_response[$i]['type'], $entities)) {
                                $continue = true;
                                break;
                            }
                        }
                    }
                }
                if ($continue || $spelling_correction) {
                    $google_search_response = sb_get('https://www.googleapis.com/customsearch/v1?key=' . $google_search_settings['dialogflow-google-search-key'] . '&cx=' . $google_search_settings['dialogflow-google-search-id'] . '&q=' . urlencode($message), true);
                    if ($sb_recursion_dialogflow[2] && $spelling_correction && isset($google_search_response['spelling'])) {
                        $sb_recursion_dialogflow[2] = false;
                        return sb_dialogflow_message($conversation_id, $google_search_response['spelling']['correctedQuery'], $token, $language, $attachments, $event, $parameters);
                    }
                    if ($continue) {
                        $google_search_response = sb_isset($google_search_response, 'items');
                        if ($google_search_response && count($google_search_response)) {
                            $google_search_response = $google_search_response[0];
                            $google_search_message = $google_search_response['snippet'];
                            $pos = strrpos($google_search_message, '. ');
                            if (!$pos && substr($google_search_message, -3) !== '...' && substr($google_search_message, -1) === '.') {
                                $pos = strlen($google_search_message);
                            }
                            if ($pos) {
                                $google_search_message = substr($google_search_message, 0, $pos);
                                $unknow_answer = false;
                                $messages = [['text' => ['text' => [$google_search_message]]]];
                                sb_dialogflow_set_active_context('google-search', ['link' => $google_search_response['link']], 2, $token, $user_id, $language[0]);
                            } else {
                                $google_search_message = false;
                            }
                        }
                    }
                }
            }
        }
    }
    if (!sb_is_agent() || $smart_reply) {
        $detected_language = false;
        $repeated_intent = false;

        // Language detection
        if ($sb_recursion_dialogflow[3] && (sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-active') || sb_get_multi_setting('google', 'google-language-detection')) && (($unknow_answer || !$user_language) && count(sb_db_get('SELECT id FROM sb_messages WHERE user_id = ' . $user_id . ' LIMIT 3', false)) < 3)) { // Deprecated: sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-active')
            $sb_recursion_dialogflow[3] = false;
            $detected_language = sb_google_language_detection($message, $token);
            if (!empty($detected_language) && ($detected_language != $language[0] || ($user_language && $detected_language != $user_language))) {
                $dialogflow_agent = sb_dialogflow_get_agent();
                sb_language_detection_db($user_id, $detected_language);
                $user_language = $detected_language;
                $payload['event'] = 'update-user';
                if ($detected_language != $language[0] && ($detected_language == sb_isset($dialogflow_agent, 'defaultLanguageCode') || in_array($detected_language, sb_isset($dialogflow_agent, 'supportedLanguageCodes', [])))) {
                    return sb_dialogflow_message($conversation_id, $message, $token, [$detected_language, 'language-detection'], $attachments, $event);
                } else if (!$multilingual_translation) {
                    $unknow_language_message = true;
                } else {
                    $event = 'translations';
                }
            }
        }

        // Repeated Intent
        if ($conversation_id && !$smart_reply && !$unknow_answer && sb_get_multi_setting('open-ai', 'open-ai-active') && empty($response_query['parameters']) && isset($messages[0]) && isset($messages[0]['text'])) {
            $last_message = sb_get_last_message($conversation_id, false, $bot_id);
            $repeated_intent = $last_message && sb_google_get_message_translation($last_message)['message'] == $messages[0]['text']['text'][0] && empty($response_query['parameters']);
        }

        if ($unknow_answer || $repeated_intent) {

            // Multilingual and translations
            if ($sb_recursion_dialogflow[4] && $multilingual_translation && !$repeated_intent) {
                $sb_recursion_dialogflow[4] = false;
                if (empty($GLOBALS['dialogflow_languages'])) {
                    $dialogflow_agent = $dialogflow_agent ? $dialogflow_agent : sb_dialogflow_get_agent();
                    $lang = sb_isset($dialogflow_agent, 'defaultLanguageCode', $language[0]);
                } else {
                    $lang = $GLOBALS['dialogflow_languages'][0];
                }
                $message_translated = sb_google_translate([$message], $lang, $token);
                if (!empty($message_translated[0])) {
                    return sb_dialogflow_message($conversation_id, $message_translated[0][0], $token, [$language[0], 'language-translation'], $attachments, $event);
                }
            }

            // OpenAI
            if ($message_length > 4 && sb_get_multi_setting('open-ai', 'open-ai-active')) {
                if ($conversation_id && !$smart_reply) {
                    $is_human_takeover = sb_dialogflow_is_human_takeover($conversation_id);
                }
                if (!$is_human_takeover || !$conversation_id) {
                    $extra = [];
                    if ($multilingual && !$multilingual_translation) {
                        $extra['language'] = $user_language ? $user_language : sb_get_user_language($user_id);
                    }
                    if ($smart_reply) {
                        $extra['smart_reply'] = true;
                    }
                    $response_open_ai = sb_open_ai_message($message, false, false, $conversation_id, $extra, false, $attachments);
                    if (!sb_is_error($response_open_ai) && $response_open_ai[0] && $response_open_ai[1] != 'sb-human-takeover') {
                        $unknow_answer = false;
                        $messages = [['text' => ['text' => [$response_open_ai[1]]]]];
                        $response = ['dialogflow' => $response, 'openai' => $response_open_ai];
                    }
                }
            }
            if ($unknow_answer && $unknow_language_message) {
                $language_detection_message = sb_get_multi_setting('google', 'google-language-detection-message', sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-message')); // Deprecated: sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-message')
                if (!empty($language_detection_message) && $conversation_id && $detected_language) {
                    $language_name = sb_google_get_language_name($detected_language);
                    $language_detection_message = str_replace('{language_name}', $language_name, sb_t($language_detection_message, $detected_language));
                    $message_id = sb_send_message($bot_id, $conversation_id, $language_detection_message)['id'];
                    return ['token' => $token, 'messages' => [['message' => $language_detection_message, 'attachments' => [], 'payload' => ['language_detection' => true], 'id' => $message_id]], 'response' => $response, 'language_detection_message' => $language_detection_message, 'message_id' => $message_id, 'user_language' => $user_language];
                }
            }
        }
    }

    $count = count($messages);
    $is_assistant = true;
    if (is_string($response)) {
        return ['response' => $response];
    }
    $response['outputAudio'] = '';
    for ($i = 0; $i < $count; $i++) {
        if (isset($messages[$i]['text']) && $messages[$i]['text']['text'][0]) {
            $is_assistant = false;
            break;
        }
    }
    for ($i = 0; $i < $count; $i++) {
        $bot_message = '';

        // Payload
        $payload = $i ? sb_isset($messages[$i], 'payload', []) : array_merge($payload, sb_isset($messages[$i], 'payload', []));
        if ($payload && $conversation_id && !$smart_reply) {
            if (isset($payload['redirect'])) {
                $payload['redirect'] = sb_dialogflow_merge_fields($payload['redirect'], $parameters, $language[0]);
            }
            if (isset($payload['archive-chat'])) {
                sb_update_conversation_status($conversation_id, 3);
                if (sb_get_multi_setting('close-message', 'close-active')) {
                    sb_close_message($conversation_id, $bot_id);
                }
                if (sb_get_multi_setting('close-message', 'close-transcript') && sb_isset(sb_get_active_user(), 'email')) {
                    $transcript = sb_transcript($conversation_id);
                    sb_email_create(sb_get_active_user_ID(), sb_get_user_name(), sb_isset(sb_get_active_user(), 'profile_image'), sb_get_multi_setting('transcript', 'transcript-message', ''), [[$transcript, $transcript]], true, $conversation_id);
                    $payload['force-message'] = true;
                }
            }
            if (isset($payload['update-user-details']) || isset($payload['update-user-language'])) {
                $payload_user_details = sb_isset($payload, 'update-user-details', []);
                $user = sb_get_user($user_id);
                if (!sb_is_agent($user)) {
                    if (isset($payload['update-user-language'])) {
                        $language_code = $payload['update-user-language'];
                        $language_codes = sb_get_json_resource('languages/language-codes.json');
                        $language_code = sb_get_language_code_by_name($language_code, $language_codes);
                        if (strlen($language_code) > 2) {
                            $language_code = sb_google_translate([$language_code], 'en', $token);
                            if (!empty($language_code[0])) {
                                foreach ($language_codes as $key => $value) {
                                    if ($language_code[0][0] == $value) {
                                        $language_code = $key;
                                        break;
                                    }
                                }
                            }
                        }
                        if (is_string($language_code) && strlen($language_code) == 2 && isset($language_codes[$language_code])) {
                            $payload_user_details['extra'] = ['language' => $language_code, 'browser_language' => ''];
                            $user_language = $language_code;
                            if ($multilingual) {
                                $dialogflow_agent = sb_dialogflow_get_agent();
                                if ($language_code == sb_isset($dialogflow_agent, 'defaultLanguageCode') || in_array($language_code, sb_isset($dialogflow_agent, 'supportedLanguageCodes', []))) {
                                    $response_success['language_detection'] = $language_code;
                                }
                            }
                        } else {
                            return false;
                        }
                    }
                    $payload['event'] = 'update-user';
                    $user['user_type'] = '';
                    sb_update_user($user_id, array_merge($user, $payload_user_details), sb_isset($payload_user_details, 'extra', []));
                }
            }
        }

        // Google Assistant
        if ($is_assistant) {
            if (isset($messages[$i]['platform']) && $messages[$i]['platform'] == 'ACTIONS_ON_GOOGLE') {
                if (isset($messages[$i]['simpleResponses']) && isset($messages[$i]['simpleResponses']['simpleResponses'])) {
                    $item = $messages[$i]['simpleResponses']['simpleResponses'];
                    if (isset($item[0]['textToSpeech'])) {
                        $bot_message = $item[0]['textToSpeech'];
                    } else if ($item[0]['displayText']) {
                        $bot_message = $item[0]['displayText'];
                    }
                }
            }
        } else if (isset($messages[$i]['text'])) {

            // Message
            $bot_message = $messages[$i]['text']['text'][0];
        }

        // Attachments
        $attachments = [];
        if ($payload) {
            if (isset($payload['attachments'])) {
                $attachments = $payload['attachments'];
                if (!$attachments && !is_array($attachments)) {
                    $attachments = [];
                }
            }
        }

        // WooCommerce
        if (defined('SB_WOOCOMMERCE')) {
            $woocommerce = sb_woocommerce_dialogflow_process_message($bot_message, $payload);
            $bot_message = $woocommerce[0];
            $payload = $woocommerce[1];
        }

        // Send message and human takeover
        if ($bot_message || $payload) {
            if ($conversation_id && !$smart_reply) {
                $is_human_takeover = sb_dialogflow_is_human_takeover($conversation_id);
                if ($human_takeover && $unknow_answer && strlen($message) > 3 && strpos($message, ' ') && !$is_human_takeover) {
                    $human_takeover_response = sb_chatbot_human_takeover($conversation_id, $human_takeover);
                    if ($human_takeover_response[1]) {
                        $response_success['human_takeover'] = true;
                    }
                    $results = array_merge($results, $human_takeover_response[0]);
                } else {
                    $last_agent = sb_isset(sb_get_last_agent_in_conversation($conversation_id), 'id');
                    if ($is_human_takeover && (isset($payload['human-takeover']) || strpos($bot_message, 'sb-human-takeover'))) {
                        $bot_message = sb_isset($human_takeover, 'message_fallback');
                        $payload = false;
                    }
                    if (($bot_message || $payload) && (!$is_human_takeover || !empty($payload['force-message']) || ((!$last_agent || !sb_is_user_online($last_agent)) && !$unknow_answer))) {
                        if (!$bot_message && isset($payload['force-message']) && $i > 0 && isset($messages[$i - 1]['text'])) {
                            $bot_message = $messages[$i - 1]['text']['text'][0];
                        }
                        $bot_message = sb_dialogflow_merge_fields($bot_message, $parameters, $language[0]);
                        if ($multilingual_translation && $bot_message) {
                            $continue = isset($language[1]) && $language[1] == 'language-translation';
                            $user_language = $user_language ? $user_language : sb_get_user_language($user_id);
                            if (!$continue) {
                                $dialogflow_agent = $dialogflow_agent ? $dialogflow_agent : sb_dialogflow_get_agent();
                                $continue = $user_language != sb_isset($dialogflow_agent, 'defaultLanguageCode') && !in_array($user_language, sb_isset($dialogflow_agent, 'supportedLanguageCodes', []));
                            }
                            if ($continue) {
                                $message = sb_google_translate([$bot_message], $user_language, $token);
                                if (!empty($message[0])) {
                                    $bot_message = $message[0][0];
                                }
                            }
                        }
                        $bot_message = sb_open_ai_text_formatting($bot_message);
                        $message_id = sb_send_message($bot_id, $conversation_id, $bot_message, $attachments, -1, $payload)['id'];
                        array_push($results, ['message' => sb_open_ai_text_formatting($bot_message), 'attachments' => $attachments, 'payload' => $payload, 'id' => $message_id]);
                    }
                }
            } else {
                array_push($results, ['message' => sb_dialogflow_merge_fields($bot_message, $parameters, $language[0]), 'attachments' => $attachments, 'payload' => $payload]);
            }
        }
    }
    if (count($results)) {
        $response_success['token'] = $token;
        $response_success['messages'] = $results;
        $response_success['response'] = $response;
        $response_success['user_language'] = $user_language;
        $response_success['message_language'] = $language[0];
        $response_success['translations'] = $translations;
        return $response_success;
    }
    if (isset($response['error']) && sb_isset($response['error'], 'code') != 400) {
        $admin_emails = sb_db_get('SELECT email FROM sb_users WHERE user_type = "admin"', false);
        $admin_emails_string = '';
        for ($i = 0; $i < count($admin_emails); $i++) {
            $admin_emails_string .= $admin_emails[$i]['email'] . ',';
        }
        $text = 'Dialogflow Error | ' . SB_URL . '/admin.php';
        sb_email_send(substr($admin_emails_string, 0, -1), $text, $text . '<br><br>' . json_encode($response));
    }
    return ['response' => $response];
}

/*
 * -----------------------------------------------------------
 * INTENTS
 * -----------------------------------------------------------
 *
 * 1. Create an Intent
 * 2. Update an existing Intent
 * 3. Create multiple Intents
 * 4. Delete multiple Intents
 * 5. Return all Intents
 *
 */

function sb_dialogflow_create_intent($training_phrases, $bot_responses, $language = '', $conversation_id = false, $services = false) {
    global $sb_entity_types;
    $training_phrases_api = [];
    $cx = sb_get_multi_setting('google', 'dialogflow-edition', sb_get_setting('dialogflow-edition')) == 'cx'; // Deprecated: sb_get_setting('dialogflow-edition')
    $sb_entity_types = $cx ? ($sb_entity_types ? $sb_entity_types : sb_isset(sb_dialogflow_curl('/entityTypes', '', false, 'GET'), 'entityTypes', [])) : false;
    $parameters = [];

    // Training phrases and parameters
    if (is_string($bot_responses)) {
        $bot_responses = [['text' => ['text' => $bot_responses]]];
    }
    for ($i = 0; $i < count($training_phrases); $i++) {
        if (is_string($training_phrases[$i])) {
            $parts = ['text' => $training_phrases[$i]];
        } else {
            $parts = $training_phrases[$i]['parts'];
            for ($j = 0; $j < count($parts); $j++) {
                if (empty($parts[$j]['text'])) {
                    array_splice($parts, $j, 1);
                } else if ($cx && isset($parts[$j]['entityType'])) {
                    for ($y = 0; $y < count($sb_entity_types); $y++) {
                        if ($sb_entity_types[$y]['displayName'] == $parts[$j]['alias']) {
                            $id = 'parameter_id_' . $y;
                            $parts[$j]['parameterId'] = $id;
                            $new = true;
                            for ($k = 0; $k < count($parameters); $k++) {
                                if ($parameters[$k]['id'] == $id) {
                                    $new = false;
                                    break;
                                }
                            }
                            if ($new) {
                                array_push($parameters, ['id' => $id, 'entityType' => $sb_entity_types[$y]['name']]);
                            }
                            break;
                        }
                    }
                }
            }
        }
        array_push($training_phrases_api, ['type' => 'TYPE_UNSPECIFIED', 'parts' => $parts, 'repeatCount' => 1]);
    }

    // Intent name
    $name = sb_isset($training_phrases_api[0]['parts'], 'text');
    if (!$name) {
        $parts = $training_phrases_api[0]['parts'];
        for ($i = 0; $i < count($parts); $i++) {
            $name .= $parts[$i]['text'];
        }
    }

    // Create the Intent
    $query = ['displayName' => ucfirst(str_replace('-', ' ', sb_string_slug(strlen($name) > 100 ? substr($name, 0, 99) : $name))), 'priority' => 500000, 'webhookState' => 'WEBHOOK_STATE_UNSPECIFIED', 'trainingPhrases' => $training_phrases_api, 'messages' => $bot_responses];
    if ($parameters) {
        $query['parameters'] = $parameters;
    }
    $response = sb_dialogflow_curl('/agent/intents', $query, $language);
    if ($cx) {
        $flow_name = '00000000-0000-0000-0000-000000000000';
        if ($conversation_id) {
            $messages = sb_db_get('SELECT payload FROM sb_messages WHERE conversation_id = ' . sb_db_escape($conversation_id, true) . ' AND payload <> "" ORDER BY id DESC');
            for ($i = 0; $i < count($messages); $i++) {
                $payload = json_decode($messages['payload'], true);
                if (isset($payload['queryResult']) && isset($payload['queryResult']['currentPage'])) {
                    $flow_name = $payload['queryResult']['currentPage'];
                    $flow_name = substr($flow_name, strpos($flow_name, '/flows/') + 7);
                    if (strpos($flow_name, '/'))
                        $flow_name = substr($flow_name, 0, strpos($flow_name, '/'));
                    break;
                }
            }
        }
        $flow = sb_dialogflow_curl('/flows/' . $flow_name, '', $language, 'GET');
        array_push($flow['transitionRoutes'], ['intent' => $response['name'], 'triggerFulfillment' => ['messages' => $bot_responses]]);
        $response = sb_dialogflow_curl('/flows/' . $flow_name . '?updateMask=transitionRoutes', $flow, $language, 'PATCH');
    }
    $response['response_open_ai'] = $services != 'dialogflow' && sb_chatbot_active(false, true) ? sb_open_ai_qea_training([[$training_phrases[0], $bot_responses[0]['text']['text']]], $language) : true;
    if (isset($response['displayName']) && $response['response_open_ai']) {
        return true;
    }
    return $response;
}

function sb_dialogflow_update_intent($intent, $training_phrases, $language = '', $services = false) {
    $intent_name = is_string($intent) ? $intent : $intent['name'];
    $pos = strpos($intent_name, '/intents/');
    $intent_name = $pos ? substr($intent_name, $pos + 9) : $intent_name;
    if (is_string($intent)) {
        $intent = sb_dialogflow_get_intents($intent_name, $language);
    }
    if (!isset($intent['trainingPhrases'])) {
        $intent['trainingPhrases'] = [];
    }
    for ($i = 0; $i < count($training_phrases); $i++) {
        array_push($intent['trainingPhrases'], ['type' => 'TYPE_UNSPECIFIED', 'parts' => ['text' => $training_phrases[$i]], 'repeatCount' => 1]);
    }
    $response = sb_dialogflow_curl('/agent/intents/' . $intent_name . '?updateMask=trainingPhrases', $intent, $language, 'PATCH');
    if ($services != 'dialogflow' && sb_chatbot_active(false, true)) {
        $response['response_open_ai'] = sb_open_ai_qea_training([[$training_phrases[0], $services]], $language);
    }
    return isset($response['name']) ? true : $response;
}

function sb_dialogflow_batch_intents($intents, $language = '') {
    if (sb_get_multi_setting('google', 'dialogflow-edition', sb_get_setting('dialogflow-edition')) == 'cx') { // Deprecated: sb_get_setting('dialogflow-edition', 'es')
        $response = [];
        for ($i = 0; $i < count($intents); $i++) {
            array_push($response, sb_dialogflow_create_intent($intents[$i]->data['trainingPhrases'], $intents[$i]->data['messages'], $language));
        }
        return $response;
    } else {
        $intents_array = [];
        for ($i = 0; $i < count($intents); $i++) {
            array_push($intents_array, $intents[$i]->data());
        }
        $query = ['intentBatchInline' => ['intents' => $intents_array], 'intentView' => 'INTENT_VIEW_UNSPECIFIED'];
        if (!empty($language))
            $query['languageCode'] = $language;
        return sb_dialogflow_curl('/agent/intents:batchUpdate', $query);
    }
}

function sb_dialogflow_batch_intents_delete($intents) {
    return sb_dialogflow_curl('/agent/intents:batchDelete', ['intents' => $intents]);
}

function sb_dialogflow_get_intents($intent_name = false, $language = '') {
    $next_page_token = true;
    $paginatad_items = [];
    $intents = [];
    while ($next_page_token) {
        $items = sb_dialogflow_curl($intent_name ? ('/agent/intents/' . $intent_name . '?intentView=INTENT_VIEW_FULL') : ('/agent/intents?pageSize=1000&intentView=INTENT_VIEW_FULL' . ($next_page_token !== true && $next_page_token !== false ? ('&pageToken=' . $next_page_token) : '')), '', $language, 'GET');
        if ($intent_name)
            return $items;
        $next_page_token = sb_isset($items, 'nextPageToken');
        if (sb_is_error($next_page_token))
            die($next_page_token);
        array_push($paginatad_items, sb_isset($items, 'intents'));
    }
    for ($i = 0; $i < count($paginatad_items); $i++) {
        $items = $paginatad_items[$i];
        if ($items) {
            for ($j = 0; $j < count($items); $j++) {
                if (!empty($items[$j]))
                    array_push($intents, $items[$j]);
            }
        }
    }
    return $intents;
}

/*
 * -----------------------------------------------------------
 * ENTITIES
 * -----------------------------------------------------------
 *
 * Create, get, update, delete a Dialogflow entities
 *
 */

function sb_dialogflow_create_entity($entity_name, $values, $language = '') {
    $response = sb_dialogflow_curl('/agent/entityTypes', is_a($values, 'SBDialogflowEntity') ? $values->data() : (new SBDialogflowEntity($entity_name, $values))->data(), $language);
    if (isset($response['displayName'])) {
        return true;
    } else if (isset($response['error']) && sb_isset($response['error'], 'status') == 'FAILED_PRECONDITION') {
        return new SBValidationError('duplicate-dialogflow-entity');
    }
    return $response;
}

function sb_dialogflow_update_entity($entity_id, $values, $entity_name = false, $language = '') {
    $response = sb_dialogflow_curl('/agent/entityTypes/' . $entity_id, is_a($values, 'SBDialogflowEntity') ? $values->data() : (new SBDialogflowEntity($entity_name, $values))->data(), $language, 'PATCH');
    if (isset($response['displayName'])) {
        return true;
    }
    return $response;
}

function sb_dialogflow_get_entity($entity_id = 'all', $language = '') {
    $entities = sb_dialogflow_curl('/agent/entityTypes', '', $language, 'GET');
    if (isset($entities['entityTypes'])) {
        $entities = $entities['entityTypes'];
        if ($entity_id == 'all') {
            return $entities;
        }
        for ($i = 0; $i < count($entities); $i++) {
            if ($entities[$i]['displayName'] == $entity_id) {
                return $entities[$i];
            }
        }
        return new SBValidationError('entity-not-found');
    } else
        return $entities;
}

/*
 * -----------------------------------------------------------
 * MISCELLANEOUS
 * -----------------------------------------------------------
 *
 * 1. Get a fresh Dialogflow access token
 * 2. Convert the Dialogflow merge fields to the final values
 * 3. Activate a context in the active conversation
 * 4. Return the details of a Dialogflow agent
 * 5. Chinese language sanatization
 * 6. Dialogflow curl
 * 7. Human takeover
 * 8. Check if human takeover is active
 * 9. Return the Dialogflow human takeover settings
 * 10. Execute payloads
 * 11. Add Intents to saved replies
 * 12. Check if unknow answer
 * 13. PDF to text
 * 14. Support Board database embedding
 * 15. Check if manual or automatic sync mode
 * 16. Data scraping
 * 17. Sitemap generation
 *
 */

function sb_dialogflow_get_token($token = true) {
    if ($token === true) {
        global $dialogflow_token;
        if (!empty($dialogflow_token)) {
            return $dialogflow_token;
        }
        $dialogflow_token = sb_get_external_setting('dialogflow_token');
        if ($dialogflow_token && time() < $dialogflow_token[1]) {
            $dialogflow_token = $dialogflow_token[0];
            return $dialogflow_token;
        }
    } else if ($token === false) {
        $token = sb_get_multi_setting('google', 'google-refresh-token');
    }
    if (empty($token)) {
        return sb_error('google-refresh-token-not-found', 'sb_open_ai_message', 'Click the synchronize button to get the refresh token.');
    }
    $info = sb_google_key();
    $query = '{ refresh_token: "' . $token . '", grant_type: "refresh_token", client_id: "' . $info[0] . '", client_secret: "' . $info[1] . '" }';
    $response = sb_curl('https://accounts.google.com/o/oauth2/token', $query, ['Content-Type: application/json', 'Content-Length: ' . strlen($query)]);
    $token = sb_isset($response, 'access_token');
    if ($token) {
        sb_save_external_setting('dialogflow_token', [$token, time() + $response['expires_in']]);
        $dialogflow_token = $token;
        return $token;
    }
    return json_encode($response);
}

function sb_dialogflow_merge_fields($message, $parameters, $language = '') {
    if (defined('SB_WOOCOMMERCE')) {
        $message = sb_woocommerce_merge_fields($message, $parameters, $language);
    }
    return $message;
}

function sb_dialogflow_set_active_context($context_name, $parameters = [], $life_span = 5, $token = false, $user_id = false, $language = false) {
    if (!sb_get_multi_setting('google', 'dialogflow-active')) {
        return false;
    }
    $language = $language === false ? (sb_get_multi_setting('google', 'google-multilingual') ? sb_get_user_language($user_id) : '') : $language;
    $session_id = $user_id === false ? sb_isset(sb_get_active_user(), 'id', 'sb') : $user_id;
    $parameters = empty($parameters) ? '' : ', "parameters": ' . (is_string($parameters) ? $parameters : json_encode($parameters));
    $query = '{ "queryInput": { "text": { "languageCode": "' . (empty($language) ? 'en' : $language) . '", "text": "sb-trigger-context" }}, "queryParams": { "contexts": [{ "name": "projects/' . sb_get_multi_setting('google', 'google-project-id') . '/agent/sessions/' . $session_id . '/contexts/' . $context_name . '", "lifespanCount": ' . $life_span . $parameters . ' }] }}';
    return sb_dialogflow_curl('/agent/sessions/' . $session_id . ':detectIntent', $query, false, 'POST', $token);
}

function sb_dialogflow_get_agent() {
    return sb_dialogflow_curl('/agent', '', '', 'GET');
}

function sb_dialogflow_language_code($language) {
    return $language == 'zh' ? 'zh-cn' : ($language == 'zt' ? 'zh-tw' : $language);
}

function sb_dialogflow_curl($url_part, $query = '', $language = false, $type = 'POST', $token = false, $project_id = false) {

    // Project ID
    if (!$project_id) {
        $project_id = sb_get_multi_setting('google', 'google-project-id');
        if (empty($project_id)) {
            return sb_error('project-id-not-found', 'sb_dialogflow_curl');
        }
    }

    // Retrive token
    $token = empty($token) || $token == -1 ? sb_dialogflow_get_token() : $token;
    if (sb_is_error($token)) {
        return sb_error('token-error', 'sb_dialogflow_curl');
    }

    // Language
    if (!empty($language)) {
        $language = (strpos($url_part, '?') ? '&' : '?') . 'languageCode=' . $language;
    }

    // Query
    if (!is_string($query)) {
        $query = json_encode($query);
    }

    // Edition and version
    $edition = sb_get_multi_setting('google', 'dialogflow-edition', sb_get_setting('dialogflow-edition', 'es')); // Deprecated: sb_get_setting('dialogflow-edition', 'es')
    $version = 'v2beta1/projects/';
    $cx = $edition == 'cx';
    if ($cx) {
        $version = 'v3beta1/';
        $url_part = str_replace('/agent/', '/', $url_part);
    }

    // Location
    $location = sb_get_multi_setting('google', 'dialogflow-location', sb_get_setting('dialogflow-location', '')); // Deprecated: sb_get_setting('dialogflow-location', '')
    $location_session = $location && !$cx ? '/locations/' . substr($location, 0, -1) : '';

    // Send
    $url = 'https://' . $location . 'dialogflow.googleapis.com/' . $version . $project_id . $location_session . $url_part . $language;
    $response = sb_curl($url, $query, ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Content-Length: ' . strlen($query)], $type);
    return $type == 'GET' ? json_decode($response, true) : $response;
}

function sb_dialogflow_human_takeover($conversation_id, $auto_messages = false) {
    $human_takeover = sb_dialogflow_get_human_takeover_settings();
    $conversation_id = sb_db_escape($conversation_id, true);
    $bot_id = sb_get_bot_id();
    $data = sb_db_get('SELECT A.id AS `user_id`, A.email, A.first_name, A.last_name, A.profile_image, B.agent_id, B.department, B.status_code FROM sb_users A, sb_conversations B WHERE A.id = B.user_id AND B.id = ' . $conversation_id);
    $user_id = $data['user_id'];
    $messages = sb_db_get('SELECT A.user_id, A.message, A.attachments, A.creation_time, B.first_name, B.last_name, B.profile_image, B.user_type FROM sb_messages A, sb_users B WHERE A.conversation_id = ' . $conversation_id . ' AND A.user_id = B.id AND A.message <> "' . $human_takeover['confirm'] . '" AND A.message NOT LIKE "%sb-human-takeover%" AND A.payload NOT LIKE "%human-takeover%" ORDER BY A.id ASC', false);
    $count = count($messages);
    $last_message = $messages[$count - 1]['message'];
    $response = [];
    sb_send_message($bot_id, $conversation_id, '', [], 2, ['human-takeover' => true]);
    $GLOBALS['human-takeover-' . $conversation_id] = true;

    // Human takeover message and status code
    $message = $human_takeover['message_confirmation'];
    if (!empty($message)) {
        $message_id = sb_send_message($bot_id, $conversation_id, $message, [], 2, ['human-takeover-message-confirmation' => true, 'preview' => $last_message])['id'];
        array_push($response, ['message' => $message, 'id' => $message_id]);
    }

    // Auto messages
    if ($auto_messages) {
        $auto_messages = ['offline', 'follow_up'];
        for ($i = 0; $i < count($auto_messages); $i++) {
            $auto_message = $i == 0 || empty($data['email']) ? sb_execute_bot_message($auto_messages[$i], $conversation_id, $last_message) : false;
            if ($auto_message) {
                array_push($response, $auto_message);
            }
        }
    }

    // Notifications
    sb_send_agents_notifications($last_message, str_replace('{T}', sb_get_setting('bot-name', 'Chatbot'), sb_('This message has been sent because {T} does not know the answer to the user\'s question.')), $conversation_id, false, $data, ['email' => sb_email_get_conversation_code($conversation_id, 20, true)]);

    // Slack
    if (defined('SB_SLACK') && sb_get_setting('slack-active')) {
        for ($i = 0; $i < count($messages); $i++) {
            sb_send_slack_message($user_id, sb_get_user_name($messages[$i]), $messages[$i]['profile_image'], $messages[$i]['message'], sb_isset($messages[$i], 'attachments', []), $conversation_id);
        }
    }

    return $response;
}

function sb_chatbot_human_takeover($conversation_id, $human_takeover_settings) {
    if ($human_takeover_settings['auto']) {
        $human_takeover_messages = sb_dialogflow_human_takeover($conversation_id);
        $messages = [];
        for ($j = 0; $j < count($human_takeover_messages); $j++) {
            array_push($messages, ['message' => sb_t($human_takeover_messages[$j]['message']), 'attachments' => [], 'payload' => false, 'id' => $human_takeover_messages[$j]['id']]);
        }
        return [$messages, true];
    } else {
        $human_takeover_message = '[chips id="sb-human-takeover" options="' . str_replace(',', '\,', sb_rich_value($human_takeover_settings['confirm'], false)) . ',' . str_replace(',', '\,', sb_rich_value($human_takeover_settings['cancel'], false)) . '" message="' . sb_rich_value($human_takeover_settings['message']) . '"]';
        $message_id = sb_send_message(sb_get_bot_id(), $conversation_id, $human_takeover_message)['id'];
        return [[['message' => $human_takeover_message, 'attachments' => [], 'payload' => false, 'id' => $message_id]], false];
    }
}

function sb_dialogflow_is_human_takeover($conversation_id) {
    $name = 'human-takeover-' . $conversation_id;
    if (isset($GLOBALS[$name])) {
        return $GLOBALS[$name];
    }
    $agent_ids = sb_get_agents_ids();
    $response = sb_isset(sb_db_get('SELECT id FROM sb_messages WHERE (user_id IN (' . implode(',', $agent_ids) . ') || payload = "{\"human-takeover\":true}") AND conversation_id = ' . sb_db_escape($conversation_id, true) . ' AND creation_time > "' . sb_gmt_now(864000) . '" LIMIT 1'), 'id');
    if ($response) {
        $response = empty(sb_db_get('SELECT id FROM sb_messages WHERE id > ' . $response . ' AND conversation_id = ' . sb_db_escape($conversation_id, true) . ' AND payload = "{\"event\":\"conversation-status-update-3\"}"'));
    }
    $GLOBALS[$name] = $response;
    return $response;
}

function sb_dialogflow_get_human_takeover_settings() {
    $settings = sb_get_setting('dialogflow-human-takeover');
    return ['active' => $settings['dialogflow-human-takeover-active'], 'message' => sb_t(sb_isset($settings, 'dialogflow-human-takeover-message', 'I\'m a chatbot. Do you want to get in touch with one of our agents?')), 'message_confirmation' => sb_t(sb_isset($settings, 'dialogflow-human-takeover-message-confirmation', 'Alright! We will get in touch soon!')), 'message_fallback' => sb_t(sb_isset($settings, 'dialogflow-human-takeover-message-fallback', 'An agent has already been contacted and will respond shortly.')), 'confirm' => sb_t(sb_isset($settings, 'dialogflow-human-takeover-confirm', 'Yes')), 'cancel' => sb_t(sb_isset($settings, 'dialogflow-human-takeover-cancel', 'Cancel')), 'auto' => $settings['dialogflow-human-takeover-auto'], 'disable_chatbot' => $settings['dialogflow-human-takeover-disable-chatbot']];
}

function sb_dialogflow_payload($payload, $conversation_id, $message = false, $extra = false) {
    if (isset($payload['agent'])) {
        sb_update_conversation_agent($conversation_id, $payload['agent'], $message);
    }
    if (isset($payload['department'])) {
        sb_update_conversation_department($conversation_id, $payload['department'], $message);
    }
    if (isset($payload['tags'])) {
        sb_tags_update($conversation_id, $payload['tags'], true);
    }
    if (isset($payload['human-takeover'])) {
        $messages = sb_dialogflow_human_takeover($conversation_id, $extra && isset($extra['source']));
        $source = sb_isset($extra, 'source');
        if ($source) {
            for ($i = 0; $i < count($messages); $i++) {
                $message = $messages[$i]['message'];
                $attachments = sb_isset($messages[$i], 'attachments', []);
                sb_messaging_platforms_send_message($message, $extra, $messages[$i]['id'], $attachments);
            }
        }
    }
    if (isset($payload['send-email'])) {
        $send_to_active_user = $payload['send-email']['recipient'] == 'active_user';
        sb_email_create($send_to_active_user ? sb_get_active_user_ID() : 'agents', $send_to_active_user ? sb_get_setting('bot-name') : sb_get_user_name(), $send_to_active_user ? sb_get_setting('bot-image') : sb_isset(sb_get_active_user(), 'profile_image'), $payload['send-email']['message'], sb_isset($payload['send-email'], 'attachments'), false, $conversation_id);
    }
    if (isset($payload['redirect']) && $extra) {
        $message_id = sb_send_message(sb_get_bot_id(), $conversation_id, $payload['redirect']);
        sb_messaging_platforms_send_message($payload['redirect'], $extra, $message_id);
    }
    if (isset($payload['transcript']) && $extra) {
        $transcript_url = sb_transcript($conversation_id);
        $attachments = [[$transcript_url, $transcript_url]];
        $message_id = sb_send_message(sb_get_bot_id(), $conversation_id, '', $attachments);
        sb_messaging_platforms_send_message($extra['source'] == 'ig' || $extra['source'] == 'fb' ? '' : $transcript_url, $attachments, $message_id);
    }
    if (isset($payload['rating'])) {
        sb_set_rating(['conversation_id' => $conversation_id, 'agent_id' => sb_isset(sb_get_last_agent_in_conversation($conversation_id), 'id', sb_get_bot_id()), 'user_id' => sb_get_active_user_ID(), 'message' => '', 'rating' => $payload['rating']]);
    }
}

function sb_dialogflow_saved_replies() {
    $settings = sb_get_settings();
    $saved_replies = sb_get_setting('saved-replies', []);
    $intents = sb_dialogflow_get_intents();
    $count = count($saved_replies);
    for ($i = 0; $i < count($intents); $i++) {
        if (isset($intents[$i]['messages'][0]) && isset($intents[$i]['messages'][0]['text']) && isset($intents[$i]['messages'][0]['text']) && isset($intents[$i]['messages'][0]['text']['text'])) {
            $slug = sb_string_slug($intents[$i]['displayName']);
            $existing = false;
            for ($j = 0; $j < $count; $j++) {
                if ($slug == $saved_replies[$j]['reply-name']) {
                    $existing = true;
                    break;
                }
            }
            if (!$existing) {
                array_push($saved_replies, ['reply-name' => $slug, 'reply-text' => $intents[$i]['messages'][0]['text']['text'][0]]);
            }
        }
    }
    $settings['saved-replies'][0] = $saved_replies;
    return sb_save_settings($settings);
}

function sb_dialogflow_is_unknow($dialogflow_response) {
    $dialogflow_response = sb_isset($dialogflow_response, 'response', $dialogflow_response);
    $query_result = sb_isset($dialogflow_response, 'queryResult', []);
    return (sb_isset($query_result, 'action') == 'input.unknown' || (isset($query_result['match']) && $query_result['match']['matchType'] == 'NO_MATCH')) || (sb_get_multi_setting('google', 'dialogflow-confidence') && sb_isset($query_result, 'intentDetectionConfidence') < floatval(sb_get_multi_setting('google', 'dialogflow-confidence'))) || isset($dialogflow_response['error']);
}

function sb_pdf_to_text($path) {
    if (file_exists($path)) {
        require('pdf/autoload.php');
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        return $pdf->getText();
    }
    return '';
}

function sb_get_sitemap_urls($sitemap_url) {
    $urls = [];
    $xml = sb_get($sitemap_url);
    $sitemap = new SimpleXmlElement($xml);
    foreach ($sitemap->url as $url) {
        if (!strpos($url->loc, 'mailto:')) {
            array_push($urls, strval($url->loc));
        }
    }
    return $urls;
}

function sb_ai_is_manual_sync($source) {
    switch ($source) {
        case 'google':
            return !sb_is_cloud() || !defined('GOOGLE_CLIENT_ID') || sb_get_multi_setting('google', 'google-sync-mode', 'manual') == 'manual'; // Deprecated: remove default , 'manual'
        case 'open-ai':
            return (!sb_is_cloud() || !defined('OPEN_AI_KEY') || sb_get_multi_setting('open-ai', 'open-ai-sync-mode', 'manual') == 'manual') && sb_defined('OPEN_AI_KEY') != trim(sb_get_multi_setting('open-ai', 'open-ai-key')); // Deprecated: remove default , 'manual'
    }
    return false;
}

/*
 * -----------------------------------------------------------
 * SMART REPLY
 * -----------------------------------------------------------
 *
 * 1. Return the suggestions
 * 2. Update a smart reply conversation with a new message
 * 3. Generate the conversation transcript data for a dataset
 *
 */

function sb_dialogflow_smart_reply($message, $dialogflow_languages = false, $token = false, $conversation_id = false, $user_id = false) {
    $suggestions = [];
    $smart_reply_response = false;
    if (!empty($dialogflow_languages)) {
        $GLOBALS['dialogflow_languages'] = $dialogflow_languages;
    }
    $token = empty($token) ? sb_dialogflow_get_token() : $token;
    $dialogflow_active = sb_chatbot_active(true, false);
    $messages = $dialogflow_active ? sb_dialogflow_message($conversation_id, $message, $token, false, [], 'smart-reply') : [];
    if (sb_is_error($messages)) {
        return sb_error('smart-reply-error', 'sb_dialogflow_smart_reply', $messages);
    }
    if (!empty($messages['messages']) && !sb_dialogflow_is_unknow($messages['response'])) {
        for ($i = 0; $i < count($messages['messages']); $i++) {
            $value = $messages['messages'][$i]['message'];
            if (!empty($value) && !strpos($value, 'sb-human-takeover')) {
                array_push($suggestions, $value);
            }
        }
        if ($messages['message_language'] != sb_get_user_language(sb_get_active_user_ID()) && sb_get_multi_setting('google', 'google-multilingual-translation')) {
            $translation = sb_google_translate($suggestions, sb_get_user_language(sb_get_active_user_ID()));
            if (!empty($translation[0])) {
                for ($i = 0; $i < count($suggestions); $i++) {
                    if (!empty($translation[0][$i])) {
                        $suggestions[$i] = $translation[0][$i];
                    }
                }
            }
        }
    }
    if (!count($suggestions) && !$dialogflow_active && (sb_get_multi_setting('open-ai', 'open-ai-active') || sb_get_multi_setting('open-ai', 'open-ai-smart-reply'))) {
        $suggestions = sb_isset(sb_open_ai_smart_reply($message, $conversation_id), 'suggestions', []);
    }
    return ['suggestions' => $suggestions, 'token' => sb_isset($messages, 'token'), 'dialogflow_languages' => $dialogflow_languages, 'smart_reply' => $smart_reply_response];
}

function sb_dialogflow_knowledge_articles($articles = false, $language = false) {
    $language = $language ? sb_dialogflow_language_code($language) : false;
    if (sb_isset(sb_dialogflow_get_agent(), 'defaultLanguageCode') != 'en') {
        return 'dialogflow-language-not-supported';
    }
    if (!$articles) {
        $articles = sb_get_articles(false, false, true, false, 'all');
    }
    if ($articles) {

        // Create articles file
        $faq = [];
        for ($i = 0; $i < count($articles); $i++) {
            $content = strip_tags($articles[$i]['content']);
            if (mb_strlen($content) > 150) {
                $content = mb_substr($content, 0, 150);
                $content = mb_substr($content, 0, mb_strrpos($content, ' ') + 1) . '... [button link="#article-' . $articles[$i]['id'] . '" name="' . sb_('Read more') . '" style="link"]';
                $content = str_replace(', ...', '...', $content);
            }
            array_push($faq, [$articles[$i]['title'], $content]);
        }
        $file_path = sb_csv($faq, false, 'dialogflow-faq', false);
        $file = fopen($file_path, 'r');
        $file_bytes = fread($file, filesize($file_path));
        fclose($file);
        unlink($file_path);

        // Create new knowledge if not exist
        $knowledge_base_name = sb_get_external_setting('dialogflow-knowledge', []);
        if (!isset($knowledge_base_name[$language ? $language : 'default'])) {
            $query = ['displayName' => 'Support Board'];
            if ($language) {
                $query['languageCode'] = $language;
            }
            $name = sb_isset(sb_dialogflow_curl('/knowledgeBases', $query, false, 'POST'), 'name');
            $name = substr($name, strripos($name, '/') + 1);
            $knowledge_base_name[$language ? $language : 'default'] = $name;
            sb_save_external_setting('dialogflow-knowledge', $knowledge_base_name);
            $knowledge_base_name = $name;
        } else {
            $knowledge_base_name = $knowledge_base_name['default'];
        }

        // Save knowledge in Dialogflow
        $documents = sb_isset(sb_dialogflow_curl('/knowledgeBases/' . $knowledge_base_name . '/documents', '', false, 'GET'), 'documents', []);
        for ($i = 0; $i < count($documents); $i++) {
            $name = $documents[0]['name'];
            $response = sb_dialogflow_curl(substr($name, stripos($name, 'knowledgeBases/') - 1), '', false, 'DELETE');
        }
        $response = sb_dialogflow_curl('/knowledgeBases/' . $knowledge_base_name . '/documents', ['displayName' => 'Support Board', 'mimeType' => 'text/csv', 'knowledgeTypes' => ['FAQ'], 'rawContent' => base64_encode($file_bytes)], false, 'POST');
        if ($response && isset($response['error']) && sb_isset($response['error'], 'status') == 'NOT_FOUND') {
            sb_save_external_setting('dialogflow-knowledge', false);
            return false;
        }
    }
    return true;
}

function sb_generate_sitemap($url) {
    require_once('sitemap-generator.php');
    set_time_limit(900);
    $path = sb_upload_path() . '/sitemap.xml';
    $smg = new SitemapGenerator([
        'SITE_URL' => $url,
        'ALLOW_EXTERNAL_LINKS' => false,
        'ALLOW_ELEMENT_LINKS' => false,
        'CRAWL_ANCHORS_WITH_ID' => '',
        'KEYWORDS_TO_SKIP' => [],
        'SAVE_LOC' => $path,
        'PRIORITY' => 1,
        'CHANGE_FREQUENCY' => 'daily',
        'LAST_UPDATED' => date('Y-m-d'),
    ]);
    $smg->GenerateSitemap();
    $urls = sb_get_sitemap_urls(sb_upload_path(true) . '/sitemap.xml');
    unlink($path);
    return $urls;
}

/*
 * -----------------------------------------------------------
 * OPEN AI
 * -----------------------------------------------------------
 *
 * 1. OpenAI curl
 * 2. Send a message and returns the OpenAI reply
 * 3. Generate Dialogflow user expressions
 * 4. Generate user questions
 * 5. Generate the smart replies
 * 6. Spelling correction
 * 7. Remove auto generated AI texts
 * 8. Check if the message returned by OpenAI is valid
 * 9. Upload a file to OpenAI
 * 10. Embedding functions
 * 11. PDF or TEXT file to paragraphs
 * 12. Get the default gpt model
 * 13. Send an audio file to OpenAI and return it's transcription
 * 14. Return the OpenAI key
 * 15. OpenAI Assistant
 * 16. AI data scraper
 * 17. Troubleshoting
 * 18. HTML to paragraphs
 * 23. Server-side training
 * 20. Get training file names
 * 21. Playground message
 * 22. Create a temporary conversation to test the chatbot
 * 23. Check if an URL is a of a file compatible with the OpenAI training
 * 24. Execute set data
 * 25. Execute actions
 * 26. Get max tokens
 * 27. Send fallback message
 *
 */

function sb_open_ai_curl($url_part, $post_fields = [], $type = 'POST') {
    if (sb_cloud_membership_has_credits('open-ai')) {
        $open_ai_key = sb_open_ai_key();
        $response = sb_curl('https://api.openai.com/v1/' . $url_part, json_encode($post_fields, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), ['Content-Type: application/json', 'Authorization: Bearer ' . $open_ai_key], $type, 30);
        if (sb_is_cloud() && sb_defined('OPEN_AI_KEY') == $open_ai_key) {
            $tokens = sb_isset(sb_isset($response, 'usage'), 'total_tokens');
            $model = sb_isset($post_fields, 'model', 'gpt-4o');
            if (!$tokens) {
                $tokens = sb_open_ai_get_max_tokens($model);
            }
            sb_cloud_membership_use_credits($model, 'open-ai', $tokens);
        }
        return $response;
    }
    return sb_error('no-credits', 'sb_open_ai_message');
}

function sb_open_ai_message($message, $max_tokens = false, $model = false, $conversation_id = false, $extra = false, $audio = false, $attachments = []) {
    global $SB_OPEN_AI_PLAYGROUND;
    $language = strtolower(sb_isset($extra, 'language'));
    $attachments_response = [];
    if ($audio) {
        $message = sb_open_ai_audio_to_text($audio, $language, sb_isset($extra, 'user_id'), false, $conversation_id);
    }
    $attachments = sb_json_array($attachments);
    for ($i = 0; $i < count($attachments); $i++) {
        if (strpos($attachments[$i][0], 'voice_message') === false) {
            $message .= ' ' . $attachments[$i][1];
        }
    }
    if (empty($message)) {
        return [true, false, false, false];
    }
    if (sb_is_cloud()) {
        sb_cloud_membership_validation(true);
        if (!sb_cloud_membership_has_credits('open-ai')) {
            return sb_error('no-credits', 'sb_open_ai_message');
        }
    }
    $settings = sb_get_setting('open-ai');
    $response = false;
    $conversation_status_code = false;
    $dialogflow_active = sb_chatbot_active(true, false);
    $token = sb_isset($extra, 'token');
    $human_takeover = false;
    $human_takeover_settings = sb_dialogflow_get_human_takeover_settings();
    $human_takeover_active = $human_takeover_settings['active'];
    $payload = [];
    $is_scraping = $extra == 'scraping';
    $is_embeddings = sb_isset($extra, 'embeddings') || $extra == 'embeddings-search';
    $is_rewrite = $extra == 'rewrite';
    $is_smart_reply = sb_isset($extra, 'smart_reply');
    $unknow_answer = false;
    $open_ai_mode = sb_isset($settings, 'open-ai-mode', '');
    $extra_response = false;
    $client_side_payload = [];
    $messages = $conversation_id ? sb_db_get('SELECT A.id, A.message, A.payload, A.user_id, A.creation_time, B.user_type FROM sb_messages A, sb_users B, sb_conversations C WHERE A.conversation_id = ' . sb_db_escape($conversation_id, true) . ' AND A.conversation_id = C.id AND B.id = A.user_id ORDER BY A.id ASC LIMIT 1000', false) : [['message' => $is_embeddings ? $message['user_prompt'] : $message, 'user_type' => 'user']];
    $count = count($messages);
    $flows_structured_output = false;
    $is_chips_response = false;
    $user_id = sb_isset($extra, 'user_id', sb_get_active_user_ID());
    $is_embedding_response = false;
    $is_human_takeover = false;
    $model = $model ? $model : sb_isset($settings, 'open-ai-custom-model', sb_isset($settings, 'open-ai-model', 'gpt-4o-mini'));
    $chat_model = $model != 'gpt-3.5-turbo-instruct';
    $query = ['model' => $model, 'temperature' => floatval(sb_isset($settings, 'open-ai-temperature', 1)), 'presence_penalty' => floatval(sb_isset($settings, 'open-ai-presence-penalty', 0)), 'frequency_penalty' => floatval(sb_isset($settings, 'open-ai-frequency-penalty', 0)), 'top_p' => 1, 'tools' => []];
    if ($token == 'false') {
        $token = false;
    }
    if (!$dialogflow_active) {
        $is_human_takeover = !$is_rewrite && !$is_scraping && !$is_smart_reply && $conversation_id && sb_dialogflow_is_human_takeover($conversation_id);
        if ($human_takeover_active && sb_isset($human_takeover_settings, 'disable-chatbot')) {
            return false;
        }
        if ($is_human_takeover && $count) {
            $time = sb_gmt_now(600, true);
            for ($i = $count - 1; $i > -1; $i--) {
                if (sb_is_agent($messages[$i]['user_type'], true)) {
                    if (sb_is_user_online($messages[$i]['user_id'])) {
                        $message_fallback = $human_takeover_settings['message_fallback'];
                        if ($message_fallback) {
                            for ($j = $count - 1; $j > -1; $j--) {
                                if (strpos($messages[$j]['payload'], 'human-takeover-message-fallback')) {
                                    if (strtotime($messages[$j]['creation_time']) > $time) {
                                        $message_fallback = false;
                                    }
                                    break;
                                }
                            }
                            if ($message_fallback) {
                                sb_send_message(sb_get_bot_id(), $conversation_id, $message_fallback, $attachments_response, false, ['human-takeover-message-fallback' => true]);
                            }
                        }
                        return [true, false];
                    }
                }
            }
        }

        // Human takeover messaging apps
        if ($extra == 'messaging-app' && $human_takeover_active && !$is_smart_reply) {
            $button_confirm = sb_rich_value($human_takeover_settings['confirm'], false) == $message;
            if ($button_confirm || sb_rich_value($human_takeover_settings['cancel'], false) == $message) {
                $last_messages = sb_db_get('SELECT message, payload FROM sb_messages WHERE conversation_id = ' . sb_db_escape($conversation_id, true) . ' ORDER BY id DESC LIMIT 2', false);
                if ($last_messages && count($last_messages) > 1 && strpos($last_messages[1]['message'] . $last_messages[1]['payload'], 'sb-human-takeover')) {
                    return [true, $button_confirm ? $is_human_takeover : false, false, $button_confirm];
                }
            }
        }

        // Multilingual
        if (!$is_embeddings && !$is_rewrite && !$is_scraping) {
            $multilingual_translation = sb_get_setting('dialogflow-multilingual-translation') || sb_get_multi_setting('google', 'google-multilingual-translation'); // Depreacted: sb_get_setting('dialogflow-multilingual-translation')
            if (sb_get_setting('dialogflow-multilingual') || sb_get_multi_setting('google', 'google-multilingual') || sb_get_setting('google-translation') || sb_get_multi_setting('google', 'google-translation') || $multilingual_translation || !empty($settings['open-ai-multlilingual-sources'])) { // Depreacted: sb_get_setting('dialogflow-multilingual') + sb_get_setting('google-translation')
                if (!$language) {
                    if ((sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-active') || sb_get_multi_setting('google', 'google-language-detection')) && strlen($message) > 2) { // Deprecated: sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-active')
                        $language = sb_get_user_extra($user_id, 'language');
                        if (!$language) {
                            $language = sb_google_language_detection($message, $token);
                            if ($language) {
                                sb_language_detection_db($user_id, $language);
                                $payload['event'] = 'update-user';
                            }
                        }
                    } else {
                        $language = sb_get_user_language($user_id);
                    }
                }
            }
        }
    }

    // Assistant
    if ($open_ai_mode == 'assistant') {
        if ($conversation_id) {
            $response = sb_open_ai_assistant($message, $conversation_id, !$is_smart_reply && !$is_rewrite && !$is_scraping);
            if (sb_is_error($response)) {
                $response = '';
            }
        } else {
            $open_ai_mode = '';
        }
    } else {

        // Flows structured output check and chips response
        if (!$is_smart_reply && !$is_rewrite && !$is_embeddings && !$is_scraping) {
            if (($count == 1 || ($count == 2 && $messages[0]['user_type'] == 'bot')) && $conversation_id) {
                $flow_start = sb_flows_on_conversation_start_or_load($messages, $language, $conversation_id);
                if ($flow_start) {
                    return [true, [['message' => $flow_start]], false, false];
                }
            }
            for ($i = $count - 1; $i > -1; $i--) {
                $is_break = false;
                $payload_temp = sb_isset($messages[$i], 'payload');
                if (strpos($payload_temp, 'flow_end_so') !== false) {
                    $flows_structured_output = false;
                    $is_break = true;
                }
                if (strpos($payload_temp, 'flow_so') !== false) {
                    $flows_structured_output = json_decode($payload_temp, true);
                    $is_break = true;
                }
                if ($is_break) {
                    break;
                }
            }
            for ($i = $count - 2; $i > -1; $i--) {
                $message_text = $messages[$i]['message'];
                if ($message_text) {
                    if (strpos($message_text, '[chips ') !== false) {
                        $message_text = sb_isset(sb_get_shortcode($message_text, false), 0);
                        $message_id = sb_isset($message_text, 'id');
                        if (strpos($message_id, 'flow_') === 0) {
                            $message_options = explode(',', sb_isset($message_text, 'options'));
                            $flow_identifier = explode('_', substr($message_id, 5));
                            for ($j = 0; $j < count($message_options); $j++) {
                                if ($message_options[$j] == $message) {
                                    $next_response = sb_flows_get_open_ai_message_response($flow_identifier[0], $flow_identifier[1], $flow_identifier[2], $j, $payload);
                                    if ($next_response[0] !== false) {
                                        $response = $next_response[0];
                                    }
                                    $payload = array_merge($payload, $next_response[1]);
                                    if ($response) {
                                        $response = ['choices' => [['message' => ['content' => trim($response)]]]];
                                        $is_chips_response = true;
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $payload_temp = sb_isset($messages[$i], 'payload');
            if (strpos($payload_temp, 'action') !== false) {
                $payload_temp = json_decode($payload_temp, true);
                $messages[$i]['message'] .= ' ' . $payload_temp['action'];
            }
        }
    }

    // Embeddings
    if (!$is_embeddings && !$flows_structured_output && !$is_rewrite && !$is_scraping && !$response && !$is_chips_response && in_array($open_ai_mode, ['sources', 'all', ''])) { // Deprecated. Remove All
        $extra_embeddings = ['conversation_id' => $conversation_id, 'user_id' => $user_id];
        if ($is_smart_reply) {
            $extra_embeddings['smart_reply'] = true;
        }
        if (!$dialogflow_active && $multilingual_translation) {
            $embeddings_language = sb_open_ai_embeddings_language();
            if (!empty($embeddings_language) && !in_array($language, $embeddings_language)) {
                $translation = sb_google_translate([$message], $embeddings_language[0], $token);
                if (!empty($translation[0])) {
                    $message = $translation[0][0];
                }
                $response = sb_open_ai_embeddings_message($message, false, $extra_embeddings);
                if ($response) {
                    $translation = sb_google_translate([$response['message']], $language, $token);
                    if (!empty($translation[0])) {
                        $response['message'] = $translation[0][0];
                    }
                }
            } else {
                $response = sb_open_ai_embeddings_message($message, empty($settings['open-ai-multlilingual-sources']) ? false : $language, $extra_embeddings);
            }
        } else {
            $response = sb_open_ai_embeddings_message($message, empty($settings['open-ai-multlilingual-sources']) ? false : $language, $extra_embeddings);
        }
        if ($response) {
            $client_side_payload = $response['payload'];
            $payload = array_merge($payload, sb_isset($response, 'payload_message', []));
            $attachments_response = $response['attachments'];
            $embedding_extra = $response['embedding_extra'];
            $embedding_extra_json = json_encode($embedding_extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            $response = $response['message'];
            $entities = ['language' => 'language name (e.g. spanish or italian)'];
            foreach ($entities as $entity => $entity_description) {
                $entity_ = '{' . $entity . '}';
                if (strpos($response, $entity_) || strpos($embedding_extra_json, $entity_)) {
                    $properties = [];
                    $query_ = $query;
                    unset($query_['tools']);
                    $properties[$entity] = ['type' => 'string', 'description' => $entity_description];
                    $query_['messages'] = [['role' => 'user', 'content' => $message]];
                    $query_['response_format'] = ['type' => 'json_schema', 'json_schema' => ['name' => $entity, 'schema' => ['type' => 'object', 'properties' => $properties, 'required' => [$entity], 'additionalProperties' => false], 'strict' => true]];
                    $response_json = sb_open_ai_curl($chat_model ? 'chat/completions' : 'completions', $query_);
                    if ($response_json && !empty($response_json['choices'])) {
                        $response_json = sb_isset(json_decode(sb_isset($response_json['choices'][0]['message'], 'content', '[]'), true), $entity);
                        if ($response_json) {
                            if ($entity == 'language') {
                                $response_json = sb_get_language_code_by_name($response_json);
                                if (strlen($response_json) == 2 && !sb_is_agent()) {
                                    $payload['event'] = 'update-user';
                                    sb_update_user($user_id, [], ['language' => [$response_json, 'Language']]);
                                }
                            }
                            $response = str_replace($entity_, $response_json, $response);
                            $embedding_extra_json = str_replace($entity_, $response_json, $embedding_extra_json);
                            if (sb_get_multi_setting('google', 'google-multilingual-translation')) {
                                $translation = sb_google_translate([$response], $response_json);
                                if (!empty($translation[0])) {
                                    $response = $translation[0][0];
                                }
                            }
                        }
                    }
                }
            }
            if ($embedding_extra && !sb_is_agent()) {
                $embedding_extra_set_data = sb_isset(json_decode($embedding_extra_json, true), 'set_data');
                if ($embedding_extra_set_data) {
                    sb_open_ai_execute_set_data($embedding_extra_set_data, $conversation_id);
                    $embedding_extra_set_data_ = [];
                    foreach ($embedding_extra_set_data as $key => $value) {
                        array_push($embedding_extra_set_data_, [$key, $value]);
                    }
                    if (isset($embedding_extra_set_data['archive_conversation']) || isset($embedding_extra_set_data['human_takeover'])) {
                        $conversation_status_code = 'skip';
                    }
                    $execute_actions = sb_open_ai_execute_actions($embedding_extra_set_data_, $conversation_id);
                    $client_side_payload = array_merge($client_side_payload, $execute_actions['client_side_payload']);
                    $attachments_response = array_merge($attachments_response, $execute_actions['attachments']);
                }
            }
        }
        $is_embedding_response = true;
    }
    if ($is_chips_response || $flows_structured_output || (!$response && (in_array($open_ai_mode, ['general', 'all', '']) || $is_embeddings || $is_rewrite || $is_scraping))) { // Deprecated. Remove All
        $max_tokens = intval($max_tokens ? $max_tokens : sb_isset($settings, 'open-ai-tokens', 150));
        $is_translations = sb_get_multi_setting('google', 'google-translation');
        $prompt_real_time = $is_embeddings && $extra != 'embeddings-search' && sb_get_setting('dialogflow-google-search', 'dialogflow-google-search-active') ? ' If the answer is about real-time information, recent events, or current information, write exactly "I don\'t know."' : '';
        $prompt_language = sb_get_user_language($is_smart_reply ? sb_get_active_user_ID() : $user_id);
        $prompt_language = $prompt_language && $prompt_language != 'en' ? ' If the answer is included, always answer to the user message in "' . $prompt_language . '" language.' : '';
        $prompt = $is_scraping ? $message : sb_isset($settings, 'open-ai-prompt', $is_embeddings ? 'Provide extensive answers to the given user question from the context below. If the answer is not included, write exactly "I don\'t know." in English language and stop after that. If you don\'t understand the request, write exactly "I don\'t know." Refuse to answer any question not about the info. Never break character.' . $prompt_language . $prompt_real_time : $prompt_language);
        $first_message = false;
        $open_ai_length = 0;
        $open_ai_max_tokens = sb_open_ai_get_max_tokens($model);
        if ($is_translations) {
            for ($i = 0; $i < $count; $i++) {
                $messages[$i] = sb_google_get_message_translation($messages[$i]);
            }
        }
        if (!empty($settings['open-ai-logit-bias'])) {
            $query['logit_bias'] = json_decode($settings['open-ai-logit-bias'], true);
        }
        $query_messages = $chat_model ? [] : '';
        if ($max_tokens && ($max_tokens != 150 || !$chat_model)) {
            $query['max_tokens'] = $max_tokens;
        }
        if ($prompt) {
            $message_context = is_string($message) ? $message : $message['context'];
            if (strlen($message_context) > 9999) {
                $message_context = substr($message_context, 0, 9999);
            }
            if (sb_is_rich_message($message_context) || strpos($message_context, '[action ')) {
                $prompt .= ' If your answer includes text in square brackets, provide all strings in square brackets as they are and stop generating additional text.';
            }
            if ($chat_model) {
                $first_message = ['role' => 'system', 'content' => $prompt . ($is_embeddings ? PHP_EOL . PHP_EOL . 'Context: """' . $message_context . '"""' : '')];
            } else {
                $first_message = 'prompt: """' . str_replace(['"', PHP_EOL], ['\'', ' '], $prompt) . '"""' . ($is_embeddings ? PHP_EOL . PHP_EOL . 'Context: """' . $message_context . '"""' : '') . PHP_EOL . PHP_EOL;
            }
            $open_ai_length += strlen($message_context);
        }
        for ($i = $count - 1; $i > -1; $i--) {
            $message_text = $messages[$i]['message'];
            if (intval(($open_ai_length + strlen($message_text)) / 4) < $open_ai_max_tokens) {
                if (sb_open_ai_is_valid($message_text) || $is_scraping) {
                    $message_is_agent = sb_is_agent($messages[$i]['user_type']);
                    if (!$is_scraping || !$message_is_agent) {
                        if ($chat_model) {
                            array_unshift($query_messages, ['role' => $message_is_agent ? 'assistant' : 'user', 'content' => $message_text]);
                        } else {
                            $query_messages = ($message_is_agent ? 'AI: ' : 'Human: ') . $message_text . PHP_EOL . $query_messages;
                        }
                        $open_ai_length += strlen($message_text);
                    }
                }
            } else {
                break;
            }
        }
        if (empty($query_messages)) {
            return [false, false];
        }
        if ($chat_model) {
            if ($first_message) {
                array_unshift($query_messages, $first_message);
            }

            // Structured output
            if ($flows_structured_output) {
                $flows_structured_output_string = $flows_structured_output['flow_so'];
                $block = sb_flows_get_by_string($flows_structured_output_string);
                if ($block) {
                    $descriptions = ['full_name' => 'The person name e.g. Olivia Smith', 'email' => 'The email address e.g. olivia.smith@gmail.com', 'password' => 'An string used as a password', 'address' => 'An full address e.g. 90 Fetter Ln, London EC4A 1EN or 125 W 24th St, New York, NY 10011, USA', 'country' => 'A country name or code e.g. US or United Kingdom', 'state' => 'The state name e.g. New York', 'phone' => 'The phone number e.g. +393203057977', 'language' => 'The language name or language code e.g. Spanish or ES', 'company' => 'The business or company name e.g. Nike', 'webiste' => 'The website URL e.g. www.google.com', 'city' => 'The city e.g. San Francisco', 'postal_code' => 'The postal code e.g. 10001 or SW1A 1AA', 'birthdate' => 'The birthdate e.g. 25 July 1990 or 31/05/89'];
                    $properties = [];
                    $required = [];
                    for ($i = 0; $i < count($block['details']); $i++) {
                        $details = $block['details'][$i];
                        $properties[$details[0]] = ['type' => ['string', 'null'], 'description' => sb_isset($details, 1, sb_isset($descriptions, $details[0], sb_string_slug($details[0], 'string')))];
                        array_push($required, $details[0]);
                    }
                    $query['response_format'] = ['type' => 'json_schema', 'json_schema' => ['name' => 'flow-' . sb_string_slug($flows_structured_output_string), 'schema' => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false], 'strict' => true]];
                    if (count($query_messages) > 1) {
                        array_shift($query_messages);
                    }
                }
            }

            // Function calling
            if (!$is_smart_reply && !$is_rewrite && !$is_scraping && $human_takeover_active && in_array($model, ['gpt-4o-mini', 'gpt-4o', 'gpt-4', 'gpt-4-32k'])) {
                $query['tools'] = [['type' => 'function', 'function' => ['name' => 'sb-human-takeover', 'description' => 'I want to contact a human support agent or team member. I want human support.', 'parameters' => ['type' => 'object', 'properties' => json_decode('{}'), 'required' => []]]]];
            }
            if (!$flows_structured_output && !$is_smart_reply && !$is_rewrite && !$is_scraping) {
                $qea = sb_get_external_setting('embedding-texts', []);
                for ($i = 0; $i < count($qea); $i++) {
                    if (!empty($qea[$i][2])) {
                        $properties = [];
                        $properties_required = [];
                        foreach ($qea[$i][5] as $value) {
                            $property_slug = sb_string_slug($value[0]);
                            $properties[$property_slug] = ['type' => 'string', 'description' => $value[1]];
                            if ($value[2]) {
                                $properties[$property_slug]['enum'] = explode(',', $value[2]);
                            }
                            array_push($properties_required, $property_slug);
                        }
                        array_push($query['tools'], ['type' => 'function', 'function' => [
                            'name' => substr(sb_string_slug(is_string($qea[$i][0]) ? $qea[$i][0] : $qea[$i][0][0]), 0, 20) . '-' . $i, // Depreacated Replace is_string($qea[$i][0]) ? $qea[$i][0] : $qea[$i][0][0] with $qea[$i][0][0]
                            'description' => is_string($qea[$i][0]) ? $qea[$i][0] : $qea[$i][0][0], // Depreacated Replace is_string($qea[$i][0]) ? $qea[$i][0] : $qea[$i][0][0] with $qea[$i][0][0]
                            'strict' => true,
                            'parameters' => [
                                'type' => 'object',
                                'properties' => $properties,
                                'required' => $properties_required,
                                'additionalProperties' => false
                            ]
                        ]]);
                    }
                }
            }
            $query['messages'] = $query_messages;
        } else {
            $query['prompt'] = ($first_message ? $first_message : '') . $query_messages . 'AI: ' . PHP_EOL;
            $query['stop'] = ['Human:', 'AI:'];
        }
        if (isset($extra['query'])) {
            $query = array_merge($query, $extra['query']);
        }
        if (empty($query['tools'])) {
            unset($query['tools']);
        }

        // OpenAI response
        if (!$is_chips_response) {
            $response = sb_open_ai_curl($chat_model ? 'chat/completions' : 'completions', $query);
        }
        if ($SB_OPEN_AI_PLAYGROUND !== null) {
            $SB_OPEN_AI_PLAYGROUND['usage'] = sb_isset($response, 'usage');
            $SB_OPEN_AI_PLAYGROUND['query'] = $query;
            $SB_OPEN_AI_PLAYGROUND['embeddings'] = sb_isset($GLOBALS, 'SB_OPEN_AI_PLAYGROUND_E');
        }
        if ($response && isset($response['choices']) && count($response['choices'])) {
            if (isset($query['n'])) {
                return $response['choices'];
            }
            $function_calling = sb_open_ai_function_calling($response);
            $response_message = $response['choices'][0]['message'];
            $response_message_content = $response_message['content'];
            if ($function_calling) {

                // Function calling response
                if ($function_calling[0] == 'sb-human-takeover') {
                    $response = 'sb-human-takeover';
                } else if ($chat_model) {
                    unset($query['tools']);
                    if ($is_embeddings) {
                        array_shift($query_messages);
                    }
                    $tool_calls = $response_message['tool_calls'][0];
                    array_push($query_messages, $response_message, ['role' => 'tool', 'content' => json_encode($function_calling[2], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), 'tool_call_id' => $tool_calls['id']]);
                    $query['messages'] = $query_messages;
                    $response = sb_open_ai_curl('chat/completions', $query);
                    if (isset($response['error'])) {
                        return [false, $response];
                    }
                    if (!empty($response['choices'])) {
                        $response_message_content = sb_open_ai_text_formatting(sb_isset($response['choices'][0], 'message', [])['content']);
                    }
                    $function_calling = false;
                }
            } else if ($flows_structured_output) {

                // Flows structured output response
                $user_data = json_decode($response_message_content, true);
                $block = sb_flows_get_by_string($flows_structured_output_string);
                $required_missing = '';
                foreach ($block['details'] as $detail) {
                    if (empty($user_data[$detail[0]]) || $user_data[$detail[0]] == 'unknown') {
                        if ($detail[2]) {
                            $required_missing .= sb_string_slug($detail[0], 'string') . ', ';
                        } else {
                            $user_data[$detail[0]] = sb_is_agent() ? '' : ($detail[0] == 'email' ? sb_get_active_user()['email'] : sb_get_user_extra($user_id, $detail[0]));
                        }
                    }
                }
                if (empty($required_missing)) {
                    if (!sb_is_agent()) {
                        $full_name = sb_isset($user_data, 'full_name');
                        if ($full_name) {
                            $full_name = sb_split_name($full_name);
                            $user_data['first_name'] = $full_name[0];
                            $user_data['last_name'] = $full_name[1];
                            unset($user_data['full_name']);
                        }
                        sb_update_user(sb_get_active_user_ID(), $user_data, $user_data);
                    }
                    $payload['flow_end_so'] = true;
                    if ($SB_OPEN_AI_PLAYGROUND !== null) {
                        $SB_OPEN_AI_PLAYGROUND['payload'] = ['flow_end_so' => true];
                    }
                    $next_response = sb_flows_get_open_ai_message_response($block['index'][0], $block['index'][1], $block['index'][2], $block['index'][3], $payload);
                    if ($next_response[0] !== false) {
                        $response_message_content = $next_response[0];
                    }
                    $payload = array_merge($payload, $next_response[1]);
                } else {
                    $required_missing = 'Rewrite the following text' . ($language ? ' and use ' . $language . ' as language' : '') . ': "What is your ' . substr($required_missing, 0, -2) . '"?';
                    $response_message_content = sb_isset(sb_open_ai_message($required_missing, false, false, false, 'rewrite'), 1, $required_missing);
                }
            }
            if ($chat_model && strpos($response_message_content, '[action ') !== false) {

                // Action response
                $action = sb_flows_execute($response_message_content, $messages, $language, $conversation_id);
                $response = $action[0];
                $client_side_payload = array_merge($client_side_payload, $action[1]);
                $attachments_response = array_merge($attachments_response, $action[2]);
                $payload = array_merge($payload, ['action' => $action[3]['shortcode']]);
            } else {

                // Normal response
                $response = sb_open_ai_text_formatting($chat_model ? $response_message_content : $response['choices'][0]['text']);
            }
        } else {
            if (isset($response['error'])) {
                return [false, $response];
            } else {
                $response = false;
            }
        }
    }
    $unknow_answer = !sb_open_ai_is_valid($response);

    // Human takeover
    if (!$is_rewrite && !$is_scraping) {
        if ($is_smart_reply) {
            return $response && !$unknow_answer ? [true, $response, $token, $extra_response, $SB_OPEN_AI_PLAYGROUND, $client_side_payload, $attachments_response, $payload] : [false, false];
        }
        if (!$is_embeddings && $unknow_answer && strlen($message) > 4) {
            $google_search_settings = sb_get_setting('dialogflow-google-search');
            if (sb_isset($google_search_settings, 'dialogflow-google-search-active')) {
                $google_search_response = sb_isset(sb_get('https://www.googleapis.com/customsearch/v1?key=' . $google_search_settings['dialogflow-google-search-key'] . '&cx=' . $google_search_settings['dialogflow-google-search-id'] . '&q=' . urlencode($message), true), 'items');
                if (!empty($google_search_response)) {
                    $limiter = 0;
                    $google_search_page_response_text = '';
                    for ($i = 0; $i < count($google_search_response); $i++) {
                        $google_search_page_response = sb_open_ai_html_to_paragraphs(sb_isset($google_search_response[$i], 'link'));
                        if ($google_search_page_response[1] == 200) {
                            $google_search_page_response_text .= ($limiter ? PHP_EOL . '--------------------------------------------------------------------------------' . PHP_EOL : '') . implode(PHP_EOL . ' ' . PHP_EOL, array_column($google_search_page_response[0], 0));
                            $limiter++;
                            if ($limiter > 4) {
                                break;
                            }
                        }
                    }
                    $google_search_response = sb_open_ai_message(['context' => trim($google_search_page_response_text), 'user_prompt' => $message], false, false, false, 'embeddings-search');
                    if ($google_search_response[0] && !empty($google_search_response[1]) && empty($google_search_response[5]['unknow_answer'])) {
                        $response = $google_search_response[1];
                        $unknow_answer = false;
                    }
                }
            }
        }
        $human_request = $response == 'sb-human-takeover';
        $human_takeover = !$is_embeddings && !$dialogflow_active && $human_takeover_active && ($human_request || ($unknow_answer && strlen($message) > 3 && strpos($message, ' ')));
        if ($human_takeover && $conversation_id) {
            if (!$is_human_takeover) {
                $human_takeover = sb_chatbot_human_takeover($conversation_id, $human_takeover_settings);
                return [true, $human_takeover[0], $token, $human_takeover[1]];
            }
            return [true, '', $token];
        } else if ($human_request && $is_human_takeover) {
            $response = $human_takeover_settings['message_fallback'];
            $payload['human-takeover-message-fallback'] = true;
            if ($is_embeddings) {
                $extra_response = 'skip-references';
            }
        } else if (!$response) {
            $response = $dialogflow_active || $is_embeddings ? false : sb_t(sb_isset($settings, 'open-ai-fallback-message', 'Sorry, I didn\'t get that. Can you rephrase?'), $language);
        } else if (!$dialogflow_active && $is_human_takeover && $unknow_answer) {
            $response = false;
        }
    }

    // Response
    if ($response) {
        if ($conversation_id && !$is_embeddings && !$is_rewrite && !$is_scraping && !empty($response) && !$dialogflow_active) {
            sb_send_message(sb_get_bot_id(), $conversation_id, $response, $attachments_response, $conversation_status_code, $payload);
            sb_webhooks('SBOpenAIMessage', ['response' => $response, 'message' => $message, 'conversation_id' => $conversation_id]);
        }
        if ($unknow_answer) {
            $client_side_payload['unknow_answer'] = true;
        }
        return [true, $response, $token, $extra_response, $SB_OPEN_AI_PLAYGROUND, $client_side_payload, $attachments_response, $payload];
    }
    return [$is_embedding_response, $response];
}

function sb_open_ai_user_expressions($message) {
    $settings = sb_get_setting('open-ai');
    $response = sb_open_ai_curl('chat/completions', ['messages' => [['role' => 'user', 'content' => 'Create a numbered list of minimum 5 variants of this sentence and only return the list. Change all the words with another word: """' . $message . '""""']], 'model' => sb_open_ai_get_gpt_model(), 'max_tokens' => 200, 'temperature' => floatval(sb_isset($settings, 'open-ai-temperature', 1)), 'presence_penalty' => floatval(sb_isset($settings, 'open-ai-presence-penalty', 0)), 'frequency_penalty' => floatval(sb_isset($settings, 'open-ai-frequency-penalty', 0))]);
    $error = sb_isset($response, 'error');
    $choices = sb_isset($response, 'choices');
    if ($choices) {
        $choices = explode("\n", trim($choices[0]['message']['content']));
        for ($i = 0; $i < count($choices); $i++) {
            $expression = trim($choices[$i]);
            if (in_array(substr($expression, 0, 2), [($i + 1) . '.', ($i + 1) . ')'])) {
                $expression = trim(substr($expression, 2));
            }
            if (substr($expression, 0, 1) === '.') {
                $expression = trim(substr($expression, 1));
            }
            $choices[$i] = $expression;
        }
        return $choices;
    } else if ($error) {
        return sb_error($error['type'], 'sb_open_ai_user_expressions', $error['message']);
    }
    return $response;
}

function sb_open_ai_user_expressions_intents() {
    $intents = sb_dialogflow_get_intents();
    $response = 0;
    $history = sb_get_external_setting('open-ai-intents-history', []);
    for ($i = 0; $i < count($intents); $i++) {
        $intent_name = substr($intents[$i]['name'], strripos($intents[$i]['name'], '/') + 1);
        if (in_array(sb_isset($intents[$i], 'action'), ['input.unknown', 'input.welcome']) || in_array($intent_name, $history)) {
            continue;
        }
        $messages = [];
        $training_phrases = $intents[$i]['trainingPhrases'];
        for ($j = 0; $j < count($training_phrases); $j++) {
            $parts = $training_phrases[$j]['parts'];
            $message = '';
            for ($y = 0; $y < count($parts); $y++) {
                $message .= $parts[$y]['text'];
            }
            array_push($messages, strtolower($message));
        }
        $count = count($messages) > 5 ? 5 : count($messages);
        $user_expressions_final = [];
        for ($j = 0; $j < $count; $j++) {
            if (strlen($messages[$j]) > 5) {
                $user_expressions = sb_open_ai_user_expressions($messages[$j]);
                for ($y = 0; $y < count($user_expressions); $y++) {
                    $expression = $user_expressions[$y];
                    if (!in_array(strtolower($expression), $messages) && strlen($expression) > 4)
                        array_push($user_expressions_final, $expression);
                }
            }
        }
        if (count($user_expressions_final)) {
            if (sb_dialogflow_update_intent($intents[$i], $user_expressions_final) === true) {
                array_push($history, $intent_name);
                sb_save_external_setting('open-ai-intents-history', $history);
            } else
                $response++;
        }
    }
    return $response === 0 ? true : $response;
}

function sb_open_ai_smart_reply($message, $conversation_id) {
    $response = sb_open_ai_message($message, false, sb_open_ai_get_gpt_model(), $conversation_id, ['smart_reply' => true]);
    $suggestions = [];
    if (sb_is_error($response) || isset($response[1]) && sb_isset($response[1], 'error')) {
        return sb_error('openai-error', 'sb_open_ai_smart_reply', $response, true);
    }
    for ($i = 0; $i < count($response); $i++) {
        if ($response[$i] && !is_bool($response[$i])) {
            $suggestion = is_string($response[$i]) ? $response[$i] : sb_isset(sb_isset($response[$i], 'message'), 'content');
            if (!in_array($suggestion, $suggestions) && strlen($suggestion) > 2) {
                array_push($suggestions, $suggestion);
            }
        }
    }
    return ['suggestions' => $suggestions];
}

function sb_open_ai_spelling_correction($message) {
    if (strlen($message) < 2) {
        return $message;
    }
    $message_original = $message;
    $skip = [];
    $text_formatting = [];
    $regexes = [['/`[\S\s]*?`/', 0], ['/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', PREG_PATTERN_ORDER]];
    $index = 0;
    for ($i = 0; $i < count($regexes); $i++) {
        preg_match_all($regexes[$i][0], $message, $skip_sub, $regexes[$i][1]);
        $skip_sub = $skip_sub[0];
        for ($j = 0; $j < count($skip_sub); $j++) {
            $message = str_replace($skip_sub[$j], '{{' . $index . '}}', $message);
            array_push($skip, $skip_sub[$j]);
            $index++;
        }
    }
    if ($message == '{{0}}') {
        return $message_original;
    }
    $regexes = ['/\*(.*?)\*/', '/__(.*?)__/', '/~(.*?)~/', '/```(.*?)```/', '/`(.*?)`/'];
    for ($i = 0; $i < count($regexes); $i++) {
        $values = [];
        if (preg_match_all($regexes[$i], $message, $values)) {
            for ($j = 0; $j < count($values[0]); $j++) {
                $message = str_replace($values[0][$j], $values[1][$j], $message);
                array_push($text_formatting, [$values[0][$j], $values[1][$j]]);
            }
        }
    }
    $shortcode = sb_get_shortcode($message);
    if (!empty($shortcode)) {
        $message = str_replace($shortcode[0]['shortcode'], 'shortcode', $message);
    }
    if ($message && $message != 'shortcode') {
        $model = sb_open_ai_get_gpt_model();
        $response = sb_open_ai_curl('chat/completions', ['model' => $model, 'messages' => [['role' => 'user', 'content' => 'Fix spelling mistakes of the following text, return only the corrected version, or the original if none, never add comments, and do not remove text markdown: "' . $message . '"']]]);
        $error = sb_isset($response, 'error');
        if ($response && isset($response['choices']) && count($response['choices'])) {
            $response = $response['choices'][0]['message']['content'];
            $response = sb_open_ai_is_valid($response) && strlen($response) > (strlen($message) * 0.5) ? $response : $message;
            if (count($skip) != substr_count($response, '{{')) {
                return $message_original;
            }
            for ($i = 0; $i < count($skip); $i++) {
                $response = str_replace('{{' . $i . '}}', $skip[$i], $response);
            }
            for ($i = 0; $i < count($text_formatting); $i++) {
                $response = str_replace($text_formatting[$i][1], $text_formatting[$i][0], $response);
            }
            $response = sb_open_ai_text_formatting($response);
            return empty($shortcode) ? $response : str_replace('shortcode', $shortcode[0]['shortcode'], $response);
        } else if ($error) {
            sb_error($error['type'], 'sb_open_ai_spelling_correction', $error['message'], true);
        }
    }
    return $message_original;
}

function sb_open_ai_text_formatting($message) {
    $message = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
        return $matches[2] . '#sb-' . str_replace(' ', '--', $matches[1]);
    }, str_replace(['**', '- *'], ['*', "\n*"], $message));
    $message = preg_replace('/mailto:([^#]+)#sb-.*?/', '', $message);
    $message = str_replace(['```html', '```php', '```css', '```js', '```javascript', '```c++', '```sql', '```plaintext', '```nginx', '```bash'], '```', $message);
    if (strpos($message, '- ') || (strpos($message, '1. ') !== false && strpos($message, '2. ') !== false) || (strpos($message, '### ') || strpos($message, '#### '))) {
        $rows = preg_split("/\r\n|\n|\r/", $message);
        $message = '';
        $is_list = false;
        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!empty(trim($row))) {
                for ($j = 1; $j < 30; $j++) {
                    $row = str_replace('     ' . $j . '. ', '  - ', $row);
                }
                $row = str_replace(['     - ', '    - ', '   - '], '  - ', $row);
                $is_line = strpos($row, '- ') === 0 || strpos($row, ' - ') === 0;
                $is_inner = strpos($row, '  - ') === 0;
                if ($is_line || $is_inner || (in_array(strpos($row, '. '), [1, 2]) && is_numeric(substr($row, 0, 1)))) {
                    $row = trim(str_replace([',', '"', ':', '[', ']'], ['\,', '\'', '\:', '', ''], substr($row, $is_inner ? 4 : 2)));
                    if ($row[0] == '.') {
                        $row = substr($row, 1);
                    }
                    if ($is_inner) {
                        $row = '- ' . $row;
                    }
                    if (!$is_list) {
                        $message .= '[list' . ($is_line ? '' : ' numeric="true"') . ' values="' . $row . PHP_EOL;
                        $is_list = true;
                    } else {
                        $message .= ',' . $row . PHP_EOL;
                    }
                    $row_next = isset($rows[$i + 1]) ? trim($rows[$i + 1]) : false;
                    if ((!$is_list || strpos($row, '*:')) && $row_next && $row_next[0] != '-' && !(is_numeric($row_next[0]) && $row_next[1] == '.')) {
                        $rows[$i + 1] = '  - ' . $row_next;
                    }
                } else {
                    if (strpos($row, '### ') === 0 || strpos($row, '#### ') === 0) {
                        $row = '*' . trim(str_replace('#', '', str_replace('*', '', $row))) . '*' . (empty($rows[$i + 1]) ? '' : PHP_EOL);
                    }
                    if ($is_list && strpos($row, '   ') !== 0) {
                        $message = $message . '"]' . (preg_match('/^\n/', $row) === 1 ? '' : PHP_EOL) . $row . PHP_EOL;
                        $is_list = false;
                    } else {
                        $message .= ($is_list ? str_replace([',', '"', ':'], ['\,', '\'', '\:'], $row) : $row) . (strpos($row, '```') !== false ? '' : PHP_EOL);
                    }
                }
            } else if (!$is_list) {
                $message .= PHP_EOL . PHP_EOL;
            }
        }
        if ($is_list) {
            $message = $message . '"]';
        }
    }
    $rows = preg_split("/\r\n|\n|\r/", $message);
    $message = '';
    $is_open = false;
    $is_open_2 = false;
    for ($i = 0; $i < count($rows); $i++) {
        if (strpos($rows[$i], '```') !== false && $i) {
            if (!$is_open) {
                if (!empty($rows[$i - 1])) {
                    $rows[$i - 1] .= PHP_EOL;
                } else if ($i > 1 && empty($rows[$i - 2])) {
                    $rows[$i - 2] = '{S}';
                }
            } else {
                if (empty($rows[$i + 1])) {
                    $rows[$i + 1] = '{S}';
                    if (isset($rows[$i + 2]) && empty($rows[$i + 2])) {
                        $rows[$i + 2] = '{S}';
                    }
                }
            }
            $is_open = !$is_open;
        } else {
            if (!$is_open_2 && sb_is_rich_message($rows[$i])) {
                if (!empty($rows[$i - 1])) {
                    $rows[$i - 1] .= PHP_EOL;
                } else if ($i > 1 && empty($rows[$i - 2])) {
                    $rows[$i - 2] = '{S}';
                }
                $is_open_2 = true;
            }
            if ($is_open_2 && strpos($rows[$i], ']')) {
                if (empty($rows[$i + 1])) {
                    $rows[$i + 1] = '{S}';
                    if (isset($rows[$i + 2]) && empty($rows[$i + 2])) {
                        $rows[$i + 2] = '{S}';
                    }
                }
                $is_open_2 = false;
            }
        }
    }
    for ($i = 0; $i < count($rows); $i++) {
        if (strpos($rows[$i], '{S}') === false) {
            $message .= $rows[$i] . PHP_EOL;
        }
    }
    $message = str_replace('```,', '```' . PHP_EOL . ',', $message);
    while (in_array(mb_substr($message, 0, 1), ["\n", "\r", '\\n', '\\', ',', ':', '?', '!', '"', '”', '\''])) {
        $message = mb_substr($message, 1);
    }
    while (in_array(mb_substr($message, -1), ["\n", "\r", '\\n', '\\', ',', ':', '”', '\''])) {
        $message = mb_substr($message, 0, -1);
    }
    while (strpos($message, "\n ")) {
        $message = str_replace("\n ", "\n", $message);
    }
    if (!strpos(mb_substr($message, 0, -5), '"')) {
        while (mb_substr($message, -1) == '"') {
            $message = mb_substr($message, 0, -1);
        }
    }
    if (mb_substr($message, 0, 2) == 'n ') {
        $message = mb_substr($message, 2);
    }
    if (preg_match('/(\|[^\r\n]+\|)(?:\r?\n\|[-:|]+)((?:\r?\n\|[^\r\n]+\|)+)/s', $message, $matches)) {
        $table = $matches[0];
        $lines = explode("\n", $table);
        $header = [];
        $values = [];
        foreach ($lines as $index => $line) {
            $columns = array_map('trim', explode('|', trim($line, '|')));
            if ($index == 0) {
                $header = $columns;
            } elseif ($index > 1 && !empty($columns)) {
                $values[] = implode(':', $columns);
            }
        }
        $header = implode(', ', $header);
        $values = implode(',', $values);
        $message = trim(str_replace($table, PHP_EOL . PHP_EOL . '[table header="' . $header . '" values="' . $values . '"]' . PHP_EOL .PHP_EOL, $message));
    }
    return trim(str_replace(['The fixed text is:', '(with correct punctuation)', 'Fix: ', 'Fixed: ', 'Corrected text:', 'A:', 'Answer: ', 'Question:', 'Fixed text:'], '', $message));
}

function sb_open_ai_is_valid($message) {
    return $message ? ($message == 'I don\'t know.' || $message == 'I don\'t know' || substr($message, -9) == 'don\'t know' || substr($message, -10) == 'don\'t know.' ? false : preg_match('/(Non lo so.|Bilmiyorum.|не знаю|Tôi không biết.|我不知道。|Jeg vet ikke.|Nie wiem.|Nu știu.|ne vem|nuk e di.|не знам.|Abdi henteu terang.|jag vet inte.|ฉันไม่รู้.|hindi ko alam.|Я не знаю.|ja neviem.|わからない。|არ ვიცი.|모르겠습니다.|aš nežinau.|не знам.|Би мэдэхгүй.|saya tak tahu.|ကျွန်တော်မသိပါ။|Ik weet het niet.|Je ne sais pas.|չգիտեմ։|لا أعرف.|аз не знам|Não sei.|Nevím.|Jeg ved det ikke.|Δεν ξέρω.|No sé.|ma ei tea.|من نمی دانم.|En tiedä.|אני לא יודע.|मुझें नहीं पता।|ne znam|Nem tudom.|Aku tidak tahu.|Ég veit það ekki.|Ich weiß nicht.|I can\'t assist with that|provide real-time|Sorry, as an AI model|don\'t have access to real-time|don\'t have real-time|can\'t access the internet or real-time|provide real-time information|access to real-time|don\'t have access to real-time|not included in the context|sb-human-takeover|provide more context|provide a valid text|What was that|I didn\'t get that|I don\'t understand|no text provided|provide the text|I cannot provide|I don\'t have access|I don\'t have any|As a language model|I do not have the capability|I do not have access|modelo de lenguaje de IA|no tengo acceso|no tinc accés|En tant qu\'IA|je n\'ai pas d\'accès|en tant qu\'intelligence artificielle|je n\'ai pas accès|programme d\'IA|স্মার্ট AI কম্পিউটার প্রোগ্রাম|আমি একটি AI|আমি জানি না|我無法回答未來的活動|AI 語言模型|我無法提供|作為AI|我無法得知|作為一名AI|我無法預測|作为AI|我没有未来预测的功能|作為一個AI|我無法預測未來|作为一个AI|我无法预测|我不具备预测|我作为一个人工智能|Как виртуальный помощник|я не могу предоставить|как AI-ассистента|Как ИИ|Как искусственный интеллект|я не имею доступа|я не могу ответить|я не могу предсказать|como um modelo de linguagem|eu não tenho informações|sou um assistente de linguagem|Não tenho acesso|modelo de idioma de AI|não é capaz de fornecer|não tenho a capacidade|como modelo de linguagem de IA|como uma AI|não tenho um|como modelo de linguagem de inteligência artificial|como modelo de linguagem AI|não sou capaz|poiché sono un modello linguistico|non posso fornire informazioni|in quanto intelligenza artificiale|non ho la capacità|non sono in grado|non ho la possibilità|non posso dare|non posso fare previsioni|non posso predire|in quanto sono un\'Intelligenza Artificiale|Come assistente digitale|come assistente virtuale|Si një AI|nuk mund të parashikoj|Si inteligjencë artificiale|nuk kam informacion|Nuk mund të jap parashikime|nuk mund të parashikoj|لا يمكنني توفير|نموذجًا لغة|لا يمكنني التنبؤ|AI भाषा मॉडल हूँ|मैं एक AI|मुझे इसकी जानकारी नहीं है|मैं आपको बता नहीं सकती|AI सहायक|मेरे पास भविष्य के बारे में कोई जानकारी नहीं है|का पता नहीं है|не мога да|Като AI|не разполагам с|нямам достъп|ne mogu pratiti|Nisam u mogućnosti|nisam sposoban|ne mogu prikazivati|ne mogu ti dati|ne mogu pružiti|nemam pristup|nemam sposobnosti|nemam trenutne informacije|nemam sposobnost|ne mogu s preciznošću|nemůžu předpovídat|nemohu s jistotou|Jako AI|nemohu předpovídat|nemohu s jistotou znát|Jako umělá inteligence|nemám informace|nemohu predikovat|Jako NLP AI|nemohu předvídat|nedokážu předvídat|nemám schopnost|som AI|som en AI|har jeg ikke adgang|Jeg kan desværre ikke besvare|jeg ikke har adgang|kan jeg ikke give|jeg har ikke|har jeg ikke mulighed|Jeg er en AI og har ikke|har jeg ikke evnen|Jeg kan desværre ikke hjælpe med|jeg kan ikke svare|Som sprog AI|jeg ikke i stand)/i', $message) !== 1) : false;
}

function sb_open_ai_upload($path, $post_fields = []) {
    return sb_curl('https://api.openai.com/v1/files', array_merge(['file' => new CurlFile($path, 'application/json')], $post_fields), ['Content-Type: multipart/form-data', 'Authorization: Bearer ' . sb_open_ai_key()], 'UPLOAD', 30);
}

function sb_open_ai_file_training($url) {
    $response = sb_open_ai_source_file_to_paragraphs($url);
    sb_file_delete($url);
    return is_array($response) ? sb_open_ai_embeddings_generate($response, $url) : $response;
}

function sb_open_ai_url_training($url) {
    $response = sb_open_ai_html_to_paragraphs($url);
    $embedding_urls = sb_open_ai_get_training_source_names();
    if ($response[1] != 200) {
        return [false, 'http-error-' . $response[1], 'The URL ' . $url . ' returned a ' . $response[1] . ' error: ' . PHP_EOL . strip_tags(preg_replace('/(<(script|style)\b[^>]*>).*?(<\/\2>)/is', "", $response[0]))];
    }
    if (in_array(rtrim($url, '/'), $embedding_urls) || in_array($url . '/', $embedding_urls)) {
        return [true];
    }
    return is_array($response) ? sb_open_ai_embeddings_generate($response[0], $url) : $response;
}

function sb_open_ai_qea_training($questions_answers, $language = false, $reset = false, $update_index = false) {
    $db_embeddings = $reset ? [] : sb_get_external_setting('embedding-texts', []);
    $db_embeddings_questions = $reset ? [] : array_column($db_embeddings, 0);
    $questions_answers_to_save = $reset ? [] : $db_embeddings;
    $embeddings = [];
    $set_data_null_keys = ['transcript', 'transcript_email', 'human_takeover', 'archive_conversation'];
    for ($i = 0; $i < count($db_embeddings); $i++) {
        $db_embeddings[$i] = [$db_embeddings[$i][0], $db_embeddings[$i][1], sb_isset($db_embeddings[$i], 6)];
    }

    // Update an existing question and answer
    if ($update_index) {
        $db_embeddings[$update_index] = [$questions_answers[0], $questions_answers[1], sb_isset($questions_answers, 6)];
        $questions_answers_to_save[$update_index] = $questions_answers;
        $questions_answers = [];
    }

    // Update embeddings and questions
    for ($i = 0; $i < count($questions_answers); $i++) {
        $questions = [];
        if (!empty($questions_answers[$i][0])) {
            if (isset($questions_answers[$i][5]) && !is_array($questions_answers[$i][5])) {
                $questions_answers[$i][5] = [];
            }
            if (count(sb_isset($questions_answers[$i], 6, [])) == 1 && !$questions_answers[$i][6][0][1] && !in_array($questions_answers[$i][6][0][0], $set_data_null_keys)) {
                $questions_answers[$i][6] = [];
            }
            if (is_string($questions_answers[$i][0])) { // Deprecated
                $questions_answers[$i][0] = [$questions_answers[$i][0]];// Deprecated
            }// Deprecated
            for ($j = 0; $j < count($questions_answers[$i][0]); $j++) {
                if (!in_array($questions_answers[$i][0][$j], $db_embeddings_questions)) {
                    $questions_answers[$i][1] = str_replace('..', '.', $questions_answers[$i][1], $questions_answers[$i][1]);
                    array_push($questions, $questions_answers[$i][0][$j]);
                }
            }
            array_push($questions_answers_to_save, $questions_answers[$i]);
            array_push($db_embeddings, [$questions, $questions_answers[$i][1], $language, sb_isset($questions_answers[$i], 6)]);
        }
    }
    for ($i = 0; $i < count($db_embeddings); $i++) {
        if (is_string($db_embeddings[$i][0])) { // Deprecated
            $db_embeddings[$i][0] = [$db_embeddings[$i][0]];// Deprecated
        }// Deprecated
        for ($j = 0; $j < count($db_embeddings[$i][0]); $j++) {
            $extra = [];
            if (!empty($db_embeddings[$i][3])) {
                foreach ($db_embeddings[$i][3] as $value) {
                    if ($value[1] || in_array($value[0], $set_data_null_keys)) {
                        $extra['set_data'][$value[0]] = $value[1];
                    }
                }
            }
            $question = trim($db_embeddings[$i][0][$j]);
            array_push($embeddings, ['Question: ' . $question . (in_array(substr($question, -1), ['.', '?', '!']) ? '' : '.') . PHP_EOL . PHP_EOL . 'Answer: ' . $db_embeddings[$i][1], sb_isset($db_embeddings[$i], 2, ''), 'qea', $extra ? $extra : false]);
        }
    }

    // Delete embeddings of deleted or updated questions
    $files_names = sb_isset(sb_get_external_setting('embedding-sources'), 'sb-database', []);
    for ($i = 0; $i < count($files_names); $i++) {
        $file_path = sb_open_ai_embeddings_get_file($files_names[$i]);
        if (file_exists($file_path)) {
            $embeddings_file = json_decode(file_get_contents($file_path), true);
            $embeddings_file_final = [];
            $is_updated = false;
            for ($j = 0; $j < count($embeddings_file); $j++) {
                $is_deleted = true;
                for ($y = 0; $y < count($embeddings); $y++) {
                    if (trim($embeddings_file[$j]['text']) == trim($embeddings[$y][0]) && json_encode(sb_isset($embeddings_file[$j], 'extra')) == json_encode(sb_isset($embeddings[$y], 3))) {
                        $is_deleted = false;
                        break;
                    }
                }
                if (!$is_deleted) {
                    array_push($embeddings_file_final, $embeddings_file[$j]);
                } else {
                    $is_updated = true;
                }
            }
            if ($is_updated) {
                if (count($embeddings_file_final)) {
                    sb_file($file_path, json_encode($embeddings_file_final, JSON_UNESCAPED_UNICODE));
                } else {
                    sb_file_delete($file_path);
                }
            }
        }
    }
    sb_save_external_setting('embedding-texts', $questions_answers_to_save);
    $response = sb_open_ai_embeddings_generate($embeddings, 'sb-database');
    return $response[0] ? true : $response;
}

function sb_open_ai_articles_training() {
    $paragraphs = [];
    $articles = sb_get_articles(false, false, true, false, 'all');
    for ($i = 0; $i < count($articles); $i++) {
        array_push($paragraphs, [strip_tags($articles[$i]['title'] . ' ' . $articles[$i]['content']), sb_isset($articles[$i], 'language'), 'article-' . $articles[$i]['id']]);
    }
    return count($paragraphs) ? sb_open_ai_embeddings_generate($paragraphs, 'sb-articles') : true;
}

function sb_open_ai_conversations_training() {
    $last_check = explode('|', sb_get_external_setting('open-ai-embeddings-conversations', '2017-01-01 01:00:00|0'));
    $last_check_unix = strtotime($last_check[0]);
    if (count($last_check) == 1) // Deprecated
        array_push($last_check, 0); // Deprecated
    $count_conversations = $last_check[1];
    $conversations = sb_db_get('SELECT conversation_id, creation_time FROM sb_messages WHERE creation_time > "' . $last_check[0] . '" GROUP BY conversation_id', false);
    $paragraphs = [];
    if ($conversations) {
        $bot_id = sb_get_bot_id();
        $agent_ids = sb_get_agents_ids();
        $language = sb_open_ai_embeddings_language();
        if (empty($language)) {
            $language = sb_get_user_language($agent_ids[0]);
        } else {
            $language = $language[0];
        }
        for ($i = 0; $i < count($conversations); $i++) {
            $conversation_id = $conversations[$i]['conversation_id'];
            $messages = sb_db_get('SELECT user_id, message, payload FROM sb_messages WHERE conversation_id = ' . $conversation_id . ' AND message != "" AND creation_time > "' . $last_check[0] . '" ORDER BY id ASC', false);
            $conversation = '';
            $messages_final = [];
            $count = count($messages);
            for ($j = 0; $j < $count; $j++) {
                $user_id = $messages[$j]['user_id'];
                $is_agent = in_array($user_id, $agent_ids);
                if (($user_id == $bot_id) || (!$is_agent && $j < ($count - 2) && $messages[$j + 1]['user_id'] == $bot_id && !in_array($messages[$j + 2]['user_id'], $agent_ids)) && (!isset($messages[$j + 3]) || !strpos($messages[$j + 3]['payload'], 'sb-human-takeover'))) {
                    continue;
                }
                array_push($messages_final, [$is_agent, $messages[$j]]);
            }
            for ($j = count($messages_final) - 1; $j > -1; $j--) {
                if (!$messages_final[$j][0]) {
                    $messages_final[$j] = false;
                } else {
                    break;
                }
            }
            $is_agent_in_conversation = false;
            $is_question_in_conversation = false;
            for ($j = 0; $j < count($messages_final); $j++) {
                if ($messages_final[$j]) {
                    $is_agent = $messages_final[$j][0];
                    $label = '';
                    if (isset($messages_final[$j - 1]) && $messages_final[$j - 1]) {
                        if ($messages_final[$j - 1][0] != $is_agent || !$conversation) {
                            $label = PHP_EOL . PHP_EOL . ($is_agent ? 'Answer: ' : 'Question: ');
                        } else {
                            $conversation .= (in_array(substr($conversation, -1), ['.', '!', '?', ';']) ? '' : '.') . ' ';
                        }
                    } else {
                        $label = $is_agent ? 'Answer: ' : 'Question: ';
                    }
                    $message = strip_tags(sb_google_get_message_translation($messages_final[$j][1], $language)['message']);
                    if (strlen($message) > 2 && strpos($message, ' ')) {
                        if ($is_agent) {
                            $is_agent_in_conversation = true;
                        } else {
                            $is_question_in_conversation = true;
                        }
                        if (!$is_agent || $conversation) {
                            $conversation .= $label . strip_tags($message);
                        }
                    }
                }
            }
            if ($is_agent_in_conversation && $is_question_in_conversation) {
                array_push($paragraphs, [$conversation, '', 'conversation-' . $conversation_id]);
                if (strtotime($conversations[$i]['creation_time']) > $last_check_unix) {
                    $count_conversations++;
                }
            }
        }
        $response = sb_open_ai_embeddings_generate($paragraphs, 'sb-conversations');
        if ($response) {
            sb_save_external_setting('open-ai-embeddings-conversations', sb_gmt_now() . '|' . $count_conversations);
        }
    }
    return $paragraphs;
}

function sb_open_ai_embeddings_delete($sources_to_delete) {
    $is_all = $sources_to_delete == 'all';
    $is_all_website = $sources_to_delete == 'all-website';
    $is_all_conversations = $sources_to_delete == 'all-conversations';
    if (empty($sources_to_delete) || (!$is_all && !$is_all_website && !$is_all_conversations && !is_array($sources_to_delete))) {
        return false;
    }
    $embedding_sources_new = [];
    $embedding_sources = sb_get_external_setting('embedding-sources', []);
    $deleted = 0;
    $count_sources_to_delete = is_array($sources_to_delete) ? count($sources_to_delete) : 0;
    foreach ($embedding_sources as $key => $value) {
        if ($is_all || ($is_all_website && !in_array($key, ['sb-conversations', 'sb-articles', 'sb-database']) && !sb_open_ai_is_file($key)) || ($is_all_conversations && $key == 'sb-conversations') || (!$is_all_website && !$is_all_conversations && in_array($key, $sources_to_delete))) {
            for ($i = 0; $i < count($value); $i++) {
                $file_name = sb_open_ai_embeddings_get_file($value[$i]);
                if (file_exists($file_name)) {
                    if (unlink($file_name)) {
                        $deleted++;
                    }
                }
            }
        } else {
            for ($i = 0; $i < $count_sources_to_delete; $i++) {
                $index = array_search($sources_to_delete[$i], $value);
                if ($index !== false) {
                    $file_name = sb_open_ai_embeddings_get_file($sources_to_delete[$i]);
                    if (file_exists($file_name)) {
                        if (unlink($file_name)) {
                            $deleted++;
                            array_splice($value, $index, 1);
                            break;
                        }
                    }
                }
            }
            $embedding_sources_new[$key] = $value;
        }
    }
    sb_db_query('DELETE FROM sb_settings WHERE name = "embeddings-language" LIMIT 1');
    sb_save_external_setting('embedding-sources', $embedding_sources_new);
    if ($is_all || $is_all_conversations) {
        sb_save_external_setting('open-ai-embeddings-conversations', '');
    }
    if ($is_all) {
        $files = scandir(sb_open_ai_embeddings_get_path());
        for ($i = 0; $i < count($files); $i++) {
            if ($files[$i] != '.' && $files[$i] != '..') {
                unlink(sb_open_ai_embeddings_get_path() . $files[$i]);
            }
        }
        sb_save_external_setting('embedding-texts', []);
        return true;
    }
    return $is_all_website || $is_all_conversations || $deleted == $count_sources_to_delete;
}

function sb_open_ai_embeddings_generate($paragraphs_or_string, $save_source = false) {
    if (is_string($paragraphs_or_string)) {
        if (mb_substr(trim($paragraphs_or_string), 0, 1) == '[') {
            $paragraphs_or_string_ = json_decode($paragraphs_or_string, true);
            if (!empty($paragraphs_or_string_)) {
                $paragraphs_or_string == $paragraphs_or_string_;
            } else {
                $paragraphs_or_string = [[$paragraphs_or_string, false]];
            }
        } else {
            $paragraphs_or_string = [[$paragraphs_or_string, false]];
        }
    }
    if (!sb_cloud_membership_has_credits('open-ai')) {
        return sb_error('no-credits', 'sb_open_ai_embeddings_generate');
    }
    $paragraphs_or_string_final = $paragraphs_or_string;
    $chars_limit = false;
    $chars_count = 0;
    if ($save_source) {
        $paragraphs_or_string_final = [];
        $path = sb_open_ai_embeddings_get_path();
        $embeddings = sb_open_ai_embeddings_get();
        $embedding_texts = [];
        if (sb_is_cloud()) {
            require_once(SB_CLOUD_PATH . '/account/functions.php');
            $chars_limit = cloud_embeddings_chars_limit();
        }
        for ($i = 0; $i < count($embeddings); $i++) {
            $embedding = $embeddings[$i];
            $texts = array_column(json_decode(file_get_contents($path . $embedding), true), 'text');
            for ($j = 0; $j < count($texts); $j++) {
                $texts[$j] = [$texts[$j], $embedding];
            }
            $embedding_texts = array_merge($embedding_texts, $texts);
        }

        // Remove duplicates and adjust paragraphs
        for ($i = 0; $i < count($paragraphs_or_string); $i++) {
            if (is_string($paragraphs_or_string[$i])) {
                $paragraphs_or_string[$i] = [$paragraphs_or_string[$i], false];
            } else {
                if (empty(trim($paragraphs_or_string[$i][0]))) {
                    continue;
                }
                if (isset($paragraphs_or_string[$i][2]) && $paragraphs_or_string[$i][2] != 'qea' && strpos($paragraphs_or_string[$i][2], 'article-') === false && strpos($paragraphs_or_string[$i][2], 'conversation-') === false && strpos($paragraphs_or_string[$i][2], 'flow-') === false) {
                    $paragraphs_or_string[$i][0] .= ' More details at ' . $paragraphs_or_string[$i][2] . '.';
                }
            }
            $paragraphs_or_string[$i][0] = trim($paragraphs_or_string[$i][0]);
            $text = $paragraphs_or_string[$i][0];
            $duplicate = false;
            for ($j = 0; $j < count($paragraphs_or_string); $j++) {
                if ($text == trim($paragraphs_or_string[$j][0]) && $j != $i) {
                    $duplicate = true;
                    break;
                }
            }
            if (!$duplicate) {
                for ($j = 0; $j < count($embedding_texts); $j++) {
                    if ($embedding_texts[$j][0] == $text) {
                        $duplicate = true;
                        break;
                    }
                }
                if (!$duplicate) {
                    array_push($paragraphs_or_string_final, $paragraphs_or_string[$i]);
                }
            }
        }
        if ($chars_limit) {
            for ($i = 0; $i < count($paragraphs_or_string_final); $i++) {
                $chars_count += strlen($paragraphs_or_string_final[$i][0]);
            }
            for ($i = 0; $i < count($embedding_texts); $i++) {
                $chars_count += strlen($embedding_texts[$i][0]);
            }
        }
    }
    if ($chars_limit && $chars_count > $chars_limit) {
        return [false, 'chars-limit-exceeded', $chars_limit, $chars_count];
    }
    $data_all = [];
    $paragraphs = sb_open_ai_embeddings_split_paragraphs($paragraphs_or_string_final, 0);
    $index = $paragraphs[1];
    $paragraphs = $paragraphs[0];
    $errors = [];

    // Generate embeddings
    while ($paragraphs) {
        $paragraphs_texts = [];
        $paragraphs_languages = [];
        for ($i = 0; $i < count($paragraphs); $i++) {
            array_push($paragraphs_texts, is_string($paragraphs[$i]) ? $paragraphs[$i] : $paragraphs[$i][0]);
            array_push($paragraphs_languages, is_string($paragraphs[$i]) ? '' : (is_string($paragraphs[$i][1]) ? $paragraphs[$i][1] : ''));
        }
        $response = sb_open_ai_curl('embeddings', ['model' => 'text-embedding-3-small', 'input' => $paragraphs_texts]);
        $data = sb_isset($response, 'data');
        if ($data) {
            for ($i = 0; $i < count($data); $i++) {
                $data[$i]['text'] = trim($paragraphs_texts[$i]);
                $data[$i]['language'] = $paragraphs_languages[$i];
                if (isset($paragraphs[$i][2])) {
                    $data[$i]['source'] = $paragraphs[$i][2];
                }
                if (isset($paragraphs[$i][3])) {
                    $attachments = sb_isset($paragraphs[$i][3], 'attachments', []);
                    for ($x = 0; $x < count($attachments); $x++) {
                        $paragraphs[$i][3]['attachments'][$x] = [basename($attachments[$x]), $attachments[$x]];
                    }
                    $data[$i]['extra'] = $paragraphs[$i][3];
                }
            }
            $data_all = array_merge($data_all, $data);
        } else {
            array_push($errors, $response);
        }
        $paragraphs = sb_open_ai_embeddings_split_paragraphs($paragraphs_or_string_final, $index);
        if (empty($paragraphs[0])) {
            $paragraphs = false;
        } else {
            $index = $paragraphs[1];
            $paragraphs = $paragraphs[0];
        }
    }

    // Save embedding files
    if ($save_source) {
        $len_total = 0;
        $embeddings_part = [];
        $count = count($data_all);
        $response = [];
        $embedding_sources = sb_get_external_setting('embedding-sources', []);
        for ($i = 0; $i < $count; $i++) {
            $len_total += strlen(json_encode($data_all[$i]));
            array_push($embeddings_part, $data_all[$i]);
            if ($len_total > 2000000 || $i == $count - 1) {
                $name = bin2hex(openssl_random_pseudo_bytes(10));
                array_push($response, sb_file(sb_open_ai_embeddings_get_file($name), json_encode($embeddings_part, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE)));
                $embeddings_part = [];
                $len_total = 0;
                if (isset($embedding_sources[$save_source])) {
                    if (!in_array($name, $embedding_sources[$save_source])) {
                        array_push($embedding_sources[$save_source], $name);
                    }
                } else {
                    $embedding_sources[$save_source] = [$name];
                }
            }
        }

        // Delete embeddings of deleted articles
        $count = count(sb_isset($embedding_sources, $save_source, []));
        if ($save_source == 'sb-articles' && $count) {
            for ($i = 0; $i < $count; $i++) {
                $file_name = sb_open_ai_embeddings_get_file($embedding_sources[$save_source][$i]);
                if (file_exists($file_name)) {
                    $article_embeddings = json_decode(file_get_contents($file_name), true);
                    $paragraphs_or_strings_text = array_column($paragraphs_or_string, 0);
                    $is_save = false;
                    for ($j = 0; $j < count($article_embeddings); $j++) {
                        if (!in_array($article_embeddings[$j]['text'], $paragraphs_or_strings_text)) {
                            array_splice($article_embeddings, $j, 1);
                            $j--;
                            $is_save = true;
                        }
                    }
                    if ($is_save) {
                        if (count($article_embeddings)) {
                            sb_file($file_name, json_encode($article_embeddings, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
                        } else {
                            unlink($file_name);
                            $embedding_sources[$save_source][$i] = false;
                        }
                    }
                }
            }
        }

        // Delete old embedding file references
        $embedding_sources_final = [];
        foreach ($embedding_sources as $key => $file_names) {
            $embedding_source_file_names = [];
            for ($i = 0; $i < count($file_names); $i++) {
                if (file_exists(sb_open_ai_embeddings_get_file($file_names[$i]))) {
                    array_push($embedding_source_file_names, $file_names[$i]);
                }
            }
            if (count($embedding_source_file_names)) {
                $embedding_sources_final[$key] = $embedding_source_file_names;
            }
        }
        sb_save_external_setting('embedding-sources', $embedding_sources_final);

        //  Miscellaneous
        if (!empty($paragraphs_languages)) {
            $language_code = strtolower(substr($paragraphs_languages[0], 0, 2));
            $embeddings_language = sb_open_ai_embeddings_language();
            if (!in_array($language_code, $embeddings_language)) {
                array_push($embeddings_language, $language_code);
                sb_save_external_setting('embeddings-language', $embeddings_language);
            }
        }
        if (!file_exists($path . 'index.html')) {
            sb_file($path . 'index.html', 'Forbidden');
        }
        return empty($paragraphs_or_string_final) ? [true, []] : [$response, $errors];
    }
    return $data_all;
}

function sb_open_ai_embeddings_split_paragraphs($paragraphs, $last_index) {
    $response = [];
    $len_total = 0;
    $paragraphs_2 = [];
    for ($i = 0; $i < count($paragraphs); $i++) {
        $len = strlen($paragraphs[$i][0]);
        if ($len > 8000) {
            $splits = mb_str_split($paragraphs[$i][0], 8000);
            for ($j = 0; $j < count($splits); $j++) {
                array_push($paragraphs_2, [$splits[$j], $paragraphs[$i][1]]);
            }
        } else {
            array_push($paragraphs_2, $paragraphs[$i]);
        }
    }
    for ($i = $last_index; $i < count($paragraphs_2); $i++) {
        $len = strlen($paragraphs_2[$i][0]);
        if ($len_total + $len < 100000 || !$len_total) {
            array_push($response, $paragraphs_2[$i]);
            $len_total += $len;
            $last_index = $i;
        } else {
            break;
        }
    }
    return [$response, $last_index + 1];
}

function sb_open_ai_embeddings_compare($a, $b) {
    $result = array_map(function ($x, $y) {
        return $x * $y;
    }, $a, $b);
    return array_sum($result);
}

function sb_open_ai_embeddings_message($user_prompt, $language = false, $extra = false, $min_score = 0.2) {
    global $SB_OPEN_AI_PLAYGROUND;
    $user_prompt_embeddings = sb_open_ai_embeddings_generate($user_prompt);
    if (!empty($user_prompt_embeddings) && isset($user_prompt_embeddings[0]['embedding'])) {
        $scores = [];
        $user_prompt_embeddings = $user_prompt_embeddings[0]['embedding'];
        $embeddings = sb_open_ai_embeddings_get();
        $path = sb_open_ai_embeddings_get_path();
        $embedding_languages = [];
        if ($language) {
            $language = strtolower($language);
        }
        for ($i = 0; $i < count($embeddings); $i++) {
            $embeddings_content = json_decode(file_get_contents($path . $embeddings[$i]), true);
            for ($j = 0; $j < count($embeddings_content); $j++) {
                $embedding = $embeddings_content[$j];
                $embedding_language = sb_isset($embedding, 'language');
                if ($embedding_language && is_string($embedding_language)) {
                    $embedding_language = substr($embedding_language, 0, 2);
                    if (!in_array($embedding_language, $embedding_languages)) {
                        array_push($embedding_languages, $embedding_language);
                    }
                }
                if (!$language || !$embedding_language || $embedding_language == $language || count(sb_open_ai_embeddings_language()) == 1) {
                    $score = !empty($user_prompt_embeddings) && !empty($embedding['embedding']) ? sb_open_ai_embeddings_compare($user_prompt_embeddings, $embedding['embedding']) : 0;
                    if ($score > $min_score && (empty($embedding['extra']) || empty($embedding['extra']['conditions']) || sb_automations_validate($embedding['extra']['conditions'], true))) {
                        array_push($scores, ['score' => $score, 'text' => $embedding['text'], 'source' => sb_isset($embedding, 'source'), 'extra' => sb_isset($embedding, 'extra')]);
                    }
                }
            }
        }
        $count = count($scores);
        if ($count) {
            usort($scores, function ($a, $b) {
                return $a['score'] <=> $b['score'];
            });
            if ($count > 10) {
                $scores = array_slice($scores, -10);
            }
            $context = '';
            $context_max_length = in_array(sb_open_ai_get_gpt_model(), ['gpt-4', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo']) ? 32000 : 4000;
            $count = count($scores);
            for ($i = $count - 1; $i > -1; $i--) {
                if (mb_strlen($context) < $context_max_length) {
                    $text = $scores[$i]['text'];
                    $chips_pos = strpos($text, 'Answer: [chips');
                    if ($chips_pos === false || !strpos(substr($text, $chips_pos), $user_prompt)) {
                        $context .= ($context ? PHP_EOL . PHP_EOL : '') . $text;
                    }
                }
            }
            $context = trim($context);
            if (mb_strlen($context) > $context_max_length) {
                $context = mb_substr($context, 0, $context_max_length);
            }
            if ($SB_OPEN_AI_PLAYGROUND !== null) {
                $GLOBALS['SB_OPEN_AI_PLAYGROUND_E'] = $scores;
            }
            $extra_ = ['embeddings' => true, 'user_id' => sb_isset($extra, 'user_id')];
            if (!empty($extra['smart_reply'])) {
                $extra_['smart_reply'] = true;
            }
            $response = sb_open_ai_message(['context' => $context, 'user_prompt' => $user_prompt], false, false, sb_isset($extra, 'conversation_id'), $extra_);
            if ($response) {
                if (empty($response[0])) {
                    if (!empty($response[1])) {
                        sb_error('open-ai-error', 'sb_open_ai_message', sb_isset($response[1], 'error', $response[1]));
                    }
                } else {
                    if (sb_open_ai_is_valid($response[1])) {
                        $response_text = sb_open_ai_text_formatting($response[1]);
                        $top_score = $scores[count($scores) - 1];
                        $embedding_extra = false;
                        if ($extra == 'translation') {
                            $message = sb_google_translate([$response_text], $language);
                            if (!empty($message[0])) {
                                $response_text = $message[0][0];
                            }
                        }
                        if (sb_get_multi_setting('open-ai', 'open-ai-source-links') && (empty($response[3]) || $response[3] != 'skip-references')) {
                            $sources_string = '';
                            $index = 1;
                            $is_articles_home = sb_get_articles_page_url();
                            for ($i = 0; $i < $count; $i++) {
                                $source = sb_isset($scores[$i], 'source');
                                $is_article = $is_articles_home && strpos($source, 'article-') === 0;
                                if ($source && strpos($sources_string, $source) === false && ($is_article || strpos($source, 'http') === 0) && strpos($response_text, $source) === false) {
                                    $sources_string .= ($is_article ? sb_get_article_url(substr($source, 8)) : $source) . '#sb-' . $index . ' | ';
                                    $index++;
                                }
                            }
                            if ($sources_string) {
                                $response_text .= PHP_EOL . PHP_EOL . sb_('References') . ': ' . substr($sources_string, 0, -3);
                            }
                        }
                        if ($top_score['score'] > 0.45 || strpos(sb_string_slug($top_score['text']), sb_string_slug(substr($response_text, 1, -1)))) {
                            $embedding_extra = sb_isset($top_score, 'extra');
                            $attachments = sb_isset($embedding_extra, 'attachments');
                            if ($attachments) {
                                $response[6] = array_merge($response[6], $attachments);
                            }
                        }
                        return ['message' => str_replace('disabled="true"]', ']', $response_text), 'payload' => $response[5], 'payload_message' => $response[7], 'attachments' => $response[6], 'embedding_extra' => $embedding_extra];
                    }
                }
            }
        } else if ($extra != 'translation' && $language && count($embedding_languages) && !in_array($language, $embedding_languages) && (sb_get_setting('dialogflow-multilingual-translation') || sb_get_multi_setting('google', 'google-multilingual-translation'))) { // Deprecated: sb_get_setting('dialogflow-multilingual-translation')
            $message = sb_google_translate([$user_prompt], $embedding_languages[0]);
            if (!empty($message[0])) {
                return sb_open_ai_embeddings_message($message[0][0], $embedding_languages[0], 'translation');
            }
        }
    }
    return false;
}

function sb_open_ai_embeddings_language() {
    global $SB_EMBEDDINGS_LANGUAGES;
    if (!empty($SB_EMBEDDINGS_LANGUAGES)) {
        return $SB_EMBEDDINGS_LANGUAGES;
    }
    $embeddings_language = sb_get_external_setting('embeddings-language');
    if (!$embeddings_language || (is_string($embeddings_language) && strpos($embeddings_language, '[') === false)) { // Deprecated: remove || (is_string($embeddings_language) && strpos($embeddings_language, '[') === false)
        $embeddings = sb_open_ai_embeddings_get();
        $embeddings_language = [];
        for ($i = 0; $i < count($embeddings); $i++) {
            $embeddings_single = json_decode(file_get_contents(sb_open_ai_embeddings_get_path() . $embeddings[$i]), true);
            for ($j = 0; $j < count($embeddings_single); $j++) {
                $language_code = sb_isset($embeddings_single[$j], 'language');
                if ($language_code && is_string($language_code)) {
                    $language_code = strtolower(substr($language_code, 0, 2));
                    if (!in_array($language_code, $embeddings_language)) {
                        array_push($embeddings_language, $language_code);
                    }
                }
            }
        }
        sb_save_external_setting('embeddings-language', $embeddings_language ? $embeddings_language : '-');
    } else if ($embeddings_language == '-') {
        $embeddings_language = '';
    }
    $SB_EMBEDDINGS_LANGUAGES = $embeddings_language;
    return $embeddings_language;
}

function sb_open_ai_embeddings_get() {
    $files = scandir(sb_open_ai_embeddings_get_path());
    $embeddings = [];
    for ($i = 0; $i < count($files); $i++) {
        $file = $files[$i];
        if (strpos($file, 'embeddings-') === 0) {
            array_push($embeddings, $files[$i]);
        }
    }
    return $embeddings;
}

function sb_open_ai_embeddings_get_path() {
    $name = 'SB_EMBEDDINGS_PATH';
    if (isset($GLOBALS[$name])) {
        return $GLOBALS[$name];
    }
    $path = sb_upload_path() . '/embeddings/';
    $cloud = sb_is_cloud() ? sb_cloud_account() : false;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
    if ($cloud) {
        require_once(SB_CLOUD_PATH . '/account/functions.php');
        $path .= $cloud['user_id'] . '/';
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }
    $GLOBALS[$name] = $path;
    return $path;
}

function sb_open_ai_embeddings_get_information() {
    $information = ['limit' => false, 'files' => [0, 0], 'website' => [0, 0], 'qea' => [0, 0], 'flows' => [0, 0], 'articles' => [0, 0], 'conversations' => [0, 0]];
    $sources = sb_get_external_setting('embedding-sources', []);
    $path = sb_open_ai_embeddings_get_path();
    $total = 0;
    foreach ($sources as $key => $embeddings) {
        $key_info = $key == 'sb-database' ? 'qea' : ($key == 'sb-conversations' ? 'conversations' : ($key == 'sb-articles' ? 'articles' : ($key == 'sb-flows' ? 'flows' : (sb_open_ai_is_file($key) ? 'files' : 'website'))));
        for ($i = 0; $i < count($embeddings); $i++) {
            $text_len = strlen(implode('', array_column(sb_open_ai_embeddings_get_file($embeddings[$i], true), 'text')));
            $information[$key_info][0] += $text_len;
            $total += $text_len;
        }
        switch ($key_info) {
            case 'website':
            case 'files':
                $count = $information[$key_info][1] + 1;
                break;
            case 'conversations':
                $count = explode('|', sb_get_external_setting('open-ai-embeddings-conversations', '0|0')); // Deprecated
                $count = isset($count[1]) ? $count[1] : 0; // Deprecated. Replace with: $information[$key_info][1] = explode('|', sb_get_external_setting('open-ai-embeddings-conversations', '0|0'))[0]
                break;
            case 'articles':
                $count = count(sb_get_articles());
                break;
            case 'qea':
                $count = count(sb_get_external_setting('embedding-texts', []));
                break;
            case 'flows':
                $count = count(sb_flows_get());
                break;
        }
        $information[$key_info][1] = $count;
    }
    $information['total'] = $total;
    if (sb_is_cloud()) {
        require_once(SB_CLOUD_PATH . '/account/functions.php');
        $information['limit'] = cloud_embeddings_chars_limit();
    }
    return $information;
}

function sb_open_ai_embeddings_get_file($embedding_id, $return_content = false) {
    $path = sb_open_ai_embeddings_get_path() . 'embeddings-' . $embedding_id . '.json';
    return $return_content ? (file_exists($path) ? json_decode(file_get_contents($path), true) : []) : $path;
}

function sb_open_ai_embeddings_update_single($embedding_id, $index, $text) {
    $path = sb_open_ai_embeddings_get_path() . 'embeddings-' . $embedding_id . '.json';
    $embedding = sb_open_ai_embeddings_get_file($embedding_id, true);
    if ($text) {
        if (sb_is_cloud()) {
            require_once(SB_CLOUD_PATH . '/account/functions.php');
            $chars_limit = cloud_embeddings_chars_limit();
            if ($chars_limit) {
                $embeddings = sb_open_ai_embeddings_get();
                for ($i = 0; $i < count($embeddings); $i++) {
                    $embedding = $embeddings[$i];
                    $chars_count += strlen(implode('', array_column(json_decode(file_get_contents(sb_open_ai_embeddings_get_path() . $embedding), true), 'text')));
                }
                if ($chars_count > $chars_limit) {
                    return 'chars-limit-exceeded';
                }
            }
        }
        $response = sb_open_ai_curl('embeddings', ['model' => 'text-embedding-3-small', 'input' => [$text]]);
        $data = sb_isset($response, 'data');
        if (!empty($data)) {
            $embedding[$index]['text'] = $text;
            $embedding[$index]['embedding'] = $data[0]['embedding'];
            sb_file($path, json_encode($embedding, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
            return true;
        }
    } else {
        array_splice($embedding, $index, 1);
        if (empty($embedding)) {
            sb_open_ai_embeddings_delete([$embedding_id]);
        } else {
            sb_file($path, json_encode($embedding, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
        }
        return true;
    }
    return $response;
}

function sb_open_ai_embeddings_get_conversations() {
    $embeddings = sb_isset(sb_get_external_setting('embedding-sources'), 'sb-conversations', []);
    $response = [];
    for ($i = 0; $i < count($embeddings); $i++) {
        $texts = array_column(sb_open_ai_embeddings_get_file($embeddings[$i], true), 'text');
        for ($y = 0; $y < count($texts); $y++) {
            $id = $y . '-' . $embeddings[$i];
            $qea = explode('|||', str_replace(['Question: ', 'Answer: '], '|||', $texts[$y]));
            $count = count($qea) - 1;
            for ($j = 1; $j < $count; $j += 2) {
                array_push($response, ['question' => trim($qea[$j]), 'answer' => $qea[$j + 1], 'id' => $id]);
            }
        }
    }
    return $response;
}

function sb_open_ai_embeddings_save_conversations($qea) {
    $response = [];
    for ($i = 0; $i < count($qea); $i++) {
        $id = explode('-', $qea[$i][0]['id']);
        $texts = array_column(sb_open_ai_embeddings_get_file($id[1], true), 'text');
        $text = trim(str_replace(['Question: ' . htmlentities($qea[$i][0]['question']), 'Answer: ' . htmlentities($qea[$i][0]['answer'])], $qea[$i][1] && $qea[$i][1] != 'false' ? ['Question: ' . $qea[$i][1][0], 'Answer: ' . $qea[$i][1][1]] : '', $texts[$id[0]]));
        array_push($response, sb_open_ai_embeddings_update_single($id[1], $id[0], $text));
    }
    return $response;
}

function sb_open_ai_source_file_to_paragraphs($url) {
    $extension = sb_open_ai_is_file($url);
    $paragraphs = [];
    if (!$extension) {
        sb_file_delete($url);
        return 'invalid-file-extension';
    }
    if ($extension == '.pdf') {
        $upload_url = sb_upload_path(true);
        $file = strpos($url, $upload_url) === 0 ? sb_upload_path() . str_replace($upload_url, '', $url) : sb_download_file($url, 'sb_open_ai_source_file' . $extension, false, [], 0, true);
        $text = sb_pdf_to_text($file);
    } else {
        $text = trim(sb_get($url));
    }
    if ($text) {
        $encoding = mb_detect_encoding($text);
        if (strpos($encoding, 'UTF-16') !== false) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16');
        }
        $separator = ['።', '。', '။', '.', '।'];
        for ($i = 0; $i < count($separator); $i++) {
            if (strpos($text, $separator[$i])) {
                $separator = $separator[$i];
                break;
            }
        }
        $parts = is_string($separator) ? explode($separator . ' ', $text) : [$text];
        $paragraph = '';
        for ($i = 0; $i < count($parts); $i++) {
            $part = trim($parts[$i]);
            $length_1 = mb_strlen($paragraph);
            $length_2 = mb_strlen($parts[$i]);
            if (($length_1 + $length_2 < 2000) || $length_1 < 100 || $length_2 < 100) {
                $paragraph .= $part;
            } else {
                array_push($paragraphs, $paragraph ? $paragraph . ' ' . $part : $part);
                $paragraph = '';
            }
        }
        if ($paragraph) {
            array_push($paragraphs, $paragraph);
        }
    }
    return $paragraphs;
}

function sb_open_ai_get_gpt_model() {
    $model = sb_get_multi_setting('open-ai', 'open-ai-model', 'gpt-4o-mini');
    return $model == 'gpt-3.5-turbo-instruct' ? 'gpt-3.5-turbo' : $model;
}

function sb_open_ai_audio_to_text($path_or_url, $audio_language = false, $user_id = false, $message_id = false, $conversation_id = false) {
    $is_delete = false;
    if (!sb_cloud_membership_has_credits('open-ai')) {
        return sb_error('no-credits', 'sb_open_ai_audio_to_text');
    }
    if (!$audio_language) {
        $audio_language = sb_get_user_language($user_id ? $user_id : sb_get_active_user_ID());
    }
    if (strpos($path_or_url, 'http') === 0) {
        $path_file = sb_upload_path(false, true) . '/' . basename($path_or_url);
        if (file_exists($path_file)) {
            $path_or_url = $path_file;
        } else {
            $is_delete = true;
            $path_or_url = sb_download_file($path_or_url, 'temp_open_ai_' . basename($path_or_url), false, [], 0, true);
        }
    }
    $response = sb_curl('https://api.openai.com/v1/audio/transcriptions', ['file' => new CURLFile($path_or_url), 'model' => 'whisper-1', 'language' => $audio_language ? $audio_language : sb_get_user_language()], ['Content-Type: multipart/form-data', 'Authorization: Bearer ' . sb_open_ai_key()], 'POST', 30);
    $message = sb_isset($response, 'text');
    if ($message) {
        if ($conversation_id || $message_id) {
            if (!$message_id) {
                $message_id = sb_isset(sb_db_get('SELECT id FROM sb_messages WHERE conversation_id = ' . sb_db_escape($conversation_id, true) . ' ORDER BY id DESC LIMIT 1'), 'id');
            }
            if ($message_id) {
                if (sb_get_multi_setting('open-ai', 'open-ai-speech-recognition')) {
                    sb_update_message($message_id, $message);
                } else {
                    sb_db_query('UPDATE sb_messages SET message = "' . sb_db_escape($message) . '" WHERE id = ' . $message_id);
                }
            }
        }
    } else {
        sb_error('open-ai-error', 'sb_open_ai_audio_to_text', $response);
    }
    if ($is_delete) {
        sb_file_delete($path_or_url);
    }
    return $message;
}

function sb_open_ai_key() {
    return sb_ai_is_manual_sync('open-ai') ? trim(sb_get_multi_setting('open-ai', 'open-ai-key')) : OPEN_AI_KEY;
}

function sb_open_ai_assistant($message, $conversation_id, $human_takeover_check = true) {
    $assistant_id = sb_get_multi_setting('open-ai', 'open-ai-assistant-id');
    $conversation = sb_db_get('SELECT extra_2, department FROM sb_conversations WHERE id = ' . sb_db_escape($conversation_id, true));
    $thread_id = sb_isset($conversation, 'extra_2');
    $department_id = sb_isset($conversation, 'department');
    if ($department_id) {
        $assistants = sb_get_setting('open-ai-assistants');
        if ($assistants && is_array($assistants)) {
            for ($i = 0; $i < count($assistants); $i++) {
                if ($assistants[$i]['open-ai-assistants-department-id'] == $department_id) {
                    $assistant_id = $assistants[$i]['open-ai-assistants-id'];
                    break;
                }
            }
        }
    }
    if (!$assistant_id) {
        return sb_error('open-ai-error', 'sb_open_ai_assistant', 'No assistant ID', true);
    }
    $header = ['Content-Type: application/json', 'OpenAI-Beta: assistants=v2', 'Authorization: Bearer ' . trim(sb_get_multi_setting('open-ai', 'open-ai-key'))];
    $url_part = 'https://api.openai.com/v1/';
    if ($thread_id) {
        $response = sb_curl($url_part . 'threads/' . $thread_id . '/messages', json_encode(['role' => 'user', 'content' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), $header);
    }
    $response = sb_curl($url_part . 'threads' . ($thread_id ? '/' . $thread_id : '') . '/runs', json_encode($thread_id ? ['assistant_id' => $assistant_id] : ['assistant_id' => $assistant_id, 'thread' => ['messages' => [['role' => 'user', 'content' => $message]]]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), $header);
    $run_id = sb_isset($response, 'id');
    if ($run_id) {
        if (!$thread_id) {
            $thread_id = sb_isset($response, 'thread_id');
            sb_db_query('UPDATE sb_conversations SET extra_2 = "' . sb_db_escape($thread_id) . '" WHERE id = ' . sb_db_escape($conversation_id, true));
        }
        for ($i = 0; $i < 30; $i++) {
            sleep(1);
            $response = json_decode(sb_curl($url_part . 'threads/' . $thread_id . '/runs/' . $run_id, '', $header, 'GET'), true);
            $status = sb_isset($response, 'status');
            if ($status == 'completed') {
                $response = json_decode(sb_curl($url_part . 'threads/' . $thread_id . '/messages', '', $header, 'GET'), true);
                $message = isset($response['data']) ? $response['data'][0]['content'][0]['text']['value'] : '';
                if ($message) {
                    $message = preg_replace('/【[\s\S]+?】/', '', sb_open_ai_text_formatting($message));
                }
                return $message;
            } else if ($status == 'expired') {
                break;
            } else {
                $function_calling = sb_open_ai_function_calling($response);
                if ($function_calling) {
                    if ($human_takeover_check || $function_calling[0] != 'sb-human-takeover') {
                        sb_curl($url_part . 'threads/' . $thread_id . '/runs/' . $run_id . '/submit_tool_outputs', json_encode(['tool_outputs' => [['tool_call_id' => $function_calling[1], 'output' => $function_calling[2]]]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), $header);
                    }
                    if ($function_calling[0] == 'sb-human-takeover') {
                        return $function_calling[0];
                    }
                }
            }
        }
    } else if (isset($response['error'])) {
        $error = sb_isset($response['error'], 'message');
        if (strpos($error, 'active run')) {
            $run_id = substr($error, strrpos($error, ' run_') + 1, -1);
            for ($i = 0; $i < 30; $i++) {
                sleep(2);
                if (sb_isset(json_decode(sb_curl($url_part . 'threads/' . $thread_id . '/runs/' . $run_id, '', $header, 'GET'), true), 'status') == 'completed') {
                    return sb_open_ai_assistant($message, $conversation_id, $human_takeover_check);
                }
            }
        }
    }
    return sb_error('open-ai-error', 'sb_open_ai_assistant', $response);
}

function sb_open_ai_data_scraping($conversation_id, $prompt_id) {
    if (!sb_cloud_membership_has_credits('open-ai')) {
        return sb_error('no-credits', 'sb_open_ai_audio_to_text');
    }
    $prompts = sb_open_ai_data_scraping_get_prompts();
    $response = sb_open_ai_message($prompts[$prompt_id][0] . ' from the user messages. Do not scrape anything else, return only the scraped information separated by breaklines, do not add text. If the information is not included, write exactly "I don\'t know.', false, false, $conversation_id, 'scraping');
    if (!$response || !$response[0]) {
        sb_error('open-ai-error', 'sb_open_ai_data_scrape', $response);
    } else if (empty($response[5]['unknow_answer'])) {
        $lines = preg_split("/\r\n|\n|\r/", $response[1]);
        $text = '';
        if (in_array('duplicate', $prompts[$prompt_id][1])) {
            $lines = array_unique($lines);
        }
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i] . '';
            $lines[$i] = '';

            for ($j = 0; $j < count($prompts[$prompt_id][1]); $j++) {
                $check = $prompts[$prompt_id][1][$j];
                if (strpos($line, $check) !== false || ($check == 123 && is_numeric($line))) {
                    continue 2;
                }
            }
            $count = count($prompts[$prompt_id][2]);
            if ($count) {
                $valid = false;
                for ($j = 0; $j < count($prompts[$prompt_id][2]); $j++) {
                    $check = $prompts[$prompt_id][2][$j];
                    if (strpos($line, $check) !== false || ($check == 123 && !is_numeric($line))) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid) {
                    continue;
                }
            }
            $text .= trim($line) . PHP_EOL;
        }
        return $text;
    } else {
        $response = '';
    }
    return $response;
}

function sb_open_ai_data_scraping_get_prompts($type = false) {
    $prompts = ['login' => ['The login details are made up of a URL or IP address, a username or email, and a password. Scrape all login details', [], [], 'Login information'], 'links' => ['Scrape all links and URLs', ['@', 123], ['http', 'www'], 'Links and URLs'], 'contacts' => ['Scrape addresses, phone numbers and emails', ['http'], [], 'Contact information']];
    if ($type == 'name') {
        foreach ($prompts as $key => $value) {
            $prompts[$key] = sb_($value[3]);
        }
    }
    return $prompts;
}

function sb_open_ai_function_calling($response) {
    $function = false;
    $function_name = false;
    $id = false;
    if (sb_isset($response, 'status') == 'requires_action') {
        $response = sb_isset($response, 'required_action');
        if ($response) {
            $response = sb_isset(sb_isset($response, 'submit_tool_outputs'), 'tool_calls');
            if (!empty($response)) {
                $function = sb_isset($response[0], 'function');
                $id = $response[0]['id'];
            }
        }
    } else if (!empty($response['choices'])) {
        $response = sb_isset(sb_isset($response['choices'][0], 'message'), 'tool_calls');
        if (!empty($response)) {
            $function = sb_isset($response[0], 'function');
        }
    }
    if ($function) {
        $function_name = $function['name'];
        if ($function_name == 'sb-human-takeover') {
            return [$function_name, $id, ''];
        }
        $qea = sb_get_external_setting('embedding-texts', []);
        for ($i = 0; $i < count($qea); $i++) {
            $qea[$i][0] = is_array($qea[$i][0]) ? $qea[$i][0] : [$qea[$i][0]]; // Deprecated;
            for ($j = 0; $j < count($qea[$i][0]); $j++) {
                if (substr(sb_string_slug($qea[$i][0][$j]), 0, 20) . '-' . $i == $function_name) {
                    $response = sb_curl($qea[$i][2], $function, $qea[$i][4] ? explode(',', $qea[$i][4]) : [], $qea[$i][3], 30);
                    return [$function_name, $id, $response];
                }
            }
        }
    }
    return false;
}

function sb_open_ai_troubleshoot($debug = false) {
    if (sb_get_multi_setting('open-ai', 'open-ai-multlilingual-sources') && count(sb_open_ai_embeddings_language()) < 2) {
        return sb_('The option at Open AI > Multilingual training sources is selected, but your sources are in one language. Disable it.');
    }
    if ($debug) {
        $_GET['debug'] = true;
    }
    $message = 'Hello world!';
    $conversation_id = false;
    $response = sb_open_ai_message($message);
    if ($response && !sb_is_error($response) && $response[0]) {
        $conversation_id = sb_open_ai_dummy_data([['user', $message]]);
        $response = sb_open_ai_message($message, false, false, $conversation_id);
        if ($response && !sb_is_error($response) && $response[0]) {
            if (sb_get_multi_setting('open-ai', 'open-ai-mode') == 'assistant') {
                $response = sb_open_ai_assistant($message, $conversation_id, false);
            }
            if ($response && !sb_is_error($response) && $response[0]) {
                sb_open_ai_dummy_data('delete');
                if ($debug) {
                    return true;
                } else {
                    return sb_open_ai_troubleshoot(true);
                }
            }
        }
    }
    if ($conversation_id) {
        sb_open_ai_dummy_data('delete');
    }
    if (sb_is_error($response)) {
        $response = $response->response();
        $response = sb_isset(sb_isset($response, 'error'), 'message', $response);
        return is_string($response) ? $response : json_encode($response, JSON_INVALID_UTF8_IGNORE);
    }
    return isset($response[1]) && isset($response[1]['error']) ? $response[1]['error']['message'] : $response;
}

function sb_open_ai_html_to_paragraphs($url) {
    error_reporting(E_ERROR | E_PARSE);
    $response = sb_curl($url, '', [], 'GET-SC');
    if ($response[1] == 200) {
        $paragraphs = [];
        $response = $response[0];
        $html_start = strpos($response, '<html');
        $html_end = strpos($response, '>', $html_start);
        $html_tag_content = substr($response, $html_start, $html_end - $html_start);
        $language = '';
        if (strpos($html_tag_content, 'lang=') !== false) {
            $lang_start = strpos($html_tag_content, 'lang=') + 6;
            $language = strtolower(substr($html_tag_content, $lang_start, strpos($html_tag_content, '"', $lang_start) - $lang_start));
        }
        $body_start = strpos($response, '<body');
        $body_content = substr($response, $body_start, strpos($response, '</body>') - $body_start);
        $body_content = str_replace('><', '> <', $body_content);
        $body_content = str_replace(['<br>', '<br />'], "\n", $body_content);
        $body_content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $body_content);
        $body_content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $body_content);
        $duplicate_check_strings = [];
        if ($body_content) {
            $dom = new DOMDocument();
            @$dom->loadHTML('<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>' . $body_content);
            $xpath = new DOMXPath($dom);
            $uls = $xpath->query('//ul');
            foreach ($uls as $ul) {
                try {
                    if (isset($ul->nodeValue)) {
                        $code = '';
                        $lis = $xpath->query('.//li', $ul);
                        $index = 0;
                        foreach ($lis as $li) {
                            $sub_uls = isset($li->nodeValue) ? $xpath->query('.//ul', $li) : [];
                            foreach ($sub_uls as $sub_ul) {
                                try {
                                    $li->removeChild($sub_ul);
                                } catch (Exception $e) {
                                }
                            }
                            $text = isset($li->textContent) ? trim($li->textContent) : '';
                            if (str_word_count($text) > 1 && strlen($text) > 10) {
                                $code .= '###B###' . ($index + 1) . '. ' . $text;
                            }
                            $index++;
                        }
                        $ul->nodeValue = '';
                        if ($code) {
                            $ul->appendChild($dom->createTextNode($code));
                        }
                    }
                } catch (Exception $e) {
                }
            }
            $ps = $xpath->query('//p');
            foreach ($ps as $p) {
                $p->nodeValue .= '###B###';
            }
            $spans_labels = $xpath->query('//span | //label');
            foreach ($spans_labels as $el) {
                try {
                    if (isset($el->nodeValue)) {
                        $el->nodeValue = ' ' . $el->nodeValue . ' ';
                    }
                } catch (Exception $e) {
                }
            }
            $as = $xpath->query('//a');
            foreach ($as as $a) {
                try {
                    $href = isset($el->getAttribute) ? $a->getAttribute('href') : false;
                    if ($href) {
                        if (strpos($href, 'http') !== 0 || strpos($href, 'www') !== 0) {
                            $base_url_parts = parse_url($url);
                            $base_protocol = isset($base_url_parts['scheme']) ? $base_url_parts['scheme'] . ':' : '';
                            $base_host = isset($base_url_parts['host']) ? '//' . $base_url_parts['host'] : '';
                            $base_path = isset($base_url_parts['path']) ? rtrim(dirname($base_url_parts['path']), '/') : '';
                            $continue = true;
                            if (substr($href, 0, 2) == "//") {
                                $href = $base_protocol . $href;
                                $continue = false;
                            }
                            if ($href[0] == '/') {
                                $href = $base_protocol . $base_host . $href;
                                $continue = false;
                            }
                            if (preg_match('/^\s*$/', $href)) {
                                $href = '';
                                $continue = false;
                            }
                            if ($continue) {
                                if (substr($href, 0, 2) == './') {
                                    $href = '.' . $href;
                                    $base_full_path = $base_protocol . $base_host . $base_path;
                                    $href = rtrim($base_full_path, '/') . '/' . ltrim($href, '/');
                                }
                            }
                            while (preg_match('/\/\.\.\//', $href)) {
                                $href = preg_replace('/[^\/]+\/+\.\.\//', '', $href);
                            }
                            $href = str_replace(['."', "/./", '"', "'", '<', '>'], ['', '/', '%22', '%27', '%3C', '%3E'], $href);
                        }
                        $a->nodeValue = ' ' . $href;
                    }
                } catch (Exception $e) {
                }
            }
            $h2s = $xpath->query('//h1/following-sibling::h2[1]');
            foreach ($h2s as $h2) {
                try {
                    $prev = $h2->previousSibling;
                    if ($prev && isset($prev->nodeValue) && $prev->nodeName === 'h1') {
                        $prev->nodeValue .= ' ' . $h2->textContent;
                        $h2->parentNode->removeChild($h2);
                    }
                } catch (Exception $e) {
                }
            }
            $headers = $xpath->query('//h1 | //h2');
            foreach ($headers as $header) {
                try {
                    if (isset($header->nodeValue)) {
                        $header->nodeValue = '--------------------------------------------------------------------------------' . $header->nodeValue . ' \n';
                    }
                } catch (Exception $e) {
                }
            }
            $all_headers = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
            foreach ($all_headers as $header) {
                try {
                    if (isset($header->nodeValue)) {
                        $header->nodeValue = '###P###' . $header->nodeValue;
                    }
                } catch (Exception $e) {
                }
            }
            $text_content = $dom->textContent;
            $list = explode('###P###', $text_content);
            $list_2 = [];
            foreach ($list as $text) {
                try {
                    $text = preg_replace('/\s\s+/', ' ', $text);
                    if (str_word_count($text) > 5 && strlen($text) > 20) {
                        $list_2[] = str_replace('###B###', ' \n ', $text);
                    }
                } catch (Exception $e) {
                }
            }
            $list = [];
            for ($i = 0; $i < count($list_2); $i++) {
                $text = trim($list_2[$i]);
                while (strlen($text) < 300 && count($list_2) > $i + 1) {
                    $text .= (in_array(substr($text, -1), ['.', ',', ':', '!', '?', ';', '።', '।', '。', '။']) ? '' : ' . ') . trim($list_2[$i + 1]);
                    $i++;
                }
                $text = str_replace(' .', '.', $text);
                if (substr($text, 0, 10) == '----------') {
                    $text = substr($text, 80);
                }
                while (substr($text, 0, 4) == 'http') {
                    $text = substr($text, strpos($text, ' ') + 1);
                }
                if (strlen($text) > 3500) {
                    $texts = explode('\n', $text);
                    $text = '';
                    foreach ($texts as $j => $t) {
                        if (strlen($text) + strlen($t) < 3500 || $j == count($texts) - 1) {
                            $text .= $t;
                        } else {
                            $temp = sb_open_ai_html_to_paragraphs_2($text, $language, $url);
                            $paragraphs[] = $temp[0];
                            $duplicate_check_strings = array_merge($duplicate_check_strings, $temp[1]);
                            $text = '';
                        }
                    }
                } else {
                    $temp = sb_open_ai_html_to_paragraphs_2($text, $language, $url);
                    $paragraphs[] = $temp[0];
                    $duplicate_check_strings = array_merge($duplicate_check_strings, $temp[1]);
                }
            }
        }

        // Check for duplicated content
        $embedding_sources = sb_get_external_setting('embedding-sources', []);
        $count = count($duplicate_check_strings);
        $count_paragraphs = count($paragraphs);
        foreach ($embedding_sources as $key => $value) {
            if (!sb_open_ai_is_file($key) && sb_isset(parse_url($url), 'host') == sb_isset(parse_url($key), 'host')) {
                for ($i = 0; $i < count($value); $i++) {
                    $text = implode('', array_column(sb_open_ai_embeddings_get_file($value[$i], true), 'text'));
                    for ($y = 0; $y < $count; $y++) {
                        $duplicate_check_string = trim($duplicate_check_strings[$y]);
                        if (strpos($text, $duplicate_check_string) !== false) {
                            for ($j = 0; $j < $count_paragraphs; $j++) {
                                $paragraphs[$j][0] = trim(str_replace($duplicate_check_string, '', $paragraphs[$j][0]));
                            }
                        }
                    }
                }
            }
        }
        return [$paragraphs, 200];
    }
    return $response;
}

function sb_open_ai_html_to_paragraphs_2($text, $language, $url) {
    if (substr($text, 0, 10) == '----------') {
        $text = substr($text, 80);
    }
    return [[preg_replace('!\s+!', ' ', str_replace(['\\n', '\n'], ' ', $text)), $language, $url], explode("\n", str_replace(['\\n', '\n'], "\n", $text))];
}

function sb_open_ai_trainig_server_side() {
    ignore_user_abort(true);
    set_time_limit(900);
    $embedding_keys = sb_open_ai_get_training_source_names();
    sb_open_ai_embeddings_delete('all-website');
    for ($i = 0; $i < count($embedding_keys); $i++) {
        $key = $embedding_keys[$i];
        if (sb_open_ai_is_file($key) || in_array($key, ['sb-conversations', 'sb-articles', 'sb-database'])) {
            continue;
        }
        $urls = strpos($key, '.xml') ? sb_get_sitemap_urls($key) : [$key];
        for ($j = 0; $j < count($urls); $j++) {
            $paragraphs = sb_isset(sb_open_ai_html_to_paragraphs($urls[$j]), 0);
            if (count($paragraphs)) {
                $response = sb_open_ai_embeddings_generate($paragraphs, $urls[$j]);
                if ($response[1] == 'chars-limit-exceeded') {
                    die($response[1]);
                }
            }
        }
    }
    sb_open_ai_articles_training();
    return true;
}

function sb_open_ai_get_training_source_names() {
    return array_keys(sb_get_external_setting('embedding-sources', []));
}

function sb_open_ai_playground_message($messages) {
    $GLOBALS['SB_OPEN_AI_PLAYGROUND'] = [];
    $response = false;
    $count = $messages ? count($messages) : false;
    if ($count) {
        sb_db_query('DELETE FROM sb_users WHERE first_name = "open-ai-temp-user"');
        $conversation_id = sb_open_ai_dummy_data($messages);
        $response = sb_open_ai_message($messages[$count - 1][1], false, false, $conversation_id);
        $response[1] = $response[0] ? preg_replace("/(\n){3,}/", "\n\n", str_replace(["\r", "\t"], "", is_string($response[1]) || empty($response[1][0]) ? $response[1] : $response[1][0]['message'])) : sb_isset(sb_isset($response[1], 'error'), 'message');
        sb_open_ai_dummy_data('delete');
        array_push($response, $conversation_id);
    }
    return $response;
}

function sb_open_ai_dummy_data($messages) {
    sb_db_query('DELETE FROM sb_users WHERE first_name = "open-ai-temp-user"');
    if ($messages === 'delete') {
        return;
    }
    $user_id = sb_db_query('INSERT INTO sb_users(first_name, last_name, user_type, token, creation_time) VALUES ("open-ai-temp-user", "", "lead", "open-ai-temp-user", NOW())', true);
    $conversation_id = sb_db_query('INSERT INTO sb_conversations(user_id, title, creation_time) VALUES (' . $user_id . ', "open-ai-temp-conversation", NOW())', true);
    $query = '';
    for ($i = 0; $i < count($messages); $i++) {
        $query .= '(' . (sb_is_agent($messages[$i][0]) ? sb_get_bot_id() : $user_id) . ', ' . $conversation_id . ', "' . sb_db_escape($messages[$i][1]) . '", "", "' . sb_db_json_escape(sb_isset($messages[$i], 2, '')) . '", NOW()),';
    }
    if ($query) {
        sb_db_query('INSERT INTO sb_messages(user_id, conversation_id, message, attachments, payload, creation_time) VALUES ' . substr($query, 0, -1));
    }
    return $conversation_id;
}

function sb_open_ai_is_file($url) {
    $extension = substr($url, -4);
    return in_array($extension, ['.pdf', '.txt']) ? $extension : false;
}

function sb_open_ai_execute_set_data($user_data, $conversation_id) {
    $full_name = sb_isset($user_data, 'full_name');
    if ($full_name) {
        $full_name = sb_split_name($full_name);
        $user_data['first_name'] = $full_name[0];
        $user_data['last_name'] = $full_name[1];
    }
    return sb_update_user(sb_get_active_user_ID(), $user_data, $user_data);
}

function sb_open_ai_execute_actions($data, $conversation_id) {
    $client_side_payload = [];
    $attachments_response = [];
    for ($i = 0; $i < count($data); $i++) {
        $action = $data[$i];
        switch ($action[0]) {
            case 'tags':
                sb_tags_update($conversation_id, explode(strpos($action[1], '|') ? '|' : ',', $action[1]));
                break;
            case 'agent':
                sb_update_conversation_agent($conversation_id, $action[1]);
                break;
            case 'department':
                sb_update_conversation_department($conversation_id, $action[1]);
                break;
            case 'send_email_agents':
            case 'send_email':
            case 'transcript_email':
            case 'archive_conversation':
                $is_archive_conversation = $action[0] == 'archive_conversation';
                $is_transcript = $action[0] == 'transcript_email' || $is_archive_conversation;
                if ($is_archive_conversation) {
                    sb_update_conversation_status($conversation_id, 3);
                    if (sb_get_multi_setting('close-message', 'close-active')) {
                        sb_close_message($conversation_id, sb_get_bot_id());
                    }
                }
                if ((!$is_archive_conversation || sb_get_multi_setting('close-message', 'close-transcript')) && sb_isset(sb_get_active_user(), 'email')) {
                    $attachments = [];
                    if ($is_transcript) {
                        $transcript = sb_transcript($conversation_id);
                        $attachments = [[$transcript, $transcript]];
                    }
                    $bot = sb_db_get('SELECT profile_image, first_name FROM sb_users WHERE user_type = "bot" LIMIT 1');
                    sb_email_create($action[0] == 'send_email_agents' ? 'agents' : sb_get_active_user_ID(), $bot['first_name'], $bot['profile_image'], $is_transcript ? sb_get_multi_setting('transcript', 'transcript-message', '') : str_replace('|', ',', $action[1]), $attachments, true, $conversation_id);
                }
                break;
            case 'transcript':
                $transcript = sb_transcript($conversation_id);
                array_push($attachments_response, [$transcript, $transcript]);
                break;
            case 'redirect':
                $client_side_payload['redirect'] = 'https://' . str_replace('https://', '', $action[1]);
                break;
            case 'open_article':
                $client_side_payload['open_article'] = $action[1];
                break;
            case 'human_takeover':
                sb_dialogflow_human_takeover($conversation_id);
                break;
        }
    }
    return ['client_side_payload' => $client_side_payload, 'attachments' => $attachments_response];
}

function sb_open_ai_send_fallback_message($conversation_id) {
    sb_send_message(sb_get_bot_id(), $conversation_id, sb_(sb_get_multi_setting('open-ai', 'open-ai-fallback-message', 'Sorry, I didn\'t get that. Can you rephrase?')));
}

/*
 * -----------------------------------------------------------
 * FLOWS
 * -----------------------------------------------------------
 *
 * 1. Save the flows
 * 2. Return the flows
 * 3. Return the block message to send to the user
 * 4. Return a block by the string identifier
 * 5. Return the next block container of the given block
 * 6. Send the start message for flows that start on new converstations
 *
 */

function sb_flows_save($flows) {
    $flows = json_decode($flows, true);
    $previous_flows = sb_flows_get();
    $response = sb_save_external_setting('open-ai-flows', $flows);
    if ($response === true) {
        $paragraphs = [];
        $updated_flows = [];
        for ($i = 0; $i < count($flows); $i++) {
            $flow_name = 'flow-' . $flows[$i]['name'];
            $is_updated = true;

            // Check if the flow has been updated
            for ($j = 0; $j < count($previous_flows); $j++) {
                if ($previous_flows[$j] == $flows[$i]) {
                    $is_updated = false;
                    break;
                }
            }

            // Train the chatbot
            if ($is_updated) {
                $steps = $flows[$i]['steps'];
                $count = count($steps) - 1;
                for ($j = 0; $j < $count; $j++) {
                    $block_cnts = $steps[$j];
                    $index = 0;
                    for ($y = 0; $y < count($block_cnts); $y++) {
                        $blocks = $block_cnts[$y];
                        for ($x = 0; $x < count($blocks); $x++) {
                            $block = $blocks[$x];
                            if ($block['type'] == 'start' && $block['start'] == 'message' && !sb_isset($block, 'disabled')) {
                                $block_cnts_next_step = $steps[$j + 1];
                                $answer = '';
                                $answer_attachments = [];
                                $blocks_next = $block_cnts_next_step[$index];
                                for ($k = 0; $k < count($blocks_next); $k++) {
                                    $answer_block = sb_flows_get_block_code($blocks_next[$k], $flows[$i]['name'] . '_' . ($j + 1) . '_' . $index . '_' . $k) . ' ';
                                    $answer = sb_flows_merge_actions($answer, $answer_block);
                                    if (!empty($blocks_next[0]['attachments'])) {
                                        $answer_attachments = array_merge($answer_attachments, $blocks_next[0]['attachments']);
                                    }
                                }
                                if ($answer || !empty($answer_attachments)) {
                                    $extra = [];
                                    if (!empty($block['conditions'])) {
                                        $extra['conditions'] = $block['conditions'];
                                    }
                                    if (!empty($answer_attachments)) {
                                        $extra['attachments'] = $answer_attachments;
                                    }
                                    array_push($paragraphs, ['Question: ' . $block['message'] . PHP_EOL . PHP_EOL . 'Answer: ' . trim($answer), false, $flow_name, empty($extra) ? false : $extra]);
                                }
                                $index++;
                            }
                        }
                    }
                }
                array_push($updated_flows, $flow_name);
            }
        }

        // Delete the previous embeddings
        $embedding_sources_all = sb_get_external_setting('embedding-sources');
        $embedding_sources = sb_isset($embedding_sources_all, 'sb-flows', []);
        $embedding_sources_new = [];
        for ($i = 0; $i < count($embedding_sources); $i++) {
            $embeddings = sb_open_ai_embeddings_get_file($embedding_sources[$i], true);
            $embeddings_new = [];
            for ($y = 0; $y < count($embeddings); $y++) {
                if (!in_array($embeddings[$y]['source'], $updated_flows)) {
                    array_push($embeddings_new, $embeddings[$y]);
                }
            }
            $file_path = sb_open_ai_embeddings_get_file($embedding_sources[$i]);
            if (empty($embeddings_new)) {
                sb_file_delete($file_path);
            } else {
                array_push($embedding_sources_new, $embedding_sources[$i]);
                if ($embeddings_new != $embeddings) {
                    sb_file($file_path, json_encode($embeddings_new, JSON_UNESCAPED_UNICODE));
                }
            }
        }
        if ($embedding_sources_new != $embedding_sources) {
            $embedding_sources_all['sb-flows'] = $embedding_sources_new;
            sb_save_external_setting('embedding-sources', $embedding_sources_all);
        }

        // Return
        $response = sb_open_ai_embeddings_generate($paragraphs, 'sb-flows');
        return $response[0] ? true : $response;
    }
    return $response;
}

function sb_flows_get($flow_name = false) {
    $flows = sb_get_external_setting('open-ai-flows', []);
    if ($flow_name) {
        for ($i = 0; $i < count($flows); $i++) {
            if ($flows[$i]['name'] == $flow_name) {
                return $flows[$i];
            }
        }
        return false;
    }
    return $flows;
}

function sb_flows_get_block_code($block, $flow_identifier) {
    switch ($block['type']) {
        case 'button_list':
            $options_text = '';
            for ($i = 0; $i < count($block['options']); $i++) {
                $options_text .= $block['options'][$i] . ',';
            }
            return '[chips id="flow_' . $flow_identifier . '" options="' . substr($options_text, 0, -1) . '" message="' . sb_merge_fields($block['message']) . '"]';
        case 'message':
            return sb_merge_fields($block['message']);
        case 'video':
            preg_match('/(?:v=|\/)([a-zA-Z0-9_-]{11})/', $block['url'], $matches);
            return $matches ? sb_merge_fields($block['message']) . ' [video type="' . (strpos($block['url'], 'vimeo') ? 'vimeo' : 'youtube') . '" id="' . $matches[1] . '"]' : false;
        case 'get_user_details':
            return '[action flow-so="' . $flow_identifier . '"]';
        case 'action':
        case 'set_data':
            $string = '';
            $slugs = $block['type'] == 'action' ? ['actions', 'actions'] : ['data', 'set-data'];
            $items = $block[$slugs[0]];
            for ($i = 0; $i < count($items); $i++) {
                $string .= $items[$i][0] . (empty($items[$i][1]) ? '' : ':' . str_replace(',', '|', $items[$i][1])) . ',';
            }
            return '[action ' . $slugs[1] . '="' . substr($string, 0, -1) . '"]';
        case 'rest_api':
            return '[action rest-api="' . $flow_identifier . '"]';
        case 'condition':
            $flow_identifier = explode('_', $flow_identifier);
            $next_block_cnt = sb_flows_get_next_block_cnt($flow_identifier[0], $flow_identifier[1], $flow_identifier[2], sb_automations_validate($block['conditions'], true) ? 0 : 1);
            $response = '';
            if ($next_block_cnt) {
                for ($i = 0; $i < count($next_block_cnt[0]); $i++) {
                    $block_code = sb_flows_get_block_code($next_block_cnt[0][$i], $flow_identifier[0] . '_' . ($flow_identifier[1] + 1) . '_' . $next_block_cnt[1] . '_' . $i);
                    if (strpos($block_code, '[action ') !== false) {
                        $response = sb_flows_merge_actions($response, $block_code);
                    } else {
                        $response .= ' ' . $block_code;
                    }
                }
            }
            return $response;
    }
    return false;
}

function sb_flows_get_by_string($flow_identifier, $type = 'block') {
    $flow_identifier = explode('_', $flow_identifier);
    $flow = sb_flows_get($flow_identifier[0]);
    $response = false;
    if ($flow) {
        $flow = $flow['steps'][$flow_identifier[1]];
        switch ($type) {
            case 'block_cnts':
                $response = $flow;
                break;
            case 'blocks':
                $response = $flow[$flow_identifier[2]];
                break;
            case 'block':
                $response = $flow[$flow_identifier[2]][$flow_identifier[3]];
                break;
        }
        $response['index'] = $flow_identifier;
    }
    return $response;
}

function sb_flows_get_next_block_cnt($flow_name, $current_step_index, $current_block_cnt_index, $current_connector_index = 0) {
    $flow = sb_isset(sb_flows_get($flow_name), 'steps');
    if ($flow && isset($flow[$current_step_index + 1])) {
        $current_block_cnts = $flow[$current_step_index];
        $next_block_cnt_index = $current_connector_index;
        for ($i = 0; $i < $current_block_cnt_index; $i++) {
            $blocks = $current_block_cnts[$i];
            for ($j = 0; $j < count($blocks); $j++) {
                if ($blocks[$j]['type'] == 'button_list') {
                    $next_block_cnt_index += count($blocks[$j]['options']);
                } else if ($blocks[$j]['type'] == 'get_user_details') {
                    $next_block_cnt_index++;
                } else if ($blocks[$j]['type'] == 'condition') {
                    $next_block_cnt_index += 2;
                }
            }
        }
        return [$flow[$current_step_index + 1][$next_block_cnt_index], $next_block_cnt_index];
    }
    return false;
}

function sb_flows_on_conversation_start_or_load($messages, $language, $conversation_id, $is_on_load = false) {
    $flows = sb_flows_get();
    $response = false;
    for ($i = 0; $i < count($flows); $i++) {
        $flow = $flows[$i];
        if ($flow) {
            $start_step = $flow['steps'][0][0][0];
            if ($start_step['start'] == ($is_on_load ? 'load' : 'conversation') && !$start_step['disabled'] && sb_automations_validate($start_step['conditions'], true)) {
                $next_block_cnt = sb_isset($flow['steps'][1], 0, []);
                $code = '';
                if (!empty($next_block_cnt)) {
                    $flow_id = $flow['name'] . '_1_0_';
                    for ($j = 0; $j < count($next_block_cnt); $j++) {
                        $code = sb_flows_merge_actions($code, sb_flows_get_block_code($next_block_cnt[$j], $flow_id . $j));
                    }
                }
                if ($is_on_load) {
                    return $code;
                }
                $action = sb_flows_execute($code, $messages, $language, $conversation_id);
                sb_send_message(sb_get_bot_id(), $conversation_id, $action[0] ? $action[0] : $code, $action[2]);
                $response = $action[0] ? $action[0] : $code;
            }
        }
    }
    return $response;
}

function sb_flows_execute($message, $messages, $language, $conversation_id) {
    global $SB_OPEN_AI_PLAYGROUND;
    $action = sb_get_shortcode($message, 'action');
    $count = count($messages);
    $response = false;
    $attachments_response = [];
    $client_side_payload = [];
    if ($action) {
        $response = str_replace($action['shortcode'], '', $message);
        $flow_shortcode = sb_isset($action, 'flow-so');
        if ($flow_shortcode) {
            $user_message_payload = json_decode(sb_isset($messages[$count - 1], 'payload', '{}'), true);
            $user_message_payload['flow_so'] = $flow_shortcode;
            $block = sb_flows_get_by_string($flow_shortcode);
            sb_db_query('UPDATE sb_messages SET payload = "' . sb_db_json_escape($user_message_payload) . '" WHERE id = ' . $messages[$count - 1]['id']);
            if ($SB_OPEN_AI_PLAYGROUND !== null) {
                $SB_OPEN_AI_PLAYGROUND['payload'] = $user_message_payload;
            }
            $response = sb_merge_fields(sb_t($block['message'], $language));
        }
        if (isset($action['set-data'])) {
            $data = explode(',', $action['set-data']);
            $user_data = [];
            for ($i = 0; $i < count($data); $i++) {
                $data_item = explode(':', $data[$i]);
                $user_data[$data_item[0]] = $data_item[1];
            }
            sb_open_ai_execute_set_data($user_data, $conversation_id);
        }
        if (isset($action['actions'])) {
            $data = explode(',', $action['actions']);
            $execute_actions = sb_open_ai_execute_actions(array_map(function ($item) {
                return explode(':', $item);
            }, $data), $conversation_id);
            $client_side_payload = $execute_actions['client_side_payload'];
            $attachments_response = $execute_actions['attachments'];
        }
        if (isset($action['rest-api'])) {
            $block = sb_flows_get_by_string($action['rest-api']);
            if ($block) {
                $headers = array_map(function ($items) {
                    return implode(':', $items);
                }, $block['headers']);
                $body = json_decode(sb_isset($block, 'body', '{}'), true);
                $body['sb'] = ['user' => sb_get_active_user(), 'user_language' => sb_get_user_language(sb_get_active_user_ID())];
                $response_rest_api = json_decode(sb_curl($block['url'], $body, $headers, $block['method']), true);
                $save_response = sb_isset($block, 'save_response');
                if ($save_response) {
                    $user_data = [];
                    for ($i = 0; $i < count($save_response); $i++) {
                        $keys = explode('.', $save_response[$i][1]);
                        $response_rest_api_now = $response_rest_api;
                        foreach ($keys as $key) {
                            if (isset($response_rest_api_now[$key])) {
                                $response_rest_api_now = $response_rest_api_now[$key];
                            } else {
                                $response_rest_api_now = false;
                                break;
                            }
                        }
                        if ($response_rest_api_now) {
                            $user_data[$save_response[$i][0]] = $response_rest_api_now;
                        }
                        if (!empty($user_data)) {
                            sb_update_user(sb_get_active_user_ID(), $user_data, $user_data);
                        }
                    }
                }
            }
        }
    }
    return [$response, $client_side_payload, $attachments_response, $action];
}

function sb_flows_merge_actions($actions_string, $action_string) {
    if (strpos($actions_string, '[action ') === false) {
        return $actions_string . ' ' . $action_string;
    }
    return str_replace('[action', '[action ' . str_replace(['[action', ']'], '', $action_string), $actions_string);
}

function sb_flows_get_open_ai_message_response($flow_name, $current_step_index, $current_block_cnt_index, $current_connector_index, $payload) {
    $next_block_cnt = sb_flows_get_next_block_cnt($flow_name, $current_step_index, $current_block_cnt_index, $current_connector_index);
    $response = false;
    if ($next_block_cnt) {
        $response = '';
        for ($i = 0; $i < count($next_block_cnt[0]); $i++) {
            $next_flow_name = $flow_name . '_' . ($current_step_index + 1) . '_' . $next_block_cnt[1] . '_' . $i;
            $block_code = sb_flows_get_block_code($next_block_cnt[0][$i], $next_flow_name);
            if (strpos($block_code, '[action ') !== false) {
                if ($next_block_cnt[0][$i]['type'] == 'get_user_details') {
                    $payload['flow_so'] = $next_flow_name;
                }
                $response = sb_flows_merge_actions($response, $block_code);
            } else {
                $response .= ' ' . $block_code;
            }
        }
    }
    return [$response, $payload];
}

function sb_open_ai_get_max_tokens($model) {
    $max_tokens_list = ['gpt-3.5-turbo' => 4097, 'gpt-3.5-turbo-1106' => 16385, 'gpt-3.5-turbo-instruct' => 4097, 'gpt-4' => 8192, 'gpt-4-turbo' => 128000, 'gpt-4o' => 128000, 'gpt-4o-mini' => 128000];
    $open_ai_max_tokens = sb_isset($max_tokens_list, $model);
    if (!$open_ai_max_tokens) {
        foreach ($max_tokens_list as $key => $value) {
            if (strpos($model, $key) !== false) {
                return $value;
            }
        }
        return 99999;
    }
    return $open_ai_max_tokens;
}

function sb_flows_run_on_load($message, $conversation_id, $language = false) {
    $action = sb_get_shortcode($message, 'action');
    $flow_shortcode = sb_isset($action, 'flow-so');
    if ($flow_shortcode) {
        $block = sb_flows_get_by_string($flow_shortcode);
        return sb_send_message(sb_get_bot_id(), $conversation_id, sb_merge_fields(sb_t($block['message'], is_array($language) ? $language[0] : $language)), [], 3, ['flow_so' => $flow_shortcode]);
    }
    return false;
}

/*
 * -----------------------------------------------------------
 * GOOGLE
 * -----------------------------------------------------------
 *
 * 1. Detect the language of a string
 * 2. Retrieve the full language name in the desired language
 * 3. Text translation
 * 4. Analyze Entities
 * 5. Return the client ID and secret key
 * 6. Return the message in the desired language
 * 7. Google troubleshooting
 *
 */

function sb_google_language_detection($string, $token = false) {
    $token = $token ? $token : sb_dialogflow_get_token(sb_defined('GOOGLE_REFRESH_TOKEN'));
    $query = json_encode(['q' => $string], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    if (!sb_cloud_membership_has_credits('google')) {
        return sb_error('no-credits', 'sb_google_get_language_name');
    }
    $response = sb_curl('https://translation.googleapis.com/language/translate/v2/detect', $query, ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Content-Length: ' . strlen($query)]);
    sb_cloud_membership_use_credits('translation', 'google', $string);
    if (isset($response['error']) && $response['error']['status'] == 'UNAUTHENTICATED') {
        global $sb_recursion_dialogflow;
        if ($sb_recursion_dialogflow[0]) {
            $sb_recursion_dialogflow[0] = false;
            $token = sb_dialogflow_get_token(sb_defined('GOOGLE_REFRESH_TOKEN'));
            return sb_google_language_detection($string, $token);
        }
    }
    return isset($response['data']) && isset($response['data']['detections']) ? sb_language_code($response['data']['detections'][0][0]['language']) : false;
}

function sb_google_get_language_name($target_language_code, $token = false) {
    $token = $token ? $token : sb_dialogflow_get_token(sb_defined('GOOGLE_REFRESH_TOKEN'));
    $query = json_encode(['target' => $target_language_code], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    $response = sb_curl('https://translation.googleapis.com/language/translate/v2/languages', $query, ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Content-Length: ' . strlen($query)]);
    if (isset($response['data'])) {
        $languages = $response['data']['languages'];
        for ($i = 0; $i < count($languages); $i++) {
            if ($languages[$i]['language'] == $target_language_code) {
                return $languages[$i]['name'];
            }
        }
    }
    return $response;
}

function sb_google_translate($strings, $language_code, $token = false, $message_ids = false, $conversation_id = false) {
    $translations = [];
    $token = $token ? $token : sb_dialogflow_get_token(sb_defined('GOOGLE_REFRESH_TOKEN'));
    $chunks = array_chunk($strings, 125);
    $language_code = strtolower(substr(sb_dialogflow_language_code($language_code), 0, 2));
    $language_code = sb_isset(['br' => 'pt'], $language_code, $language_code);
    $shortcode_replacements = [
        ['[chips ', '[buttons ', '[button ', '[select ', '[email ', '[articles ', '[rating ', '[list ', '[list-image ', '[table ', '[inputs ', '[card ', '[slider ', '[slider-images ', '[video ', '[image ', '[share ', '[registration]', '[timetable]', '[email]', '[articles]', ' options="', ' title="', ' message="', ' success="', ' placeholder="', ' name="', ' phone="', ' phone-required="', ' link="', ' label="', '  label-positive="', ' label-negative="', ' success-negative="', ' values="', ' header="', ' button="', ' image="', ' target="', ' extra="', ' link-text="', ' type="', ' height="', ' id="', ' url="', ' numeric="true', ']'],
        ['[1 ', '[2 ', '[3 ', '[4 ', '[5 ', '[7 ', '[9 ', '[10 ', '[11 ', '[12 ', '[13 ', '[14 ', '[15 ', '[16 ', '[17', '[18', '[19', '[20', '[21', ' 22="', ' 23="', ' 24="', ' 25="', ' 26="', ' 27="', ' 28="', ' 29="', ' 30="', ' 31="', ' 32="', ' 33="', ' 34="', ' 35="', ' 36="', ' 37="', ' 38="', ' 39="', ' 40="', ' 41="', ' 42="', ' 43="', ' 44="', ' 45="', ' 46=', ' 47=', ' 48=', ' 49=']
    ];
    $skipped_translations = [];
    $strings_original = $strings;
    if (!sb_cloud_membership_has_credits('google')) {
        return sb_error('no-credits', 'sb_dialogflow_message');
    }
    for ($j = 0; $j < count($chunks); $j++) {
        $strings = $chunks[$j];
        for ($i = 0; $i < count($strings); $i++) {
            $string = $strings[$i];
            if (strpos($string, '[') !== false || strpos($string, '="') !== false) {
                $string = str_replace($shortcode_replacements[0], $shortcode_replacements[1], $string);
                $string = str_replace('="true"', '="1"', $string);
            }
            preg_match_all('/`[\S\s]*?`/', $string, $matches);
            $matches = $matches[0];
            array_push($skipped_translations, $matches);
            for ($y = 0; $y < count($matches); $y++) {
                if ($matches[$y] != '``') {
                    $string = str_replace($matches[$y], '"' . $y . '"', $string);
                }
            }
            $strings[$i] = str_replace('"', '«»', str_replace(['\r\n', PHP_EOL, '\r', '\n'], '~~', $string));
        }
        $query = json_encode(['q' => $strings, 'target' => $language_code, 'format' => 'text'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        $response = sb_curl('https://translation.googleapis.com/language/translate/v2', $query, ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Content-Length: ' . strlen($query)]);
        if ($response && isset($response['data'])) {
            sb_cloud_membership_use_credits('translation', 'google', json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
            $translations_partial = sb_isset($response['data'], 'translations', []);
            for ($i = 0; $i < count($translations_partial); $i++) {
                $string = $translations_partial[$i]['translatedText'];
                while (mb_substr($string, 0, 1) == '"') {
                    $string = mb_substr($string, 1);
                }
                $string = str_replace([PHP_EOL, '\r\n', '\r', '<br>', '~~', '”', '«»', '« »', '»»', '««', '_}', '“', '""'], ["\n", "\n", "\n", "\n", "\n", '"', '"', '"', '"', '"', '}', '', '"'], $string);
                for ($y = 0; $y < count($skipped_translations[$i]); $y++) {
                    $string = str_replace('"' . $y . '"', $skipped_translations[$i][$y], $string);
                }
                $string = str_replace($shortcode_replacements[1], $shortcode_replacements[0], str_replace('44 =', '44=', $string));
                $string = str_replace('="1"', '="true"', $string);
                array_push($translations, $string);
            }
        } else {
            $error = sb_isset($response, 'error');
            if ($error) {
                if (sb_isset($error, 'status') == 'UNAUTHENTICATED') {
                    global $sb_recursion_dialogflow;
                    if ($sb_recursion_dialogflow[0]) {
                        $sb_recursion_dialogflow[0] = false;
                        $token = sb_dialogflow_get_token(sb_defined('GOOGLE_REFRESH_TOKEN'));
                        return sb_google_translate($strings_original, $language_code, $token);
                    }
                }
                sb_error('error', 'sb_google_translate', $error, sb_is_agent());
                return [$strings_original, $token];
            }
        }
    }
    $count = count($translations);
    if ($count && $message_ids && $conversation_id && $count == count($message_ids)) {
        $data = sb_db_get('SELECT id, payload FROM sb_messages WHERE id IN (' . implode(',', $message_ids) . ') AND conversation_id = ' . sb_db_escape($conversation_id, true), false);
        for ($i = 0; $i < $count; $i++) {
            if (strlen($string) > 1) {
                $payload = json_decode($data[$i]['payload'], true);
                $payload['translation'] = $translations[$i];
                $payload['translation-language'] = $language_code;
                sb_db_query('UPDATE sb_messages SET payload = "' . sb_db_json_escape($payload) . '" WHERE id = ' . $data[$i]['id']);
            }
        }
    }
    return [$count ? $translations : $response, $token];
}

function sb_google_translate_auto($string, $user_id) {
    if (is_numeric($user_id) && (sb_get_setting('google-translation') || sb_get_multi_setting('google', 'google-translation'))) { // Deprecated: sb_get_setting('google-translation')
        $recipient_language = sb_get_user_language($user_id);
        $active_user_language = sb_get_user_language(sb_get_active_user_ID());
        if ($recipient_language && $active_user_language && $recipient_language != $active_user_language) {
            $translation = sb_google_translate([$string], $recipient_language)[0];
            if (count($translation)) {
                $translation = trim($translation[0]);
                if (!empty($translation)) {
                    return $translation;
                }
            }
        }
    }
    return $string;
}

function sb_google_translate_article($article_id, $language_code) {
    $article = sb_get_articles($article_id, false, true);
    if (count($article)) {
        $article = $article[0];
        $editos_js = json_decode($article['editor_js'], true);
        $blocks = sb_isset($editos_js, 'blocks', []);
        $strings = [$article['title']];
        foreach ($blocks as $block) {
            switch ($block['type']) {
                case 'header':
                case 'paragraph':
                    array_push($strings, html_entity_decode($block['data']['text']));
                    break;
                case 'list':
                    foreach ($block['data']['items'] as $item) {
                        array_push($strings, html_entity_decode($item));
                    }
                    break;
            }
        }
        $strings_translated = sb_google_translate($strings, $language_code);
        if (sb_is_error($strings_translated)) {
            return $article;
        }
        $index = 1;
        for ($i = 0; $i < count($blocks); $i++) {
            switch ($blocks[$i]['type']) {
                case 'header':
                case 'paragraph':
                    $blocks[$i]['data']['text'] = $strings_translated[0][$index];
                    $index++;
                    break;
                case 'list':
                    $items = [];
                    foreach ($blocks[$i]['data']['items'] as $item) {
                        array_push($items, $strings_translated[0][$index]);
                        $index++;
                    }
                    $blocks[$i]['data']['items'] = $items;
            }
        }
        for ($i = 0; $i < count($strings); $i++) {
            $article['content'] = str_replace(htmlentities($strings[$i]), htmlentities($strings_translated[0][$i]), $article['content']);
        }
        $editos_js['blocks'] = $blocks;
        $article['title'] = $strings_translated[0][0];
        $article['language'] = $language_code;
        $article['parent_id'] = $article_id;
        $article['editor_js'] = $editos_js;
        unset($article['id']);
        return $article;
    }
    return false;
}

function sb_google_language_detection_update_user($string, $user_id = false, $token = false) {
    $user_id = $user_id ? $user_id : sb_get_active_user_ID();
    $detected_language = sb_google_language_detection($string, $token);
    $language = sb_get_user_language($user_id);
    if ($detected_language != $language[0] && !empty($detected_language)) {
        $response = sb_language_detection_db($user_id, $detected_language);
        if ($response) {
            unset($GLOBALS['SB_LANGUAGE']);
            return sb_get_current_translations();
        }
    }
    return false;
}

function sb_language_detection_db($user_id, $detected_language) {
    $response = sb_update_user_value($user_id, 'language', $detected_language);
    sb_db_query('DELETE FROM sb_users_data WHERE user_id = ' . sb_db_escape($user_id) . ' AND slug = "browser_language"');
    return $response;
}

function sb_google_language_detection_get_user_extra($message) {
    if ($message && (sb_get_multi_setting('google', 'google-language-detection') || sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-active'))) { // Deprecated: sb_get_multi_setting('dialogflow-language-detection', 'dialogflow-language-detection-active')
        return [sb_google_language_detection($message), 'Language'];
    }
    return '';
}

function sb_google_analyze_entities($string, $language = false, $token = false) {
    if (!strpos(trim($string), ' ')) {
        return false;
    }
    $token = $token ? $token : sb_dialogflow_get_token();
    $query = ['document' => ['type' => 'PLAIN_TEXT', 'content' => ucwords($string)]];
    if ($language) {
        $query['document']['language'] = $language;
    }
    $query = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    $response = sb_curl('https://language.googleapis.com/v1/documents:analyzeEntities', $query, ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Content-Length: ' . strlen($query)]);
    if (isset($response['error'])) {
        trigger_error($response['error']['message']);
    }
    return $response;
}

function sb_google_key() {
    return sb_ai_is_manual_sync('google') ? [trim(sb_get_multi_setting('google', 'google-client-id')), trim(sb_get_multi_setting('google', 'google-client-secret'))] : [GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET];
}

function sb_google_get_message_translation($message, $language = false) {
    $payload = json_decode(sb_isset($message, 'payload'), true);
    $translation = sb_isset($payload, 'original-message');
    if ($translation && (!$language || $language == sb_isset($payload, 'original-message-language'))) {
        $message['message'] = $translation;
    } else {
        $translation = sb_isset($payload, 'translation');
        if ($translation && (!$language || $language == sb_isset($payload, 'translation-language'))) {
            $message['message'] = $translation;
        }
    }
    return $message;
}

function sb_google_troubleshoot($debug = false) {
    if ($debug) {
        $_GET['debug'] = true;
    }
    if (sb_chatbot_active(true, false) || sb_get_setting('ai-smart-reply')) {
        $response = sb_dialogflow_message(false, 'Hello world');
        if (sb_is_error($response)) {
            return $response;
        }
        if ($response && isset($response['response']) && isset($response['response']['error'])) {
            return $response['response']['error']['message'];
        }
    }
    if (sb_get_multi_setting('google', 'google-multilingual-translation') || sb_get_multi_setting('google', 'google-translation') || sb_get_multi_setting('google', 'google-language-detection')) {
        $query = json_encode(['q' => ['hello world'], 'target' => 'it', 'format' => 'text']);
        $token = sb_dialogflow_get_token();
        if (sb_is_error($token)) {
            return $token;
        }
        $response = sb_curl('https://translation.googleapis.com/language/translate/v2', $query, ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Content-Length: ' . strlen($query)]);
        if ($response && isset($response['error'])) {
            return $response['error']['message'];
        }
    }
    if ($debug) {
        return true;
    } else {
        return sb_google_troubleshoot(true);
    }
}