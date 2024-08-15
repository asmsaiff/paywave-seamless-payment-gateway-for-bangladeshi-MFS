<?php
class ProcessController extends Controller
{
    // protected $bkashbase_URL;
    // public function __construct() {
    //     $this->bkashbase_URL = "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout";//Sandbox
    //     $this->bkashbase_URL = "https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout"; //Live
    // }
    public static function process($deposit)
    {
        $alias = $deposit->gateway->alias;

        $val['track'] = $deposit->trx;
        $send['val'] = $val;
        $send['view'] = 'user.payment.'.$alias;
        $send['method'] = 'post';
        $send['url'] = route('ipn.'.$alias);
        return json_encode($send);
    }

    public function ipn(Request $request)
    {
        $deposit =
        $base_URL = "https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout";
        // dd($deposit);

        // Api Credentials
        $username = trim($bkashAcc->username);
        $password = trim($bkashAcc->password);
        $app_key = trim($bkashAcc->app_key);
        $app_secret_key = trim($bkashAcc->app_secret_key);

        if($request->isMethod('post')){


            try {
                if ($track!=$request->track) {
                    $notify[] = ['error', 'Invalid request track.'];
                    return to_route(gatewayRedirectUrl())->withNotify($notify);
                }


                if ($deposit->status == 1) {
                    $notify[] = ['error', 'Invalid request status.'];
                    return to_route(gatewayRedirectUrl())->withNotify($notify);
                }

                //create bKash Grant Token
                $request_data = array(
                    'app_key'=>trim($bkashAcc->app_key),
                    'app_secret'=>trim($bkashAcc->app_secret_key)
                );
                $url = curl_init($base_URL.'/token/grant');
                $request_data_json=json_encode($request_data);
                $header = array(
                    'Content-Type:application/json',
                    'username:'.trim($bkashAcc->username),
                    'password:'.trim($bkashAcc->password)
                );
                curl_setopt($url,CURLOPT_HTTPHEADER, $header);
                curl_setopt($url,CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($url,CURLOPT_RETURNTRANSFER, true);
                curl_setopt($url,CURLOPT_POSTFIELDS, $request_data_json);
                curl_setopt($url,CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                $response = curl_exec($url);
                curl_close($url);
                Session::put('bkash_token',$response);
            } catch (\Exception $e) {
                $notify[] = ['error', $e->getMessage()];
            }

            try {
                //bKash Create Payment
                $auth = json_decode($response)->id_token;

                // call back url
                $callbackURL = route('ipn.'.$deposit->gateway->alias);

                // invoice id
                $invoice_id = $deposit->invoice_id!=0? '_invoice_'.$deposit->invoice_id : '_deposit_'.$deposit->id;

                // Payment Details
                $requestbody = array(
                    'mode' => '0011',
                    'amount' => ceil(round($deposit->final_amo,2)),
                    'currency' => $deposit->method_currency,
                    'intent' => 'sale',
                    'payerReference' => $general->site_name,
                    'merchantInvoiceNumber' => preg_replace('/[^a-z0-9]+/', '_', strtolower($general->site_name)).$invoice_id,
                    'callbackURL' => $callbackURL
                );
                $url = curl_init($base_URL.'/create');
                $requestbodyJson = json_encode($requestbody);

                $header = array(
                    'Content-Type:application/json',
                    'Authorization:'. $auth,
                    'X-APP-Key:'.trim($bkashAcc->app_key)
                );

                curl_setopt($url, CURLOPT_HTTPHEADER, $header);
                curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
                curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                $resultdata = curl_exec($url);
                curl_close($url);
                // echo $resultdata;

                $obj = json_decode($resultdata);
                // dd($obj);
                if ($obj->statusMessage != "Successful") {

                    // Destroy all payment data if failed
                    Session::forget('Track');
                    Session::forget('bkash_token');

                    $notify[] = ['error', 'Invalid request.'];
                    return to_route(gatewayRedirectUrl())->withNotify($notify);
                }
                return redirect($obj->bkashURL);

            } catch (\Exception $e) {
                $notify[] = ['error', $e->getMessage()];
            }
        }

        if(isset($request->paymentID) && isset($request->status)){

             try {
                if($request->status=="cancel"){
                    // Destroy all payment data if failed
                    Session::forget('Track');
                    Session::forget('bkash_token');

                    $notify[] = ['error', 'Payment Request Cancle.'];
                    return to_route(gatewayRedirectUrl())->withNotify($notify);
                }
                elseif($request->status=="failure"){
                    // Destroy all payment data if failed
                    Session::forget('Track');
                    Session::forget('bkash_token');

                    $notify[] = ['error', 'It seems like there might be an issue. Please try again later.'];
                    return to_route(gatewayRedirectUrl())->withNotify($notify);
                }
                elseif($request->status=="success"){
                    try {
                        // Execute Payment
                        $paymentID = $request->paymentID;
                        $auth = json_decode(Session::get('bkash_token'))->id_token;
                        $post_token = array(
                            'paymentID' => $paymentID
                        );
                        $url = curl_init($base_URL.'/execute');
                        $posttoken = json_encode($post_token);
                        $header = array(
                            'Content-Type:application/json',
                            'Authorization:' . $auth,
                            'X-APP-Key:'.trim($bkashAcc->app_key)
                        );
                        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
                        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
                        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($url, CURLOPT_POSTFIELDS, $posttoken);
                        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
                        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                        $resultdata = curl_exec($url);
                        curl_close($url);
                        $obj = json_decode($resultdata);


                        // dd($obj);

                        // Set Deposit details
                        if(isset($obj->transactionStatus) && $obj->transactionStatus == "Completed" && isset($obj->trxID)){
                            $deposit->detail = json_decode($resultdata);
                            $deposit->trx = $obj->trxID;

                            //Update Deposit
                            PaymentController::userDataUpdate($deposit);
                            $notify[] = ['success', 'Transation Completed Successfully.'];
                            // Destroy all payment session
                            Session::forget('Track');
                            Session::forget('bkash_token');
                            return to_route(gatewayRedirectUrl(true))->withNotify($notify);
                        }
                        else{
                            $notify[] = ['error', 'It seems like there might be an issue. Please contact with support.'];
                            return to_route(gatewayRedirectUrl())->withNotify($notify);
                        }


                        // Query Payment
                        // $requestbody = array(
                        //     'paymentID' => $paymentID
                        // );
                        // $requestbodyJson = json_encode($requestbody);
                        // $url=curl_init($base_URL.'/payment/status');
                        // $header=array(
                        //     'Content-Type:application/json',
                        //     'authorization:'.$auth,
                        //     'x-app-key:'.trim($bkashAcc->app_key)
                        // );
                        // curl_setopt($url,CURLOPT_HTTPHEADER, $header);
                        // curl_setopt($url,CURLOPT_CUSTOMREQUEST, "POST");
                        // curl_setopt($url,CURLOPT_RETURNTRANSFER, true);
                        // curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
                        // curl_setopt($url,CURLOPT_FOLLOWLOCATION, 1);
                        // $resultdatax=curl_exec($url);
                        // curl_close($url);
                        // $obj = json_decode($resultdatax);

                        // dd($obj);

                        // // error here -------------------------------------------------------------------------------------
                        // if( isset($obj->transactionStatus) && $obj->transactionStatus == "Completed"){
                            // Update user data
                            // PaymentController::userDataUpdate($deposit);
                            // $notify[] = ['success', 'Transation Completed Successfully.'];

                            // // Destroy all payment session
                            // Session::forget('Track');
                            // Session::forget('bkash_token');
                            // return to_route(gatewayRedirectUrl(true))->withNotify($notify);
                        // }
                        // else{
                        //     $notify[] = ['error', 'It seems like there might be an issue. Please try again later.'];
                        //     return to_route(gatewayRedirectUrl())->withNotify($notify);
                        // }
                    } catch (\Exception $e) {
                        $notify[] = ['error', $e->getMessage()];
                    }
                }
                else{

                    // Destroy all payment data if failed
                    Session::forget('Track');
                    Session::forget('bkash_token');

                    $notify[] = ['error', 'It seems like there might be an issue. Please try again later.'];
                    return to_route(gatewayRedirectUrl())->withNotify($notify);
                }
            } catch (\Exception $e) {
                $notify[] = ['error', $e->getMessage()];
            }
        }

        $notify[] = ['error', 'It seems like there might be an issue. Please try again later or contact support for assistance.'];
        return to_route(gatewayRedirectUrl())->withNotify($notify);
    }

    public function query($deposit){

    }

    public static function search($deposit)
    {
        $bkashAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $base_URL = "https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout";
        // Api Credentials
        $username = trim($bkashAcc->username);
        $password = trim($bkashAcc->password);
        $app_key = trim($bkashAcc->app_key);
        $app_secret_key = trim($bkashAcc->app_secret_key);

        try {
            if ($deposit->status == 1) {
                $notify = ['error'=>'Invalid request status.'];
                return $notify;
            }

            // create bKash Grant Token---------------------------------------------------
            $request_data = array(
                'app_key'=>trim($bkashAcc->app_key),
                'app_secret'=>trim($bkashAcc->app_secret_key)
            );
            $url = curl_init($base_URL.'/token/grant');
            $request_data_json=json_encode($request_data);
            $header = array(
                'Content-Type:application/json',
                'username:'.trim($bkashAcc->username),
                'password:'.trim($bkashAcc->password)
            );
            curl_setopt($url,CURLOPT_HTTPHEADER, $header);
            curl_setopt($url,CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($url,CURLOPT_RETURNTRANSFER, true);
            curl_setopt($url,CURLOPT_POSTFIELDS, $request_data_json);
            curl_setopt($url,CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $response = curl_exec($url);
            curl_close($url);
            $auth = json_decode($response)->id_token;


            // Search Payment-------------------------------------------------------------------------
            $requestbody = array(
                'trxID' => $deposit->trx
            );
            $requestbodyJson = json_encode($requestbody);
            $url=curl_init($base_URL.'/general/searchTransaction');
            $header=array(
                'Content-Type:application/json',
                'authorization:'.$auth,
                'x-app-key:'.$app_key
            );

    		curl_setopt($url,CURLOPT_HTTPHEADER, $header);
    		curl_setopt($url,CURLOPT_CUSTOMREQUEST, "POST");
    		curl_setopt($url,CURLOPT_RETURNTRANSFER, true);
    		curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
    		curl_setopt($url,CURLOPT_FOLLOWLOCATION, 1);
    		$resultdatax=curl_exec($url);
    		curl_close($url);
            $obj = json_decode($resultdatax);

            // dd($obj);
            // update user data -------------------------------------------------------------------------------------
            if( isset($obj->transactionStatus) && $obj->transactionStatus == "Completed"){
                // Update user data
                $deposit->detail = json_decode($resultdatax);
                $deposit->trx = $obj->trxID;
                PaymentController::userDataUpdate($deposit);
                $notify = ['success'=>'Transation Completed Successfully.'];
                return $notify;
            }
            else{
                $deposit->detail = json_encode(['TransationApprovedBy'=>auth()->user()->username,'ApprovedAdminId'=>auth()->user()->id,'ApprovedAdminName'=>auth()->user()->name]);
                $deposit->trx = $obj->trxID;
                PaymentController::userDataUpdate($deposit);
                $notify = ['success' => 'Transation Completed Successfully.'];
                return $notify;
            }
        } catch (\Exception $e) {
            $notify = ['error' => $e->getMessage()];
        }


        $notify = ['error' => 'It seems like there might be an issue. Please try again later or contact support for assistance.'];
        return $notify;
    }
}
