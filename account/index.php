<?php
use Componere\Value;

global $cloud_settings;
require_once('functions.php');
sb_cloud_load();
$account = account();
$cloud_settings = super_get_settings();
$rtl = sb_get_setting('rtl-admin') || defined('SB_CLOUD_DEFAULT_RTL');
$custom_code = db_get('SELECT value FROM settings WHERE name = "custom-code-admin"');
if (!function_exists('sup' . 'er_adm' . 'in_con' . 'fig')) {
    die();
}
if (isset($_GET['login_email'])) {
    $account = false;
}
if (sb_isset($_GET, 'payment_type') == 'credits' && PAYMENT_PROVIDER == 'stripe') {
    $required_action = super_get_user_data('stripe_next_action', $account['user_id']);
    if ($required_action) {
        $required_action = explode('|', $required_action);
        if ($required_action[0] > (time() - 86400)) {
            super_delete_user_data($account['user_id'], 'stripe_next_action');
            header('Location: ' . $required_action[1]);
            die();
        }
    }
}
?>
<html lang="en-US">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />
    <title>
        <?php echo SB_CLOUD_BRAND_NAME ?>
    </title>
    <script src="../script/js/min/jquery.min.js"></script>
    <script id="sbinit" src="../script/js/<?php echo sb_is_debug() ? 'main' : 'min/main.min' ?>.js?v=<?php echo SB_VERSION ?>"></script>
    <link rel="stylesheet" href="../script/css/admin.css?v=<?php echo SB_VERSION ?>" type="text/css" media="all" />
    <link rel="stylesheet" href="../script/css/responsive-admin.css?v=<?php echo SB_VERSION ?>" media="(max-width: 464px)" />
    <?php
    if ($rtl) {
        echo '<link rel="stylesheet" href="../script/css/rtl-admin.css?v=' . SB_VERSION . '" />';
    }
    ?>
    <link rel="stylesheet" href="css/skin.min.css?v=<?php echo SB_VERSION ?>" type="text/css" media="all" />
    <link rel="shortcut icon" href="<?php echo SB_CLOUD_BRAND_ICON ?>" />
    <link rel="apple-touch-icon" href="<?php echo SB_CLOUD_BRAND_ICON_PNG ?>" />
    <link rel="manifest" href="<?php echo SB_CLOUD_MANIFEST_URL ?>" />
    <?php account_js() ?>
</head>
<body class="on-load<?php echo $rtl ? ' sb-rtl' : '' ?>">
    <div id="preloader"></div>
    <?php
    cloud_custom_code();
    if ($account && !empty($_COOKIE['sb-cloud'])) {
        if (empty($account['owner']) && db_get('SELECT id FROM agents WHERE admin_id = ' . db_escape($account['user_id'], true) . ' AND email = "' . $account['email'] . '"')) { // Deprecated. Remove && db_get('....
            echo '<script>document.location = "' . CLOUD_URL . '"</script>';
        } else {
            box_account();
        }
    } else {
        $GLOBALS['SB_LANGUAGE'] = [sb_defined('SB_CLOUD_DEFAULT_LANGUAGE_CODE'), 'front'];
        box_registration_login();
    }
    ?>
    <footer>
        <script src="js/cloud<?php echo (sb_is_debug() ? '' : '.min') ?>.js?v=<?php echo SB_VERSION ?>"></script>
        <?php sb_cloud_css_js() ?>
    </footer>
</body>
</html>

