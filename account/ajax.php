<?php

/*
 *
 * ===================================================================
 * CLOUD AJAX PHP FILE
 * ===================================================================
 *
 * © 2017-2024 board.support. All rights reserved.
 *
 */

require('functions.php');

if (isset($_POST['function'])) {
    if (in_array($_POST['function'], ['super-reset-affiliate', 'super-get-affiliates', 'super-membership-plans', 'super-get-customers', 'super-get-customer', 'super-delete-customer', 'super-save-customer', 'super-get-emails', 'super-save-emails', 'super-get-settings', 'super-save-settings', 'super-save-membership-plans', 'super-save-white-label']) && !super_admin()) {
        die();
    }
    switch ($_POST['function']) {
        case 'registration':
            ajax_response(account_registration($_POST['details']));
        case 'login':
            ajax_response(account_login($_POST['email'], $_POST['password']));
        case 'account':
            ajax_response(account());
        case 'account-user-details':
            ajax_response(account_get_user_details());
        case 'account-save':
            ajax_response(account_save($_POST['details']));
        case 'account-reset-password':
            ajax_response(account_reset_password(sb_isset($_POST, 'email'), sb_isset($_POST, 'token'), sb_isset($_POST, 'password')));
        case 'account-welcome':
            ajax_response(account_welcome());
        case 'account-delete':
            ajax_response(account_delete());
        case 'account-delete-agents-quota':
            ajax_response(account_delete_agents_quota());
        case 'verify':
            ajax_response(verify(sb_isset($_POST, 'email'), sb_isset($_POST, 'phone'), sb_isset($_POST, 'code_pairs')));
        case 'get-payments':
            ajax_response(membership_get_payments());
        case 'get-invoice':
            ajax_response(membership_get_invoice($_POST['payment_id']));
        case 'delete-invoice':
            ajax_response(unlink(SB_CLOUD_PATH . '\script\uploads\invoices\/' . $_POST['file_name']));
        case 'membership':
            ajax_response(membership_get_active());
        case 'super-login':
            ajax_response(super_login($_POST['email'], $_POST['password']));
        case 'purchase-white-label':
            ajax_response(membership_purchase_white_label(sb_isset($_POST, 'external_integration')));
        case 'purchase-credits':
            ajax_response(membership_purchase_credits($_POST['amount'], sb_isset($_POST, 'external_integration')));
        case 'set-auto-recharge-credits':
            ajax_response(membership_set_auto_recharge(sb_isset($_POST, 'enabled')));
        case 'super-get-customers':
            ajax_response(super_get_customers(sb_isset($_POST, 'membership')));
        case 'super-get-customer':
            ajax_response(super_get_customer($_POST['customer_id']));
        case 'super-delete-customer':
            ajax_response(super_delete_customer($_POST['customer_id']));
        case 'super-save-customer':
            ajax_response(super_save_customer($_POST['customer_id'], $_POST['details'], sb_isset($_POST, 'extra_details')));
        case 'super-get-emails':
            ajax_response(super_get_emails());
        case 'super-save-emails':
            ajax_response(super_save_emails($_POST['settings']));
        case 'super-get-settings':
            ajax_response(super_get_settings());
        case 'super-save-settings':
            ajax_response(super_save_settings($_POST['settings']));
        case 'super-membership-plans':
            ajax_response(super_membership_plans());
        case 'super-save-membership-plans':
            ajax_response(super_save_membership_plans($_POST['plans']));
        case 'super-get-affiliates':
            ajax_response(super_get_affiliates());
        case 'super-reset-affiliate':
            ajax_response(super_reset_affiliate($_POST['affiliate_id']));
        case 'super-save-white-label':
            ajax_response(super_save_white_label($_POST['price']));
        case 'stripe-create-session':
            ajax_response(stripe_create_session($_POST['price_id'], $_POST['cloud_user_id']));
        case 'stripe-cancel-subscription':
            ajax_response(stripe_cancel_subscription());
        case 'rapyd-checkout':
            ajax_response(rapyd_create_checkout($_POST['price_id'], $_POST['cloud_user_id']));
        case 'verifone-checkout':
            ajax_response(verifone_create_checkout($_POST['price_id'], $_POST['cloud_user_id']));
        case 'verifone-cancel-subscription':
            ajax_response(verifone_cancel_subscription());
        case 'whatsapp-sync':
            ajax_response(cloud_meta_whatsapp_sync($_POST['code']));
        case 'messenger-sync':
            ajax_response(cloud_meta_messenger_sync($_POST['access_token']));
        case 'purchase-addon':
            ajax_response(cloud_addon_purchase($_POST['index']));
        case 'razorpay-create-subscription':
            ajax_response(razorpay_create_subscription($_POST['price_id'], $_POST['cloud_user_id']));
        case 'razorpay-cancel-subscription':
            ajax_response(razorpay_cancel_subscription());
        case 'shopify-subscription':
            ajax_response(shopify_subscription($_POST['price_id']));
    }
}

function ajax_response($response) {
    die($response === true ? true : json_encode($response, JSON_INVALID_UTF8_IGNORE));
}

?>