
/*
* 
* ===================================================================
* CLOUD FILE FOR SUPPORT BOARD ADMIN AREA
* ===================================================================
*
*/

(function ($) {
    var admin;
    var CLOUD_URL;
    var is_settings_loaded = false;
    var SBCloud = {
        removeAdminID: function (ids) {
            let index = ids.indexOf(SB_ADMIN_SETTINGS.cloud.id);
            if (index != -1) {
                ids.splice(index, 1);
            }
            return ids;
        },

        creditsAlert: function (element, e) {
            let id = $(element).closest('[id]').attr('id');
            if (SB_ADMIN_SETTINGS.credits <= 0 && (((id.includes('google') || id.includes('dialogflow')) && id != 'open-ai-spelling-correction-dialogflow' && admin.find('#google-sync-mode select').val() == 'auto') || (id.includes('open-ai') && admin.find('#open-ai-sync-mode select').val() == 'auto'))) {
                SBAdmin.genericPanel('credits-panel', 'Credits required', '<p>' + sb_('To use the {R} feature in automatic sync mode, credits are required. If you don\'t want to buy credits, switch to manual sync mode and use your own API key.').replace('{R}', '<b>' + $(element).prev().html() + '</b>') + '</p>', [['Buy credits', 'plus']]);
                SBAdmin.settings.input.reset(element);
                e.preventDefault();
                return true;
            }
            return false;
        }
    }

    window.SBCloud = SBCloud;

    function manualSyncSettingsVisibility(setting_name, show = true) {
        let selectors = { google: '#google-client-id, #google-client-secret, #google-refresh-token', 'open-ai': '#open-ai-key', 'whatsapp-cloud': '#whatsapp-twilio-btn, #whatsapp-cloud-key', 'messenger': '#messenger-key, #messenger-path-btn' };
        let selectors_hide = { 'whatsapp-cloud': '#whatsapp-cloud-sync-btn, #whatsapp-cloud-reconnect-btn' };
        let items = admin.find(selectors[setting_name]);
        let items_hide = admin.find(selectors_hide[setting_name]);
        items.sbActive(show);
        items_hide.sbActive(!show);
        if (!show) {
            items.each(function () {
                $(this).find('input').val('');
            });
        }
    }

    function sb_(text) {
        return SB_TRANSLATIONS && text in SB_TRANSLATIONS ? SB_TRANSLATIONS[text] : text;
    }


    function meta_sync(whatsapp = true) {
        let config = whatsapp ? { config_id: SB_CLOUD_WHATSAPP.configuration_id, response_type: 'code', override_default_response_type: true } : { config_id: SB_CLOUD_MESSENGER.configuration_id, response_type: 'token' };
        FB.logout();
        FB.login(function (response) {
            response = response.authResponse ? response.authResponse : false;
            if (response && ((whatsapp && response.code) || (!whatsapp && response.accessToken))) {
                let button = admin.find(whatsapp ? '#whatsapp-cloud-sync-btn a' : '#messenger-sync-btn a');
                if (SBAdmin.loading(button)) {
                    return;
                }
                ajax(whatsapp ? 'whatsapp-sync' : 'messenger-sync', { access_token: response.accessToken, code: response.code }, (response) => {
                    button.sbLoading(false);
                    if (response && ((whatsapp && response.access_token) || (!whatsapp && Array.isArray(response) && response.length))) {
                        let repeater = admin.find(whatsapp ? '#whatsapp-cloud-numbers' : '#messenger-pages');
                        let repeater_items = repeater.find('.repeater-item');
                        let count_start = repeater_items.length;
                        let index = 0;
                        if (count_start == 1 && !repeater_items.eq(0).find('input').eq(0).val()) {
                            count_start = 0;
                        }
                        let count_end = (whatsapp ? response.phone_numbers.length : response.length) + count_start;
                        let existing_items = repeater.find(whatsapp ? '[data-id=whatsapp-cloud-numbers-phone-id]' : '[data-id=messenger-page-id]').map(function () { return $(this).val() }).get();
                        for (var i = count_start; i < count_end; i++) {
                            if (!existing_items.includes(whatsapp ? response.phone_numbers[index] : response[index].page_id)) {
                                if (i >= repeater_items.length) {
                                    repeater.find('.sb-repeater-add').click();
                                    repeater_items = repeater.find('.repeater-item');
                                }
                                let repeater_item = repeater_items.last();
                                if (whatsapp) {
                                    repeater_item.find('[data-id=whatsapp-cloud-numbers-phone-id]').val(response.phone_numbers[index]);
                                    repeater_item.find('[data-id=whatsapp-cloud-numbers-token]').val(response.access_token);
                                    repeater_item.find('[data-id=whatsapp-cloud-numbers-account-id]').val(response.waba_id);
                                } else {
                                    repeater_item.find('[data-id=messenger-page-name]').val(response[index].name);
                                    repeater_item.find('[data-id=messenger-page-id]').val(response[index].page_id);
                                    repeater_item.find('[data-id=messenger-page-token]').val(response[index].access_token);
                                    repeater_item.find('[data-id=messenger-instagram-id]').val(response[index].instagram);
                                }
                            }
                            index++;
                        }
                        SBAdmin.settings.save();
                        SBAdmin.infoBottom('Synchronization completed.');
                    } else {
                        console.error(response);
                    }
                });
            }
        }, config);
    }

    function ajax(function_name, data = {}, onSuccess = false) {
        $.extend(data, { function: function_name });
        $.ajax({
            method: 'POST',
            url: 'account/ajax.php',
            data: data
        }).done((response) => {
            if (onSuccess) {
                onSuccess(response === false ? false : JSON.parse(response));
            }
        });
    }

    $(document).ready(function () {
        admin = $('.sb-admin');
        CLOUD_URL = SB_URL.substring(0, SB_URL.substring(0, SB_URL.length - 2).lastIndexOf('/'));

        // Disable apps for free plan
        if (DISABLE_APPS == 'true' && (!SB_CLOUD_MEMBERSHIP || SB_CLOUD_MEMBERSHIP == '0' || SB_CLOUD_MEMBERSHIP == 'free')) {
            admin.find('#tab-messenger,#tab-whatsapp,#tab-twitter,#tab-telegram,#tab-wechat,#tab-viber,#tab-line').hide();
            admin.find('[data-app="messenger"],[data-app="whatsapp"],[data-app="twitter"],[data-app="telegram"],[data-app="wechat"],[data-app="viber"],[data-app="line"],[data-app="zalo"]').addClass('sb-disabled');
        }

        $(document).on('SBSettingsLoaded', function (e, settings) {
            is_settings_loaded = true;

            // Credits and sync mode
            let settings_check = [['google', 'client-id'], ['open-ai', 'key'], ['whatsapp-cloud', 'key']];
            for (var i = 0; i < settings_check.length; i++) {
                let key = settings_check[i][0];
                if (settings[key] && ((settings[key][0][key + '-sync-mode'] && settings[key][0][key + '-sync-mode'][0] == 'manual') || settings[key][0][key + '-' + settings_check[i][1]][0])) {
                    manualSyncSettingsVisibility(key);
                    admin.find('#' + key + '-sync-mode select').val('manual');
                }
            }
            for (var key in SB_AUTO_SYNC) {
                if (!SB_AUTO_SYNC[key]) {
                    let element = admin.find('#' + key + '-sync-mode');
                    element.find('select').val('manual');
                    element.addClass('sb-hide');
                    manualSyncSettingsVisibility(key, true);
                }
            }
        });

        // Credits and sync mode
        admin.find('#sb-url .sb-setting-content').html('<h2>Installation URL</h2><p>This returns your installation URL.</p>');

        $(admin).on('change', '#google-sync-mode select, #open-ai-sync-mode select, #whatsapp-cloud-sync-mode select', function () {
            manualSyncSettingsVisibility($(this).parent().attr('id').replace('-sync-mode', ''), $(this).val() == 'manual');
            SBAdmin.infoBottom('Save changes to apply new sync mode.', 'info');
        });

        $(admin).on('click', '#open-ai-active input, #open-ai-spelling-correction input, #open-ai-spelling-correction-dialogflow input, #open-ai-rewrite input, #open-ai-speech-recognition input, #sb-train-chatbot, #dialogflow-sync-btn .sb-btn, #dialogflow-active input, #google-multilingual input, #google-multilingual-translation input, #google-translation input, #google-language-detection input', function (e) {
            if (SBCloud.creditsAlert(this, e)) {
                return false;
            }
        });

        $(admin).on('change', '#open-ai-mode select', function () {
            if ($(this).val() == 'assistant') {
                admin.find('#open-ai-sync-mode select').val('manual');
                admin.find('#open-ai-key').sbActive(true);
            }
        });

        $(admin).on('change', '#open-ai-sync-mode select', function () {
            if ($(this).val() == 'auto') {
                let select = admin.find('#open-ai-mode select');
                if (select.val() == 'assistant') {
                    select.val('');
                }
                admin.find('#open-ai-assistant-id').sbActive(false);
            }
        });

        if (SB_ADMIN_SETTINGS.credits_required) {
            let docs = admin.find('.sb-docs').attr('href');
            SBAdmin.infoBottom(sb_('You have used all of your credits. Add more credits {R}.').replace('{R}', '<a href="account?tab=membership#credits">' + sb_('here') + '</a>') + (docs ? '<a href="' + docs + '#cloud-credits" target="_blank" class="sb-icon-link"><i class="sb-icon-help"></i></a>' : ''), 'error');
        }

        // WhatsApp and Messenger
        let is_meta_sdk_loaded = false;
        $(admin).on('click', '#whatsapp-cloud-sync-btn .sb-btn, #whatsapp-cloud-reconnect-btn .sb-btn, #messenger-sync-btn a', function (e) {
            let id = $(this).parent().attr('id');
            let is_whatsapp = id != 'messenger-sync-btn';
            let reconnect = id == 'whatsapp-cloud-reconnect-btn' ? { scope: 'whatsapp_business_messaging, whatsapp_business_management, business_management' } : false;
            if (is_meta_sdk_loaded) {
                if (reconnect) {
                    FB.login(() => { }, reconnect);
                } else {
                    meta_sync(is_whatsapp);
                }
            } else {
                window.fbAsyncInit = function () {
                    FB.init(is_whatsapp ? { appId: SB_CLOUD_WHATSAPP.app_id, autoLogAppEvents: true, xfbml: true, version: 'v18.0' } : { appId: SB_CLOUD_MESSENGER.app_id, cookie: true, xfbml: true, version: 'v18.0' });
                };
                $.getScript('https://connect.facebook.net/en_US/sdk.js', () => {
                    is_meta_sdk_loaded = true;
                    if (reconnect) {
                        FB.login(() => { }, reconnect);
                    } else {
                        meta_sync(is_whatsapp);
                    }
                });
            }
            e.preventDefault()
            return false;
        });

        // Onboarding
        let URL = document.location.href;
        if ((URL.includes('board.support') || URL.includes('support-board')) && URL.includes('welcome')) {
            admin.find('#sb-settings').click();
            let items = [
                ['Notifications', 'Activate Push and Email notifications to receive alerts for incoming messages. On iPhone, the mobile app is required.', ['t-qzDPG88Xg', 'enb291Aai5Q'], '#notifications', 'Activate'],
                ['Chatbot', 'Activate the OpenAI chatbot and Human Takeover to transfer the chat to an agent when needed.', ['0p2YWQtsglg'], '#optimal-configuration-ai', 'Activate'],
                ['Try out the chat', 'Try out the chat and send a test message to test notifications and the chatbot functionalities.', ['mxjRevd_8bw'], '#widget-hidden', 'Try now', 'https://chat.cloud.board.support/' + SB_ADMIN_SETTINGS.cloud.chat_id],
                ['Mobile app', 'The admin area is a PWA that can be installed on iPhones, Android, and mobile devices.', ['IhoAlXFywFY'], '#pwa', 'Read more', 'https://board.support/docs#pwa?cloud']
            ];
            let code = '<div>';
            let checks = ['push-notifications-active', 'open-ai-active'];
            for (var i = 0; i < items.length; i++) {
                let id = `id="onboarding-${items[i][3].replace('#', '')}"`;
                let video = '';
                for (var j = 0; j < items[i][2].length; j++) {
                    video += `<a href="https://www.youtube.com/watch?v=${items[i][2][j]}" target="_blank"><img src="account/media/play-video.svg"></a>`;
                }
                code += `<div class="sb-setting"><div><h2>${items[i][0]} ${video}<a href="https://board.support/docs/${items[i][3]}" target="_blank"><i class="sb-icon-help"></i></a></h2><p>${items[i][1]}</p></div><div>${items[i][5] ? `<a ${id} href="${items[i][5]}" target="_blank" class="sb-btn">${items[i][4]}</a>` : `<div ${id} class="sb-btn">${items[i][4]}</div>`}</div></div>`;
            }
            setTimeout(() => {
                SBAdmin.genericPanel('onboarding', `Welcome ${admin.find('> .sb-header > .sb-admin-nav-right .sb-account .sb-name').html()} 👋`, '<p>Support Board is a powerful tool with many options. Let\'s start by activating the basic functionalities below. Don\'t hesitate to reach out to us through the chat on the docs page if you have any questions.</p>' + code + '</div>', [], '', true);
                for (var i = 0; i < checks.length; i++) {
                    if (admin.find('#' + checks[i] + ' input').prop('checked')) {
                        $('.sb-onboarding-box [id].sb-btn').eq(i).sbActive(true);
                    }
                }
                if (admin.find('.sb-admin-list .sb-scroll-area > ul li').length) {
                    $('#onboarding-widget-hidden').sbActive(true);
                }
            }, 1000);
        }

        $(admin).on('click', '#onboarding-notifications', function () {
            if ($(this).sbActive() || SBAdmin.loading(this)) {
                return
            }
            let ids = ['notify-agent-email', 'notify-user-email', 'push-notifications-active'];
            for (var i = 0; i < ids.length; i++) {
                admin.find('#' + ids[i] + ' input').prop('checked', true);
            }
            if (typeof OneSignal != 'undefined') {
                OneSignal.Slidedown.promptPush({ force: true });
            } else {
                SBF.serviceWorker.initPushNotifications();
            }
            $(document).on('SBPushNotificationSubscription', (e, response) => {
                $(this).sbLoading(false);
                if (response.optedIn) {
                    $(this).sbActive(true);
                }
            });
            SBAdmin.settings.save();
        });

        $(admin).on('click', '#onboarding-optimal-configuration-ai', function () {
            admin.find('#open-ai-active input').prop('checked', true);
            admin.find('#dialogflow-human-takeover-active input').prop('checked', true);
            admin.find('#dialogflow-human-takeover-message textarea').val('I\'m a chatbot. Do you want to get in touch with one of our agents?');
            admin.find('#dialogflow-human-takeover-message-confirmation textarea').val('Alright! We will get in touch soon!');
            admin.find('#dialogflow-human-takeover-confirm input').val('Yes');
            admin.find('#dialogflow-human-takeover-cancel input').val('Cancel');
            SBAdmin.settings.save();
            $(this).sbActive(true);
        });

        $(admin).on('click', '#onboarding-widget-hidden', function () {
            if ($(this).sbActive() || SBAdmin.loading(this)) {
                return
            }
            $(document).on('SBAdminNewConversation', () => {
                $(this).sbLoading(false);
                $(this).sbActive(true);
            });
        });

        $(admin).on('click', '#onboarding-pwa', function () {
            $(this).sbActive(true);
        });

        // Miscellaneous
        $(admin).on('click', '.sb-admin-nav-right [data-value="account"], #sb-buy-credits', function () {
            document.location = CLOUD_URL + '/account?tab=membership' + ($(this).attr('id') == 'sb-buy-credits' ? '#credits' : '');
        });

        $(admin).on('click', '.sb-btn-app-disable', function () {
            if (SBAdmin.loading(this)) return;
            SBF.ajax({
                function: 'app-disable',
                app_name: $(this).closest('[data-app]').attr('data-app')
            }, (response) => {
                location.reload();
            });
        });
    });
}(jQuery));