<?php function box_account() {
    global $cloud_settings;
    $membership = membership_get_active(false);
    $expiration = DateTime::createFromFormat('d-m-y', $membership['expiration']);
    $expired = $membership['price'] != 0 && (!$expiration || time() > $expiration->getTimestamp());
    $shopify = defined('SHOPIFY_CLIENT_ID') ? super_get_user_data('shopify_shop', get_active_account_id()) : false;
    echo '<script>var messages_volume = [' . implode(',', membership_volume()) . ']; var membership = { quota: ' . $membership['quota'] . ', count: ' . $membership['count'] . ', expired: ' . ($expired ? 'true' : 'false') . (isset($membership['quota_agents']) ? (', quota_agents: ' . $membership['quota_agents'] . ', count_agents: ' . $membership['count_agents']) : '') . ', credits: ' . $membership['credits'] . ' }; var CLOUD_USER_ID = ' . account()['user_id'] . '; var CLOUD_CURRENCY = "' . strtoupper(membership_currency()) . '"; var TWILIO_SMS = ' . (defined('CLOUD_TWILIO_SID') && !empty(CLOUD_TWILIO_SID) ? 'true' : 'false') . '; var external_integration = "' . ($shopify ? 'shopify' : '') . '";</script>' . PHP_EOL; ?>
    <div class="sb-account-box sb-admin sb-loading">
        <div class="sb-top-bar">
            <div>
                <h2>
                    <img src="<?php echo SB_CLOUD_BRAND_ICON ?>" />
                    <?php sb_e('Account') ?>
                </h2>
            </div>
            <div>
                <a class="sb-btn sb-btn-dashboard" href="../">
                    <?php sb_e('Dashboard') ?>
                </a>
            </div>
        </div>
        <div class="sb-tab">
            <div class="sb-nav">
                <div>
                    <?php sb_e('Installation') ?>
                </div>
                <ul>
                    <li id="nav-installation" class="sb-active">
                        <?php sb_e('Installation') ?>
                    </li>
                    <li id="nav-membership">
                        <?php sb_e('Membership') ?>
                    </li>
                    <li id="nav-invoices">
                        <?php sb_e(PAYMENT_PROVIDER == 'stripe' ? 'Invoices' : 'Payments') ?>
                    </li>
                    <li id="nav-profile">
                        <?php sb_e('Profile') ?>
                    </li>
                    <?php
                    if (!empty($cloud_settings['referral-commission'])) {
                        echo '<li id="nav-referral">' . sb_('Refer a friend') . '</li>';
                    }
                    ?>
                    <li id="nav-logout">
                        <?php sb_e('Logout') ?>
                    </li>
                </ul>
                <?php
                if (defined('SB_CLOUD_DOCS')) {
                    echo '<a href=" ' . SB_CLOUD_DOCS . '" target="_blank" class="sb-docs sb-btn-text"><i class="sb-icon-help"></i> ' . sb_('Help') . '</a>';
                }
                ?>
            </div>
            <div class="sb-content sb-scroll-area">
                <div id="tab-installation" class="sb-active">
                    <h2 class="addons-title first-title">
                        <?php sb_e($shopify ? 'Installation' : 'Embed code') ?>
                    </h2>
                    <?php
                    if ($shopify) {
                        echo '<p>' . str_replace('{R}', SB_CLOUD_BRAND_NAME, sb_('Customize your store and enable {R} in the app embeds section.')) . '</p><a class="sb-btn sb-btn-white" href="https://' . $shopify . '/admin/themes/current/editor?context=apps&activateAppId=' . SHOPIFY_APP_ID . '/sb" target="_blank">' . sb_('Preview in theme') . '</a>';
                    } else {
                        echo '<p>' . sb_(sb_isset($cloud_settings, 'text_embed_code', 'To add the chat to your website, paste this code before the closing </body> tag on each page. Then, reload your website to see the chat in the bottom-right corner. Click the dashboard button in the top-right to access the admin area.')) . '</p><div class="sb-setting"><textarea id="embed-code" readonly></textarea></div>';
                    }
                    if (defined('DIRECT_CHAT_URL')) {
                        $link = DIRECT_CHAT_URL . '/' . account_chat_id(account()['user_id']);
                        echo '<h2 class="addons-title">' . sb_('Chat direct link') . '</h2><p>' . sb_('Use this unique URL to access the chat widget directly. Include the attribute ?ticket in the URL to view the tickets area.') . '</p><div class="sb-setting sb-direct-link"><input onclick="window.open(\'' . $link . '\')" value="' . $link . '" readonly /></div>';
                    }
                    if (defined('ARTICLES_URL')) {
                        $link = ARTICLES_URL . '/' . account_chat_id(account()['user_id']);
                        echo '<h2 class="addons-title">' . sb_('Articles link') . '</h2><p>' . sb_('Use this unique URL to access the articles page. See the docs for other display options.') . '</p><div class="sb-setting sb-direct-link"><input onclick="window.open(\'' . $link . '\')" value="' . $link . '" readonly /></div>';
                    }
                    ?>
                    <h2 class="addons-title">
                        <?php sb_e('API token') ?>
                    </h2>
                    <p>
                        <?php sb_e(str_replace('{R}', SB_CLOUD_BRAND_NAME, 'The API token is a required for using the {R} WEB API.')) ?>
                    </p>
                    <div class="sb-setting">
                        <input value="<?php echo account()['token'] ?>" readonly />
                    </div>
                </div>
                <div id="tab-membership">
                    <h2 class="addons-title first-title">
                        <?php sb_e('Membership') ?>
                    </h2>
                    <?php box_membership($membership) ?>
                    <hr />
                    <?php box_membership_plans($expired ? false : $membership) ?>
                    <?php box_credits(!$shopify) ?>
                    <?php box_addons() ?>
                    <?php box_chart() ?>
                    <hr />
                    <hr />
                    <?php
                    if (!$shopify) {
                        button_cancel_membership($membership);
                    }
                    ?>
                </div>
                <div id="tab-invoices" class="sb-loading">
                    <h2 class="addons-title first-title">
                        <?php sb_e(PAYMENT_PROVIDER == 'stripe' ? 'Invoices' : 'Payments') ?>
                    </h2>
                    <p>
                        <?php sb_e(PAYMENT_PROVIDER == 'stripe' ? 'Download your invoices here.' : 'View your payments here.') ?>
                    </p>
                    <table class="sb-table">
                        <tbody></tbody>
                    </table>
                </div>
                <div id="tab-profile">
                    <h2 class="addons-title first-title">
                        <?php sb_e('Manage profile') ?>
                    </h2>
                    <p>
                        <?php sb_e('Update here your profile information.') ?>
                    </p>
                    <div id="first_name" data-type="text" class="sb-input">
                        <span>
                            <?php sb_e('First name') ?>
                        </span>
                        <input type="text" />
                    </div>
                    <div id="last_name" data-type="text" class="sb-input">
                        <span>
                            <?php sb_e('Last name') ?>
                        </span>
                        <input type="text" />
                    </div>
                    <div id="email" data-type="text" class="sb-input sb-type-input-button">
                        <span>
                            <?php sb_e('Email') ?>
                        </span>
                        <input type="email" readonly />
                        <a class="sb-btn btn-verify-email">
                            <?php sb_e('Verify') ?>
                        </a>
                    </div>
                    <div id="phone" data-type="text" class="sb-input sb-type-input-button">
                        <span>
                            <?php sb_e('Phone') ?>
                        </span>
                        <input type="tel" />
                        <a class="sb-btn btn-verify-phone">
                            <?php sb_e('Verify') ?>
                        </a>
                    </div>
                    <div id="password" data-type="text" class="sb-input sb-type-input-button">
                        <span>
                            <?php sb_e('Password') ?>
                        </span>
                        <input type="password" value="12345678" />
                    </div>
                    <div id="company_details" data-type="text" class="sb-input">
                        <span>
                            <?php sb_e('Company details') ?>
                        </span>
                        <input type="text" />
                    </div>
                    <hr />
                    <div class="sb-flex">
                        <a id="save-profile" class="sb-btn sb-btn-white sb-icon">
                            <i class="sb-icon-check"></i>
                            <?php sb_e('Save changes') ?>
                        </a>
                        <a id="delete-account" class="sb-btn-text">
                            <?php sb_e('Delete account') ?>
                        </a>
                    </div>

                </div>
                <?php box_referral() ?>
            </div>
        </div>
    </div>

<?php } ?>

