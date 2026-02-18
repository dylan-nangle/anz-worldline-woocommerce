<?php
/**
 * ANZ Worldline Payment Gateway
 *
 * @package ANZ_Worldline_Gateway
 */

defined('ABSPATH') || exit;

use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\DefaultConnection;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\HostedCheckoutSpecificInput;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutRequest;
use OnlinePayments\Sdk\Domain\CardPaymentMethodSpecificInput;
use OnlinePayments\Sdk\Domain\RefundRequest;
use OnlinePayments\Sdk\Domain\CapturePaymentRequest;
use OnlinePayments\Sdk\ApiException;

/**
 * WC_Gateway_ANZ_Worldline class
 */
class WC_Gateway_ANZ_Worldline extends WC_Payment_Gateway {

    /**
     * Test API endpoint
     */
    const TEST_ENDPOINT = 'https://payment.preprod.anzworldline-solutions.com.au';

    /**
     * Live API endpoint
     */
    const LIVE_ENDPOINT = 'https://payment.anzworldline-solutions.com.au';

    /**
     * Whether test mode is enabled
     *
     * @var bool
     */
    private $testmode;

    /**
     * Merchant ID (PSPID)
     *
     * @var string
     */
    private $merchant_id;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * API Secret
     *
     * @var string
     */
    private $api_secret;

    /**
     * Payment action (authorize or sale)
     *
     * @var string
     */
    private $payment_action;

    /**
     * Maximum payment attempts allowed
     *
     * @var int
     */
    private $max_payment_attempts;

    /**
     * Template variant for hosted checkout customization
     *
     * @var string
     */
    private $template_variant;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'anz_worldline';
        $this->has_fields         = false;
        $this->method_title       = __('ANZ Worldline', 'anz-worldline-gateway');
        $this->method_description = __('Accept payments via ANZ Worldline Payment Solutions. Customers are redirected to a secure payment page.', 'anz-worldline-gateway');
        $this->supports           = array('products', 'refunds');

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->testmode             = 'yes' === $this->get_option('testmode');
        $this->payment_action       = $this->get_option('payment_action', 'authorize_capture');
        $this->max_payment_attempts = (int) $this->get_option('max_payment_attempts', 3);
        $this->template_variant     = $this->get_option('template_variant', '');

