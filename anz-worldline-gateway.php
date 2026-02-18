<?php
/**
 * Plugin Name: ANZ Worldline Payment Gateway
 * Plugin URI: https://www.anzworldline-solutions.com.au/
 * Description: Accept payments via ANZ Worldline Payment Solutions Hosted Checkout
 * Version: 1.0.0
 * Author: Dylan Nangle
 * Author URI: https://aussiewebpress.online
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anz-worldline-gateway
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

defined('ABSPATH') || exit;

define('ANZ_WORLDLINE_VERSION', '1.0.0');
define('ANZ_WORLDLINE_PLUGIN_FILE', __FILE__);
define('ANZ_WORLDLINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANZ_WORLDLINE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function anz_worldline_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'anz_worldline_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display notice if WooCommerce is not active
 */
function anz_worldline_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('ANZ Worldline Payment Gateway requires WooCommerce to be installed and active.', 'anz-worldline-gateway'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the gateway
 */
function anz_worldline_init_gateway() {
    if (!anz_worldline_check_woocommerce()) {
        return;
    }

    // Load Composer autoloader
    $autoloader = ANZ_WORLDLINE_PLUGIN_DIR . 'vendor/autoload.php';
    if (!file_exists($autoloader)) {
        add_action('admin_notices', 'anz_worldline_composer_missing_notice');
        return;
    }
    require_once $autoloader;

    // Load gateway class
    require_once ANZ_WORLDLINE_PLUGIN_DIR . 'includes/class-wc-gateway-anz-worldline.php';

    // Add gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'anz_worldline_add_gateway');
}
add_action('plugins_loaded', 'anz_worldline_init_gateway', 0);

/**
 * Display notice if Composer dependencies are missing
 */
function anz_worldline_composer_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('ANZ Worldline Payment Gateway requires Composer dependencies. Please run "composer install" in the plugin directory.', 'anz-worldline-gateway'); ?></p>
    </div>
    <?php
}

/**
 * Add the gateway to WooCommerce
 */
function anz_worldline_add_gateway($gateways) {
    $gateways[] = 'WC_Gateway_ANZ_Worldline';
    return $gateways;
}

/**
 * Add plugin action links
 */
function anz_worldline_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=anz_worldline') . '">' . esc_html__('Settings', 'anz-worldline-gateway') . '</a>',
        '<a href="' . admin_url('admin.php?page=anz-worldline-logs') . '">' . esc_html__('Logs', 'anz-worldline-gateway') . '</a>',
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'anz_worldline_plugin_action_links');

/**
 * Add admin menu for logs
 */
function anz_worldline_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __('ANZ Worldline Logs', 'anz-worldline-gateway'),
        __('ANZ Worldline Logs', 'anz-worldline-gateway'),
        'manage_woocommerce',
        'anz-worldline-logs',
        'anz_worldline_logs_page'
    );
}
add_action('admin_menu', 'anz_worldline_admin_menu');

/**
 * Handle clear logs action
 */
function anz_worldline_handle_clear_logs() {
    if (isset($_POST['anz_worldline_clear_logs']) && check_admin_referer('anz_worldline_clear_logs')) {
        if (class_exists('WC_Gateway_ANZ_Worldline')) {
            WC_Gateway_ANZ_Worldline::clear_transaction_logs();
        }
        wp_redirect(admin_url('admin.php?page=anz-worldline-logs&cleared=1'));
        exit;
    }
}
add_action('admin_init', 'anz_worldline_handle_clear_logs');

/**
 * Display logs page
 */
