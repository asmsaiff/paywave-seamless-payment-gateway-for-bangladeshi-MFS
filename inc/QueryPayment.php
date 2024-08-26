<?php
    namespace PayWave;

    class QueryPayment {
        public function save_transaction($payment_id, $bkash_token, $x_app_key, $base_url) {
            global $wpdb;

            // Table Name
            $table_name = $wpdb->prefix . "transactions";
            $charset_collate = $wpdb->get_charset_collate();

            // Create Table
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                statusCode text NOT NULL,
                statusMessage text NOT NULL,
                paymentID text NOT NULL,
                mode text NOT NULL,
                paymentCreateTime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                amount float NOT NULL,
                currency varchar(20) NOT NULL,
                intent varchar(20) NOT NULL,
                merchantInvoice text NOT NULL,
                transactionStatus text NOT NULL,
                verificationStatus text NOT NULL,
                payerReference text NOT NULL,
                agreementID text NOT NULL,
                agreementStatus text NOT NULL,
                agreementCreateTime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                agreementExecuteTime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            // Query Request
            $requestbody = array(
                'paymentID' => $payment_id
            );
            $requestbodyJson = json_encode($requestbody);

            // Curl Request
            $url = curl_init($base_url . '/tokenized/checkout/payment/status');
            $header = array(
                'Content-Type:application/json',
                'authorization:' . $bkash_token->id_token,
                'x-app-key:' . $x_app_key
            );

            curl_setopt($url, CURLOPT_HTTPHEADER, $header);
            curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
            curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
            $resultdatax = curl_exec($url);
            curl_close($url);

            // Decode the response from bKash API
            $obj = json_decode($resultdatax);

            if (!empty($obj)) {
                // Insert data into the transactions table
                $wpdb->insert(
                    $table_name,
                    array(
                        'statusCode' => isset($obj->statusCode) ? $obj->statusCode : '',
                        'statusMessage' => isset($obj->statusMessage) ? $obj->statusMessage : '',
                        'paymentID' => isset($obj->paymentID) ? $obj->paymentID : '',
                        'mode' => isset($obj->mode) ? $obj->mode : '',
                        'paymentCreateTime' => isset($obj->paymentCreateTime) ? date('Y-m-d H:i:s', strtotime($obj->paymentCreateTime)) : current_time('mysql'),
                        'amount' => isset($obj->amount) ? $obj->amount : 0,
                        'currency' => isset($obj->currency) ? $obj->currency : '',
                        'intent' => isset($obj->intent) ? $obj->intent : '',
                        'merchantInvoice' => isset($obj->merchantInvoiceNumber) ? $obj->merchantInvoiceNumber : '',
                        'transactionStatus' => isset($obj->transactionStatus) ? $obj->transactionStatus : '',
                        'verificationStatus' => isset($obj->verificationStatus) ? $obj->verificationStatus : '',
                        'payerReference' => isset($obj->payerReference) ? $obj->payerReference : '',
                        'agreementID' => isset($obj->agreementID) ? $obj->agreementID : '',
                        'agreementStatus' => isset($obj->agreementStatus) ? $obj->agreementStatus : '',
                        'agreementCreateTime' => isset($obj->agreementCreateTime) ? date('Y-m-d H:i:s', strtotime($obj->agreementCreateTime)) : current_time('mysql'),
                        'agreementExecuteTime' => isset($obj->agreementExecuteTime) ? date('Y-m-d H:i:s', strtotime($obj->agreementExecuteTime)) : current_time('mysql'),
                    ),
                    array(
                        '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                    )
                );
            }

            unset($_SESSION['bkash_token'], $_SESSION['bkash_payment_info']);
            // Return the decoded object (optional)
            return $obj;
        }
    }