<?php

function box_membership($membership) {
    $membership_type = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages');
    $membership_type_ma = $membership_type == 'messages-agents';
    $name = $membership_type_ma ? 'messages' : $membership_type;
    $box_two = $membership_type_ma ? '<div><span>' . $membership['count_agents'] . ' / <span class="membership-quota">' . ($membership['quota_agents'] == 9999 ? '∞' : $membership['quota_agents']) . '</span></span> <span>' . sb_('Agents') . '</span></div>' : '';
    $price_string = $membership['price'] == 0 ? '' : (substr($membership['expiration'], -2) == '37' ? '<span id="membership-appsumo" data-id="' . account_get_payment_id() . '"></span>' : (mb_strtoupper($membership['currency']) . ' ' . $membership['price'] . ' ' . membership_get_period_string($membership['period'])));
    echo '<div class="box-maso box-membership"><div class="box-black"><h2>' . sb_(date('F')) . ', ' . date('Y') . '</h2><div><div><span>' . $membership['count'] . ' / <span class="membership-quota">' . $membership['quota'] . '</span></span> <span>' . sb_($name) . '</span></div>' . $box_two . '</div></div><div class="box-black"><h2>' . sb_('Active Membership') . '</h2><div><div><span class="membership-name">' . sb_($membership['name']) . '</span> <span class="membership-price" data-currency="' . $membership['currency'] . '">' . $price_string . '</span></div></div></div></div>';
}