function anz_worldline_logs_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have permission to access this page.', 'anz-worldline-gateway'));
    }

    $logs = array();
    if (class_exists('WC_Gateway_ANZ_Worldline')) {
        $logs = WC_Gateway_ANZ_Worldline::get_transaction_logs();
    }

    // Reverse to show newest first
    $logs = array_reverse($logs);

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('ANZ Worldline Transaction Logs', 'anz-worldline-gateway'); ?></h1>

        <?php if (isset($_GET['cleared'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Logs cleared successfully.', 'anz-worldline-gateway'); ?></p>
            </div>
        <?php endif; ?>

        <div class="anz-worldline-logs-header" style="margin: 20px 0; display: flex; gap: 15px; align-items: center;">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('anz_worldline_clear_logs'); ?>
                <button type="submit" name="anz_worldline_clear_logs" class="button button-secondary"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'anz-worldline-gateway'); ?>');">
                    <?php esc_html_e('Clear All Logs', 'anz-worldline-gateway'); ?>
                </button>
            </form>
            <button type="button" class="button button-secondary" onclick="location.reload();">
                <?php esc_html_e('Refresh', 'anz-worldline-gateway'); ?>
            </button>
            <span style="color: #666;">
                <?php printf(esc_html__('Showing %d log entries (max 100)', 'anz-worldline-gateway'), count($logs)); ?>
            </span>
        </div>

        <div class="anz-worldline-status-codes" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('ANZ Worldline Status Code Reference', 'anz-worldline-gateway'); ?></h3>
            <table style="width: auto; border-collapse: collapse;">
                <tr style="background: #e8f5e9;">
                    <td style="padding: 5px 15px 5px 5px;" colspan="3"><strong><?php esc_html_e('Successful', 'anz-worldline-gateway'); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 3px 15px 3px 5px;"><strong>5</strong> - <?php esc_html_e('Authorized', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>9</strong> - <?php esc_html_e('Captured/Paid', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>8</strong> - <?php esc_html_e('Refund successful', 'anz-worldline-gateway'); ?></td>
                </tr>
                <tr style="background: #ffebee;">
                    <td style="padding: 5px 15px 5px 5px;" colspan="3"><strong><?php esc_html_e('Failed/Declined', 'anz-worldline-gateway'); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 3px 15px 3px 5px;"><strong>2</strong> - <?php esc_html_e('Declined by issuer', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>1</strong> - <?php esc_html_e('Cancelled', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>83</strong> - <?php esc_html_e('Refund rejected', 'anz-worldline-gateway'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 3px 15px 3px 5px;"><strong>57/59</strong> - <?php esc_html_e('Fraud prevention', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>0</strong> - <?php esc_html_e('Invalid/Error', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"></td>
                </tr>
                <tr style="background: #fff3e0;">
                    <td style="padding: 5px 15px 5px 5px;" colspan="3"><strong><?php esc_html_e('Pending/Uncertain', 'anz-worldline-gateway'); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 3px 15px 3px 5px;"><strong>51</strong> - <?php esc_html_e('Authorization pending', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>52</strong> - <?php esc_html_e('Uncertain outcome', 'anz-worldline-gateway'); ?></td>
                    <td style="padding: 3px 15px 3px 0;"><strong>82</strong> - <?php esc_html_e('Refund uncertain', 'anz-worldline-gateway'); ?></td>
                </tr>
            </table>
            <p style="margin-bottom: 0; margin-top: 10px; font-size: 12px; color: #666;">
                <?php esc_html_e('Payment Action modes: "Authorize Only" reserves funds (code 5), "Authorize & Capture" charges immediately (code 9).', 'anz-worldline-gateway'); ?>
            </p>
        </div>

        <?php if (empty($logs)): ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('No transaction logs found. Logs will appear here when payment transactions occur.', 'anz-worldline-gateway'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 160px;"><?php esc_html_e('Timestamp', 'anz-worldline-gateway'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Level', 'anz-worldline-gateway'); ?></th>
                        <th><?php esc_html_e('Message', 'anz-worldline-gateway'); ?></th>
                        <th style="width: 35%;"><?php esc_html_e('Context', 'anz-worldline-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $level_class = '';
                        $level_style = '';
                        switch ($log['level']) {
                            case 'error':
                                $level_style = 'background: #dc3545; color: white; padding: 2px 8px; border-radius: 3px;';
                                break;
                            case 'warning':
                                $level_style = 'background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px;';
                                break;
                            case 'info':
                                $level_style = 'background: #17a2b8; color: white; padding: 2px 8px; border-radius: 3px;';
                                break;
                            default:
                                $level_style = 'background: #6c757d; color: white; padding: 2px 8px; border-radius: 3px;';
                        }
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($log['timestamp']); ?></code></td>
                            <td><span style="<?php echo esc_attr($level_style); ?>"><?php echo esc_html(strtoupper($log['level'])); ?></span></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td>
                                <?php if (!empty($log['context'])): ?>
                                    <code style="font-size: 12px; word-break: break-all;"><?php echo esc_html(json_encode($log['context'], JSON_PRETTY_PRINT)); ?></code>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <h4 style="margin-top: 0;"><?php esc_html_e('WooCommerce Log Files', 'anz-worldline-gateway'); ?></h4>
            <p style="margin-bottom: 5px;">
                <?php esc_html_e('Additional logs are also written to WooCommerce log files:', 'anz-worldline-gateway'); ?>
            </p>
            <code><?php echo esc_html(WC_LOG_DIR . 'anz-worldline-*.log'); ?></code>
            <p style="margin-bottom: 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-status&tab=logs')); ?>"><?php esc_html_e('View WooCommerce Logs', 'anz-worldline-gateway'); ?></a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Enqueue frontend styles for checkout
 */
function anz_worldline_enqueue_checkout_styles() {
    if (!is_checkout()) {
        return;
    }

    $custom_css = '
        /* ANZ Worldline Payment Method - Prominent Styling */
        .wc_payment_method.payment_method_anz_worldline {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border: 2px solid #007bff !important;
            border-radius: 12px !important;
            padding: 20px !important;
            margin: 15px 0 !important;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.15) !important;
            transition: all 0.3s ease !important;
        }

        .wc_payment_method.payment_method_anz_worldline:hover {
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.25) !important;
            border-color: #0056b3 !important;
        }

        .wc_payment_method.payment_method_anz_worldline label {
            font-size: 20px !important;
            font-weight: 600 !important;
            color: #1a1a2e !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            cursor: pointer !important;
        }

        .wc_payment_method.payment_method_anz_worldline label img.anz-worldline-icon {
            height: 44px !important;
            width: auto !important;
            max-height: 44px !important;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)) !important;
        }

        .wc_payment_method.payment_method_anz_worldline .payment_box {
            background: #ffffff !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
            font-size: 15px !important;
            padding: 18px !important;
            margin-top: 15px !important;
            color: #495057 !important;
        }

        .wc_payment_method.payment_method_anz_worldline .payment_box::before {
            border-color: transparent transparent #ffffff transparent !important;
        }

        /* Secure payment indicator */
        .wc_payment_method.payment_method_anz_worldline label::after {
            content: "🔒 Secure" !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            color: #28a745 !important;
            background: #d4edda !important;
            padding: 4px 10px !important;
            border-radius: 20px !important;
            margin-left: auto !important;
        }

        /* Radio button styling - always visible and prominent */
        .wc_payment_method.payment_method_anz_worldline input[type="radio"] {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            width: 24px !important;
            height: 24px !important;
            min-width: 24px !important;
            min-height: 24px !important;
            border: 3px solid #007bff !important;
            border-radius: 50% !important;
            background: #ffffff !important;
            cursor: pointer !important;
            position: relative !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            margin-right: 12px !important;
            flex-shrink: 0 !important;
        }

        .wc_payment_method.payment_method_anz_worldline input[type="radio"]:checked {
            border-color: #007bff !important;
            background: #ffffff !important;
        }

        .wc_payment_method.payment_method_anz_worldline input[type="radio"]:checked::after {
            content: "" !important;
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            width: 12px !important;
            height: 12px !important;
            background: #007bff !important;
            border-radius: 50% !important;
        }

        .wc_payment_method.payment_method_anz_worldline input[type="radio"]:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25) !important;
        }

        /* Force radio to show even when single payment method */
        .woocommerce-checkout .wc_payment_method.payment_method_anz_worldline input[type="radio"],
        .woocommerce-checkout-payment .wc_payment_method.payment_method_anz_worldline input[type="radio"] {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            left: 0 !important;
        }

        /* Hide default WooCommerce hidden radio override */
        .woocommerce-checkout .wc_payment_methods .wc_payment_method.payment_method_anz_worldline > input[type="radio"] {
            display: inline-block !important;
            visibility: visible !important;
            clip: unset !important;
            clip-path: none !important;
            width: 24px !important;
            height: 24px !important;
            position: relative !important;
            margin: 0 12px 0 0 !important;
        }

        /* WooCommerce Blocks checkout support */
        .wc-block-components-radio-control__option.wc-block-components-payment-method-radio-control__option--anz_worldline {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border: 2px solid #007bff !important;
            border-radius: 12px !important;
            padding: 20px !important;
            margin: 15px 0 !important;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.15) !important;
        }

        .wc-block-components-radio-control__option.wc-block-components-payment-method-radio-control__option--anz_worldline .wc-block-components-radio-control__label {
            font-size: 20px !important;
            font-weight: 600 !important;
        }
    ';

    wp_add_inline_style('woocommerce-general', $custom_css);

    // Fallback if woocommerce-general is not enqueued
    if (!wp_style_is('woocommerce-general', 'enqueued')) {
        wp_register_style('anz-worldline-checkout', false);
        wp_enqueue_style('anz-worldline-checkout');
        wp_add_inline_style('anz-worldline-checkout', $custom_css);
    }
}
add_action('wp_enqueue_scripts', 'anz_worldline_enqueue_checkout_styles');

