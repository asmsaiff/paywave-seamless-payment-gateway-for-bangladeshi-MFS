<?php
namespace PayWave;

use WC_Payment_Gateway;

class Gateway extends WC_Payment_Gateway {
    public function __construct() {
        session_start();
        $this->id = 'paywave_payment';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'PayWave';
        $this->method_description = 'Supports bKash, Nagad, Rocket, etc.';

        // Load form fields and settings
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->credential_type = $this->get_option('credential_type');

        $_SESSION["base_url"] = $this->credential_type === 'live'
            ? 'https://tokenized.pay.bka.sh/v1.2.0-beta'
            : 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

        $this->base_url = $_SESSION["base_url"];
    }

    public static function add_gateway_class($gateways) {
        $gateways[] = 'PayWave\\Gateway'; // Add this class as a gateway
        return $gateways;
    }

    public function init_form_fields() {
        // Define form fields
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable PayWave Payment',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'bKash',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Pay with your mobile wallet via bKash, Nagad, or Rocket.',
            ),
            'credential_type' => array(
                'title'   => 'Type',
                'type'    => 'select',
                'label'   => 'Enable for',
                'options' => array(
                    'sandbox'   =>  __("SandBox", "paywave"),
                    'live'      =>  __("Live", "paywave"),
                ),
                'default' =>    $this->credential_type
            ),
            'app_key' => array(
                'title'       => 'App Key',
                'type'        => 'text',
                'description' => 'Your bKash App Key.',
            ),
            'app_secret' => array(
                'title'       => 'App Secret',
                'type'        => 'password',
                'description' => 'Your bKash App Secret.',
            ),
            'username' => array(
                'title'       => 'Username',
                'type'        => 'text',
                'description' => 'Your bKash Username.',
            ),
            'password' => array(
                'title'       => 'Password',
                'type'        => 'password',
                'description' => 'Your bKash Password.',
            ),
        );
    }

    public function process_payment($order_id) {
        // Payment processing logic
        $order = wc_get_order($order_id);
        $token_response = $this->get_bkash_token();

        if (!$token_response || isset($token_response->error)) {
            wc_add_notice('Payment error: Failed to authenticate with bKash.', 'error');
            return;
        }

        $payment_response = $this->create_bkash_payment($order, $token_response->id_token);

        if (!$payment_response || isset($payment_response->error)) {
            wc_add_notice('Payment error: Failed to create payment.', 'error');
            return;
        }

        return [
            'result'   => 'success',
            'redirect' => $payment_response->bkashURL,
        ];
    }

    private function get_bkash_token() {
        // Authentication logic
        $request_data = array(
            'app_key' => $this->get_option('app_key'),
            'app_secret' => $this->get_option('app_secret'),
        );

        $ch = curl_init($this->base_url . "/tokenized/checkout/token/grant");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "username: " . $this->get_option('username'),
            "password: " . $this->get_option('password'),
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        $response = curl_exec($ch);
        $_SESSION["bkash_token"] = $response;
        curl_close($ch);

        return json_decode($response);
    }

    private function create_bkash_payment($order, $token) {
        // Payment creation logic
        $request_data = array(
            'mode' => '0011',
            'payerReference' => '01770618576',
            'callbackURL' => get_home_url() . "/execute-payment/",
            'merchantAssociationInfo' => 'MI05MID54RF09123456One',
            'amount' => strval($order->get_total()),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => strval($order->get_order_number()),
        );

        $bkash_token = $_SESSION["bkash_token"];
        $ch = curl_init($this->base_url . "/tokenized/checkout/create");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer $token",
            "X-APP-Key: " . $this->get_option('app_key'),
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        $response = curl_exec($ch);
        $_SESSION["bkash_payment_info"] = $response;
        curl_close($ch);

        return json_decode($response);
    }
}