function box_membership_plans($exclude = false) {
    $plans = memberships();
    $code = '<div id="plans" class="plans-box">';
    $menu_items = [];
    $membership_type = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages');
    $membership_type_ma = $membership_type == 'messages-agents';
    for ($i = 1; $i < count($plans); $i++) {
        $plan = $plans[$i];
        $plan_period = $plan['period'];
        $menu = $plan_period == 'month' ? 'Monthly' : ($plan_period == 'year' ? 'Annually' : 'More');
        if ($exclude && $exclude['id'] == $plan['id']) {
            continue;
        }
        $period_top = membership_get_period_string($plan_period);
        $period = $membership_type_ma || $membership_type == 'messages' ? $period_top : '';
        if (!in_array($menu, $menu_items)) {
            array_push($menu_items, $menu);
        }
        $code .= '<div data-menu="' . $menu . '" data-id="' . $plan['id'] . '"><h3>' . mb_strtoupper($plan['currency']) . ' ' . $plan['price'] . ' ' . $period_top . '</h3><h4>' . $plan['name'] . '</h4><p>' . $plan['quota'] . ' ' . sb_($membership_type_ma ? 'messages' : $membership_type) . ' ' . $period . ($membership_type_ma ? ('<br>' . ($plan['quota_agents'] == 9999 ? sb_('unlimited') : $plan['quota_agents']) . ' ' . sb_('agents')) : '') . '<br>' . cloud_embeddings_chars_limit($plan) . ' ' . sb_('characters to train the chatbot') . '</p></div>';
    }
    $code .= '</div>';
    if (count($menu_items) > 1) {
        $menu = ['Monthly', 'Annually', 'More'];
        $code_menu = '<div class="plans-box-menu sb-menu-wide"><div>' . sb_($menu_items[0]) . '</div><ul>';
        for ($i = 0; $i < count($menu); $i++) {
            if (in_array($menu[$i], $menu_items)) {
                $code_menu .= '<li data-type="' . $menu[$i] . '">' . sb_($menu[$i]) . '</li>';
            }
        }
        $code = $code_menu . '</ul></div>' . $code;
    }
    echo $code;
}

