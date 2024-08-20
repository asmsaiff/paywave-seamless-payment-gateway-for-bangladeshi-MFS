<?php
/**
 * Plugin Name:       PayWave
 * Plugin URI:        https://paywave.com
 * Description:       PayWave is a versatile plugin designed to integrate all Bangladeshi payment gateways seamlessly. It simplifies online transactions by offering a unified platform for various payment methods, ensuring secure and efficient processing. Ideal for businesses, PayWave supports popular gateways like bKash, Nagad, Rocket, and more, enhancing the e-commerce experience.
 * Version:           1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            S. Saif
 * Author URI:        https://saif.im
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       paywave
 * Domain Path:       /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function my_plugin_activation() {
    my_custom_rewrite_rule();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'my_plugin_activation');

function my_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'my_plugin_deactivation');

/**
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
function paywave_add_gateway_class($gateways) {
    $gateways[] = 'PayWave_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paywave_add_gateway_class');

/**
 * Initialize PayWave Gateway
 */
function paywave_init_gateway_class() {
    class PayWave_Gateway extends WC_Payment_Gateway {
        /**
         * Class constructor
         */
        public function __construct() {
            $this->id = 'paywave_payment';
            $this->icon = ''; // URL of the icon
            $this->has_fields = false;
            $this->method_title = 'PayWave';
            $this->method_description = 'PayWave supports popular gateways like bKash, Nagad, Rocket, and more, enhancing the e-commerce experience.';

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Save settings in admin
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');


        }

        function create_custom_page_with_template() {
            // Check if the page already exists
            $page = get_page_by_title('My Custom Page', OBJECT, 'page');
            if (!$page) {
                // Create the page
                $page_id = wp_insert_post(array(
                    'post_title'  => 'Execute Payment',
                    'post_content'=> 'This is the content of the page.',
                    'post_status' => 'publish',
                    'post_type'   => 'page',
                    'page_template' => 'template/execute-payment.php'
                ));
            }
        }

        /**
         * Plugin options
         */
        public function init_form_fields() {
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
                    )
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

        /**
         * Process the payment and return the result
         */
        public function process_payment($order_id) {
            $_SESSION["order_id"] = $order_id;
            $order = wc_get_order($order_id);

            // Step 1: Authenticate and get a token
            $token_response = $this->get_bkash_token();

            if (!$token_response || isset($token_response->error)) {
                wc_add_notice('Payment error: Failed to authenticate with bKash.', 'error');
                return;
            }

            $token = $token_response->id_token;

            // Step 2: Create a payment request
            $payment_response = $this->create_bkash_payment($order, $token);

            if (!$payment_response || isset($payment_response->error)) {
                wc_add_notice('Payment error: Failed to create payment.', 'error');
                return;
            }

            $payment_id = $payment_response->paymentID;

            // Step 3: Redirect to the bKash transaction page
            if (isset($payment_response->bkashURL) && !empty($payment_response->bkashURL)) {
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
                'app_key' => $this->get_option('app_key'),
                'app_secret' => $this->get_option('app_secret'),
            );

            $ch = curl_init("https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant");
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

        /**
         * Create bKash Payment
         */
        private function create_bkash_payment($order, $token) {
            $request_data = array(
                'mode' => '0011',
                'payerReference' => '01770618576',
                'callbackURL' => "http://localhost/ogsbd/execute-payment/",
                'merchantAssociationInfo' => 'MI05MID54RF09123456One',
                'amount' => strval($order->get_total()),
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => strval($order->get_order_number()),
            );

            $bkash_token = $_SESSION["bkash_token"];
            $ch = curl_init("https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create");
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

        /**
         * Execute bKash Payment
         */
        private function execute_bkash_payment($order, $payment_id, $token) {
            $ch = curl_init("https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute/$payment_id");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Authorization: Bearer $token",
                "X-APP-Key: " . $this->get_option('app_key'),
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            return $response;
        }

        /**
         * Webhook handler for bKash (Optional)
         */
        public function webhook() {
            // Handle webhook events here (e.g., for payment status updates)
        }
    }
}
add_action('plugins_loaded', 'paywave_init_gateway_class');

function my_custom_rewrite_rule() {
    add_rewrite_rule('^execute-payment/?$', 'index.php?execute_payment=1', 'top');
}
add_action('init', 'my_custom_rewrite_rule');

function my_custom_query_vars($vars) {
    $vars[] = 'execute_payment';
    return $vars;
}
add_filter('query_vars', 'my_custom_query_vars');

function my_execute_payment() {
    global $wp_query;

    if (isset($wp_query->query_vars['execute_payment'])) {
        $order_id = $_SESSION["order_id"];
        $bkash_token = json_decode($_SESSION["bkash_token"]);
        $bkash_payment_info = $_SESSION["bkash_payment_info"];

        $order = wc_get_order($order_id);

        $url = curl_init("https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute");
        $header = array(
            "Content-Type: application/json",
            "Authorization: Bearer $bkash_token->id_token",
            "X-APP-Key: 0vWQuCRGiUX7EPVjQDr0EUAYtc",
        );

        $posttoken = array(
            'paymentID' => $_GET["paymentID"]
        );

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, json_encode($posttoken));
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);

        curl_close($url);
        $response = curl_exec($url);
        curl_close($url);


        $order->update_status('completed', 'Order completed.');

        wp_safe_redirect(get_home_url() . "/checkout/order-received/" . $order_id . "/?key=" . $order->get_meta('_order_key'));
        unset($_SESSION["order_id"]);
        unset($_SESSION["bkash_token"]);
        unset($_SESSION["bkash_payment_info"]);
        exit;
    }
}
add_action('template_redirect', 'my_execute_payment');
