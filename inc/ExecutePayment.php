<?php
namespace PayWave;

class ExecutePayment {
    public static function onboard() {
        if (get_query_var('execute_payment')) {
            $order_id = $_SESSION["order_id"];
            $bkash_token = json_decode($_SESSION["bkash_token"]);
            $order = wc_get_order($order_id);

            $base_url = $_SESSION["base_url"];

            // Execute the payment
            $ch = curl_init($base_url . "/tokenized/checkout/execute");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $bkash_token->id_token",
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'paymentID' => $_GET['paymentID']
            ]));
            $response = curl_exec($ch);
            curl_close($ch);

            // Update order status
            $order->update_status('completed', 'Order completed.');
            wp_safe_redirect(get_home_url() . "/checkout/order-received/{$order_id}/?key=" . $order->get_order_key());

            // Clean up session data
            unset($_SESSION["order_id"], $_SESSION["bkash_token"]);
            exit;
        }
    }
}
add_action('template_redirect', ['PayWave\\ExecutePayment', 'onboard']);
