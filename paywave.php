<?php
    /**
     *    Plugin Name:       PayWave
     *    Plugin URI:        https://paywave.com
     *    Description:       PayWave is a versatile plugin designed to integrate all Bangladeshi payment gateways seamlessly. It simplifies online transactions by offering a unified platform for various payment methods, ensuring secure and efficient processing. Ideal for businesses, PayWave supports popular gateways like bKash, Nagad, Rocket, and more, enhancing the e-commerce experience.
     *    Version:           1.0
     *    Requires at least: 6.6
     *    Requires PHP:      7.4
     *    Author:            S. Saif
     *    Author URI:        https://saif.im
     *    License:           GPL v2 or later
     *    License URI:       https://www.gnu.org/licenses/gpl-2.0.html
     *    Update URI:
     *    Text Domain:       paywave
     *    Domain Path:       /languages
     */
    // Exit if accessed directly
    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     *  This action hook registers our PHP class as a WooCommerce payment gateway
     */
    function paywave_add_gateway_class( $gateways ) {
        $gateways[] = 'PayWave_Gateway';
        return $gateways;
    }
    add_filter( 'woocommerce_payment_gateways', 'paywave_add_gateway_class' );

    /**
     * Initialize PayWave Gateway
     */
    function paywave_init_gateway_class() {
        class PayWave_Gateway extends WC_Payment_Gateway {

            /**
             * Class constructor, more about it in Step 3
            */
            public function __construct() {
                $this->id = 'paywave_payment';
                $this->icon = '';
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

            /**
             * Plugin options, we deal with it in Step 3 too
            */
            public function init_form_fields(){
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => 'Enable/Disable',
                        'type'    => 'checkbox',
                        'label'   => 'Enable bKash Payment',
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title'       => 'Title',
                        'type'        => 'text',
                        'description' => 'This controls the title which the user sees during checkout.',
                        'default'     => 'seamless gateway for bangladeshi MFS',
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => 'Description',
                        'type'        => 'textarea',
                        'description' => 'This controls the description which the user sees during checkout.',
                        'default'     => 'Pay with your credit card via My Custom Payment.',
                    ),
                );
            }

            /**
             * You will need it if you want your custom credit card form, Step 4 is about it
             */
            public function payment_fields() {

            }

            /*
            * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
            */
            public function payment_scripts() {

            }

            /*
            * Fields validation, more in Step 5
            */
            public function validate_fields() {

            }

            /*
            * We're processing the payments here, everything about it is in Step 5
            */
            public function process_payment( $order_id ) {
                $order = wc_get_order($order_id);

                // Set order status to 'on-hold' until payment is confirmed
                $order->update_status('on-hold', __('Awaiting payment confirmation', 'woocommerce'));

                // For illustration: Simulate payment confirmation (replace with actual payment confirmation code)
                $payment_confirmed = $this->simulate_payment_confirmation($order_id);

                if ($payment_confirmed) {
                    // Update order status to 'processing' or 'completed' upon successful payment
                    $order->update_status('processing', __('Payment received, awaiting fulfillment', 'woocommerce'));

                    // Return thank you page redirect
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                } else {
                    // If payment fails or is not confirmed, keep order on hold and notify the user
                    return array(
                        'result'   => 'fail',
                        'redirect' => wc_get_checkout_url(), // Redirect back to checkout with a failure notice
                    );
                }
            }

            // Simulate payment confirmation (replace with actual payment gateway code)
            private function simulate_payment_confirmation($order_id) {
                // In a real scenario, you would call the payment gateway API to confirm payment
                // For now, we'll just return true to simulate a successful payment
                return true;
            }

            /*
            * In case you need a webhook, like PayPal IPN etc
            */
            public function webhook() {

            }
        }
    }
    add_action( 'plugins_loaded', 'paywave_init_gateway_class' );
