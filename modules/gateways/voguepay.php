<?php

/**
 * WHMCS Sample Merchant Gateway Module
 *
 * This sample file demonstrates how a merchant gateway module supporting
 * 3D Secure Authentication, Captures and Refunds can be structured.
 *
 * If your merchant gateway does not support 3D Secure Authentication, you can
 * simply omit that function and the callback file from your own module.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "merchantgateway" and therefore all functions
 * begin "merchantgateway_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function merchantgateway_MetaData()
{
    return array(
        'DisplayName' => 'VoguePay Credit Card Payment',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => false,
        'TokenisedStorage' => true,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */
function voguepay_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'VoguePay Debit / Credit Card Payment',
        ),
        // Merchant ID from VoguePay
        'voguepay_id' => array(
            'FriendlyName' => 'VoguePay Merchant ID',
            'Type' => 'text',
            'Size' => '15',
            'Default' => 'demo',
            'Description' => 'Enter your Merchant ID here',
        ),
        // Merchant email from VoguePay
        'voguepay_email' => array(
            'FriendlyName' => 'VoguePay Email',
            'Type' => 'text',
            'Default' => 'email@usedonvoguepay.com',
            'Description' => 'Email used for VoguePay account',
        ),
        // a password field type allows for masked text input
        'voguepay_token' => array(
            'FriendlyName' => 'VoguePay Token',
            'Type' => 'password',
            'Default' => '',
            'Description' => 'VoguePay command API',
        ),
        // Enabling demo mode
        'voguepay_demo' => array(
            'FriendlyName' => 'Enable Demo Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable Demo mode',
        ),
    );
}

/**
 * Perform 3D Authentication.
 *
 * Called upon checkout using a credit card.
 *
 * Optional: Exclude this function if your merchant gateway does not support
 * 3D Secure Authentication.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/3d-secure/
 *
 * @return string 3D Secure Form
 */
