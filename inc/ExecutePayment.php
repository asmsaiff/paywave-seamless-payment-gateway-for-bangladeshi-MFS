<?php
    namespace PayWave;

    class ExecutePayment {
        public static function handle_payment_execution() {
            global $wp_query;

            if (isset($wp_query->query_vars['execute_payment'])) {
                $order_id = $_GET['order_id'];
                $x_app_key = $_GET['app_key'];
                $base_url = $_GET['base_url'];
                $status = $_GET['status'];
                $order = wc_get_order($order_id);

                if('failure' == $status) {
                    wp_safe_redirect(get_home_url() . "/checkout/order-pay/" . $order_id . "/?pay_for_order=true&key=" . $order->get_meta('_order_key'));
                    wc_add_notice('Payment failed.', 'error');
                    return;
                } elseif ('cancel' == $status) {
                    wp_safe_redirect(get_home_url() . "/checkout/order-pay/" . $order_id . "/?pay_for_order=true&key=" . $order->get_meta('_order_key'));
                    wc_add_notice('Payment cancelled', 'error');
                    return;
                }

                $order = wc_get_order($order_id);
                $bkash_token = json_decode($_SESSION['bkash_token']);
                $bkash_payment_info = json_decode($_SESSION['bkash_payment_info']);

                $url = curl_init($base_url . "/tokenized/checkout/execute");
                $header = array(
                    "Content-Type: application/json",
                    "Authorization: Bearer $bkash_token->id_token",
                    "X-APP-Key: $x_app_key",
                );

                $posttoken = array(
                    'paymentID' => $bkash_payment_info->paymentID
                );

                curl_setopt($url, CURLOPT_HTTPHEADER, $header);
                curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($url, CURLOPT_POSTFIELDS, json_encode($posttoken));
                curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                $resultdata = curl_exec($url);

                curl_close($url);
                $response = json_decode(curl_exec($url));
                curl_close($url);

                if("2062" === $response->statusCode) {
                    $order->update_status('processing', 'Order is now processing');
                    $order->save();
                } else {
                    $order->update_status('pending', 'Awaiting bKash payment confirmation.');
                    $order->save();
                }

                sleep(5);

                // Save transaction to database
                $query = new QueryPayment();
                $payment_data = $query->save_transaction($bkash_payment_info->paymentID, $bkash_token, $x_app_key, $base_url);

                if("Completed" === $payment_data->transactionStatus && "0000" === $payment_data->statusCode && "Completed" === $payment_data->transactionStatus && "Complete" === $payment_data->verificationStatus) {
                    wp_safe_redirect(get_home_url() . "/checkout/order-received/" . $order_id . "/?key=" . $order->get_meta('_order_key'));
                    exit;
                } else {
                    wp_safe_redirect(get_home_url() . "/payment-failed/");
                    exit;
                }
            }
        }
    }
