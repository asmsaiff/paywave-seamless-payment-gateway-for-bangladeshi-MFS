<?php
namespace PayWave;

class Init {
    public static function run() {
        // Register the gateway
        add_filter('woocommerce_payment_gateways', ['PayWave\\Gateway', 'add_gateway_class']);
    }
}
