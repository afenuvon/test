<?php

/*
 *
 * ===================================================================
 * LOAD THE CHAT
 * ===================================================================
 *
 */

if (isset($_GET['id'])) {
    require('functions.php');
    $cloud_user_id = (intval($_GET['id']) + 153) / 95675;
    $token = cloud_get_token_by_id($cloud_user_id);
    if ($token) {
        die(json_encode([sb_encryption(json_encode(['user_id' => $cloud_user_id, 'token' => $token])), SB_VERSION]));
    }
}
die();

?>