function voguepay_3dsecure($params)
{
    // Gateway Configuration Parameters
    $voguepay_id = $params['voguepay_id'];
    $voguepay_email = $params['voguepay_email'];
    $voguepay_token = $params['voguepay_token'];
    $voguepay_demo = $params['voguepay_demo'];

    // Invoice Parameters
    //unique invoice identification
    $whmcs_invoice = $params['invoiceid'];
    //invoice description
    $whmcs_description = $params["description"];
    //invoice amount to be paid
    $whmcs_amount = $params['amount'];
    //invoicing currency
    // supports gateways currencies USD, EUR, GBP, NGN, ZAR, KES
    $whmcs_currency = $params['currency'];

    // Credit Card Parameters
    //card type not needed by gateway
    //$cardType = $params['cardtype'];
    $card_number = $params['cardnum'];
    $card_expiry = $params['cardexp'];
    //card start not needed by gateway
   // $cardStart = $params['cardstart'];
   //issue number not needed by gateway
    //$cardIssueNumber = $params['cardissuenum'];
    $card_ccv = $params['cccvv'];

    // Client Parameters
    $card_holder_fullname = $params['clientdetails']['firstname'] . ' '. $params['clientdetails']['lastname'];
    $card_holder_email = $params['clientdetails']['email'];
    $card_holder_address = $params['clientdetails']['address1'] .', '.$params['clientdetails']['address2'] .', '.$params['clientdetails']['city'] .', '.$params['clientdetails']['state'] .', '.$params['clientdetails']['postcode'] .', '.$params['clientdetails']['country'];
    $card_holder_phone = $params['clientdetails']['phonenumber'];

    //other details needed by the gateway
    // the originating site url
    $origin_url = $params['systemurl'];
    $callback_url = $params['systemurl'] . 'modules/gateways/callback/' . $params['paymentmethod'] . '.php';

    // compile parameters and send request to gateway
    $url = 'https://voguepay.com/api/';
    $voguepay_url = 'https://voguepay.com/api/';
    $operation = 'card';
    $identification = $voguepay_id;

    // not needed, referenced as $voguepay_email
    //$merchant_email_on_voguepay = 'accountemail@gmail.com';
    //set a random reference
    $reference = time().mt_rand(0,9999999);
    //not needed referenced as $voguepay_token
    //$command_api_token = 'sdjhsdjysd78df6sdhjsdgfdhdfs';
    $voguepay_hash = hash('sha512',$voguepay_token.$operation.$voguepay_email.$reference);

    //defined gateway arrays
    $voguepay_array = array(
        "task" => $operation,
        "merchant" => $identification,
        "ref" => $reference,
        "hash" => $voguepay_hash,
        "total" => $whmcs_amount,
        "email" => $card_holder_email,
        "merchant_ref" => $whmcs_invoice,
        "currency" => $whmcs_currency,
        "memo" => $whmcs_description,
        "referral_url" => $origin_url,
        "response_url" => $callback_url,
        "redirect_url" => $callback_url,
        "demo" => ($voguepay_demo == 'on') ? true : false,
        "card" => array (
            "name" => $card_holder_fullname,
            "pan" => $card_number,
            "month" => substr($card_expiry, 0, 2),
            "year" => substr($card_expiry, -2),
            "cvv" => $card_ccv
        ),
        "phone" => $card_holder_phone,
        "address" => $card_holder_address,
        "developer_code" => "5c654d119982a"
    );

    //json encode array details
    $voguepay_string = 'json='.urlencode(json_encode($voguepay_array));

    // initiate connection to gateway
    //open curl connection
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $voguepay_string);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
    curl_setopt($ch,CURLOPT_MAXREDIRS,2);
    $voguepay_response = curl_exec($ch);//execute post
    curl_close($ch);//close connection

    // Process response recived from gateway
    // validate response from gateway
    $json_response = substr($voguepay_response, 3);
    $json_response = json_decode($json_response, true);
    // compare the hash received
    $received_hash = $json_response['hash'];
    // form a new hash using the previous set details
    $expected_hash = hash('sha512',$voguepay_token.$voguepay_email.$json_response['salt']);
    if ($received_hash != $expected_hash) {
        $htmlOutput = "<div class='alert alert-success text-center' role='alert'>
                        <strong>
                            <i class='fas fa-times-circle'></i>
                            Unable to process payment request. Please try again later. If the issue persists, kindly contact administrator.
                        </strong>
                    </div>";
    } else if ($json_response['status'] == "OK") {
        // confirm if ssl is active for website
        // if ssl is active, load payment form in an iframe
        // if not active, redirect 
        $activeSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
        if ($activeSSL) {
            $htmlOutput = '<form method="post" action="'. $json_response['redirect_url'] .'">';
            $htmlOutput .= '<input type="hidden" name="voguepay" value="" />';
            $htmlOutput .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
            $htmlOutput .= '</form>';
            return $htmlOutput;
        } else {
            header("Location: ".$json_response['redirect_url']);
        }
    } else {
        $htmlOutput = "<div class='alert alert-success text-center' role='alert'>
                        <strong>
                            <i class='fas fa-times-circle'></i>
                            Unable to process payment request. Error received - {$json_response['message']} - {{$json_response['response']}}
                        </strong>
                    </div>";
    }
    echo $htmlOutput;
}

/**
 * Capture payment.
 *
 * Called when a payment is to be processed and captured.
 *
 * The card cvv number will only be present for the initial card holder present
 * transactions. Automated recurring capture attempts will not provide it.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/merchant-gateway/
 *
 * @return array Transaction response status
 */
function merchantgateway_capture($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Credit Card Parameters
    $cardType = $params['cardtype'];
    $cardNumber = $params['cardnum'];
    $cardExpiry = $params['cardexp'];
    $cardStart = $params['cardstart'];
    $cardIssueNumber = $params['cardissuenum'];
    $cardCvv = $params['cccvv'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to capture payment and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the capture transaction
        'transid' => $transactionId,
        // Optional fee amount for the fee value refunded
        'fee' => $feeAmount,
    );
}


/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function merchantgateway_refund($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fee' => $feeAmount,
    );
}
