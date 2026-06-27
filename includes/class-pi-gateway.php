<?php
if (!defined('ABSPATH')) exit;

class XpertPi_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'xpertcreation_pi_pay';
        $this->icon               = PI_PAY_PLUGIN_URL . 'assets/images/pi-logo.png';
        $this->has_fields         = true;
        $this->method_title       = 'XpertCreation Pi Network Payment';
        $this->method_description = 'Accept Pi cryptocurrency payments via Pi Network SDK. Not officially affiliated with Pi Network.';
        $this->supports           = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->app_id       = $this->get_option('app_id');
        $this->api_key      = $this->get_option('api_key');
        $this->sandbox      = $this->get_option('sandbox') === 'yes';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_api_pi_pay_callback', [$this, 'handle_callback']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Pi Network Payment',
                'default' => 'yes',
            ],
            'title' => [
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Payment title shown to customer at checkout.',
                'default'     => 'Pay with Pi',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment description shown to customer.',
                'default'     => 'Pay securely using Pi Network cryptocurrency.',
            ],
            'app_id' => [
                'title'       => 'Pi App ID',
                'type'        => 'text',
                'description' => 'Your Pi Network App ID from Pi Developer Portal.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => 'Pi API Key',
                'type'        => 'password',
                'description' => 'Your Pi Network API Key from Pi Developer Portal.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'sandbox' => [
                'title'       => 'Sandbox Mode',
                'type'        => 'checkbox',
                'label'       => 'Enable Sandbox/Test Mode',
                'default'     => 'yes',
                'description' => 'Use Pi testnet for testing. Disable for mainnet.',
            ],
            'pi_rate' => [
                'title'       => 'Pi Exchange Rate (USD)',
                'type'        => 'text',
                'description' => 'Value of 1 Pi in USD. Leave empty to use live rate.',
                'default'     => '',
                'desc_tip'    => true,
            ],
        ];
    }

    public function payment_scripts() {
        if (!is_checkout()) return;

        wp_enqueue_script('pi-sdk', 'https://sdk.minepi.com/pi-sdk.js', [], null, true);
        wp_enqueue_script('pi-pay-checkout', PI_PAY_PLUGIN_URL . 'assets/js/pi-checkout.js', ['jquery', 'pi-sdk'], PI_PAY_VERSION, true);
        wp_enqueue_style('pi-pay-style', PI_PAY_PLUGIN_URL . 'assets/css/pi-checkout.css', [], PI_PAY_VERSION);

        wp_localize_script('pi-pay-checkout', 'pi_pay_params', [
            'app_id'      => $this->app_id,
            'sandbox'     => $this->sandbox ? 'true' : 'false',
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('pi_pay_nonce'),
            'currency'    => get_woocommerce_currency(),
        ]);
    }

    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . esc_html($this->description) . '</p>';
        }
        echo '<div id="pi-pay-container">
            <div id="pi-pay-btn-wrap">
                <button type="button" id="pi-pay-btn" class="pi-pay-button">
                    <img src="' . PI_PAY_PLUGIN_URL . 'assets/images/pi-logo.png" width="20" height="20" alt="Pi">
                    Authenticate with Pi
                </button>
            </div>
            <div id="pi-pay-status"></div>
            <input type="hidden" name="pi_payment_id" id="pi_payment_id" value="">
            <input type="hidden" name="pi_username" id="pi_username" value="">
            <input type="hidden" name="pi_amount" id="pi_amount" value="">
        </div>';
    }

    public function validate_fields() {
        if (empty($_POST['pi_payment_id'])) {
            wc_add_notice('Please complete Pi Network payment first.', 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $payment_id = sanitize_text_field($_POST['pi_payment_id']);
        $pi_username = sanitize_text_field($_POST['pi_username']);
        $pi_amount = sanitize_text_field($_POST['pi_amount']);

        // Verify payment with Pi API
        $pi_api = new Pi_Pay_API($this->api_key, $this->sandbox);
        $payment = $pi_api->get_payment($payment_id);

        if (!$payment || $payment['status']['developer_approved'] !== true) {
            wc_add_notice('Pi payment verification failed. Please try again.', 'error');
            return ['result' => 'fail'];
        }

        // Complete the payment
        $pi_api->complete_payment($payment_id, $order_id);

        // Update order
        $order->payment_complete($payment_id);
        $order->add_order_note('Pi payment completed. Payment ID: ' . $payment_id . ' | Pi Username: ' . $pi_username . ' | Amount: ' . $pi_amount . ' Pi');
        $order->update_meta_data('_pi_payment_id', $payment_id);
        $order->update_meta_data('_pi_username', $pi_username);
        $order->update_meta_data('_pi_amount', $pi_amount);
        $order->save();

        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public function handle_callback() {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) wp_die('Invalid request', 400);

        $payment_id = sanitize_text_field($body['paymentId'] ?? '');
        $order_id = sanitize_text_field($body['metadata']['orderId'] ?? '');

        if (!$payment_id || !$order_id) wp_die('Missing data', 400);

        $pi_api = new Pi_Pay_API($this->api_key, $this->sandbox);
        $pi_api->complete_payment($payment_id, $order_id);

        wp_send_json(['status' => 'ok']);
    }
}
