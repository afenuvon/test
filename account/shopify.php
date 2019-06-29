<?php

/*
 * ==========================================================
 * SHOPIFY
 * ==========================================================
 *
 * Shopify main file. © 2017-2024 board.support. All rights reserved.
 *
 */

require('functions.php');

$raw = file_get_contents('php://input');
$headers = apache_request_headers();
if ($raw) {
    $calculated_hmac = base64_encode(hash_hmac('sha256', $raw, SHOPIFY_CLIENT_SECRET, true));
    if (!hash_equals($calculated_hmac, sb_isset($headers, 'X-Shopify-Hmac-Sha256'))) {
        http_response_code(401);
        die();
    }
    $response = json_decode($raw, true);
    $one_time_purchase = sb_isset($response, 'app_purchase_one_time');
    if ($one_time_purchase) {
        $shopify_shop = sb_isset($headers, 'X-Shopify-Shop-Domain');
        if ($shopify_shop) {
            $cloud_user_id = sb_isset(db_get('SELECT user_id FROM users_data WHERE slug = "shopify_shop" AND value = "' . db_escape($shopify_shop) . '" ORDER BY id DESC LIMIT 1'), 'user_id');
            if ($cloud_user_id) {
                $purchase_name = $one_time_purchase['name'];
                if (strpos($purchase_name, 'Add credits') !== false) {
                    $amount = explode(' ', explode(' - ', $purchase_name)[1]);
                    membership_set_purchased_credits($amount[0], $amount[1], $cloud_user_id, 'shopify');
                } else if (strpos($purchase_name, 'White Label') !== false) {
                    membership_save_white_label($cloud_user_id);
                }
            }
        }
    }
}
if (isset($_GET['hmac'])) {
    $account = account();
    $shopify_shop = sb_isset($_GET, 'shop');
    $auth_url = 'https://' . $shopify_shop . '/admin/oauth/authorize?client_id=faab7cc14479dc1df1d403a5fc4f40d6&scope=customer_read_customers,read_customers&redirect_uri=' . CLOUD_URL . '/account/shopify.php';
    if (!$account) {
        header('Location: ' . CLOUD_URL . '/account?redirect=' . urlencode($auth_url));
        return;
    }
    $cloud_user_id = $account['user_id'];
    if (super_get_user_data('shopify_id', $cloud_user_id)) {
        header('Location: ' . CLOUD_URL);
        return;
    }
    $code = sb_isset($_GET, 'code');
    if (!$code) {
        header('Location: ' . $auth_url);
        return;
    }
    $response = shopify_curl('oauth/access_token', $shopify_shop, false, ['client_id' => SHOPIFY_CLIENT_ID, 'client_secret' => SHOPIFY_CLIENT_SECRET, 'code' => $code], [], 'GET');
    $access_token = sb_isset(json_decode($response, true), 'access_token');
    if ($access_token) {
        $chat_id = account_chat_id($cloud_user_id);
        $response = shopify_curl('api/2023-07/metafields.json', $shopify_shop, $access_token, ['metafield' => ['namespace' => 'support_board', 'key' => 'chat_id', 'value' => $chat_id, 'type' => 'single_line_text_field']]);
        $metafield = sb_isset($response, 'metafield');
        $shopify_shop_id = sb_isset($metafield, 'owner_id');
        if (sb_isset($metafield, 'value') == $chat_id) {
            sb_save_external_setting('shopify_token', $access_token);
            if ($shopify_shop_id) {
                db_query('DELETE FROM users_data WHERE user_id = ' . $cloud_user_id . ' AND (slug = "shopify_token" || slug = "shopify_id" || slug = "shopify_shop")');
                super_insert_user_data('(' . $cloud_user_id . ', "shopify_token", "' . $access_token . '"), (' . $cloud_user_id . ', "shopify_id", "' . $shopify_shop_id . '"), (' . $cloud_user_id . ', "shopify_shop", "' . $shopify_shop . '")');
            }
            header('Location: ' . CLOUD_URL);
            return;
        }
    }
    die(json_encode($response));
} else if ($raw) {
    $response = sb_isset(json_decode($raw, true), 'app_subscription');
    $shopify_shop_id = sb_isset($response, 'admin_graphql_api_shop_id');
    if ($shopify_shop_id && sb_isset($response, 'status') == 'ACTIVE') {
        $shopify_shop_id = substr($shopify_shop_id, strrpos($shopify_shop_id, '/') + 1);
        $cloud_user_id = sb_isset(db_get('SELECT user_id FROM users_data WHERE slug = "shopify_id" AND value = "' . db_escape($shopify_shop_id, true) . '" ORDER BY id DESC LIMIT 1'), 'user_id');
        if ($cloud_user_id) {
            $plan_name = sb_isset($response, 'name');
            $memberships = memberships();
            for ($i = 0; $i < count($memberships); $i++) {
                if ($memberships[$i]['name'] == $plan_name) {
                    membership_update($memberships[$i]['id'], $memberships[$i]['period'], $cloud_user_id);
                    return;
                }
            }

        }
    }
    header('Location: ' . CLOUD_URL);
}
if (sb_isset($headers, 'X-Shopify-Topic') == 'app/uninstalled') {
    $shopify_shop = sb_isset($headers, 'X-Shopify-Shop-Domain');
    if ($shopify_shop) {
        $cloud_user_id = sb_isset(db_get('SELECT A.id FROM users A, users_data B WHERE slug = "shopify_shop" AND value = "' . $shopify_shop . '" AND A.id = B.user_id'), 'id');
        if ($cloud_user_id) {
            $shopify_token = sb_isset(db_get('SELECT value FROM users_data WHERE slug = "shopify_token" AND user_id = ' . $cloud_user_id . ' LIMIT 1'), 'value');
            if ($shopify_token) {
                $status_code = shopify_curl('api/2023-07/metafields.json?namespace=support_board', $shopify_shop, $shopify_token, '', [], 'GET-SC')[1];
                if ($status_code == 401) {
                    db_query('DELETE FROM users_data WHERE user_id = ' . $cloud_user_id . ' AND (slug = "shopify_token" || slug = "shopify_id" || slug = "shopify_shop")');
                }
            }
        }
    }
}

?>