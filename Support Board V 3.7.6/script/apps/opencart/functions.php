<?php

/*
 * ==========================================================
 * OPENCART APP
 * ==========================================================
 *
 * OpenCart App main file. © 2017-2024 board.support. All rights reserved.
 *
 */

define('SB_OPENCART', '1.0.0');

function sb_opencart_panel($opencart_id, $store_url = false) {
    $total = 0;
    $customer_id = explode('|', $opencart_id)[0];
    $cart = sb_opencart_curl('api/sb/cart', '&customer_id=' . $customer_id);
    $orders = sb_opencart_curl('api/sb/orders', '&customer_id=' . $customer_id);
    $count = count($cart);
    $count_orders = count($orders);
    for ($i = 0; $i < count($orders); $i++) {
        $total += round($orders[$i]['total'], 2);
    }
    $code = '<i class="sb-icon-refresh"></i><h3>OpenCart</h3><div><div class="sb-split"><div><div class="sb-title">' . sb_('Number of orders') . '</div><span>' . $count_orders . ' ' . sb_('orders') . '</span></div><div><div class="sb-title">' . sb_('Total spend') . '</div><span>' . sb_get_multi_setting('opencart-details', 'opencart-details-currency') . ' ' . $total . '</span></div></div><div class="sb-title">' . sb_('Cart') . '</div><div class="sb-list-items sb-list-links sb-opencart-cart">';
    if ($count) {
        for ($i = 0; $i < $count; $i++) {
            $product = $cart[$i];
            $id = $product['product_id'];
            $url = $product['url'];
            if ($store_url && strpos($url, $store_url) === false) {
                $url = 'https://' . $store_url . '/index.php?route=product/product&product_id=' . $id;
            }
            $code .= '<a href="' . $url . '" target="_blank" data-id="' . $id . '"><span>#' . $id . '</span> <span>' . $product['name'] . '</span> <span>x ' . $product['quantity'] . '</span></a>';
        }
    } else {
        $code .= '<p>' . sb_('The cart is currently empty.') . '</p>';
    }
    $code .= '</div>';
    if ($count_orders) {
        $code .= '<div class="sb-title">' . sb_('Orders') . '</div><div class="sb-list-items sb-list-links sb-opencart-orders">';
        for ($i = 0; $i < $count_orders; $i++) {
            $order = $orders[$i];
            $code .= '<a data-id="' . $order['order_id'] . '"><span>#' . $order['order_id'] . '</span> <span>' . $order['date_added'] . '</span> <span>' . $order['currency_code'] . ' ' . round($order['total']) . '</span></a>';
        }
        $code .= '</div>';
    }
    return $code;
}

function sb_opencart_order_details($order_id) {
    $order = sb_opencart_curl('api/sb/orderdetails', '&order_id=' . $order_id);
    $code = '<div class="sb-bold-list"><p>';
    $products = $order['products'];
    $count = count($products);
    unset($order['products']);
    unset($order['user_agent']);
    unset($order['accept_language']);
    foreach ($order as $key => $value) {
        if (!empty($value) && $value != '[]') {
            $code .= '<b>' . sb_string_slug($key, 'string') . '</b> ' . $value . '<br />';
        }
    }
    if ($count) {
        $code .= '<b class="oc-b-products">' . sb_('Products') . '</b><span class="sb-panel-details"><span class="sb-list-items sb-list-links">';
        for ($i = 0; $i < $count; $i++) {
            $product = $products[$i];
            $code .= '<a href="' . $product['url'] . '" target="_blank" data-id="' . $product['product_id'] . '"><span>#' . $product['product_id'] . '</span> <span>' . $product['name'] . '</span> <span>x ' . $product['quantity'] . '</span></a>';
        }
        $code . '</span></span></p>';
    }
    return $code . '</div>';
}

function sb_opencart_curl($route, $parameters = '', $post_fields = []) {
    $open_cart = sb_get_setting('opencart-details');
    $token = sb_get_external_setting('opencart-token');
    $url = sb_isset($open_cart, 'opencart-details-url');
    if (!$open_cart || !$url) {
        return sb_error('OpenCart details not found', 'sb_opencart_curl', 'Enter the details in Settings > OpenCart > OpenCart details.', true);
    }
    if (substr($url, -1) == '/') {
        $url = substr($url, 0, -1);
    }
    if (!$token) {
        $response = sb_curl($url . '/index.php?route=api/login', ['username' => sb_isset($open_cart, 'opencart-details-user', 'Default'), 'key' => $open_cart['opencart-details-api-key']], ['Content-Type: multipart/form-data']);
        $token = sb_isset($response, 'api_token');
        if ($token) {
            sb_save_external_setting('opencart-token', $token);
        } else {
            return sb_error('Invalid API token', 'sb_opencart_curl', json_encode($response), true);
        }
    }
    return sb_curl($url . '/index.php?route=' . $route . '&api_token=' . $token . $parameters, $post_fields, ['Content-Type: multipart/form-data']);
}

function sb_opencart_sync() {
    $customers = sb_opencart_curl('api/sb/customers');
    for ($i = 0; $i < count($customers); $i++) {
        $customer = $customers[$i];
        if (!sb_get_user_by('email', $customer['email'])) {
            sb_add_user(['first_name' => $customer['firstname'], 'last_name' => $customer['lastname'], 'email' => $customer['email']], ['phone' => [$customer['telephone'], 'Phone'], 'opencart_id' => [$customer['customer_id'], 'OpenCart ID'], 'opencart_store' => [$customer['name'], 'OpenCart Store'], 'opencart_store_url' => [$customer['url'], 'OpenCart Store URL']]);
        }
    }
    return true;
}

?>