        // Set credentials based on test mode
        if ($this->testmode) {
            $this->merchant_id = $this->get_option('test_merchant_id');
            $this->api_key     = $this->get_option('test_api_key');
            $this->api_secret  = $this->get_option('test_api_secret');
        } else {
            $this->merchant_id = $this->get_option('live_merchant_id');
            $this->api_key     = $this->get_option('live_api_key');
            $this->api_secret  = $this->get_option('live_api_secret');
        }

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_anz_worldline', array($this, 'handle_payment_callback'));
    }

    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'anz-worldline-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable ANZ Worldline Payment Gateway', 'anz-worldline-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'anz-worldline-gateway'),
                'type'        => 'text',
                'description' => __('Payment method title that customers see at checkout.', 'anz-worldline-gateway'),
                'default'     => __('Credit Card (ANZ Worldline)', 'anz-worldline-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'anz-worldline-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that customers see at checkout.', 'anz-worldline-gateway'),
                'default'     => __('Pay securely using your credit or debit card.', 'anz-worldline-gateway'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test Mode', 'anz-worldline-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable Test Mode', 'anz-worldline-gateway'),
                'description' => __('Use the test environment for transactions. Disable for live payments.', 'anz-worldline-gateway'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'payment_action' => array(
                'title'       => __('Payment Action', 'anz-worldline-gateway'),
                'type'        => 'select',
                'description' => __('Choose whether to authorize only (capture later) or authorize and capture immediately.', 'anz-worldline-gateway'),
                'default'     => 'authorize_capture',
                'desc_tip'    => true,
                'options'     => array(
                    'authorize_capture' => __('Authorize & Capture (recommended)', 'anz-worldline-gateway'),
                    'authorize'         => __('Authorize Only', 'anz-worldline-gateway'),
                ),
            ),
            'max_payment_attempts' => array(
                'title'       => __('Max Payment Attempts', 'anz-worldline-gateway'),
                'type'        => 'select',
                'description' => __('Limit retry attempts after declined payments. Helps mitigate fraud and reduce transaction costs. After reaching the limit, customers are redirected back to checkout.', 'anz-worldline-gateway'),
                'default'     => '3',
                'desc_tip'    => true,
                'options'     => array(
                    '1'  => __('1 attempt (no retries)', 'anz-worldline-gateway'),
                    '2'  => __('2 attempts', 'anz-worldline-gateway'),
                    '3'  => __('3 attempts (recommended)', 'anz-worldline-gateway'),
                    '4'  => __('4 attempts', 'anz-worldline-gateway'),
                    '5'  => __('5 attempts', 'anz-worldline-gateway'),
                    '10' => __('10 attempts (maximum)', 'anz-worldline-gateway'),
                ),
            ),
            'template_variant' => array(
                'title'       => __('Template Variant', 'anz-worldline-gateway'),
                'type'        => 'text',
                'description' => __('Optional: Enter your custom hosted checkout template variant name. This is provided by ANZ Worldline after uploading your custom template. Leave blank to use the default template.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => false,
                'placeholder' => __('e.g., MyCustomTemplate', 'anz-worldline-gateway'),
            ),
            'live_credentials_title' => array(
                'title'       => __('Live/Production Credentials', 'anz-worldline-gateway'),
                'type'        => 'title',
                'description' => __('Enter your live credentials from the ANZ Worldline Merchant Portal. These are used when Test Mode is disabled.', 'anz-worldline-gateway'),
            ),
            'live_merchant_id' => array(
                'title'       => __('Live Merchant ID (PSPID)', 'anz-worldline-gateway'),
                'type'        => 'text',
                'description' => __('Your live Merchant ID (PSPID) from the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_api_key' => array(
                'title'       => __('Live API Key', 'anz-worldline-gateway'),
                'type'        => 'password',
                'description' => __('Your live API Key from the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_api_secret' => array(
                'title'       => __('Live API Secret', 'anz-worldline-gateway'),
                'type'        => 'password',
                'description' => __('Your live API Secret from the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_credentials_title' => array(
                'title'       => __('Test/Sandbox Credentials', 'anz-worldline-gateway'),
                'type'        => 'title',
                'description' => __('Enter your test credentials from the ANZ Worldline Merchant Portal. These are used when Test Mode is enabled.', 'anz-worldline-gateway'),
            ),
            'test_merchant_id' => array(
                'title'       => __('Test Merchant ID (PSPID)', 'anz-worldline-gateway'),
                'type'        => 'text',
                'description' => __('Your test Merchant ID (PSPID) from the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_key' => array(
                'title'       => __('Test API Key', 'anz-worldline-gateway'),
                'type'        => 'password',
                'description' => __('Your test API Key from the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_secret' => array(
                'title'       => __('Test API Secret', 'anz-worldline-gateway'),
                'type'        => 'password',
                'description' => __('Your test API Secret from the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (empty($this->merchant_id) || empty($this->api_key) || empty($this->api_secret)) {
            return false;
        }

        return true;
    }

    /**
     * Get payment method icon HTML
     *
     * @return string Icon HTML
     */
    public function get_icon() {
        $icons_html = '';
        $plugin_url = ANZ_WORLDLINE_PLUGIN_URL . 'assets/images/';

        // Define available payment method icons
        // Images should be 64-300px for optimal rendering on high-resolution displays
        $icons = array(
            'visa'       => array(
                'src' => $plugin_url . 'visa.png',
                'alt' => __('Visa', 'anz-worldline-gateway'),
            ),
            'mastercard' => array(
                'src' => $plugin_url . 'mastercard.png',
                'alt' => __('Mastercard', 'anz-worldline-gateway'),
            ),
        );

        // Styling for optimal rendering on all screens including retina/high-DPI
        $icon_style = implode(';', array(
            'height: 40px',
            'width: auto',
            'max-height: 40px',
            'margin-right: 8px',
            'vertical-align: middle',
            'image-rendering: -webkit-optimize-contrast', // Sharper on WebKit
            'image-rendering: crisp-edges', // Sharper rendering
        ));

        foreach ($icons as $icon) {
            $icons_html .= sprintf(
                '<img src="%s" alt="%s" class="anz-worldline-icon" style="%s" />',
                esc_url($icon['src']),
                esc_attr($icon['alt']),
                esc_attr($icon_style)
            );
        }

        return apply_filters('woocommerce_gateway_icon', $icons_html, $this->id);
    }

    /**
     * Display admin options with environment indicator
     */
    public function admin_options() {
        $test_configured = !empty($this->get_option('test_merchant_id'))
                           && !empty($this->get_option('test_api_key'))
                           && !empty($this->get_option('test_api_secret'));
        $live_configured = !empty($this->get_option('live_merchant_id'))
                           && !empty($this->get_option('live_api_key'))
                           && !empty($this->get_option('live_api_secret'));
        ?>
        <div class="anz-worldline-environment-notice" style="margin-bottom: 20px;">
            <?php if ($this->testmode): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 12px 15px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">&#9888;</span>
                    <div>
                        <strong><?php esc_html_e('Test Mode Active', 'anz-worldline-gateway'); ?></strong>
                        <span style="color: #856404;"> &mdash; <?php esc_html_e('Using test/sandbox credentials and pre-production environment.', 'anz-worldline-gateway'); ?></span>
                        <?php if (!$test_configured): ?>
                            <br><span style="color: #721c24;"><?php esc_html_e('Test credentials are not configured. Please enter your test credentials below.', 'anz-worldline-gateway'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="background: #d4edda; border: 1px solid #28a745; border-left: 4px solid #28a745; padding: 12px 15px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">&#10004;</span>
                    <div>
                        <strong><?php esc_html_e('Live Mode Active', 'anz-worldline-gateway'); ?></strong>
                        <span style="color: #155724;"> &mdash; <?php esc_html_e('Using live/production credentials. Real transactions will be processed.', 'anz-worldline-gateway'); ?></span>
                        <?php if (!$live_configured): ?>
                            <br><span style="color: #721c24;"><?php esc_html_e('Live credentials are not configured. Please enter your live credentials below.', 'anz-worldline-gateway'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                <strong><?php esc_html_e('Credentials Status:', 'anz-worldline-gateway'); ?></strong>
                <?php esc_html_e('Live:', 'anz-worldline-gateway'); ?>
                <span style="color: <?php echo $live_configured ? '#28a745' : '#dc3545'; ?>;">
                    <?php echo $live_configured ? '&#10004; ' . esc_html__('Configured', 'anz-worldline-gateway') : '&#10006; ' . esc_html__('Not configured', 'anz-worldline-gateway'); ?>
                </span>
                &nbsp;|&nbsp;
                <?php esc_html_e('Test:', 'anz-worldline-gateway'); ?>
                <span style="color: <?php echo $test_configured ? '#28a745' : '#dc3545'; ?>;">
                    <?php echo $test_configured ? '&#10004; ' . esc_html__('Configured', 'anz-worldline-gateway') : '&#10006; ' . esc_html__('Not configured', 'anz-worldline-gateway'); ?>
                </span>
            </div>
        </div>
        <?php
        parent::admin_options();
    }

    /**
     * Get the API endpoint based on test mode
     *
     * @return string
     */
    private function get_api_endpoint() {
        return $this->testmode ? self::TEST_ENDPOINT : self::LIVE_ENDPOINT;
    }

    /**
     * Get the SDK client
     *
     * @return Client
     */
    private function get_client() {
        $communicatorConfiguration = new CommunicatorConfiguration(
            $this->api_key,
            $this->api_secret,
            $this->get_api_endpoint(),
            'WooCommerce'
        );

        $connection = new DefaultConnection();
        $communicator = new Communicator($connection, $communicatorConfiguration);

        return new Client($communicator);
    }

    /**
     * Process payment
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('Order not found during process_payment', 'error', array('order_id' => $order_id));
            wc_add_notice(__('Order not found.', 'anz-worldline-gateway'), 'error');
            return array('result' => 'failure');
        }

        // Determine authorization mode based on payment action setting
        $authorization_mode = $this->payment_action === 'authorize' ? 'FINAL_AUTHORIZATION' : 'SALE';

        $this->log('Initiating payment', 'info', array(
            'order_id' => $order_id,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'testmode' => $this->testmode ? 'yes' : 'no',
            'payment_action' => $this->payment_action,
            'authorization_mode' => $authorization_mode,
            'max_attempts' => $this->max_payment_attempts,
            'template_variant' => !empty($this->template_variant) ? $this->template_variant : 'default',
        ));

        try {
            $client = $this->get_client();

            // Create amount object (convert to cents)
            $amountOfMoney = new AmountOfMoney();
            $amountOfMoney->setAmount((int) round($order->get_total() * 100));
            $amountOfMoney->setCurrencyCode($order->get_currency());

            // Create order object
            $orderObject = new Order();
            $orderObject->setAmountOfMoney($amountOfMoney);

            // Create hosted checkout specific input
            $hostedCheckoutSpecificInput = new HostedCheckoutSpecificInput();
            $hostedCheckoutSpecificInput->setReturnUrl($this->get_callback_url($order_id));
            $hostedCheckoutSpecificInput->setLocale($this->get_customer_locale());

            // Limit payment retry attempts (helps mitigate fraud and reduce costs)
            if ($this->max_payment_attempts >= 1 && $this->max_payment_attempts <= 10) {
                $hostedCheckoutSpecificInput->setAllowedNumberOfPaymentAttempts($this->max_payment_attempts);
            }

            // Set custom template variant if configured
            if (!empty($this->template_variant)) {
                $hostedCheckoutSpecificInput->setVariant($this->template_variant);
            }

            // Create card payment method specific input with authorization mode
            $cardPaymentMethodSpecificInput = new CardPaymentMethodSpecificInput();
            $cardPaymentMethodSpecificInput->setAuthorizationMode($authorization_mode);

            // Create the request
            $createHostedCheckoutRequest = new CreateHostedCheckoutRequest();
            $createHostedCheckoutRequest->setOrder($orderObject);
            $createHostedCheckoutRequest->setHostedCheckoutSpecificInput($hostedCheckoutSpecificInput);
            $createHostedCheckoutRequest->setCardPaymentMethodSpecificInput($cardPaymentMethodSpecificInput);

            // Send request to ANZ
            $response = $client->merchant($this->merchant_id)->hostedCheckout()->createHostedCheckout($createHostedCheckoutRequest);

            // Store the hosted checkout ID for later verification
            $order->update_meta_data('_anz_worldline_hosted_checkout_id', $response->getHostedCheckoutId());
            $order->update_meta_data('_anz_worldline_returnmac', $response->getRETURNMAC());
            $order->save();

            // Log without exposing full redirect URL (contains session tokens)
            $redirect_url = $response->getRedirectUrl();
            $this->log('Hosted checkout created', 'info', array(
                'order_id' => $order_id,
                'hosted_checkout_id' => $response->getHostedCheckoutId(),
                'redirect_host' => wp_parse_url($redirect_url, PHP_URL_HOST),
            ));

            // Add order note
            $order->add_order_note(
                sprintf(
                    __('ANZ Worldline payment initiated. Hosted Checkout ID: %s. Mode: %s', 'anz-worldline-gateway'),
                    $response->getHostedCheckoutId(),
                    $authorization_mode === 'SALE' ? __('Authorize & Capture', 'anz-worldline-gateway') : __('Authorize Only', 'anz-worldline-gateway')
                )
            );

            // Return success with redirect URL
            return array(
                'result'   => 'success',
                'redirect' => $response->getRedirectUrl(),
            );

        } catch (ApiException $e) {
            $this->log('API Exception during payment initiation', 'error', array(
                'order_id' => $order_id,
                'message' => $e->getMessage(),
                'exception_type' => get_class($e),
                'code' => $e->getCode(),
            ));
            // Don't expose API error details to customers
            wc_add_notice(__('Payment could not be initiated. Please try again or contact support.', 'anz-worldline-gateway'), 'error');
            return array('result' => 'failure');

        } catch (\Exception $e) {
            $this->log('Exception during payment initiation', 'error', array(
                'order_id' => $order_id,
                'message' => $e->getMessage(),
                'exception_type' => get_class($e),
                'code' => $e->getCode(),
            ));
            // Don't expose internal error details to customers
            wc_add_notice(__('Payment could not be initiated. Please try again or contact support.', 'anz-worldline-gateway'), 'error');
            return array('result' => 'failure');
        }
    }

    /**
     * Handle payment callback from ANZ
     */
    public function handle_payment_callback() {
        // Get order ID from query string
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

        $this->log('Callback received', 'info', array(
            'order_id' => $order_id,
            'get_params' => array_keys($_GET),
        ));

        if (!$order_id) {
            $this->log('Callback received without order ID', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('Callback received for invalid order: ' . $order_id, 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Check if order is already processed
        if ($order->is_paid()) {
            $this->log('Order already paid, redirecting to thank you page', 'info', array('order_id' => $order_id));
            wp_redirect($this->get_return_url($order));
            exit;
        }

        // Get the hosted checkout ID
        $hosted_checkout_id = $order->get_meta('_anz_worldline_hosted_checkout_id');

        if (!$hosted_checkout_id) {
            $this->log('No hosted checkout ID found for order: ' . $order_id, 'error');
            wc_add_notice(__('Payment verification failed. Please try again.', 'anz-worldline-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Verify RETURNMAC to prevent callback manipulation
        $stored_returnmac = $order->get_meta('_anz_worldline_returnmac');
        $received_returnmac = isset($_GET['RETURNMAC']) ? sanitize_text_field(wp_unslash($_GET['RETURNMAC'])) : '';

        if (empty($stored_returnmac) || empty($received_returnmac) || !hash_equals($stored_returnmac, $received_returnmac)) {
            $this->log('RETURNMAC verification failed', 'error', array(
                'order_id' => $order_id,
                'has_stored' => !empty($stored_returnmac),
                'has_received' => !empty($received_returnmac),
            ));
            wc_add_notice(__('Payment verification failed. Please try again.', 'anz-worldline-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        try {
            $client = $this->get_client();

            // Get the hosted checkout status
            $hostedCheckoutStatus = $client->merchant($this->merchant_id)->hostedCheckout()->getHostedCheckout($hosted_checkout_id);

            $status = $hostedCheckoutStatus->getStatus();

            $this->log('Hosted checkout status retrieved', 'info', array(
                'order_id' => $order_id,
                'hosted_checkout_id' => $hosted_checkout_id,
                'status' => $status,
            ));

            if ($status === 'PAYMENT_CREATED' || $status === 'PAYMENT_FINISHED') {
                // Get payment details
                $createdPaymentOutput = $hostedCheckoutStatus->getCreatedPaymentOutput();

                if ($createdPaymentOutput) {
                    $payment = $createdPaymentOutput->getPayment();

                    if ($payment) {
                        $statusOutput = $payment->getStatusOutput();
                        $statusCode = $statusOutput ? $statusOutput->getStatusCode() : 0;
                        $paymentId = $payment->getId();
                        $paymentStatus = $payment->getStatus();

                        $this->log('Payment details retrieved', 'info', array(
                            'order_id' => $order_id,
                            'payment_id' => $paymentId,
                            'status_code' => $statusCode,
                            'payment_status' => $paymentStatus,
                        ));

                        // ANZ Worldline status codes:
                        // 5 = Authorized (successful authorization)
                        // 9 = Captured/Payment completed
                        // 2 = Declined
                        // 51 = Pending
                        // Also check for legacy 800-999 range for backwards compatibility
                        $successful_status_codes = array(5, 9);
                        $is_success = in_array($statusCode, $successful_status_codes, true)
                                      || ($statusCode >= 800 && $statusCode <= 999);

                        if ($is_success) {
                            // Check if this is authorize-only (status code 5) or captured (status code 9)
                            $is_authorize_only = ($statusCode === 5);

                            if ($is_authorize_only) {
                                // Authorization only - mark for later capture
                                $order->set_transaction_id($paymentId);
                                $order->update_meta_data('_anz_worldline_needs_capture', 'yes');
                                $order->update_meta_data('_anz_worldline_authorized_amount', $order->get_total());
                                $order->update_status('on-hold', sprintf(
                                    __('ANZ Worldline payment authorized. Awaiting capture. Transaction ID: %s', 'anz-worldline-gateway'),
                                    $paymentId
                                ));
                                $order->save();

                                $this->log('Payment authorized (awaiting capture)', 'info', array(
                                    'order_id' => $order_id,
                                    'payment_id' => $paymentId,
                                    'status_code' => $statusCode,
                                    'needs_capture' => true,
                                ));
                            } else {
                                // Payment captured immediately
                                $order->update_meta_data('_anz_worldline_needs_capture', 'no');
                                $order->update_meta_data('_anz_worldline_captured', 'yes');
                                $order->save();

                                $order->payment_complete($paymentId);
                                $order->add_order_note(
                                    sprintf(
                                        __('ANZ Worldline payment completed. Transaction ID: %s, Status Code: %d', 'anz-worldline-gateway'),
                                        $paymentId,
                                        $statusCode
                                    )
                                );

                                $this->log('Payment completed successfully', 'info', array(
                                    'order_id' => $order_id,
                                    'payment_id' => $paymentId,
                                    'status_code' => $statusCode,
                                ));
                            }

                            // Redirect to thank you page
                            wp_redirect($this->get_return_url($order));
                            exit;
                        }

                        // Payment failed - get detailed error information
                        $failure_info = $this->get_payment_failure_info($statusCode, $statusOutput, $payment);

                        $this->log('Payment failed', 'error', array(
                            'order_id' => $order_id,
                            'payment_id' => $paymentId,
                            'status_code' => $statusCode,
                            'payment_status' => $paymentStatus,
                            'failure_reason' => $failure_info['reason'],
                            'error_code' => $failure_info['error_code'],
                        ));

                        $order->update_status('failed', sprintf(
                            __('ANZ Worldline payment failed. Status Code: %d. Reason: %s', 'anz-worldline-gateway'),
                            $statusCode,
                            $failure_info['reason']
                        ));

                        wc_add_notice($failure_info['customer_message'], 'error');
                        wp_redirect(wc_get_checkout_url());
                        exit;
                    } else {
                        $this->log('Payment object is null', 'warning', array('order_id' => $order_id));
                    }
                } else {
                    $this->log('CreatedPaymentOutput is null', 'warning', array('order_id' => $order_id));
                }
            }

            // Handle non-payment statuses (cancelled, in progress, etc.)
            $failure_reason = $this->get_checkout_status_message($status);

            $this->log('Hosted checkout not completed', 'warning', array(
                'order_id' => $order_id,
                'status' => $status,
                'failure_reason' => $failure_reason,
            ));

            $order->update_status('failed', sprintf(
                __('ANZ Worldline checkout not completed. Status: %s', 'anz-worldline-gateway'),
                $status
            ));

            wc_add_notice($failure_reason, 'error');
            wp_redirect(wc_get_checkout_url());
            exit;

        } catch (\Exception $e) {
            $this->log('Callback exception: ' . $e->getMessage(), 'error', array(
                'order_id' => $order_id,
                'exception_type' => get_class($e),
                'exception_code' => $e->getCode(),
            ));
            $order->add_order_note(__('Payment verification failed: ', 'anz-worldline-gateway') . $e->getMessage());
            wc_add_notice(__('Payment verification failed. Please contact support.', 'anz-worldline-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }

    /**
     * Process a refund
     *
     * @param int        $order_id Order ID.
     * @param float|null $amount   Refund amount.
     * @param string     $reason   Refund reason.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('Refund failed: Order not found', 'error', array('order_id' => $order_id));
            return new \WP_Error('invalid_order', __('Order not found.', 'anz-worldline-gateway'));
        }

        // Get the transaction ID (payment ID) from the order
        $payment_id = $order->get_transaction_id();

        if (empty($payment_id)) {
            $this->log('Refund failed: No transaction ID found', 'error', array('order_id' => $order_id));
            return new \WP_Error('missing_transaction_id', __('No transaction ID found for this order. The payment may not have been completed.', 'anz-worldline-gateway'));
        }

        // Validate refund amount
        if (is_null($amount) || $amount <= 0) {
            $this->log('Refund failed: Invalid amount', 'error', array(
                'order_id' => $order_id,
                'amount' => $amount,
            ));
            return new \WP_Error('invalid_amount', __('Invalid refund amount.', 'anz-worldline-gateway'));
        }

        // Check that refund amount doesn't exceed order total
        $order_total = (float) $order->get_total();
        $already_refunded = (float) $order->get_total_refunded();
        $max_refundable = $order_total - $already_refunded;

        if ($amount > $max_refundable) {
            $this->log('Refund failed: Amount exceeds maximum refundable', 'error', array(
                'order_id' => $order_id,
                'requested_amount' => $amount,
                'max_refundable' => $max_refundable,
            ));
            return new \WP_Error('invalid_amount', sprintf(
                __('Refund amount exceeds the maximum refundable amount of %s.', 'anz-worldline-gateway'),
                wc_price($max_refundable)
            ));
        }

        $this->log('Processing refund', 'info', array(
            'order_id' => $order_id,
            'payment_id' => $payment_id,
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'reason' => $reason,
            'testmode' => $this->testmode ? 'yes' : 'no',
        ));

        try {
            $client = $this->get_client();

            // Create amount object (convert to cents)
            $amountOfMoney = new AmountOfMoney();
            $amountOfMoney->setAmount((int) round($amount * 100));
            $amountOfMoney->setCurrencyCode($order->get_currency());

            // Create refund request
            $refundRequest = new RefundRequest();
            $refundRequest->setAmountOfMoney($amountOfMoney);

            // Process the refund via the API
            $refundResponse = $client->merchant($this->merchant_id)->payments()->refundPayment($payment_id, $refundRequest);

            // Get refund details
            $refundId = $refundResponse->getId();
            $refundStatus = $refundResponse->getStatus();
            $statusOutput = $refundResponse->getStatusOutput();
            $statusCode = $statusOutput ? $statusOutput->getStatusCode() : 0;

            $this->log('Refund API response received', 'info', array(
                'order_id' => $order_id,
                'refund_id' => $refundId,
                'refund_status' => $refundStatus,
                'status_code' => $statusCode,
            ));

            // ANZ Worldline refund status codes:
            // 8 = Refund successful
            // 81 = Refund pending
            // 82 = Refund uncertain
            // 83 = Refund rejected
            // Also check payment status strings for additional confirmation
            $successful_statuses = array('REFUNDED', 'REFUND_REQUESTED');
            $pending_statuses = array('PENDING_APPROVAL');
            $successful_codes = array(8, 81); // 8 = success, 81 = pending (will complete)

            $is_success = in_array($statusCode, $successful_codes, true)
                          || in_array($refundStatus, $successful_statuses, true)
                          || in_array($refundStatus, $pending_statuses, true);

            if ($is_success) {
                // Refund successful or pending
                $note = sprintf(
                    __('Refund of %s processed successfully via ANZ Worldline. Refund ID: %s, Status: %s', 'anz-worldline-gateway'),
                    wc_price($amount),
                    $refundId,
                    $refundStatus
                );

                if (!empty($reason)) {
                    $note .= sprintf(__(' Reason: %s', 'anz-worldline-gateway'), $reason);
                }

                $order->add_order_note($note);

                // Store refund ID in order meta for reference
                $refund_ids = $order->get_meta('_anz_worldline_refund_ids');
                $refund_ids = $refund_ids ? $refund_ids : array();
                $refund_ids[] = $refundId;
                $order->update_meta_data('_anz_worldline_refund_ids', $refund_ids);
                $order->save();

                $this->log('Refund completed successfully', 'info', array(
                    'order_id' => $order_id,
                    'refund_id' => $refundId,
                    'amount' => $amount,
                    'status_code' => $statusCode,
                ));

                return true;
            }

            // Refund failed - get error details
            $error_message = $this->get_refund_error_message($statusCode, $refundStatus, $statusOutput);

            $this->log('Refund failed', 'error', array(
                'order_id' => $order_id,
                'refund_id' => $refundId,
                'status_code' => $statusCode,
                'refund_status' => $refundStatus,
                'error_message' => $error_message,
            ));

            $order->add_order_note(sprintf(
                __('ANZ Worldline refund failed. Amount: %s, Status Code: %d, Error: %s', 'anz-worldline-gateway'),
                wc_price($amount),
                $statusCode,
                $error_message
            ));

            return new \WP_Error('refund_failed', $error_message);

        } catch (ApiException $e) {
            $this->log('Refund API Exception', 'error', array(
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ));

            $order->add_order_note(sprintf(
                __('ANZ Worldline refund failed. Amount: %s, Error: %s', 'anz-worldline-gateway'),
                wc_price($amount),
                $e->getMessage()
            ));

            return new \WP_Error('refund_api_error', __('Refund could not be processed. Please try again or process the refund directly in the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'));

        } catch (\Exception $e) {
            $this->log('Refund Exception', 'error', array(
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'message' => $e->getMessage(),
                'exception_type' => get_class($e),
            ));

            $order->add_order_note(sprintf(
                __('ANZ Worldline refund error. Amount: %s, Error: %s', 'anz-worldline-gateway'),
                wc_price($amount),
                $e->getMessage()
            ));

            return new \WP_Error('refund_error', __('An error occurred while processing the refund. Please try again or contact support.', 'anz-worldline-gateway'));
        }
    }

    /**
     * Get user-friendly error message for refund failures
     *
     * @param int    $status_code   The refund status code.
     * @param string $refund_status The refund status string.
     * @param object $status_output The status output object.
     * @return string Error message.
     */
    private function get_refund_error_message($status_code, $refund_status, $status_output) {
        // Try to get error details from status output
        if ($status_output) {
            $errors = method_exists($status_output, 'getErrors') ? $status_output->getErrors() : null;
            if ($errors && is_array($errors) && !empty($errors)) {
                $first_error = $errors[0];
                if (method_exists($first_error, 'getMessage')) {
                    return $first_error->getMessage();
                }
            }
        }

        // Map status codes to error messages
        switch ($status_code) {
            case 82:
                return __('Refund outcome is uncertain. Please check the ANZ Worldline Merchant Portal for the actual status.', 'anz-worldline-gateway');

            case 83:
                return __('Refund was rejected by ANZ Worldline. The original transaction may not be eligible for refund.', 'anz-worldline-gateway');

            default:
                if ($refund_status === 'REJECTED') {
                    return __('Refund was rejected. Please verify the transaction is eligible for refund.', 'anz-worldline-gateway');
                }
                return sprintf(__('Refund failed with status code %d.', 'anz-worldline-gateway'), $status_code);
        }
    }

    /**
     * Capture an authorized payment
     *
     * @param int        $order_id Order ID.
     * @param float|null $amount   Amount to capture (null for full amount).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function capture_payment($order_id, $amount = null) {
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('Capture failed: Order not found', 'error', array('order_id' => $order_id));
            return new \WP_Error('invalid_order', __('Order not found.', 'anz-worldline-gateway'));
        }

        // Check if order needs capture
        $needs_capture = $order->get_meta('_anz_worldline_needs_capture');
        if ($needs_capture !== 'yes') {
            $this->log('Capture failed: Order does not need capture', 'error', array('order_id' => $order_id));
            return new \WP_Error('capture_not_needed', __('This order does not require capture. It may have already been captured or was processed with immediate capture.', 'anz-worldline-gateway'));
        }

        // Get the transaction ID (payment ID) from the order
        $payment_id = $order->get_transaction_id();

        if (empty($payment_id)) {
            $this->log('Capture failed: No transaction ID found', 'error', array('order_id' => $order_id));
            return new \WP_Error('missing_transaction_id', __('No transaction ID found for this order.', 'anz-worldline-gateway'));
        }

        // Determine capture amount
        if (is_null($amount)) {
            $amount = (float) $order->get_total();
        }

        // Validate capture amount
        if ($amount <= 0) {
            $this->log('Capture failed: Invalid amount', 'error', array(
                'order_id' => $order_id,
                'amount' => $amount,
            ));
            return new \WP_Error('invalid_amount', __('Invalid capture amount.', 'anz-worldline-gateway'));
        }

        $this->log('Processing capture', 'info', array(
            'order_id' => $order_id,
            'payment_id' => $payment_id,
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'testmode' => $this->testmode ? 'yes' : 'no',
        ));

        try {
            $client = $this->get_client();

            // Create capture request
            $captureRequest = new CapturePaymentRequest();
            $captureRequest->setAmount((int) round($amount * 100));

            // Process the capture via the API
            $captureResponse = $client->merchant($this->merchant_id)->payments()->capturePayment($payment_id, $captureRequest);

            // Get capture details
            $captureId = $captureResponse->getId();
            $captureStatus = $captureResponse->getStatus();
            $statusOutput = $captureResponse->getStatusOutput();
            $statusCode = $statusOutput ? $statusOutput->getStatusCode() : 0;

            $this->log('Capture API response received', 'info', array(
                'order_id' => $order_id,
                'capture_id' => $captureId,
                'capture_status' => $captureStatus,
                'status_code' => $statusCode,
            ));

            // ANZ Worldline capture status codes:
            // 9 = Captured successfully
            // 91 = Capture pending
            // 92 = Capture uncertain
            // 93 = Capture rejected
            $successful_statuses = array('CAPTURED', 'CAPTURE_REQUESTED');
            $pending_statuses = array('PENDING_CAPTURE');
            $successful_codes = array(9, 91);

            $is_success = in_array($statusCode, $successful_codes, true)
                          || in_array($captureStatus, $successful_statuses, true)
                          || in_array($captureStatus, $pending_statuses, true);

            if ($is_success) {
                // Capture successful
                $order->update_meta_data('_anz_worldline_needs_capture', 'no');
                $order->update_meta_data('_anz_worldline_captured', 'yes');
                $order->update_meta_data('_anz_worldline_capture_id', $captureId);
                $order->save();

                $order->add_order_note(sprintf(
                    __('Payment captured successfully via ANZ Worldline. Capture ID: %s, Amount: %s, Status: %s', 'anz-worldline-gateway'),
                    $captureId,
                    wc_price($amount),
                    $captureStatus
                ));

                // Update order status to processing if it was on-hold
                if ($order->has_status('on-hold')) {
                    $order->update_status('processing', __('Payment captured.', 'anz-worldline-gateway'));
                }

                $this->log('Capture completed successfully', 'info', array(
                    'order_id' => $order_id,
                    'capture_id' => $captureId,
                    'amount' => $amount,
                    'status_code' => $statusCode,
                ));

                return true;
            }

            // Capture failed
            $error_message = $this->get_capture_error_message($statusCode, $captureStatus, $statusOutput);

            $this->log('Capture failed', 'error', array(
                'order_id' => $order_id,
                'capture_id' => $captureId,
                'status_code' => $statusCode,
                'capture_status' => $captureStatus,
                'error_message' => $error_message,
            ));

            $order->add_order_note(sprintf(
                __('ANZ Worldline capture failed. Amount: %s, Status Code: %d, Error: %s', 'anz-worldline-gateway'),
                wc_price($amount),
                $statusCode,
                $error_message
            ));

            return new \WP_Error('capture_failed', $error_message);

        } catch (ApiException $e) {
            $this->log('Capture API Exception', 'error', array(
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ));

            $order->add_order_note(sprintf(
                __('ANZ Worldline capture failed. Amount: %s, Error: %s', 'anz-worldline-gateway'),
                wc_price($amount),
                $e->getMessage()
            ));

            return new \WP_Error('capture_api_error', __('Capture could not be processed. Please try again or process the capture directly in the ANZ Worldline Merchant Portal.', 'anz-worldline-gateway'));

        } catch (\Exception $e) {
            $this->log('Capture Exception', 'error', array(
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'message' => $e->getMessage(),
                'exception_type' => get_class($e),
            ));

            $order->add_order_note(sprintf(
                __('ANZ Worldline capture error. Amount: %s, Error: %s', 'anz-worldline-gateway'),
                wc_price($amount),
                $e->getMessage()
            ));

            return new \WP_Error('capture_error', __('An error occurred while processing the capture. Please try again or contact support.', 'anz-worldline-gateway'));
        }
    }

    /**
     * Get user-friendly error message for capture failures
     *
     * @param int    $status_code    The capture status code.
     * @param string $capture_status The capture status string.
     * @param object $status_output  The status output object.
     * @return string Error message.
     */
    private function get_capture_error_message($status_code, $capture_status, $status_output) {
        // Try to get error details from status output
        if ($status_output) {
            $errors = method_exists($status_output, 'getErrors') ? $status_output->getErrors() : null;
            if ($errors && is_array($errors) && !empty($errors)) {
                $first_error = $errors[0];
                if (method_exists($first_error, 'getMessage')) {
                    return $first_error->getMessage();
                }
            }
        }

        // Map status codes to error messages
        switch ($status_code) {
            case 92:
                return __('Capture outcome is uncertain. Please check the ANZ Worldline Merchant Portal for the actual status.', 'anz-worldline-gateway');

            case 93:
                return __('Capture was rejected by ANZ Worldline. The authorization may have expired or been cancelled.', 'anz-worldline-gateway');

            default:
                if ($capture_status === 'REJECTED' || $capture_status === 'CANCELLED') {
                    return __('Capture was rejected. The authorization may have expired.', 'anz-worldline-gateway');
                }
                return sprintf(__('Capture failed with status code %d.', 'anz-worldline-gateway'), $status_code);
        }
    }

    /**
     * Check if order needs capture
     *
     * @param WC_Order $order Order object.
     * @return bool
     */
    public function order_needs_capture($order) {
        return $order->get_meta('_anz_worldline_needs_capture') === 'yes';
    }

    /**
     * Get the callback URL for payment return
     *
     * @param int $order_id Order ID.
     * @return string
     */
    private function get_callback_url($order_id) {
        return add_query_arg(
            array('order_id' => $order_id),
            WC()->api_request_url('anz_worldline')
        );
    }

    /**
     * Get customer locale for hosted checkout page
     *
     * @return string
     */
    private function get_customer_locale() {
        $locale = get_locale();

        // Map WordPress locale to ANZ supported locales
        $locale_map = array(
            'en_AU' => 'en_AU',
            'en_US' => 'en_US',
            'en_GB' => 'en_UK',
            'zh_CN' => 'zh_CN',
            'ja'    => 'ja_JP',
            'ko_KR' => 'ko_KR',
        );

        // Try exact match first
        if (isset($locale_map[$locale])) {
            return $locale_map[$locale];
        }

        // Try language code only
        $lang = substr($locale, 0, 2);
        foreach ($locale_map as $wp_locale => $anz_locale) {
            if (substr($wp_locale, 0, 2) === $lang) {
                return $anz_locale;
            }
        }

        // Default to English AU
        return 'en_AU';
    }

    /**
     * Get detailed payment failure information based on status code
     *
     * @param int    $status_code The payment status code.
     * @param object $status_output The status output object from the API.
     * @param object $payment The payment object from the API.
     * @return array Array with 'reason', 'error_code', and 'customer_message'.
     */
    private function get_payment_failure_info($status_code, $status_output, $payment) {
        $reason = __('Unknown error', 'anz-worldline-gateway');
        $error_code = '';
        $customer_message = __('Payment was not successful. Please try again or use a different payment method.', 'anz-worldline-gateway');

        // Try to get error details from the status output
        if ($status_output) {
            $errors = method_exists($status_output, 'getErrors') ? $status_output->getErrors() : null;
            if ($errors && is_array($errors) && !empty($errors)) {
                $first_error = $errors[0];
                if (method_exists($first_error, 'getErrorCode')) {
                    $error_code = $first_error->getErrorCode();
                }
                if (method_exists($first_error, 'getMessage')) {
                    $reason = $first_error->getMessage();
                } elseif (method_exists($first_error, 'getId')) {
                    $reason = $first_error->getId();
                }
            }
        }

        // Map status codes to user-friendly messages
        switch ($status_code) {
            case 2:
                // Declined
                $reason = $reason !== __('Unknown error', 'anz-worldline-gateway') ? $reason : __('Transaction declined by issuer', 'anz-worldline-gateway');
                $customer_message = __('Your payment was declined. Please check your card details or try a different card.', 'anz-worldline-gateway');
                break;

            case 51:
                // Pending
                $reason = __('Transaction pending', 'anz-worldline-gateway');
                $customer_message = __('Your payment is pending. Please wait or contact support if the issue persists.', 'anz-worldline-gateway');
                break;

            case 0:
                // Invalid/Unknown
                $reason = __('Invalid payment response', 'anz-worldline-gateway');
                $customer_message = __('We could not process your payment. Please try again.', 'anz-worldline-gateway');
                break;

            case 1:
                // Cancelled
                $reason = __('Transaction cancelled', 'anz-worldline-gateway');
                $customer_message = __('The payment was cancelled. Please try again if you wish to complete your purchase.', 'anz-worldline-gateway');
                break;

            case 52:
                // Uncertain (for captures/refunds)
                $reason = __('Transaction outcome uncertain', 'anz-worldline-gateway');
                $customer_message = __('We could not confirm your payment status. Please contact support.', 'anz-worldline-gateway');
                break;

            case 57:
            case 59:
                // Rejected by fraud prevention
                $reason = __('Transaction rejected by fraud prevention', 'anz-worldline-gateway');
                $customer_message = __('Your payment could not be processed. Please try a different payment method or contact support.', 'anz-worldline-gateway');
                break;

            case 83:
                // Refund failed
                $reason = __('Refund rejected', 'anz-worldline-gateway');
                $customer_message = __('The refund could not be processed. Please contact support.', 'anz-worldline-gateway');
                break;

            default:
                // Use API error message if available, otherwise generic
                if ($reason === __('Unknown error', 'anz-worldline-gateway')) {
                    $reason = sprintf(__('Payment failed with status code %d', 'anz-worldline-gateway'), $status_code);
                }
                break;
        }

        return array(
            'reason'           => $reason,
            'error_code'       => $error_code,
            'customer_message' => $customer_message,
        );
    }

    /**
     * Get user-friendly message for hosted checkout status
     *
     * @param string $status The hosted checkout status.
     * @return string User-friendly error message.
     */
    private function get_checkout_status_message($status) {
        switch ($status) {
            case 'CANCELLED_BY_CONSUMER':
                return __('You cancelled the payment. Please try again if you wish to complete your purchase.', 'anz-worldline-gateway');

            case 'CLIENT_NOT_ELIGIBLE_FOR_SELECTED_PAYMENT_PRODUCT':
                return __('The selected payment method is not available. Please try a different payment method.', 'anz-worldline-gateway');

            case 'IN_PROGRESS':
                return __('Your payment is still being processed. Please wait a moment and check your order status.', 'anz-worldline-gateway');

            case 'PAYMENT_NOT_COMPLETED':
                return __('Payment was not completed. Please try again.', 'anz-worldline-gateway');

            case 'PAYMENT_TIMED_OUT':
                return __('The payment session timed out. Please try again.', 'anz-worldline-gateway');

            default:
                return __('Payment was not successful. Please try again.', 'anz-worldline-gateway');
        }
    }

    /**
     * Log messages - logs to both WooCommerce logs and custom log table
     *
     * @param string $message Message to log.
     * @param string $level Log level (info, warning, error).
     * @param array  $context Additional context data.
     */
    private function log($message, $level = 'error', $context = array()) {
        $logger = wc_get_logger();
        $logger->log($level, $message, array('source' => 'anz-worldline'));

        // Also log to custom table for admin viewing
        self::add_transaction_log($message, $level, $context);
    }

    /**
     * Add entry to custom transaction log
     *
     * @param string $message Log message.
     * @param string $level Log level.
     * @param array  $context Additional context.
     */
    public static function add_transaction_log($message, $level = 'info', $context = array()) {
        $logs = get_option('anz_worldline_transaction_logs', array());

        // Keep only last 100 entries
        if (count($logs) >= 100) {
            $logs = array_slice($logs, -99);
        }

        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        );

        // Don't autoload logs on every page load (performance optimization)
        update_option('anz_worldline_transaction_logs', $logs, false);
    }

    /**
     * Get transaction logs
     *
     * @return array
     */
    public static function get_transaction_logs() {
        return get_option('anz_worldline_transaction_logs', array());
    }

    /**
     * Clear transaction logs
     */
    public static function clear_transaction_logs() {
        delete_option('anz_worldline_transaction_logs');
    }
}
