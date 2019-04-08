<?php

require_once('functions.php');
$account = super_admin();
super_admin_config();
$super_branding = defined('SUPER_BRANDING') && SUPER_BRANDING;
$brand_name = $super_branding ? SB_CLOUD_BRAND_NAME : 'Support Board';
?>
<html lang="en-US">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />
    <title>
        Super Admin | <?php echo $brand_name ?> | Cloud
    </title>
    <link rel="stylesheet" href="../script/css/admin.css?v=1" type="text/css" media="all" />
    <link rel="stylesheet" href="css/skin.min.css?v=1" type="text/css" media="all" />
    <link rel="shortcut icon" href="<?php echo $super_branding ? SB_CLOUD_BRAND_ICON : 'media/icon.svg' ?>" />
</head>
<body class="on-load">
    <div id="preloader"></div>
    <main>
        <section class="section-base">
            <div class="container sb-super-box">
                <?php
                if ($account) {
                    box_main($super_branding);
                } else {
                    box_login($super_branding);
                }
                ?>
            </div>
        </section>
    </main>
    <footer>
        <script>
            var MEMBERSHIPS = [<?php echo substr(json_encode(memberships()), 1, -1) ?>]; var SB_TRANSLATIONS = false; var PAYMENT_PROVIDER = "<?php echo PAYMENT_PROVIDER ?>"; var CURRENCY = "<?php echo defined('RAPYD_CURRENCY') ? RAPYD_CURRENCY : (defined('VERIFONE_CURRENCY') ? VERIFONE_CURRENCY : '') ?>"; var MEMBERSHIP_TYPE = "<?php echo defined('SB_CLOUD_MEMBERSHIP_TYPE') ? SB_CLOUD_MEMBERSHIP_TYPE : '' ?>"
        </script>
        <script src="../script/js/min/jquery.min.js"></script>
        <script src="js/cloud<?php echo sb_is_debug() ? '.min' : '' ?>.js?v=<?php echo SB_VERSION ?>"></script>
    </footer>
    <div class="sb-lightbox-overlay"></div>
    <div class="sb-loading-global sb-loading sb-lightbox"></div>
</body>
</html>

