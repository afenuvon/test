<?php

/*
 * ==========================================================
 * RAZORPAY.PHP
 * ==========================================================
 *
 * Process Razorpay payments. © 2017-2024 board.support. All rights reserved.
 *
 */


header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$response = json_decode($raw, true);

if ($response && isset($response['event'])) {
    require('functions.php');
    $signature = hash_hmac('sha256', $raw, RAZORPAY_KEY_SECRET);
    if ($signature === $_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) {
        $payment = $response['payload']['payment']['entity'];
        if ($payment) {
            switch ($response['event']) {
                case 'subscription.charged':
                    $response = $response['payload']['subscription']['entity'];
                    $notes = $response['notes'];
                    $customer_id = db_escape($notes['customer_id'], true);
                    $previous_subscription = sb_isset(db_get('SELECT customer_id FROM users WHERE customer_id = ' . $customer_id), 'customer_id');
                    $membership = membership_get($response['plan_id']);
                    if ($membership) {
                        razorpay_cancel_subscription($customer_id);
                        membership_update($membership['id'], $membership['period'], $customer_id, $response['id'], sb_isset($notes, 'referral'));
                        cloud_add_to_payment_history($customer_id, $payment['amount'] / currency_get_divider($payment['currency']), 'membership', $data['id'], $payment['id']);
                    } else {
                        sb_error('Membership not found', 'razorpay.php');
                    }
                    break;
                case 'payment.authorized':
                    $notes = $payment['notes'];
                    $currency = $payment['currency'];
                    $amount = $payment['amount'] / currency_get_divider($currency);
                    $customer_id = sb_isset($notes, 'customer_id');
                    if ($amount && $customer_id) {
                        $is_credits = !empty($notes['credits']);
                        if ($is_credits || !empty($notes['white_label'])) {
                            $user_id = sb_isset(db_get('SELECT id FROM users WHERE id = "' . db_escape($customer_id) . '"'), 'id');
                            if ($user_id) {
                                if ($is_credits) {
                                    membership_set_purchased_credits($amount, $currency, $user_id, $payment['id']);
                                } else {
                                    if ($amount == super_get_white_label() && strtolower($currency) == strtolower(RAZORPAY_CURRENCY)) {
                                        membership_save_white_label($user_id);
                                        cloud_add_to_payment_history($user_id, sb_get_stripe_amount($data), 'white-label', $payment['id']);
                                    }
                                }
                            }
                        }
                    }
                    break;
            }
        }
    }
}

?>