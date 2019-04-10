<?php

/*
 * ==========================================================
 * RAPYD.PHP
 * ==========================================================
 *
 * Process Rapyd payments. © 2017-2024 board.support. All rights reserved.
 *
 */

header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$response = json_decode($raw, true);

if ($response && isset($response['data'])) {
    require('functions.php');
    if ($response['type'] == 'PAYMENT_COMPLETED') {
        $response = $response['data'];
        $metadata = $response['metadata'];
        $error = false;
        $cloud_user_id = false;
        $payment_history_type = 'membership';
        if ($metadata && $metadata['rapyd_secret_key'] == RAPYD_SECRET_KEY) {
            $cloud_user_id = sb_isset($metadata, 'cloud_user_id');
            if ($cloud_user_id) {
                if (isset($metadata['membership_id'])) {
                    membership_update($metadata['membership_id'], $metadata['membership_period'], $cloud_user_id, $response['customer_token'], sb_isset($metadata, 'referral'));
                } else if (isset($metadata['white_label'])) {
                    $payment_history_type = 'white-label';
                    membership_save_white_label($cloud_user_id);
                    membership_add_reseller_sale(false, 'white-label', $response['amount']);
                } else if (isset($metadata['credits'])) {
                    $payment_history_type = 'credits';
                    membership_set_purchased_credits($response['amount'], RAPYD_CURRENCY, $cloud_user_id, $response['id']);
                    membership_add_reseller_sale(false, 'credits', $response['amount']);
                }
            } else {
                $error = 'Rapyd cloud user id not found:';
            }
        }
    } else {
        $error = 'Wrong Rapyd response type:';
    }
    if ($error) {
        sb_cloud_debug($error);
        sb_cloud_debug($response);
    } else {
        cloud_add_to_payment_history($cloud_user_id, $data['amount_paid'], $payment_history_type, $response['id']);
    }
}

?>