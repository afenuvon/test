<?php

/*
 * ==========================================================
 * VERIFONE.PHP
 * ==========================================================
 *
 * Process 2Checkout Verifone payments
 *
 */

header('Content-Type: application/json');
$raw = file_get_contents('php://input');

if ($raw) {
    require('functions.php');
    $response = [];
    $rows = explode('&', urldecode($raw));
    for ($i = 0; $i < count($rows); $i++) {
        $value = explode('=', $rows[$i]);
        $response[$value[0]] = str_replace('\/', '/', $value[1]);
    }
    $message_type = sb_isset($response, 'message_type');
    if ($message_type == 'ORDER_CREATED' || $message_type == 'RECURRING_INSTALLMENT_SUCCESS') {
        $metadata = explode('|', sb_encryption($response['vendor_order_id'], false));
        if (!is_array($metadata)) die();
        if ($metadata[0] == 'white_label') {
            membership_save_white_label($metadata[1]);
        } else {
            $count = count($metadata);
            if ($count > 2) {
                $membership_id = $metadata[1];
                $subscriptions = sb_isset(verifone_curl('subscriptions?CustomerEmail=' . $response['customer_email'] . '&SubscriptionEnabled=true', 'GET'), 'Items');
                membership_update($membership_id, $metadata[2], $metadata[0], $response['customer_email'], $count > 3 ? $metadata[3] : false);
                if ($subscriptions) {
                    for ($i = 0; $i < count($subscriptions); $i++) {
                        $id = $subscriptions[$i]['SubscriptionReference'];
                        if ($id != $membership_id) {
                            verifone_curl('subscriptions/' . $id, 'DELETE');
                        }
                    }
                }
            }
        }
    }
}

?>