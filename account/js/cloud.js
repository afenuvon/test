
/*
* 
* ===================================================================
* CLOUD MAIN JS FILE
* ===================================================================
*
* © 2017-2024 board.support. All rights reserved.
*
*/

'use strict';

(function ($) {
    let body;
    let box_account;
    let box_registration;
    let box_super;
    let box_loading;
    let lightbox_profile;
    let URL = document.location.href;
    let URL_NO_PARS = URL;
    let account;
    let pusher;
    let pusher_channel;
    let responsive = $(window).width() < 465;
    let razorpay = PAYMENT_PROVIDER == 'razorpay';
    let stripe = PAYMENT_PROVIDER == 'stripe';
    let rapyd = PAYMENT_PROVIDER == 'rapyd';
    let membership_type_ma = MEMBERSHIP_TYPE == 'messages-agents';
    let messages = {
        password_length: sb_('The password must be at least 8 characters long.'),
        password_match: sb_('The passwords do not match.'),
        email: sb_('The email address is not valid.')
    };

    window.addEventListener('unload', function () { });

    $(document).ready(function () {
        body = $('body');
        box_account = body.find('.sb-account-box');
        box_registration = box_account.length ? false : body.find('.sb-registration-box');
        box_super = body.find('.sb-super-box');
        lightbox_profile = body.find('.sb-profile-edit-box');
        box_loading = body.find('.sb-loading-global');
        body.removeClass('on-load');

        // Global
        body.on('click', '.sb-nav li', function () {
            $(this).siblings().sbActive(false);
            $(this).sbActive(true);
            $(this).closest('.sb-tab').find('> .sb-content > div').sbActive(false).eq($(this).index()).sbActive(true);
        });

        body.on('click', '.sb-lightbox .sb-close', function () {
            $(this).closest('.sb-lightbox').sbActive(false);
            body.find('.sb-lightbox-overlay').sbActive(false);
        });

        body.on('click', '.sb-lightbox .sb-info', function () {
            $(this).sbActive(false);
        });

        body.on('click', '.banner > i', function () {
            $(this).parent().remove();
        });

        if (URL.includes('?')) {
            URL_NO_PARS = URL.substring(0, URL.indexOf('?'));
        }

        if (URL.includes('reload=true')) {
            box_account.startLoading();
            setTimeout(() => {
                document.location = URL.replace('&reload=true', '').replace('?reload=true', '');
            }, 2000);
            return;
        }

        // Responsive
        if (responsive) {
            body.on('click', '.sb-nav,.sb-menu-wide', function () {
                $(this).setClass('sb-active', !$(this).hasClass('sb-active'));
            });

            body.on('click', '.sb-nav li,.sb-menu-wide li', function () {
                $(this).parents().eq(1).find(' > div').html($(this).html());
            });
        }

        // # Account
        if (box_account.length) {
            let chart;
            let chart_cnt = box_account.find('#chart-usage');
            let tabs = ['installation', 'membership', 'invoices', 'profile'];
            let encrypted_code;
            let profile_keys = box_account.find('#tab-profile .sb-input:not(#password)').map(function () { return $(this).attr('id') }).get();
            let menu = box_account.find('.plans-box-menu');

            if (URL.includes('#credits')) {
                setTimeout(() => {
                    box_account.find('#credits')[0].scrollIntoView();
                }, 500);
            }

            ajax('account-user-details', {}, (response) => {
                account = response;
                box_account.find('#embed-code').val(`<!-- ${BRAND_NAME} -->\n<script id="chat-init" src="${CLOUD_URL}/account/js/init.js?id=${response.chat_id}"></script>`);
                box_account.stopLoading();
                for (var i = 0; i < profile_keys.length; i++) {
                    if (profile_keys[i] in response) {
                        box_account.find(`#${profile_keys[i]} input`).val(response[profile_keys[i]]);
                    }
                }
                for (var i = 0; i < 2; i++) {
                    let name = i ? 'email' : 'phone';
                    if (response[name + '_confirmed'] == 1 || (!i && !TWILIO_SMS)) {
                        box_account.find('#' + name).removeClass('sb-type-input-button').find('.sb-btn').remove();
                    }
                }
                banners('verify');
            });

            if (menu.length) {
                let selected = menu.find('li').eq(0).sbActive(true);
                box_account.find('#plans > div').removeClass('sb-visible');
                box_account.find(`#plans > [data-menu="${selected.attr('data-type')}"]`).addClass('sb-visible');
                $(menu).on('click', 'li', function () {
                    menu.find('li').sbActive(false);
                    box_account.find('#plans > div').removeClass('sb-visible');
                    box_account.find(`#plans > [data-menu="${$(this).attr('data-type')}"]`).addClass('sb-visible');
                    $(this).sbActive(true);
                });
                if (box_account.find('#membership-appsumo').length) {
                    menu.find('ul').append(`<li><a href="https://appsumo.com/account/products/" target="_blank" style="color:#028be5;text-decoration:none">AppSumo</a></li>`);
                }
            } else {
                box_account.find('#plans > div').addClass('sb-visible');
            }

            box_account.on('click', ' > .sb-tab > .sb-nav li', function () {
                let tab_id = $(this).attr('id').replace('nav-', '');
                if (tab_id == 'membership') {
                    if (chart_cnt.isLoading()) {
                        $.getScript(CLOUD_URL + '/script/vendor/chart.min.js', () => {
                            chart_cnt.stopLoading();
                            chart = new Chart(chart_cnt, {
                                type: 'bar',
                                data: {
                                    labels: [sb_('January'), sb_('February'), sb_('March'), sb_('April'), sb_('May'), sb_('June'), sb_('July'), sb_('August'), sb_('September'), sb_('October'), sb_('November'), sb_('December')],
                                    datasets: [{
                                        data: messages_volume,
                                        backgroundColor: '#009BFC'
                                    }],
                                }, options: {
                                    legend: {
                                        display: false
                                    }
                                }
                            });
                        });
                    }
                    setTimeout(() => { banners('suspended') }, 300);
                }
                if (tab_id == 'profile') {
                    setTimeout(() => { banners('verify') }, 1000);
                }
                if (tab_id == 'logout') {
                    SBF.logout(false);
                    setTimeout(() => { document.location = location.href.substring(0, location.href.indexOf('/account')) + '?login' }, 300);
                }
                window.history.replaceState(null, null, '?tab=' + tab_id + (location.href.includes('debug') ? '&debug' : ''));
            });

            box_account.on('click', '.btn-verify-email,.btn-verify-phone', function () {
                let value = $(this).parent().find('input').val();
                if (!value || loading(this)) return;
                let data = {};
                let is_email = $(this).hasClass('btn-verify-email');
                data[is_email ? 'email' : 'phone'] = $(this).parent().find('input').val();
                ajax('verify', data, (response) => {
                    encrypted_code = response;
                    banner(`We sent you a secret code`, `We sent you a secret code, please enter it below to verify your ${is_email ? 'email address' : 'phone number'}`, `<div data-type="text" class="sb-input sb-type-input-button"><input type="text"><a id="btn-verify-code" class="sb-btn">${sb_('Complete verification')}</a></div>`);
                    $(this).stopLoading();
                });
            });

            box_account.on('click', '.banner #btn-verify-code', function () {
                let code = $(this).parent().find('input').val();
                if (!code || loading(this)) return;
                ajax('verify', { 'code_pairs': [encrypted_code, code] }, (response) => {
                    if (response) {
                        let email = response[0] == 'email';
                        setLogin(response[1][0], response[1][1]);
                        let setting = $(box_account).find(email ? '#email' : '#phone');
                        setting.removeClass('sb-type-input-button').find('.sb-btn').remove();
                        box_account.find('.banner').remove();
                        banner_success(`Thank you! Your ${email ? 'email address' : 'phone number'} has been verified.`);
                        $(this).stopLoading();
                    } else {
                        banner_error('Error. Something went wrong.');
                    }
                });
            });

            box_account.on('click', '#save-profile', function () {
                if (loading(this)) return;
                let details = {};
                let error = false;
                box_account.find('#tab-profile .sb-input input').each((e, element) => {
                    let id = $(element).parent().attr('id');
                    let value = $.trim($(element).val());
                    if (!value) {
                        banner_error('All fields are required.');
                        error = true;
                    }
                    if (id == 'password' && value.length < 8) {
                        banner_error(messages.password_length);
                        error = true;
                    }
                    if (id == 'email' && (!value.includes('@') || !value.includes('.'))) {
                        banner_error(messages.email);
                        error = true;
                    }
                    details[id] = value;
                });
                if (!error) {
                    ajax('account-save', { 'details': details }, (response) => {
                        if (Array.isArray(response)) {
                            setLogin(response[0], response[1]);
                            banner_success('Your profile information has been updated successfully.');
                        } else {
                            banner_error(response);
                        }
                        $(this).stopLoading();
                    });
                } else {
                    $(this).stopLoading();
                }
            });

            box_account.on('click', '#nav-invoices', function () {
                let tab = box_account.find('#tab-invoices');
                if (tab.isLoading()) {
                    ajax('get-payments', {}, (response) => {
                        let code = '';
                        for (var i = 0; i < response.length; i++) {
                            code += `<tr><td><i class="sb-icon-file"></i>${get_invoice_string(response[i])}</td></tr>`;
                        }
                        if (code) {
                            tab.find('tbody').html(code);
                        } else {
                            tab.append(`<p>${sb_(`There are no ${stripe ? 'invoices' : 'payments'} yet.`)}</p>`);
                        }
                        tab.stopLoading();
                    });
                }
            });

            box_account.on('click', '.sb-invoice-row', function () {
                ajax('get-invoice', { payment_id: $(this).attr('data-id') }, (response) => {
                    window.open(CLOUD_URL + '/script/uploads/invoices/' + response);
                    setTimeout(() => {
                        ajax('delete-invoice', { file_name: response });
                    }, 1000);
                });
            });

            box_account.on('click', '#plans > div', function (e) {
                e.preventDefault();
                if (loading(this)) return;
                let names = { stripe: 'stripe-create-session', rapyd: 'rapyd-checkout', verifone: 'verifone-checkout', razorpay: 'razorpay-create-subscription' };
                ajax(external_integration == 'shopify' ? 'shopify-subscription' : names[PAYMENT_PROVIDER], { price_id: $(this).attr('data-id'), cloud_user_id: CLOUD_USER_ID }, (response) => {
                    document.location = response.url;
                });
            });

            box_account.on('click', '#purchase-white-label', function (e) {
                e.preventDefault();
                if ($(this).hasClass('sb-plan-active') || loading(this)) {
                    return;
                }
                ajax('purchase-white-label', { external_integration: external_integration }, (response) => {
                    document.location = response.url;
                });
            });

            box_account.on('change', '#add-credits select', function (e) {
                let value = $(this).val();
                e.preventDefault();
                if (!value || loading($(this).parent())) {
                    return;
                }
                ajax('purchase-credits', { amount: value, external_integration: external_integration }, (response) => {
                    if (response.error) {
                        console.error(response.error);
                    } else {
                        document.location = response.url;
                    }
                });
            });

            box_account.on('click', '.sb-custom-addon', function (e) {
                if (loading(this)) {
                    return;
                }
                ajax('purchase-addon', { index: $(this).attr('data-index') }, (response) => {
                    document.location = response.url;
                });
            });

            box_account.on('click', '#credits-recharge input', function (e) {
                ajax('set-auto-recharge-credits', { enabled: $(this).is(':checked') }, (response) => {
                    banner_success('Settings saved.');
                });
            });

            box_account.on('click', '#cancel-subscription', function (e) {
                e.preventDefault();
                if (confirm(sb_('Are you sure?'))) {
                    if (loading(this)) return;
                    ajax((external_integration ? external_integration : PAYMENT_PROVIDER) + '-cancel-subscription', {}, (response) => {
                        if (response == 'no-subscriptions') {
                            banner('Subscription already cancelled', 'You do not have any active subscription.', '', false, false, true);
                        } else if (response && 'status' in response && response.status == 'canceled') {
                            banner('Subscription cancelled', 'The subscription has ben cancelled sucessfully.', '', false, false, true);
                        } else {
                            banner('Error', JSON.stringify(response), '', false, true);
                        }
                        $(this).stopLoading();
                    });
                }
            });

            if (URL.includes('welcome') && SETTINGS.text_welcome_title) {
                banner(SETTINGS.text_welcome_title, SETTINGS.text_welcome, '', SETTINGS.text_welcome_image);
                window.history.replaceState({}, document.title, URL_NO_PARS);
                box_account.find('.sb-btn-dashboard').addClass('animation-button').attr('href', '../?welcome').attr('target', '_blank');
            }

            if (URL.includes('tab=')) {
                for (var i = 0; i < tabs.length; i++) {
                    if (URL.includes('tab=' + tabs[i])) {
                        let nav = box_account.find(' > .sb-tab > .sb-nav');
                        nav.find('li').eq(i).click();
                        nav.sbActive(false);
                        break;
                    }
                }
            }

            box_account.on('click', '#delete-account', function (e) {
                e.preventDefault();
                if (confirm(sb_('Are you sure? Your account, along with all its users and conversations, will be deleted permanently.'))) {
                    if (loading(this)) return;
                    ajax('account-delete', {}, () => {
                        SBF.cookie('sb-login', '', '', false);
                        SBF.cookie('sb-cloud', '', '', false);
                        SBF.storage('open-conversation', '');
                        SBF.storage('login', '');
                        setTimeout(() => { location.reload() }, 500);
                    });
                }
            });

            box_account.on('click', '#delete-agents-quota', function () {
                if (confirm(sb_('Are you sure?'))) {
                    ajax('account-delete-agents-quota', {}, () => {
                        location.href = CLOUD_URL;
                    });
                }
            });

            banners('suspended');
        }

        // # Registration and login
        if (box_registration.length) {
            let box_login = body.find('.sb-login-box');
            let box_reset_password = body.find('.sb-reset-password-box');

            if (SBF.getURL('login_email')) {
                setTimeout(() => {
                    box_login.find('#email input').val(SBF.getURL('login_email'));
                    box_login.find('#password input').val(SBF.getURL('login_password'));
                    active(box_login, true);
                    active(box_registration, false);
                    box_login.find('.btn-login').click();
                }, 300);
            }

            $(box_registration).on('click', '.btn-register', function (e) {
                if (loading(this)) return;
                let details = {};
                let errors = false;
                let errors_area = box_registration.find('.sb-errors-area');
                errors_area.html('');
                box_registration.find('[id].sb-input').each(function () {
                    let input = $(this).find('input');
                    if ($.trim(input.val())) {
                        input.removeClass('sb-error');
                    } else {
                        input.addClass('sb-error');
                        errors = true;
                    }
                    details[$(this).attr('id')] = $.trim(input.val());
                });
                if (errors) {
                    errors_area.html(sb_('All fields are required.'));
                } else if (details['password'].length < 8) {
                    errors_area.html(messages.password_length);
                    errors = true;
                } else if (details['password'] != details['password_2']) {
                    errors_area.html(messages.password_match);
                    errors = true;
                } else if (!details['email'].includes('@') || !details['email'].includes('.')) {
                    errors_area.html(messages.email);
                    errors = true;
                } else {
                    setLogin('', '');
                    if (URL.includes('ref=')) {
                        cookie('sb-referral', SBF.getURL('ref'), 60);
                    }
                    ajax('registration', { 'details': details }, (response) => {
                        if (response == 'duplicate-email') {
                            errors_area.html(sb_('This email is already in use. Please use another email.'));
                        } else {
                            setLogin(response[0], response[1]);
                            ajax('account-welcome');
                            setTimeout(() => { document.location = SBF.getURL('redirect') ? SBF.getURL('redirect') : CLOUD_URL + '/account?welcome' }, 300);
                        }
                        $(this).stopLoading();
                    });
                }
                if (errors) {
                    $(this).stopLoading();
                }
                e.preventDefault();
                return false;
            });

            $(box_login).on('click', '.btn-login', function (e) {
                let email = box_login.find('#email input').val();
                let password = box_login.find('#password input').val();
                let errors_area = box_login.find('.sb-errors-area');
                if (!email || !password || loading(this)) {
                    return;
                }
                errors_area.html('');
                ajax('login', { 'email': email, 'password': password }, (response) => {
                    if (response === false) {
                        errors_area.html(sb_('Invalid email or password.'));
                    } else if (response === 'ip-ban') {
                        errors_area.html(sb_('Too many login attempts. Please retry again in a few hours.'));
                    } else {
                        setLogin(response[0], response[1]);
                        document.location = SBF.getURL('redirect') ? SBF.getURL('redirect') : CLOUD_URL;
                    }
                    $(this).stopLoading();
                });
                e.preventDefault();
                return false;
            });

            $(box_login).on('click', '.btn-registration-box', function () {
                active(box_login, false);
                active(box_registration, true);
            });

            $(box_registration).on('click', '.sb-btn-login-box', function () {
                active(box_registration, false);
                active(box_login, true);
            });

            $(box_reset_password).on('click', '.btn-reset-password', function () {
                let email = $.trim(box_reset_password.find('#reset-password-email').val());
                if (email && email.includes('@') && email.includes('.')) {
                    ajax('account-reset-password', { 'email': email });
                    box_reset_password.html(`<div class="sb-top-bar"><div class="sb-title">${sb_('Check your email')}</div><div class="sb-text">${sb_('If an account linked to the email provided exists you will receive an email with a link to reset your password.')}</div></div>`);
                }
            });

            $(box_reset_password).on('click', '.btn-cancel-reset-password', function () {
                active(box_login, true);
                active(box_reset_password, false);
            });

            $(box_login).on('click', '.btn-forgot-password', function () {
                active(box_registration, false);
                active(box_login, false);
                active(box_reset_password, true);
            });

            if (URL.includes('reset=')) {
                let box_reset_password_2 = body.find('.sb-reset-password-box-2');
                let info = box_reset_password_2.find('.sb-info');
                $(box_reset_password_2).on('click', '.btn-reset-password-2', function () {
                    let password = box_reset_password_2.find('#reset-password-1').val();
                    info.html('').sbActive(false);
                    if (!password) {
                        return;
                    }
                    if (password != box_reset_password_2.find('#reset-password-2').val()) {
                        info.html(messages.password_match).sbActive(true);
                        return;
                    }
                    if (password.length < 8) {
                        info.html(messages.password_length).sbActive(true);
                        return;
                    }
                    if (loading(this)) return;
                    ajax('account-reset-password', { 'email': SBF.getURL('email'), 'token': SBF.getURL('reset'), 'password': password }, (response) => {
                        active(box_login, true);
                        active(box_reset_password_2, false);
                        $(this).stopLoading();
                    });
                });
            }

            $(window).keydown(function (e) {
                if (e.which == 13) {
                    $('.btn-login').click();
                }
            });
        }

        // # Super
        if (box_super.length) {
            if (box_super.find('.table-customers').length) {
                ajax('super-get-customers', {}, (response) => {
                    let code = '';
                    for (var i = 0; i < response.length; i++) {
                        let user = response[i];
                        code += `<tr data-customer-id="${user.id}"><td data-id="id">${user.id}</td><td data-id="name">${user.first_name} ${user.last_name}</td><td data-id="email">${user.email}</td><td data-id="phone">${user.phone}</td><td data-id="membership">${get_membership(user.membership).name}</td><td data-id="token">${user.token}</td><td data-id="creation_time">${user.creation_time}</td></tr>`;
                    }
                    box_super.find('.table-customers tbody').html(code);
                    box_super.find('#tab-customers').stopLoading();
                });
            }

            $(box_super).on('click', '.btn-login', function (e) {
                let email = box_super.find('#email input').val();
                let password = box_super.find('#password input').val();
                let errors_area = box_super.find('.sb-errors-area');
                if (!email || !password || loading(this)) return;
                ajax('super-login', { 'email': email, 'password': password }, (response) => {
                    if (response === false) {
                        errors_area.html('Invalid email or password.');
                    } else {
                        cookie('sb-super', response, 3650);
                        document.location = URL_NO_PARS + '?login=success';
                    }
                    $(this).stopLoading();
                });
                e.preventDefault();
                return false;
            });

            $(box_super).on('click', '.table-customers td', function (e) {
                box_loading.sbActive(true);
                ajax('super-get-customer', { 'customer_id': $(this).parent().attr('data-customer-id') }, (response) => {
                    let fields_editable = ['first_name', 'last_name', 'email', 'phone', 'password', 'credits'];
                    let fields_readonly = ['id', 'lifetime_value', 'token', 'creation_time', 'customer_id', 'count_users', 'count_agents', 'membership_expiration'];
                    let code = '';
                    for (var i = 0; i < fields_editable.length; i++) {
                        let slug = fields_editable[i];
                        code += `<div data-type="text" class="sb-input"><span>${slugToString(slug)}</span><input id="${slug}" type="text" value="${response[slug]}" ${slug != 'phone' ? 'required' : ''} /></div>`;
                    }
                    for (var i = 0; i < response.extra_fields.length; i++) {
                        let item = response.extra_fields[i];
                        if (!['payment', 'active_membership_cache', 'notifications_credits_count'].includes(item.slug)) {
                            code += `<div data-type="text" class="sb-input"><span>${slugToString(item.slug)}</span><input id="${item.slug}" type="text" value="${item.value}" data-extra="true" /></div>`;
                        }
                    }
                    code += `<div data-type="text" class="sb-input"><span>Membership</span><select id="membership" required>`;
                    for (var i = 0; i < MEMBERSHIPS.length; i++) {
                        code += `<option value="${MEMBERSHIPS[i].id}"${MEMBERSHIPS[i].id == response.membership ? ' selected' : ''}>${MEMBERSHIPS[i].name}${MEMBERSHIPS[i].period ? (' | ' + MEMBERSHIPS[i].period) : ''}</option>`;
                    }
                    code += '<option value="manual_membership_renewal">Manual membership renewal</option></select></div>';
                    lightbox_profile.find('.sb-edit-box').html(code);

                    code = '';
                    for (var i = 0; i < fields_readonly.length; i++) {
                        code += `<div data-type="readonly" class="sb-input"><span>${slugToString(fields_readonly[i])}</span><input id="${fields_readonly[i]}" type="text" value="${response[fields_readonly[i]]}" readonly /></div>`;
                    }
                    lightbox_profile.find('.sb-readonly-box').html(code);
                    code = '';
                    for (var i = 0; i < response.invoices.length; i++) {
                        code += get_invoice_string_payment_gateway(response.invoices[i]);
                    }
                    lightbox_profile.find('.sb-sales-box').html(code ? code : '<div>No data available</div>');
                    code = '';
                    for (var i = 0; i < response.monthly_volume.length; i++) {
                        code += `<div>${response.monthly_volume[i].date} | ${response.monthly_volume[i].count} messages</div>`;
                    }
                    lightbox_profile.find('.sb-volume-box').html(code ? code : '<div>No data available</div>');
                    lightbox_profile.find('.sb-name').html(response.first_name + ' ' + response.last_name);
                    lightbox_profile.find('.sb-delete-box input').val('');
                    lightbox_profile.attr('data-customer-id', response.id);
                    lightbox_profile.lightbox();
                });
                e.preventDefault();
                return false;
            });

            $(lightbox_profile).on('click', '.sb-save', function (e) {
                if (loading(this)) return;
                let details = {};
                let extra_details = {};
                let error = false;
                let free = lightbox_profile.find('#membership').val() == 'free';
                lightbox_profile.find('input:not([readonly]), select').each((e, input) => {
                    let value = $.trim($(input).val());
                    let id = $(input).attr('id');
                    if (id) {
                        if (!free && id == 'membership_expiration') {
                            if (!value || value.length != 8) {
                                lightbox_profile.lightboxError('The membership expiration must be in the following format: dd-mm-yy (ex. 25-10-22).');
                                error = true;
                                return;
                            }
                        }
                        if (!value && $(input).attr('required')) {
                            $(this).stopLoading();
                            $(lightbox_profile).find('.sb-info').html('All fields are required.').sbActive(true);
                            error = true;
                            return;
                        }
                        if ($(input).attr('data-extra')) {
                            extra_details[id] = value;
                        } else {
                            details[id] = value;
                        }
                    }
                });
                if (error) {
                    $(this).stopLoading();
                    return;
                }
                let customer_id = $(this).closest('[data-customer-id]').attr('data-customer-id');
                ajax('super-save-customer', { 'customer_id': customer_id, 'details': details, 'extra_details': extra_details }, () => {
                    let row = box_super.find(`.table-customers [data-customer-id="${customer_id}"]`);
                    let keys = ['name', 'email', 'phone', 'membership'];
                    details.name = details.first_name + ' ' + details.last_name;
                    for (var i = 0; i < MEMBERSHIPS.length; i++) {
                        if (details.membership == MEMBERSHIPS[i].id) {
                            details.membership = MEMBERSHIPS[i].name;
                            break;
                        }
                    }
                    for (var i = 0; i < keys.length; i++) {
                        row.find(`[data-id="${keys[i]}"]`).html(details[keys[i]]);
                    }
                    banner_success('Settings saved. Reload to apply the changes.');
                    body.find('.sb-lightbox,.sb-lightbox-overlay').sbActive(false);
                    $(this).stopLoading();
                });
                e.preventDefault();
                return false;
            });

            $(lightbox_profile).on('click', '.sb-delete-box .sb-btn-text', function () {
                if ($(this).parent().find('input').val().toUpperCase() == 'DELETE') {
                    let customer_id = $(this).closest('[data-customer-id]').attr('data-customer-id');
                    ajax('super-delete-customer', { 'customer_id': customer_id });
                    box_super.find(`.table-customers [data-customer-id="${customer_id}"]`).remove();
                    body.find('.sb-lightbox,.sb-lightbox-overlay').sbActive(false);
                }
            });

            $(box_super).on('click', '#save-emails, #save-settings', function (e) {
                if (loading(this)) return;
                let settings = {};
                let email = $(this).attr('id') == 'save-emails';
                box_super.find(email ? '#tab-emails' : '#tab-settings').find(' .sb-setting textarea,.sb-setting input,.sb-setting select').each((e, input) => {
                    input = $(input);
                    settings[input.attr('id')] = input.is(':checkbox') ? input.is(':checked') : $.trim(input.val());
                });
                ajax(email ? 'super-save-emails' : 'super-save-settings', { settings: settings }, (response) => {
                    if (is_true(response)) {
                        banner_success('Settings saved successfully.');
                    } else {
                        banner_error('Error:' + response);
                    }
                    $(this).stopLoading();
                });
                e.preventDefault();
                return false;
            });

            $(body).on('click', '#nav-emails, #nav-settings, #nav-membership-plans', function () {
                let id = $(this).attr('id');
                let email = id == 'nav-emails';
                let settings = id == 'nav-settings';
                let area = $(body).find('#' + id.replace('nav', 'tab'));
                if (area.isLoading()) {
                    ajax(email ? 'super-get-emails' : (settings ? 'super-get-settings' : 'super-membership-plans'), {}, (response) => {
                        if (settings || email) {
                            for (var key in response) {
                                let input = area.find('#' + key);
                                if (input.is(':checkbox')) {
                                    input.prop('checked', response[key] != 'false');
                                } else {
                                    input.val(response[key]);
                                }
                            }
                        } else {
                            area.append(response);
                            if (!stripe) {
                                area.find('[data-period]').each(function () {
                                    $(this).find('select').val($(this).attr('data-period'));
                                });
                            }
                        }
                        area.stopLoading();
                    });
                }
            });

            $(box_super).on('click', '#save-membership-plans', function () {
                if (loading(this)) return;
                let button = $(this);
                let plans = [];
                let is_error = false;
                box_super.find('#membership-plans > div').each(function () {
                    let item = { id: $(this).attr('data-id'), price: stripe || razorpay ? $(this).attr('data-price') : $(this).find('.price').val(), currency: stripe || razorpay ? $(this).attr('data-currency') : CURRENCY, period: stripe || razorpay ? $(this).attr('data-period') : $(this).find('.period').val(), name: $(this).find('.name').val().trim(), quota: $(this).find('.quota').val().trim() };
                    if (!item.currency) item.currency = 'usd';
                    if (membership_type_ma) {
                        item['quota_agents'] = $(this).find('.quota-agents').val().trim();
                    }
                    if (item.quota && item.name && $.isNumeric(item.quota) && (item.id == 'free' || (item.price && item.period))) {
                        plans.push(item);
                    } else {
                        banner_error('All fields are required. Quota must be an integer.');
                        button.stopLoading();
                        is_error = true;
                    }
                });
                if (!is_error && window.confirm('Are you sure to update the membership plans? The changes will be live instantaneously.')) {
                    ajax('super-save-membership-plans', { 'plans': plans }, (response) => {
                        button.stopLoading();
                        if (is_true(response)) banner_success('Membership plans saved successfully.');
                        else banner_error('Error:' + response);
                    });
                } else {
                    button.stopLoading();
                }
            });

            $(box_super).on('click', '#save-white-label', function () {
                if (loading(this)) return;
                ajax('super-save-white-label', { 'price': box_super.find('.super-white-label input').val() }, (response) => {
                    $(this).stopLoading();
                    if (is_true(response)) banner_success('White label price saved successfully.');
                    else banner_error('Error:' + response);
                });
            });

            $(box_super).on('click', '#logout', function () {
                cookie('sb-super', '', 0);
                document.location = URL_NO_PARS + '?logout=true';
            });

            $(box_super).on('click', '#membership-plans > div > i', function () {
                $(this).parent().remove();
            });

            $(box_super).on('click', '#add-membership', function () {
                $(box_super).find('#membership-plans').append(`<div data-id="${random()}" data-price="" data-period="" data-currency=""><div class="sb-input"><h5>Name</h5><input class="name" type="text" value="" placeholder="Insert plan name..."><h5>Quota</h5><input type="number" class="quota" placeholder="0" value="">${membership_type_ma ? '<h5>Quota agents</h5><input type="number" class="quota-agents" placeholder="0" value="">' : ''}<h5>Price</h5><input type="number" class="price" placeholder="0" value=""><h5>Period</h5><select class="period"><option value="month">Monthly</option><option value="year">Yearly</option></select></div><i class="sb-icon-close"></i></div>`);
            });

            $(box_super).on('click', '#nav-affiliates', function () {
                let area = $(body).find('#tab-affiliates');
                if (area.isLoading()) {
                    ajax('super-get-affiliates', {}, (response) => {
                        let code = '';
                        for (var i = 0; i < response.length; i++) {
                            let row = response[i];
                            code += `<tr><td>${row.id}</td><td>${row.first_name} ${row.last_name}</td ><td>${row.email}</td><td>${CURRENCY.toUpperCase()} <span>${row.value}</span></td><td><div class="sb-btn" data-id="${row.id}">Reset to zero</div></td></tr>`;
                        }
                        area.find('tbody').html(code);
                        area.stopLoading();
                    });
                }
            });

            $(box_super).on('click', '.table-affiliates .sb-btn', function () {
                if (confirm('Are you sure?')) {
                    ajax('super-reset-affiliate', { affiliate_id: $(this).attr('data-id') }, () => {
                        $(this).closest('tr').remove();
                    });
                }
            });

            $(box_super).on('click', '#sb-get-emails', function () {
                let code = '';
                box_super.find('.table-customers [data-customer-id]').each(function () {
                    if ($(this).find('[data-id="membership"]').html().trim() != 'Free') {
                        code += $(this).find('[data-id="email"]').html().trim() + ', ';
                    }
                });
                $('#sb-response-area').html('<br>' + code.substring(0, code.length - 2));
            });
        }
    });

    function ajax(function_name, data = {}, onSuccess = false) {
        $.extend(data, { function: function_name });
        $.ajax({
            method: 'POST',
            url: 'ajax.php',
            data: data
        }).done((response) => {
            if (onSuccess) {
                onSuccess(response === false ? false : JSON.parse(response));
            }
        });
    }

    function cookie(name, value, expiration_days) {
        let date = new Date();
        date.setTime(date.getTime() + expiration_days * 5040000);
        document.cookie = name + "=" + value + ";expires=" + (expiration_days == 0 ? 'Thu, 01 Jan 1970 00:00:01 GMT' : date.toUTCString()) + ";path=/;SameSite=None;Secure;";
    }

    function setLogin(cloud, sb) {
        cookie('sb-cloud', cloud, 3650);
        cookie('sb-login', sb, 3650);
    }

    function loading(element) {
        if ($(element).hasClass('sb-loading')) {
            return true;
        } else {
            $(element).addClass('sb-loading');
        }
        return false;
    }

    function banner(title, message, code = '', image = false, error = false, success = false) {
        let id = stringToSlug(title);
        body.find(`#banner-${id}`).remove();
        body.find('.sb-tab > .sb-content > .sb-active').prepend(`<div id="banner-${id}" class="banner${image ? ' banner-img' : ''}${error ? ' banner-error' : ''}${success ? ' banner-success' : ''}">${image ? `<img src="${image}" />` : ''}<h2>${sb_(title)}</h2><p>${sb_(message)}</p><div>${code}</div><i class="sb-btn-icon sb-icon sb-icon-close"></i></div>`);

    }

    function banner_success(message) {
        banner('', message, '', false, false, true);
    }

    function banner_error(message) {
        banner('', message, '', false, true);
    }

    function banners(type) {
        switch (type) {
            case 'suspended':
                let agents_quota_exceeded = 'quota_agents' in membership && membership.count_agents > membership.quota_agents;
                if (membership.count > membership.quota || membership.expired || agents_quota_exceeded) {
                    banner(SETTINGS.text_suspended_title ? SETTINGS.text_suspended_title : 'Your account has been suspended', (SETTINGS.text_suspended ? SETTINGS.text_suspended : sb_('Your website visitors can still use the chat but you are not able to view the messages and reply to your visitors because you can not enter the administration area. Please renew your subscription below or upgrade to a higher plan to reactivate your account again.')) + (agents_quota_exceeded ? ' ' + sb_('You can also delete newly created agents or admins and reactivate your account by clicking {R}.').replace('{R}', '<a id="delete-agents-quota">' + sb_('here') + '</a>') : ''), '', false, true);
                }
                break;
            case 'verify':
                let verify_email = box_account.find('.btn-verify-email').length;
                let verify_phone = box_account.find('.btn-verify-phone').length;
                let text = verify_email && verify_phone ? 'email and phone number' : (verify_email ? 'email' : 'phone number');
                if ((verify_email || verify_phone) && !URL.includes('welcome')) {
                    banner(`Verify your ${text}`, `Please verify your ${text} from the profile area.`, '', false, true);
                }
                break;
        }
    }

    function is_true(value) {
        return value === true || value == 1 || value === 'true';
    }

    function sb_(text) {
        return SB_TRANSLATIONS && text in SB_TRANSLATIONS ? SB_TRANSLATIONS[text] : text;
    }

    function get_membership(id) {
        for (var i = 0; i < MEMBERSHIPS.length; i++) {
            if (MEMBERSHIPS[i].id == id) return MEMBERSHIPS[i];
        }
        return MEMBERSHIPS[0];
    }

    function get_invoice_string(item) {
        let values = JSON.parse(item.value);
        return `<div class="sb-invoice-row" data-id="${item.id}"><span>INV-${values[5]}-${CLOUD_USER_ID}</span><span>${CLOUD_CURRENCY} ${values[0]}</span><span>${slugToString(values[1])}</span><span>${new Date(values[5] * 1000).toLocaleString()}</span></div>`;
    }

    function get_invoice_string_payment_gateway(item) {
        if (stripe || razorpay) {
            return `${(new Date(item.created * 1000)).toISOString().slice(0, 10)} | ${item.currency.toUpperCase()} ${item.amount_paid / (['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'].includes(item.currency.toUpperCase()) ? 1 : 100)} | ${item.number}<br>`;
        }
        if (rapyd) {
            return `${item.currency_code} ${item.amount} | ${(new Date(item.paid_at * 1000)).toISOString().slice(0, 10)} | ${item.payment_method_type.replace(/_/g, ' ').toUpperCase()}<br>`;
        }
        return `${item.Currency.toUpperCase()} ${item.NetPrice} | ${item.OrderDate} | ${item.RefNo}<br>`;
    }

    function stringToSlug(string) {
        string = string.trim();
        string = string.toLowerCase();
        let from = "åàáãäâèéëêìíïîòóöôùúüûñç·/_,:;";
        let to = "aaaaaaeeeeiiiioooouuuunc------";
        for (var i = 0, l = from.length; i < l; i++) {
            string = string.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
        }
        return string.replace(/[^a-z0-9 -]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+/, '').replace(/-+$/, '').replace(/ /g, '');
    }

    function slugToString(string) {
        string = string.replace(/_/g, ' ').replace(/-/g, ' ');
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function random() {
        let chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        let result = '';
        for (var i = 5; i > 0; --i) result += chars[Math.floor(Math.random() * 62)];
        return result;
    }

    function active(element, set_active) {
        if (set_active === false || set_active === true) {
            $(element).setClass('active', set_active);
            return element;
        }
        return $(element).hasClass('active');
    }

    $.fn.lightbox = function () {
        $(this).css({ 'margin-top': ($(this).outerHeight() / -2) + 'px', 'margin-left': ($(this).outerWidth() / -2) + 'px' });
        box_loading.sbActive(false);
        $(this).sbActive(true);
        body.find('.sb-lightbox-overlay').sbActive(true);
    }

    $.fn.lightboxError = function (error_message) {
        let area = $(this).find(' > .sb-info');
        area.html(error_message).sbActive(true);
        setTimeout(() => {
            area.html('').sbActive(false);
        }, 10000);
    }

    $.fn.setClass = function (class_name, add = true) {
        if (add) {
            $(this).addClass(class_name);
        } else {
            $(this).removeClass(class_name);
        }
        return this;
    }

    $.fn.stopLoading = function () {
        $(this).removeClass('sb-loading');
        return this;
    }

    $.fn.startLoading = function () {
        $(this).addClass('sb-loading');
        return this;
    }

    $.fn.isLoading = function () {
        return $(this).hasClass('sb-loading');
    }

    $.fn.sbActive = function (show = -1) {
        if (show === -1) return $(this).hasClass('sb-active');
        $(this).setClass('sb-active', show);
        return this;
    };

}(jQuery)); 
