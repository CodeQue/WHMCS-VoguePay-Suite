<?php
/**
 * WHMCS Merchant Gateway 3D Secure Callback File
 *
 * The purpose of this file is to demonstrate how to handle the return post
 * from a 3D Secure Authentication process.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * Users are expected to be redirected to this file as part of the 3D checkout
 * flow so it also demonstrates redirection to the invoice upon completion.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

//get the gateway parameters needed
$voguepay_id = $gatewayParams['voguepay_id'];
$voguepay_email = $gatewayParams['voguepay_email'];
$voguepay_token = $gatewayParams['voguepay_token'];
$voguepay_demo = $gatewayParams['voguepay_demo'];

$url = 'https://voguepay.com/api/';
$operation = 'query';
$reference = time().mt_rand(0,9999999);
$hash = hash('sha512', $gatewayParams['voguepay_token']. $operation.$gatewayParams['voguepay_email'].$reference);

$voguepay_array = array (
    "task" : $operation,
    "merchant" : $gatewayParams['voguepay_id'],
    "ref" : $reference,
    "hash" : $hash,
    "demo" : ($gatewayParams['voguepay_demo'] == 'yes') ? true : false,
    "transaction_id" : $_POST["transaction_id"],
);
//json encode and send details to the gateway
$voguepay_string = 'json='.urlencode(json_encode($voguepay_array));

//open curl connection
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $api);
curl_setopt($ch,CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch,CURLOPT_POSTFIELDS, $voguepay_string);
curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
curl_setopt($ch,CURLOPT_MAXREDIRS,2);
$voguepay_response = curl_exec($ch);
curl_close($ch);
//Result is json string so we convert into array
$voguepay_response = substr($voguepay_response, 3);
$voguepay_response = json_decode($voguepay_response,true);
// confirm transaction hash
$received_hash = $voguepy_response['hash'];
$expected_hash = hash('sha512',$gatewayParams['voguepay_token'].$gatewayParams['voguepay_email'].$voguepay_response['salt']);
if($received_hash != $expected_hash){
    //transaction is either not from voguepay or manipulated
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
}


// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$success = $_POST["x_status"];
$invoiceId = $_POST["x_invoice_id"];
$transactionId = $_POST["x_trans_id"];
$paymentAmount = $_POST["x_amount"];
$paymentFee = $_POST["x_fee"];
$hash = $_POST["x_hash"];

$transactionStatus = $success ? 'Success' : 'Failure';


/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($voguepay_response['merchant_ref'], $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($voguepay_response['transaction_id']);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $voguepay_response, $voguepay_response['response_message']);

$paymentSuccess = false;

if ($voguepay_response['merchant_id'] == $gatewayParams['voguepay_id'] && $voguepay_response['status'] == 'Approved') {

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    $paymentFee = '';
    addInvoicePayment(
        $voguepay_response['merchant_ref'],
        $voguepay_response['transaction_id'],
        $voguepay_response['total_amount'],
        $paymentFee,
        $gatewayModuleName
    );

    $paymentSuccess = true;

}

/**
 * Redirect to invoice.
 *
 * Performs redirect back to the invoice upon completion of the 3D Secure
 * process displaying the transaction result along with the invoice.
 *
 * @param int $invoiceId        Invoice ID
 * @param bool $paymentSuccess  Payment status
 */
callback3DSecureRedirect($invoiceId, $paymentSuccess);