function box_credits($auto_recharge = true) {
    if (!sb_defined('GOOGLE_CLIENT_ID') && !sb_defined('OPEN_AI_KEY') && !sb_defined('WHATSAPP_APP_ID')) {
        return false;
    }
    $prices = [5, 10, 20, 50, 100, 250, 500, 1000, 3000];
    $code_prices = '';
    $currency = strtoupper(membership_currency());
    $exchange_rate = $currency == 'USD' ? 1 : sb_usd_rates($currency);
    for ($i = 0; $i < count($prices); $i++) {
        $prices[$i] = intval($prices[$i] * $exchange_rate);
        $code_prices .= '<option value="' . $prices[$i] . '">' . $currency . ' ' . $prices[$i] . '</option>';
    }
    $user_id = db_escape(account()['user_id'], true);
    $credits = sb_isset(db_get('SELECT credits FROM users WHERE id = ' . $user_id), 'credits', 0);
    $checked = super_get_user_data('auto_recharge', $user_id) ? ' checked' : '';
    echo '<h2 id="credits" class="addons-title">' . sb_('Credits') . '</h2><p>' . str_replace('{R}', '<a href="' . (defined('SB_CLOUD_DOCS') ? SB_CLOUD_DOCS : '') . '#cloud-credits" target="_blank" class="sb-link-text">here</a>', sb_('Credits are required to use some features in automatic sync mode. If you don\'t want to buy credits, switch to manual sync mode and use your own API key. For more details click {R}.')) . '</p><div class="box-maso maso-box-credits"><div class="box-black"><h2>' . sb_('Active credits') . '</h2><div>' . ($credits ? $credits : '0') . '</div></div><div><h2>' . sb_('Add credits') . '</h2><div><div id="add-credits" data-type="text" class="sb-input"><select><option></option>' . $code_prices . '</select></div></div></div>' . (PAYMENT_PROVIDER == 'stripe' && $auto_recharge ? '<div><h2>' . sb_('Auto recharge') . '</h2><div><div id="credits-recharge" data-type="checkbox" class="sb-input"><input type="checkbox"' . $checked . '></div></div></div>' : '') . '</div>';
}

function box_addons() {
    $white_label_price = super_get_white_label();
    $addons = sb_defined('CLOUD_ADDONS');
    if ($white_label_price || $addons) {
        $account = account();
        $code = '<h2 class="addons-title">' . sb_('Add-ons') . '</h2><p>' . sb_('Add-ons are optional features with a fixed subscription cost.') . '</p><div id="addons" class="plans-box">';
        if ($white_label_price) {
            $code .= '<div class="sb-visible' . (membership_is_white_label($account['user_id']) ? ' sb-plan-active' : '') . '" id="purchase-white-label"><h3>' . strtoupper(membership_currency()) . ' ' . $white_label_price . ' ' . sb_('a year') . '</h3><h4>' . sb_('White Label') . '</h4><p>' . sb_('Remove our branding and logo from the chat widget.') . '</p></div>';
        }
        if ($addons) {
            for ($i = 0; $i < count($addons); $i++) {
                $addon = $addons[$i];
                $code .= '<div class="sb-visible sb-custom-addon" data-index="' . $i . '" data-id="' . sb_string_slug($addon['title']) . '"><h3>' . strtoupper(membership_currency()) . ' ' . $addon['price'] . '</h3><h4>' . sb_($addon['title']) . '</h4><p>' . sb_($addon['description']) . '</p></div>';
            }
        }
        echo $code . '</div>';
    }
}

function button_cancel_membership($membership) {
    if ($membership['price'] != 0) {
        echo '<a id="cancel-subscription" class="sb-btn-text sb-icon sb-btn-red"><i class="sb-icon-close"></i>' . sb_('Cancel subscription') . '</a>';
    }
}

function account_js() {
    $account = account();
    global $cloud_settings;
    if ($account) {
        require_once('../script/config/config_' . account()['token'] . '.php');
    } else {
        echo '<script>var SB_URL = "' . CLOUD_URL . '/script"; var SB_CLOUD_SW = true; var SB_DISABLED = true; (function() { SBF.serviceWorker.init(); }())</script>';
    }
    if ($cloud_settings) {
        unset($cloud_settings['js']);
        unset($cloud_settings['js-front']);
        unset($cloud_settings['css']);
        unset($cloud_settings['css-front']);
    }
    if (isset($_GET['appsumo']) && sb_is_agent()) {
        die('<script>document.cookie="sb-login=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/;";document.cookie="sb-cloud=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/;";location.reload();</script>');
    }
    $language = sb_get_admin_language();
    $translations = ($language && $language != 'en' ? file_get_contents(SB_PATH . '/resources/languages/admin/js/' . $language . '.json') : '[]');
    echo '<script>var CLOUD_URL = "' . CLOUD_URL . '"; var BRAND_NAME = "' . SB_CLOUD_BRAND_NAME . '"; var PUSHER_KEY = "' . sb_pusher_get_details()[0] . '"; var LANGUAGE = "' . sb_get_admin_language() . '"; var SETTINGS = ' . ($cloud_settings ? json_encode($cloud_settings, JSON_INVALID_UTF8_IGNORE) : '{}') . '; var SB_TRANSLATIONS = ' . ($translations ? $translations : '[]') . '; var PAYMENT_PROVIDER = "' . PAYMENT_PROVIDER . '"; var MEMBERSHIP_TYPE = "' . sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages') . '";</script>';
}

