<?php

/*
 * ==========================================================
 * COMPONENTS.PHP
 * ==========================================================
 *
 * Library of static html components for the Artificial Intelligence area. This file must not be executed directly. © 2017-2024 board.support. All rights reserved.
 *
 */

function sb_dialogflow_chatbot_area() { ?>
    <div class="sb-area-chatbot">
        <div class="sb-top-bar">
            <div>
                <h2>
                    <?php sb_e('Chatbot') ?>
                </h2>
                <div class="sb-menu-wide sb-menu-chatbot">
                    <div>
                        <?php sb_e('Chatbot') ?>
                    </div>
                    <ul>
                        <li data-type="training" class="sb-active">
                            <?php sb_e('Training') ?>
                        </li>
                        <li data-type="flows">
                            <?php sb_e('Flows') ?>
                        </li>
                        <li data-type="playground">
                            <?php sb_e('Playground') ?>
                        </li>
                        <li data-type="settings">
                            <?php sb_e('Settings') ?>
                        </li>
                        <?php sb_docs_link('#open-ai') ?>
                    </ul>
                </div>
            </div>
            <div>
                <a id="sb-train-chatbot" class="sb-btn sb-icon">
                    <i class="sb-icon-automation"></i>
                    <?php sb_e('Train chatbot') ?>
                </a>
            </div>
        </div>
        <div data-id="training" class="sb-tab sb-inner-tab sb-active">
            <div class="sb-nav">
                <ul>
                    <li data-value="files" class="sb-active">
                        <?php sb_e('Files') ?>
                    </li>
                    <li data-value="website">
                        <?php sb_e('Website') ?>
                    </li>
                    <li data-value="qea">
                        <?php sb_e('Q&A') ?>
                    </li>
                    <?php
                    if (sb_get_multi_setting('open-ai', 'open-ai-user-train-conversations')) {
                        echo '<li data-value="conversations">' . sb_('Conversations') . '</li>';
                    }
                    ?>
                    <li data-value="info">
                        <?php sb_e('Information') ?>
                    </li>
                </ul>
            </div>
            <div class="sb-content sb-scroll-area">
                <div class="sb-active">
                    <table id="sb-table-chatbot-files" class="sb-table sb-loading">
                        <tbody></tbody>
                    </table>
                    <div class="sb-flex">
                        <div id="sb-chatbot-add-files" class="sb-btn sb-icon sb-btn-white">
                            <i class="sb-icon-plus"></i>
                            <?php sb_e('Add new files') ?>
                        </div>
                        <div id="sb-chatbot-delete-files" class="sb-btn-icon sb-btn-red">
                            <i class="sb-icon-delete"></i>
                        </div>
                    </div>
                </div>
                <div>
                    <table id="sb-table-chatbot-website" class="sb-table sb-loading">
                        <tbody></tbody>
                    </table>
                    <hr />
                    <div id="sb-repeater-chatbot-website" data-type="repeater" class="sb-setting sb-type-repeater">
                        <div class="input">
                            <div class="sb-repeater">
                                <div class="repeater-item">
                                    <div>
                                        <label>
                                            <?php sb_e('URL') ?>
                                        </label>
                                        <input data-id="open-ai-sources-url" type="url" />
                                    </div>
                                    <div>
                                        <label>
                                            <?php sb_e('Extract URLs') ?>
                                        </label>
                                        <input data-id="open-ai-sources-extract-url" type="checkbox" />
                                    </div>
                                    <i class="sb-icon-close"></i>
                                </div>
                            </div>
                            <div class="sb-btn sb-repeater-add sb-btn-white sb-icon">
                                <i class="sb-icon-plus"></i>
                                <?php sb_e('Add new item') ?>
                            </div>
                            <div id="sb-chatbot-delete-website" class="sb-btn-icon sb-btn-red">
                                <i class="sb-icon-delete"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div id="sb-chatbot-qea" data-type="repeater" class="sb-setting sb-type-repeater">
                        <div class="input">
                            <div class="sb-repeater">
                                <div class="repeater-item">
                                    <div>
                                        <label>
                                            <?php sb_e('Question') ?>
                                        </label>
                                        <div>
                                            <div data-id="open-ai-faq-questions" class="sb-repeater">
                                                <div class="repeater-item">
                                                    <input data-id="question" placeholder="<?php sb_e('Add question') ?>" type="text" />
                                                    <i class="sb-icon-close"></i>
                                                </div>
                                            </div>
                                            <div class="sb-btn sb-btn-white sb-repeater-add sb-icon">
                                                <i class="sb-icon-plus"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sb-qea-repeater-answer">
                                        <label>
                                            <?php sb_e('Answer') ?>
                                            <i class="sb-btn-open-ai sb-icon-openai sb-btn-icon" data-sb-tooltip="<?php sb_e('Rewrite') ?>" data-sb-tooltip-init="true"></i>
                                        </label>
                                        <textarea data-id="open-ai-faq-answer"></textarea>
                                    </div>
                                    <div>
                                        <label>
                                            <?php sb_e('Function calling') ?>
                                        </label>
                                        <div class="sb-enlarger sb-enlarger-function-calling">
                                            <div>
                                                <label>
                                                    <?php sb_e('URL') ?>
                                                </label>
                                                <input data-id="open-ai-faq-function-calling-url" type="text" />
                                            </div>
                                            <div>
                                                <label>
                                                    <?php sb_e('Method') ?>
                                                </label>
                                                <select data-id="open-ai-faq-function-calling-method">
                                                    <option>POST</option>
                                                    <option value="GET">GET</option>
                                                    <option value="PUT">PUT</option>
                                                    <option value="PATH">PATH</option>
                                                    <option value="DELETE">DELETE</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>
                                                    <?php sb_e('Headers') ?>
                                                </label>
                                                <input data-id="open-ai-faq-function-calling-headers" type="text" />
                                            </div>
                                            <div>
                                                <label>
                                                    <?php sb_e('Properties') ?>
                                                </label>
                                                <div>
                                                    <div data-id="open-ai-faq-function-calling-properties" class="sb-repeater">
                                                        <div class="repeater-item">
                                                            <div>
                                                                <input data-id="name" type="text" placeholder="<?php sb_e('Name') ?>" />
                                                            </div>
                                                            <div>
                                                                <input data-id="description" type="text" placeholder="<?php sb_e('Description') ?>" />
                                                            </div>
                                                            <div>
                                                                <input data-id="allowed" type="text" placeholder="<?php sb_e('Allowed values separated by commas') ?>" />
                                                            </div>
                                                            <i class="sb-icon-close"></i>
                                                        </div>
                                                    </div>
                                                    <div class="sb-btn sb-btn-white sb-repeater-add sb-icon">
                                                        <i class="sb-icon-plus"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label>
                                            <?php sb_e('Set data and actions') ?>
                                        </label>
                                        <div class="sb-enlarger">
                                            <div class="sb-repeater" data-id="open-ai-faq-set-data">
                                                <div class="repeater-item">
                                                    <div>
                                                        <div class="sb-setting">
                                                            <select data-id="id">
                                                                <?php
                                                                $code = '';
                                                                $fields = array_merge([['name' => 'Name', 'id' => 'full_name'], ['name' => 'Email', 'id' => 'email'], ['name' => 'Password', 'id' => 'password']], sb_users_get_fields(), [['name' => 'Assign tags', 'id' => 'tags'], ['name' => 'Assign a department', 'id' => 'department'], ['name' => 'Assign an agent', 'id' => 'agent'], ['name' => 'Go to URL', 'id' => 'redirect'], ['name' => 'Show an article', 'id' => 'open_article'], ['name' => 'Download transcript', 'id' => 'transcript'], ['name' => 'Email transcript', 'id' => 'transcript_email'], ['name' => 'Send email to user', 'id' => 'send_email'], ['name' => 'Send email to agents', 'id' => 'send_email_agents'], ['name' => 'Archive the conversation', 'id' => 'archive_conversation'], ['name' => 'Human takeover', 'id' => 'human_takeover']]);
                                                                for ($i = 0; $i < count($fields); $i++) {
                                                                    $code .= '<option value="' . $fields[$i]['id'] . '">' . $fields[$i]['name'] . '</option>';
                                                                }
                                                                echo $code;
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="sb-setting">
                                                            <input data-id="value" type="text" placeholder="<?php sb_e('Enter the value') ?>" />
                                                        </div>
                                                    </div>
                                                    <i class="sb-icon-close"></i>
                                                </div>
                                            </div>
                                            <div class="sb-btn sb-btn-white sb-repeater-add sb-icon">
                                                <i class="sb-icon-plus"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <i class="sb-icon-close"></i>
                                </div>
                            </div>
                            <div class="sb-btn sb-btn-white sb-repeater-add sb-icon">
                                <i class="sb-icon-plus"></i>
                                <?php sb_e('Add new item') ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (sb_get_multi_setting('open-ai', 'open-ai-user-train-conversations')) { ?>
                    <div>
                        <div id="sb-chatbot-conversations">
                            <div data-type="repeater" class="sb-setting sb-type-repeater">
                                <div class="input">
                                    <div class="sb-repeater"></div>
                                </div>
                            </div>
                            <hr />
                            <div id="sb-chatbot-delete-all-training-conversations" class="sb-btn sb-btn-white">Delete all training data</div>
                        </div>
                    </div>
                <?php } ?>
                <div id="sb-chatbot-info" class="sb-active"></div>
            </div>
        </div>
        <div data-id="flows" class="sb-tab sb-inner-tab sb-loading">
            <div class="sb-nav sb-nav-only sb-scroll-area">
                <ul id="sb-flows-nav"></ul>
                <div id="sb-flow-add" class="sb-btn sb-icon sb-btn-white">
                    <i class="sb-icon-plus"></i>
                    <?php sb_e('Add new flow') ?>
                </div>
            </div>
            <div class="sb-content sb-scroll-area"></div>
            <i class="sb-flow-scroll sb-btn sb-btn-white sb-icon-arrow-left"></i>
            <i class="sb-flow-scroll sb-btn sb-btn-white sb-icon-arrow-right"></i>
        </div>
        <div data-id="playground">
            <div class="sb-flex">
                <div class="sb-playground">
                    <div class="sb-scroll-area sb-list"></div>
                    <div class="sb-no-results">
                        <?php sb_e('Send a message') ?>
                    </div>
                    <div class="sb-playground-editor">
                        <div class="sb-setting">
                            <textarea placeholder="<?php sb_e('Write a message...') ?>"></textarea>
                        </div>
                        <div class="sb-flex">
                            <div class="sb-flex">
                                <div data-value="user" class="sb-btn sb-btn-white sb-icon">
                                    <i class="sb-icon-reload"></i>
                                    <?php sb_e('User') ?>
                                </div>
                                <i data-value="clear" class="sb-icon-close sb-btn-icon sb-btn-red"></i>
                            </div>
                            <div class="sb-flex">
                                <div data-value="add" class="sb-btn sb-btn-white">
                                    <?php sb_e('Add') ?>
                                </div>
                                <div data-value="send" class="sb-btn sb-btn-white sb-icon" data-sb-tooltip="<?php sb_e('Send message') ?>">
                                    <i class="sb-icon-send"></i>
                                    <?php sb_e('Send') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sb-playground-info"></div>
            </div>
        </div>
    </div>
<?php } ?>
<?php
function sb_dialogflow_intent_box() {
    $is_dialogflow = sb_chatbot_active(true, false);
    ?>
    <div class="sb-lightbox sb-dialogflow-intent-box<?php echo $is_dialogflow ? '' : ' sb-dialogflow-disabled' ?>">
        <div class="sb-info"></div>
        <div class="sb-top-bar">
            <div>
                <?php sb_e('Chatbot Training') ?><a href="<?php echo sb_is_cloud() ? sb_defined('SB_CLOUD_DOCS', '') : 'https://board.support/docs' ?>#chatbot-training-window" target="_blank">
                    <i class="sb-icon-help"></i>
                </a>
            </div>
            <div>
                <a class="sb-send sb-btn sb-icon">
                    <i class="sb-icon-check"></i>
                    <?php sb_e('Train chatbot') ?>
                </a>
                <a class="sb-close sb-btn-icon sb-btn-red">
                    <i class="sb-icon-close"></i>
                </a>
            </div>
        </div>
        <div class="sb-main sb-scroll-area">
            <div class="sb-title sb-intent-add">
                <?php echo sb_('Question') . '<i data-value="add" data-sb-tooltip="' . sb_('Add question') . '" class="sb-btn-icon sb-icon-plus"></i><i data-value="previous" class="sb-btn-icon sb-icon-arrow-up"></i><i data-value="next" class="sb-btn-icon sb-icon-arrow-down"></i>' ?>
            </div>
            <div class="sb-setting sb-type-text sb-first">
                <input type="text" />
            </div>
            <div class="sb-title sb-bot-response">
                <?php
                sb_e('Answer');
                if (sb_get_multi_setting('open-ai', 'open-ai-rewrite')) {
                    echo '<i class="sb-btn-open-ai sb-btn-icon sb-icon-openai" data-sb-tooltip="' . sb_('Rewrite') . '"></i>';
                }
                ?>
            </div>
            <div class="sb-setting sb-type-textarea sb-bot-response">
                <textarea></textarea>
            </div>
            <div class="sb-title">
                <?php sb_e('Language') ?>
            </div>
            <?php
            echo sb_dialogflow_languages_list();
            if ($is_dialogflow) {
                echo '<div class="sb-title sb-title-search">' . sb_('Intent') . '<div class="sb-search-btn"><i class="sb-icon sb-icon-search"></i><input type="text" autocomplete="false" placeholder="' . sb_('Search for Intents...') . '" /></div><i id="sb-intent-preview" data-sb-tooltip="' . sb_('Preview') . '" class="sb-icon-help"></i></div><div class="sb-setting sb-type-select"><select id="sb-intents-select"></select></div>';
                if (sb_chatbot_active(false, true)) {
                    echo '<div class="sb-title">' . sb_('Services to update') . '</div><div class="sb-setting sb-type-select"><select id="sb-train-chatbots"><option value="">' . sb_('All') . '</option><option value="open-ai">OpenAI</option><option value="dialogflow">Dialogflow</option></select></div>';
                }
            } else {
                echo '<div class="sb-title sb-title-search">' . sb_('Q&A') . '<div class="sb-search-btn"><i class="sb-icon sb-icon-search"></i><input type="text" autocomplete="false" placeholder="' . sb_('Search for Q&A...') . '" /></div><i id="sb-qea-preview" data-sb-tooltip="' . sb_('Preview') . '" class="sb-icon-help"></i></div><div class="sb-setting sb-type-select"><select id="sb-qea-select"></select></div>';
            }
            ?>
        </div>
    </div>
<?php } ?>