<?php function box_main($super_branding) { ?>
    <div class="sb-admin">
        <div class="sb-top-bar">
            <div>
                <h2>
                    <img src="<?php echo $super_branding ? SB_CLOUD_BRAND_ICON : '../script/media/icon.svg' ?>" />
                    Super Admin
                </h2>
            </div>
            <div>
                <a id="logout" class="sb-btn" href="#">
                    Log out
                </a>
            </div>
        </div>
        <div class="sb-tab">
            <div class="sb-nav">
                <div>Customers</div>
                <ul>
                    <li id="nav-customers" class="sb-active">
                        Customers
                    </li>
                    <li id="nav-emails">
                        Emails
                    </li>
                    <li id="nav-settings">
                        Settings
                    </li>
                    <li id="nav-membership-plans">
                        Membership
                    </li>
                    <li id="nav-affiliates">
                        Affiliates
                    </li>
                </ul>
            </div>
            <div class="sb-content sb-scroll-area">
                <div id="tab-customers" class="sb-active sb-loading">
                    <div class="sb-horizontal-scroll">
                        <table class="sb-table table-customers">
                            <thead>
                                <tr>
                                    <th data-field="id">
                                        ID
                                    </th>
                                    <th data-field="name">
                                        Name
                                    </th>
                                    <th data-field="email">
                                        Email
                                    </th>
                                    <th data-field="phone">
                                        Phone
                                    </th>
                                    <th data-field="membership">
                                        Membership
                                    </th>
                                    <th data-field="token">
                                        Token
                                    </th>
                                    <th data-field="creation_time">
                                        Registration date
                                    </th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <hr class="hr-border" />
                        <div id="sb-get-emails" class="sb-btn-text">Get paying customer emails</div>
                        <div id="sb-response-area"></div>
                        <hr class="space-sm" />
                    </div>
                </div>
                <div id="tab-emails" class="sb-loading">
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Welcome</h2>
                            <p>Email sent on user registration. Use the string {user_name} to include the user name.</p>
                        </div>
                        <div class="input">
                            <input id="email_subject_welcome" placeholder="Subject..." type="text" />
                            <textarea id="email_template_welcome" placeholder="Email..."></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Membership quota at 90%</h2>
                            <p>Email sent when the membership quota reach 90%.</p>
                        </div>
                        <div class="input">
                            <input id="email_subject_membership_90" placeholder="Subject..." type="text" />
                            <textarea id="email_template_membership_90" placeholder="Email..."></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Account suspended</h2>
                            <p>Email sent when the membership quota reach 100%, or the membership has expired, and the account is suspended.</p>
                        </div>
                        <div class="input">
                            <input id="email_subject_membership_100" placeholder="Subject..." type="text" />
                            <textarea id="email_template_membership_100" placeholder="Email..."></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Reset password</h2>
                            <p>Email sent to the customer to reset the password of his account. Use the string {link} to include the reset password link.</p>
                        </div>
                        <div class="input">
                            <input id="email_subject_reset_password" placeholder="Subject..." type="text" />
                            <textarea id="email_template_reset_password" placeholder="Email..."></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Email verification code</h2>
                            <p>Email sent to verify the customer' email address. Use the string {code} to include the verification code.</p>
                        </div>
                        <div class="input">
                            <input id="email_subject_verification_code_email" placeholder="Subject..." type="text" />
                            <textarea id="email_template_verification_code_email" placeholder="Email..."></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>No credits</h2>
                            <p>Email send when the user's credits has finished.</p>
                        </div>
                        <div class="input">
                            <input id="email_subject_no_credits" placeholder="Subject..." type="text" />
                            <textarea id="email_template_no_credits" placeholder="Email..."></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Text message | Phone verification code</h2>
                            <p>Text message sent to verify the customer' phone number. Use the string {code} to include the verification code.</p>
                        </div>
                        <div class="input">
                            <textarea id="template_verification_code_phone" placeholder="Text..."></textarea>
                        </div>
                    </div>
                    <hr />
                    <a id="save-emails" class="sb-btn sb-btn-black" href="#">
                        Save emails
                    </a>
                </div>
                <div id="tab-settings" class="sb-loading">
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Embed code message</h2>
                            <p>The text of the embed code message area.</p>
                        </div>
                        <div class="input">
                            <textarea id="text_embed_code"></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Disclaimer</h2>
                            <p>The disclaimer text at the bottom of the registration and login area.</p>
                        </div>
                        <div class="input">
                            <textarea id="disclaimer"></textarea>
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Welcome message</h2>
                            <p>The text shown to the user after registration.</p>
                        </div>
                        <div class="input">
                            <textarea id="text_welcome"></textarea>
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Welcome message title</h2>
                            <p>The title of the welcome message.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="text_welcome_title" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Welcome message image</h2>
                            <p>The URL of the image of the welcome message.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="text_welcome_image" />
                        </div>
                    </div>
                    <div data-type="textarea" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>Suspended message</h2>
                            <p>The text shown to the user when the quota is reached and the account is suspended.</p>
                        </div>
                        <div class="input">
                            <textarea id="text_suspended"></textarea>
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Suspended message title</h2>
                            <p>The title of the suspended message.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="text_suspended_title" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Invoice details</h2>
                            <p>Provide your company information, it will be displayed on the users' invoices.</p>
                        </div>
                        <div class="input">
                            <textarea id="text_invoice"></textarea>
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Color</h2>
                            <p>Change the main color of the customer's admin and account area. Insert HEX or RGB values.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="color" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Hover color</h2>
                            <p>Change the main hover color of the customer's admin and account area. Insert HEX or RGB values.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="color-2" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Optional registration field #1</h2>
                            <p>Show an additional field in the registration form. Enter the field name.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="registration-field-1" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Optional registration field #2</h2>
                            <p>Show an additional field in the registration form. Enter the field name.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="registration-field-2" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Optional registration field #3</h2>
                            <p>Show an additional field in the registration form. Enter the field name.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="registration-field-3" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Optional registration field #4</h2>
                            <p>Show an additional field in the registration form. Enter the field name.</p>
                        </div>
                        <div class="input">
                            <input type="text" id="registration-field-4" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>CSS</h2>
                            <p>
                                Enter the URL of a CSS file, it will be loaded in the customer's admin area and account area.
                            </p>
                        </div>
                        <div class="input">
                            <input type="text" id="css" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>JavaScript</h2>
                            <p>
                                Enter the URL of a JS file, it will be loaded in the customer's admin area and account area. JQuery is supported.                                                                                                                                                                                                          Do not Enter the &lt;script> and &lt;/script> code because it is inserted automatically.
                            </p>
                        </div>
                        <div class="input">
                            <input type="text" id="js" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>CSS Front-side</h2>
                            <p>
                                Enter the URL of a CSS file, it will be loaded alongside the chat widget.
                            </p>
                        </div>
                        <div class="input">
                            <input id="css-front" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-textarea">
                        <div class="sb-setting-content">
                            <h2>JavaScript Front-side</h2>
                            <p>
                                Enter the URL of a JS file, it will be loaded alongside the chat widget. JQuery is supported.                                                                                                                                                                                             Do not Enter the &lt;script> and &lt;/script> code because it is inserted automatically.
                            </p>
                        </div>
                        <div class="input">
                            <input type="text" id="js-front" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Referral fee</h2>
                            <p>Set the referral fee and activate the referral program. Enter a value from 1 to 100.</p>
                        </div>
                        <div class="input">
                            <input type="number" id="referral-commission" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Referral text</h2>
                            <p>Explain here how much is the commission, how it works, and how your users will be paid.</p>
                        </div>
                        <div class="input">
                            <textarea id="referral-text"></textarea>
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Disable messaging apps for free plan</h2>
                            <p>Disable the following messaging apps for free plan: WhatsApp, Twitter, Messenger, Google Business Messages, Telegram, WeChat, Viber, LINE.</p>
                        </div>
                        <div class="input">
                            <input type="checkbox" id="disable-apps" />
                        </div>
                    </div>
                    <div data-type="text" class="sb-setting sb-type-text">
                        <div class="sb-setting-content">
                            <h2>Webhook URL</h2>
                            <p>
                                Enter your webhook URL. This URL will receive data from the following event: user registration.
                            </p>
                        </div>
                        <div class="input">
                            <input type="text" id="webhook-url" />
                        </div>
                    </div>
                    <hr />
                    <a id="save-settings" class="sb-btn sb-btn-black" href="#">
                        Save settings
                    </a>
                </div>
                <div id="tab-membership-plans" class="sb-loading">
                    <h2>Membership plans</h2>
                    <p>
                        Edit your membership plans.
                        <?php
                        $membership_type = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages');
                        $payment_provider = sb_defined('PAYMENT_PROVIDER', 'stripe');
                        $quota_text = in_array($membership_type, ['messages', 'messages-agents']) ? 'messages that the customer can send each month' : ($membership_type == 'users' ? 'registered users' : 'agents and admins');
                        if ($payment_provider == 'stripe') {
                            echo 'The plans are linked to your Stripe price IDs and are retrieved automatically from Stripe.';
                        } else {
                            echo 'The ' . ucfirst(PAYMENT_PROVIDER) . ' checkout will be used automatically. ';
                        }
                        echo 'The free plan is required and it can not be deleted. Quota is the maximum number of ' . $quota_text . '.';
                        ?>
                    </p>
                </div>
                <div id="tab-affiliates" class="sb-loading">
                    <h2>Affiliate program</h2>
                    <p>
                        List of affiliates waiting for payment. You have to send the affiliate payment manually and immediately after the payment reset to zero the affiliate earnings. Affiliates earn a commission on any first membership purchase they refer.
                    </p>
                    <table class="sb-table table-affiliates">
                        <thead>
                            <tr>
                                <th width="30">
                                    ID
                                </th>
                                <th>
                                    Name
                                </th>
                                <th>
                                    Email
                                </th>
                                <th>
                                    Pending earnings
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="sb-profile-edit-box sb-customer-box sb-lightbox">
        <div class="sb-info"></div>
        <div class="sb-top-bar">
            <div class="sb-profile">
                <span class="sb-name"></span>
            </div>
            <div>
                <a class="sb-save sb-btn sb-icon">
                    <i class="sb-icon-check"></i>
                    Save changes
                </a>
                <a class="sb-close sb-btn-icon" data-button="toggle" data-hide="sb-profile-area" data-show="sb-table-area">
                    <i class="sb-icon-close"></i>
                </a>
            </div>
        </div>
        <div class="sb-main sb-scroll-area">
            <div class="sb-details">
                <div class="sb-title">
                    Edit details
                </div>
                <div class="sb-edit-box"></div>
                <div class="sb-readonly-box"></div>
                <div class="sb-delete-box">
                    <div>
                        <input placeholder="Type DELETE" type="text" />
                        <div class="sb-btn-text">
                            <i class="sb-icon-delete"></i>
                            Delete user
                        </div>
                    </div>
                    <p>All user's data will be permanently deleted, including the customer's Support Board database.</p>
                </div>
            </div>
            <div class="sb-additional-details">
                <div class="sb-title">
                    Sales history
                </div>
                <div class="sb-sales-box"></div>
                <hr />
                <div class="sb-title">
                    Monthly volume
                </div>
                <div class="sb-volume-box"></div>
            </div>
        </div>
    </div>
<?php } ?>

<?php function box_login($super_branding) { ?>
    <div class="sb-super-login-box sb-admin-box">
        <div class="sb-info"></div>
        <div class="sb-top-bar">
            <img src="<?php echo $super_branding ? SB_CLOUD_BRAND_LOGO : 'media/logo-cloud.svg' ?>" />
            <div class="sb-title">Sign in</div>
            <div class="sb-text">
                To enter the <?php echo $super_branding ? SB_CLOUD_BRAND_NAME : 'Support Board' ?> Super Admin area
            </div>
        </div>
        <div class="sb-main">
            <div id="email" class="sb-input">
                <span>Email</span>
                <input type="email" required />
            </div>
            <div id="password" class="sb-input">
                <span>Password</span>
                <input type="password" required />
            </div>
            <div class="sb-bottom">
                <div class="sb-btn btn-login">Sign in</div>
            </div>
            <div class="sb-errors-area"></div>
        </div>
    </div>
<?php } ?>