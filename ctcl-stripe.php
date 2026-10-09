<?php
/**
 * Plugin Name: CTCL Stripe
 * Plugin URI: https://github.com/ujw0l/ctcl-stripe
 * Description: Stripe Payment Element checkout for CT Commerce Lite, with verified totals and webhook order confirmation.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: ctc-lite
 * Author: Ujwol Bastakoti
 * Author URI: https://ujw0l.github.io/
 * Text Domain: ctcl-stripe
 * License: GPLv2 or later
 */
if (!defined('ABSPATH')) { exit; }
define('CTCL_STRIPE_VERSION', '2.0.0');
require_once __DIR__ . '/stripe-php/init.php';
require_once __DIR__ . '/includes/checkout.php';
register_activation_hook(__FILE__, array('CTCL_Stripe_Checkout', 'install'));
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('ctcl_stripe_cleanup'); });

add_action('init', function () {
    if (!class_exists('ctclBillings') || !class_exists('ctclProcessing')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('CTCL Stripe requires CT Commerce Lite to be installed and activated.', 'ctcl-stripe') . '</p></div>';
        });
        return;
    }
    require_once __DIR__ . '/includes/settings.php';
    new ctclStripe();
    new CTCL_Stripe_Checkout();
});
