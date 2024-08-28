<?php
    namespace PayWave;
    use WC_Payment_Gateway;

    class PayWaveGateway extends WC_Payment_Gateway {
        // Properties
        public $id;
        public $icon;
        public $has_fields;
        public $method_title;
        public $method_description;
        public $title;
        public $description;
        public $credential_type;
        public $base_url;
        public $order_id;
        public $sandbox_wallet;
        public $paywave_bkash_app_key;
        public $paywave_bkash_app_secret;
        public $paywave_bkash_username;
        public $paywave_bkash_password;

        public function __construct() {
            $this->id = 'paywave_payment';
            $this->method_title = 'PayWave';
            $this->method_description = 'PayWave is a seamless gateway for bKash';

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            $this->title = $this->get_option('paywave_title');
            $this->description = $this->get_option('paywave_description');
            $this->credential_type = $this->get_option('paywave_credential_type');

            // bKash Credentials
            $this->sandbox_wallet = $this->get_option('paywave_bkash_sandbox_wallet_number');
            $this->paywave_bkash_app_key = $this->get_option('paywave_app_key');
            $this->paywave_bkash_app_secret = $this->get_option('paywave_app_secret');
            $this->paywave_bkash_username = $this->get_option('paywave_bkash_username');
            $this->paywave_bkash_password = $this->get_option('paywave_bkash_password');

            $this->base_url = $this->credential_type == "live" ?
                "https://tokenized.pay.bka.sh/v1.2.0-beta" :
                "https://tokenized.sandbox.bka.sh/v1.2.0-beta";
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'paywave_enabled' => array(
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Enable PayWave Payment',
                    'default' => 'yes'
                ),
                'paywave_title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'bKash',
                    'desc_tip'    => true,
                ),
                'paywave_description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your mobile wallet via bKash',
                ),
                'paywave_credential_type' => array(
                    'title'   => 'Type',
                    'type'    => 'select',
                    'label'   => 'Enable for',
                    'options' => array(
                        'sandbox'   =>  __("SandBox", "paywave"),
                        'live'      =>  __("Live", "paywave"),
                    ),
                    'default' =>    $this->credential_type
                ),
                'paywave_bkash_sandbox_wallet_number' => array(
                    'title'       => 'Sandbox Wallet',
                    'type'        => 'text',
                    'description' => 'Your bKash sandbox wallet. Leave it blank if you have input live credentials',
                ),
                'paywave_bkash_username' => array(
                    'title'       => 'Username',
                    'type'        => 'text',
                    'description' => 'Your bKash Username.',
                ),
                'paywave_bkash_password' => array(
                    'title'       => 'Password',
                    'type'        => 'password',
                    'description' => 'Your bKash Password.',
                ),
                'paywave_app_key' => array(
                    'title'       => 'App Key',
                    'type'        => 'text',
                    'description' => 'Your bKash App Key.',
                ),
                'paywave_app_secret' => array(
                    'title'       => 'App Secret',
                    'type'        => 'password',
                    'description' => 'Your bKash App Secret.',
                ),
            );
        }

        public static function init() {
            class_alias(__CLASS__, 'PayWave_Gateway');
        }

        /**
         * Process the payment and return the result
         */
        public function process_payment($order_id) {
            // Credential validation before payment
            if (!$this->sandbox_wallet && !$this->paywave_bkash_app_key && !$this->paywave_app_secret && !$this->paywave_bkash_username && !$this->paywave_bkash_password) {
                wc_add_notice('Payment error : Failed to authenticate with bKash. Please check your credentials and try again.', 'error');
                return;
            }

            $this->order_id = $order_id;
            $order = wc_get_order($order_id);

            // Step 1: Authenticate and get a token
            $token_response = $this->get_bkash_token();

            if (!$token_response || isset($token_response->error)) {
                wc_add_notice('Payment error: Failed to authenticate with bKash.', 'error');
                return;
            }

            $token = $token_response->id_token;

            // Step 2: Create a payment request
            $payment_response = $this->create_bkash_payment($order, $token, $order_id, $this->paywave_bkash_app_key, $this->base_url);

            if (!$payment_response || isset($payment_response->error)) {
                wc_add_notice('Payment error: Failed to create payment.', 'error');
                return;
            }

            $payment_id = $payment_response->paymentID;

            // Step 3: Redirect to the bKash transaction page
            if ($payment_response->bkashURL) {
                $order->update_status('pending', 'Awaiting bKash payment confirmation.');
                return array(
                    'result'   => 'success',
                    'redirect' => $payment_response->bkashURL,
                );
            } else {
                wc_add_notice('Payment error: bKash payment URL not received.', 'error');
                return;
            }
        }

        /**
         * Get bKash Token
         */
        private function get_bkash_token() {
            $request_data = array(
                'app_key' => $this->paywave_bkash_app_key,
                'app_secret' => $this->paywave_bkash_app_secret,
            );

            $ch = curl_init($this->base_url . "/tokenized/checkout/token/grant");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "username: " . $this->paywave_bkash_username,
                "password: " . $this->paywave_bkash_password,
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
            $response = curl_exec($ch);
            $_SESSION["bkash_token"] = $response;
            curl_close($ch);

            return json_decode($response);
        }

        /**
         * Create bKash Payment
         */
        private function create_bkash_payment($order, $token, $order_id, $paywave_bkash_app_key, $base_url) {
            $request_data = array(
                'mode' => '0011',
                'payerReference' => $this->credential_type == "live" ? bloginfo('title') : $this->sandbox_wallet,
                'callbackURL' => get_home_url() . "/execute-payment?order_id=" . $order_id . "&app_key=" . $paywave_bkash_app_key . "&base_url=" . $base_url,
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
                "X-APP-Key: " . $this->paywave_bkash_app_key,
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
            $response = curl_exec($ch);
            $_SESSION["bkash_payment_info"] = $response;
            curl_close($ch);

            return json_decode($response);
        }
    }