/**
 * Add capture action to order actions dropdown
 *
 * @param array $actions Order actions.
 * @return array
 */
function anz_worldline_add_order_actions($actions) {
    global $theorder;

    if (!$theorder) {
        return $actions;
    }

    // Check if this order was paid via ANZ Worldline and needs capture
    if ($theorder->get_payment_method() === 'anz_worldline'
        && $theorder->get_meta('_anz_worldline_needs_capture') === 'yes') {
        $actions['anz_worldline_capture'] = __('Capture ANZ Worldline payment', 'anz-worldline-gateway');
    }

    return $actions;
}
add_filter('woocommerce_order_actions', 'anz_worldline_add_order_actions');

/**
 * Handle capture order action
 *
 * @param WC_Order $order Order object.
 */
function anz_worldline_handle_capture_action($order) {
    // Get the gateway instance
    $gateways = WC()->payment_gateways()->get_available_payment_gateways();

    if (!isset($gateways['anz_worldline'])) {
        $order->add_order_note(__('Capture failed: ANZ Worldline gateway not available.', 'anz-worldline-gateway'));
        return;
    }

    $gateway = $gateways['anz_worldline'];
    $result = $gateway->capture_payment($order->get_id());

    if (is_wp_error($result)) {
        // Error is already logged and noted by capture_payment method
        // Add admin notice
        set_transient('anz_worldline_capture_error_' . get_current_user_id(), $result->get_error_message(), 60);
    } else {
        // Success notice
        set_transient('anz_worldline_capture_success_' . get_current_user_id(), true, 60);
    }
}
add_action('woocommerce_order_action_anz_worldline_capture', 'anz_worldline_handle_capture_action');

