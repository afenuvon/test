<?php

/*
 * ==========================================================
 * STRIPE.PHP
 * ==========================================================
 *
 * Process Stripe payments. © 2017-2024 board.support. All rights reserved.
 *
 */

header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$response = json_decode($raw, true);

if ($response && isset($response['id']) && empty($response['error'])) {
    require('functions.php');
    $data = $response['data']['object'];
    $error = false;
    switch ($response['type']) {
        case 'checkout.session.completed':
            if (strpos($data['client_reference_id'], '|sb')) {
                $response = stripe_curl('events/' . $response['id'], 'GET');
                $error = sb_isset($response, 'error');
                if (!$error) {
                    $data = $response['data']['object'];
                    $membership_data = explode('|', $data['client_reference_id']);
                    if (defined('STRIPE_PRODUCT_ID_WHITE_LABEL') && $membership_data[0] == 'white_label') {
                        membership_save_white_label($membership_data[1]);
                        cloud_add_to_payment_history($membership_data[1], sb_get_stripe_amount($data), 'white-label', $data['id']);
                        membership_add_reseller_sale(false, 'white-label', $data['amount_paid']);
                    } else if (defined('STRIPE_CURRENCY') && $membership_data[0] == 'credits') {
                        $setup_intent = sb_isset($data, 'setup_intent');
                        if ($setup_intent) {
                            $payment_method = sb_isset(stripe_curl('setup_intents/' . $setup_intent, 'GET'), 'payment_method');
                            $cloud_user_id = sb_db_escape($membership_data[1], true);
                            db_query('DELETE FROM users_data WHERE user_id = ' . $cloud_user_id . ' AND slug = "stripe_payment_method"');
                            super_insert_user_data('(' . $cloud_user_id . ', "stripe_payment_method", "' . sb_db_escape($payment_method) . '")');
                            $response = stripe_curl('payment_intents?confirm=true&setup_future_usage=off_session&return_url=' . CLOUD_URL . '/account%2F%23credits%3Ftab%3Dmembership&amount=' . $membership_data[2] . '&currency=' . STRIPE_CURRENCY . '&payment_method=' . $payment_method . '&customer=' . $data['customer'] . '&metadata[sb_credits]=true&description=' . urlencode(SB_CLOUD_BRAND_NAME . ' Credits'));
                            if (!sb_isset($response, 'status') == 'succeeded') {
                                sb_cloud_debug($response);
                            }
                            if (sb_isset(sb_isset($response, 'next_action', []), 'redirect_to_url')) {
                                $url = sb_isset($response['next_action']['redirect_to_url'], 'url');
                                if ($url) {
                                    super_insert_user_data('(' . $cloud_user_id . ', "stripe_next_action", "' . time() . '|' . $url . '")');
                                }
                            }
                            return $response;
                        }
                    } else {
                        $subscriptions = sb_isset(stripe_curl('subscriptions?customer=' . $data['customer'], 'GET'), 'data');
                        membership_update($membership_data[0], $membership_data[2], $membership_data[1], $data['customer'], count($membership_data) > 3 ? $membership_data[3] : false);
                        if ($subscriptions) {
                            $subscription_id = $data['subscription'];
                            for ($i = 0; $i < count($subscriptions); $i++) {
                                if ($subscriptions[$i]['id'] != $subscription_id) {
                                    stripe_curl('subscriptions/' . $subscriptions[$i]['id'], 'DELETE');
                                }
                            }
                        }
                        cloud_add_to_payment_history($membership_data[1], sb_get_stripe_amount($data), 'membership', $data['id'], $price['id']);
                    }
                }
            } else if (!isset($data['metadata']) || !isset($data['metadata']['source']) || $data['metadata']['source'] != 'boxcoin') {
                sb_cloud_debug('Value client_reference_id not found:');
                sb_cloud_debug($response);
            }
            break;
        case 'invoice.paid':
            $response = stripe_curl('invoices/' . $data['id'], 'GET');
            $error = sb_isset($response, 'error');
            if (!$error && !empty($response['customer'])) {
                $data = $response['lines']['data'][0];
                $user_id = sb_isset(db_get('SELECT id FROM users WHERE customer_id = "' . db_escape($response['customer']) . '" LIMIT 1'), 'id');
                if ($user_id) {
                    if (defined('STRIPE_PRODUCT_ID_WHITE_LABEL') && $data['plan']['product'] == STRIPE_PRODUCT_ID_WHITE_LABEL) {
                        membership_save_white_label($user_id);
                        cloud_add_to_payment_history($user_id, sb_get_stripe_amount($data), 'white-label', $data['id']);
                    } else {
                        $price = $data['price'];
                        $period = $price['recurring']['interval'];
                        if ($price['recurring']['interval_count'] > 1) {
                            $period = $price['recurring']['interval_count'] . $period;
                        }
                        membership_update($price['id'], $period, $user_id);
                        cloud_add_to_payment_history($user_id, sb_get_stripe_amount($data), 'membership', $data['id'], $price['id']);
                    }
                } else {
                    sb_cloud_debug('User ID not found:');
                    sb_cloud_debug($response);
                }
            }
            break;
        case 'payment_intent.succeeded':
            $response = stripe_curl('payment_intents/' . $data['id'], 'GET');
            $error = sb_isset($response, 'error');
            if (!$error) {
                if ($response['status'] == 'succeeded') {
                    $is_credits = !empty(sb_isset($response, 'metadata', [])['sb_credits']);
                    if (!$is_credits) {
                        $charges = sb_isset($response, 'charges', [])['data'];
                        $is_credits = $charges && count($charges) && !empty(sb_isset($charges[0], 'metadata', [])['sb_credits']);
                    }
                    if ($is_credits) {
                        $amount = $response['amount'] / currency_get_divider($response['currency']);
                        if ($amount) {
                            $user_id = sb_isset(db_get('SELECT id FROM users WHERE customer_id = "' . db_escape($response['customer']) . '"'), 'id');
                            if ($user_id) {
                                membership_set_purchased_credits($amount, $response['currency'], $user_id, $data['id'], $response['charges']['data'][0]['payment_method']);
                            } else {
                                sb_cloud_debug('User ID not found:');
                                sb_cloud_debug($response);
                            }
                        }
                    }
                }
            }
            break;
    }
    if ($error) {
        sb_cloud_debug('Stripe error:');
        sb_cloud_debug($response);
    }
}

function sb_get_stripe_amount($data) {
    $amount = isset($data['amount']) ? $data['amount'] : (isset($data['amount_total']) ? $data['amount_total'] : $data['amount_paid']);
    return $amount / currency_get_divider($data['currency']);
}

?>