function box_chart() {
    if (in_array(sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages'), ['messages', 'messages-agents'])) {
        echo '<div class="chart-box"><div><h2 class="addons-title">' . sb_('Monthly usage in') . ' ' . date('Y') . '</h2><p>' . sb_('The number of messages sent monthly, all messages are counted, including messages from agents, administrators and chatbot.') . '</p></div></div><canvas id="chart-usage" class="sb-loading" height="100"></canvas>';
    }
}

?>

<?php function box_registration_login() {
    $appsumo = base64_decode(sb_isset($_GET, 'appsumo'));
    global $cloud_settings; ?>
    <div class="sb-registration-box sb-cloud-box sb-admin-box<?php echo !isset($_GET['login']) && !isset($_GET['reset']) ? ' active' : '' ?>">
        <div class="sb-info"></div>
        <div class="sb-top-bar">
            <img src="<?php echo SB_CLOUD_BRAND_LOGO ?>" />
            <div class="sb-title">
                <?php sb_e('New account') ?>
            </div>
            <div class="sb-text">
                <?php sb_e($appsumo ? 'Complete the AppSumo registration.' : 'Create your free account. No payment information required.') ?>
            </div>
        </div>
        <div class="sb-main">
            <div id="first_name" class="sb-input">
                <span>
                    <?php sb_e('First name') ?>
                </span>
                <input type="text" required />
            </div>
            <div id="last_name" class="sb-input">
                <span>
                    <?php sb_e('Last name') ?>
                </span>
                <input type="text" required />
            </div>
            <div id="email" class="sb-input">
                <span>
                    <?php sb_e('Email') ?>
                </span>
                <input type="email" <?php echo $appsumo ? 'value="' . $appsumo . '" readonly="true" style="color:#989898"' : '' ?> required />
            </div>
            <div id="password" class="sb-input">
                <span>
                    <?php sb_e('Password') ?>
                </span>
                <input type="password" required />
            </div>
            <div id="password_2" class="sb-input">
                <span>
                    <?php sb_e('Repeat password') ?>
                </span>
                <input type="password" required />
            </div>
            <?php
            $code = '';
            for ($i = 1; $i < 5; $i++) {
                $name = sb_isset($cloud_settings, 'registration-field-' . $i);
                if ($name) {
                    $code .= '<div id="' . sb_string_slug($name) . '" class="sb-input"><span>' . sb_($name) . '</span><input type="text" required /></div>';
                }
            }
            echo $code;
            ?>
            <div class="sb-bottom">
                <div class="sb-btn btn-register">
                    <?php sb_e('Create account') ?>
                </div>
                <div class="sb-text">
                    <?php sb_e('Already have an account?') ?>
                </div>
                <div class="sb-text sb-btn-login-box">
                    <?php sb_e('Log in') ?>
                </div>
            </div>
            <div class="sb-errors-area"></div>
        </div>
        <div class="loading-screen">
            <i class="sb-loading"></i>
            <p>
                <?php sb_e('We are creating your account...') ?>
            </p>
        </div>
    </div>
    <div class="sb-login-box sb-cloud-box sb-admin-box<?php if (isset($_GET['login']))
        echo ' active' ?>">
            <div class="sb-info"></div>
            <div class="sb-top-bar">
                <img src="<?php echo SB_CLOUD_BRAND_LOGO ?>" />
            <div class="sb-title">
                <?php sb_e('Sign in to your account') ?>
            </div>
            <div class="sb-text">
                <?php echo sb_('To continue to') . ' ' . SB_CLOUD_BRAND_NAME ?>
            </div>
        </div>
        <div class="sb-main">
            <div id="email" class="sb-input">
                <span>
                    <?php sb_e('Email') ?>
                </span>
                <input type="email" required />
            </div>
            <div id="password" class="sb-input">
                <span>
                    <?php sb_e('Password') ?>
                </span>
                <input type="password" required />
            </div>
            <div class="sb-text btn-forgot-password">
                <?php sb_e('Forgot your password?') ?>
            </div>
            <div class="sb-bottom">
                <div class="sb-btn btn-login">
                    <?php sb_e('Sign in') ?>
                </div>
                <div class="sb-text">
                    <?php sb_e('Need new account?') ?>
                </div>
                <div class="sb-text btn-registration-box">
                    <?php sb_e('Sign up free') ?>
                </div>
            </div>
            <div class="sb-errors-area"></div>
        </div>
    </div>
    <div class="sb-reset-password-box sb-cloud-box sb-admin-box">
        <div class="sb-info"></div>
        <div class="sb-top-bar">
            <img src="<?php echo SB_CLOUD_BRAND_LOGO ?>" />
            <div class="sb-title">
                <?php sb_e('Reset password') ?>
            </div>
            <div class="sb-text">
                <?php sb_e('Enter your email below, you will receive an email with instructions on how to reset your password.') ?>
            </div>
        </div>
        <div class="sb-main">
            <div class="sb-input">
                <span>
                    <?php sb_e('Email') ?>
                </span>
                <input id="reset-password-email" type="email" required />
            </div>
            <div class="sb-bottom">
                <div class="sb-btn btn-reset-password">
                    <?php sb_e('Reset password') ?>
                </div>
                <div class="sb-text btn-cancel-reset-password">
                    <?php sb_e('Cancel') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="sb-reset-password-box-2 sb-cloud-box sb-admin-box<?php if (isset($_GET['reset']))
        echo ' active' ?>">
            <div class="sb-info"></div>
            <div class="sb-top-bar">
                <img src="<?php echo SB_CLOUD_BRAND_LOGO ?>" />
            <div class="sb-title">
                <?php sb_e('Reset password') ?>
            </div>
            <div class="sb-text">
                <?php sb_e('Enter your new password here.') ?>
            </div>
        </div>
        <div class="sb-main">
            <div class="sb-input">
                <span>
                    <?php sb_e('Password') ?>
                </span>
                <input id="reset-password-1" type="password" required />
            </div>
            <div class="sb-input">
                <span>
                    <?php sb_e('Repeat password') ?>
                </span>
                <input id="reset-password-2" type="password" required />
            </div>
            <div class="sb-bottom">
                <div class="sb-btn btn-reset-password-2">
                    <?php sb_e('Reset password') ?>
                </div>
            </div>
        </div>
    </div>
    <p class="disclaimer">
        <?php sb_e(sb_isset($cloud_settings, 'disclaimer', 'By creating an account you agree to our <a target="_blank" href="https://board.support/terms-of-service">Terms Of Service</a> and <a target="_blank" href="https://board.support/privacy">Privacy Policy</a>.<br />&copy; 2022-2024 board.support. All rights reserved.')) ?>
    </p>
<?php } ?>

<?php function box_referral() {
    global $cloud_settings;
    if (isset($cloud_settings['referral-commission'])) { ?>
        <div id="tab-referral">
            <h2 class="addons-title first-title">
                <?php sb_e('Refer a friend') ?>
            </h2>
            <p>
                <?php echo sb_isset($cloud_settings, 'referral-text', '') ?>
            </p>
            <div class="sb-input">
                <input value="<?php echo CLOUD_URL . '?ref=' . sb_encryption('encrypt', account()['user_id']) ?>" type="text" readonly />
            </div>
            <hr class="space" />
            <h2 class="addons-title">
                <?php sb_e('Your current earnings') ?>
            </h2>
            <hr />
            <div class="white-box">
                <?php echo strtoupper(membership_currency()) . ' ' . super_get_user_data('referral', account()['user_id'], 0) ?>
            </div>
        </div>
    <?php }
} ?>