/**
 * Display admin notices for capture results
 */
function anz_worldline_capture_admin_notices() {
    $user_id = get_current_user_id();

    // Check for success
    if (get_transient('anz_worldline_capture_success_' . $user_id)) {
        delete_transient('anz_worldline_capture_success_' . $user_id);
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('ANZ Worldline payment captured successfully.', 'anz-worldline-gateway'); ?></p>
        </div>
        <?php
    }

    // Check for error
    $error = get_transient('anz_worldline_capture_error_' . $user_id);
    if ($error) {
        delete_transient('anz_worldline_capture_error_' . $user_id);
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(sprintf(__('ANZ Worldline capture failed: %s', 'anz-worldline-gateway'), $error)); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'anz_worldline_capture_admin_notices');

/**
 * Add capture status meta box to order page
 */
function anz_worldline_add_capture_meta_box() {
    // Determine screens to add meta box to
    $screens = array('shop_order'); // Classic editor

    // Add HPOS screen if available
    if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
        $screens[] = wc_get_page_screen_id('shop-order');
    }

    foreach ($screens as $screen) {
        add_meta_box(
            'anz_worldline_capture_status',
            __('ANZ Worldline Payment', 'anz-worldline-gateway'),
            'anz_worldline_capture_meta_box_content',
            $screen,
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'anz_worldline_add_capture_meta_box');

/**
 * Display capture meta box content
 *
 * @param WP_Post|WC_Order $post_or_order Post object or order object.
 */
function anz_worldline_capture_meta_box_content($post_or_order) {
    $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);

    if (!$order || $order->get_payment_method() !== 'anz_worldline') {
        echo '<p>' . esc_html__('This order was not paid via ANZ Worldline.', 'anz-worldline-gateway') . '</p>';
        return;
    }

    $needs_capture = $order->get_meta('_anz_worldline_needs_capture') === 'yes';
    $is_captured = $order->get_meta('_anz_worldline_captured') === 'yes';
    $transaction_id = $order->get_transaction_id();
    $authorized_amount = $order->get_meta('_anz_worldline_authorized_amount');

    ?>
    <div class="anz-worldline-payment-info">
        <p>
            <strong><?php esc_html_e('Transaction ID:', 'anz-worldline-gateway'); ?></strong><br>
            <code><?php echo esc_html($transaction_id ?: __('N/A', 'anz-worldline-gateway')); ?></code>
        </p>

        <p>
            <strong><?php esc_html_e('Payment Status:', 'anz-worldline-gateway'); ?></strong><br>
            <?php if ($needs_capture): ?>
                <span style="color: #d63638; font-weight: 600;">
                    &#9888; <?php esc_html_e('Authorized - Awaiting Capture', 'anz-worldline-gateway'); ?>
                </span>
                <?php if ($authorized_amount): ?>
                    <br><small><?php printf(esc_html__('Authorized amount: %s', 'anz-worldline-gateway'), wc_price($authorized_amount)); ?></small>
                <?php endif; ?>
            <?php elseif ($is_captured): ?>
                <span style="color: #00a32a; font-weight: 600;">
                    &#10004; <?php esc_html_e('Captured', 'anz-worldline-gateway'); ?>
                </span>
            <?php else: ?>
                <span style="color: #666;">
                    <?php esc_html_e('Unknown', 'anz-worldline-gateway'); ?>
                </span>
            <?php endif; ?>
        </p>

        <?php if ($needs_capture): ?>
            <hr>
            <p class="description" style="margin-bottom: 10px;">
                <?php esc_html_e('This payment has been authorized but not yet captured. Use the "Capture ANZ Worldline payment" action to charge the customer.', 'anz-worldline-gateway'); ?>
            </p>
            <form method="post" style="margin: 0;">
                <?php wp_nonce_field('anz_worldline_capture_payment', 'anz_worldline_capture_nonce'); ?>
                <input type="hidden" name="anz_worldline_capture_order_id" value="<?php echo esc_attr($order->get_id()); ?>">
                <button type="submit" name="anz_worldline_capture_submit" class="button button-primary"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to capture this payment? This will charge the customer.', 'anz-worldline-gateway'); ?>');">
                    <?php esc_html_e('Capture Payment', 'anz-worldline-gateway'); ?>
                </button>
            </form>
            <p class="description" style="margin-top: 8px; font-size: 11px; color: #d63638;">
                <?php esc_html_e('Note: Authorizations typically expire after 7-30 days depending on the card issuer.', 'anz-worldline-gateway'); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Handle capture form submission from meta box
 */
function anz_worldline_handle_capture_form() {
    if (!isset($_POST['anz_worldline_capture_submit'])) {
        return;
    }

    if (!isset($_POST['anz_worldline_capture_nonce']) || !wp_verify_nonce($_POST['anz_worldline_capture_nonce'], 'anz_worldline_capture_payment')) {
        return;
    }

    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $order_id = isset($_POST['anz_worldline_capture_order_id']) ? absint($_POST['anz_worldline_capture_order_id']) : 0;

    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    // Get the gateway instance
    $gateways = WC()->payment_gateways()->get_available_payment_gateways();

    if (!isset($gateways['anz_worldline'])) {
        set_transient('anz_worldline_capture_error_' . get_current_user_id(), __('Gateway not available.', 'anz-worldline-gateway'), 60);
        return;
    }

    $gateway = $gateways['anz_worldline'];
    $result = $gateway->capture_payment($order_id);

    if (is_wp_error($result)) {
        set_transient('anz_worldline_capture_error_' . get_current_user_id(), $result->get_error_message(), 60);
    } else {
        set_transient('anz_worldline_capture_success_' . get_current_user_id(), true, 60);
    }

    // Redirect to avoid form resubmission
    // Support both HPOS and classic order editing
    if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
        $redirect_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
    } else {
        $redirect_url = admin_url('post.php?post=' . $order_id . '&action=edit');
    }

    wp_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'anz_worldline_handle_capture_form');
