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
use WHMCS\Database\Capsule;
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
// Retrieve data returned in payment gateway callback
// Varies per payment gateway
//transaction id from gateway
$transactionId = $_POST["transaction_id"];

//set parameters needed to connect to gateway
$voguepay_id = $gatewayParams['voguepay_id'];
$voguepay_email = $gatewayParams['voguepay_email'];
$voguepay_token = $gatewayParams['voguepay_token'];
$voguepay_demo = $gatewayParams['voguepay_demo'];

$voguepay_url = 'https://voguepay.com/api/';
$voguepay_operation = 'query';
$voguepay_reference = time().mt_rand(0,9999999);
$voguepay_hash = hash('sha512', $voguepay_token. $voguepay_operation.$voguepay_email.$voguepay_reference);

$voguepay_array = array (
    "task" => $voguepay_operation,
    "merchant" => $voguepay_id,
    "ref" => $voguepay_reference,
    "hash" => $voguepay_hash,
    "demo" => ($gatewayParams['voguepay_demo'] == 'on') ? true : false,
    "transaction_id" => $transactionId
);

//json encode and send details to the gateway
$voguepay_string = 'json='.urlencode(json_encode($voguepay_array));

//open curl connection
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $voguepay_url);
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
// split the merchant ref, it contains both the invoice id and the user id
$split_reference = explode("##", $voguepay_response['transaction']['merchant_ref']);

$success = ($voguepay_response['transaction']['status'] == "Approved") ? true : false;
$invoiceId = $split_reference[0];
$paymentAmount = $voguepay_response['transaction']['total'];
$paymentFee = $voguepay_response['transaction']['charges_paid_by_merchant'];
$hash = $voguepay_response['hash'];
$transactionStatus = $success ? 'Success' : 'Failure';
/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
$expected_hash = hash('sha512',$voguepay_token.$voguepay_email.$voguepay_response['salt']);
if($hash != $expected_hash){
    //transaction is either not from voguepay or manipulated
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
}
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
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

//check invoice status
// if status of invoice is paid
// redirect to invoice to show status
$command = 'GetInvoice';
$postData = array(
    'invoiceid' => $invoiceId,
);
$adminUsername = ''; // Optional for WHMCS 7.2 and later
// get invoice details
$invoice_details = localAPI($command, $postData, $adminUsername);
if ($invoice_details['status'] == "Paid") $paymentSuccess = true;
else{
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
    checkCbTransID($transactionId);
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
    logTransaction($gatewayParams['name'], $voguepay_response, $transactionStatus);
    $paymentSuccess = false;
    //check demo for merchant id
    $voguepay_id = ($gatewayParams['voguepay_demo'] == 'on') ? "demo" : $voguepay_id;
    if ($success && $voguepay_response['transaction']['merchant_id'] == $voguepay_id && $voguepay_response['transaction']['status'] == 'Approved') {
        
        //check if token is returned
        // if returned then update the token in the clients table
        if (!empty($voguepay_response['transaction']['token'])) {
            Capsule::table('tblclients')
            ->where('id', $split_reference[1])
            ->update(
                [
                    'gatewayid' => $voguepay_response['transaction']['token'],
                ]
            );
        }
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
        //check if invoice is approved already
        // if not the approve it
        
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            $paymentFee,
            $gatewayModuleName
        );
        $paymentSuccess = true;
    } else {
        // get client details
        $command = 'GetClientsDetails';
        $postData = array(
            'clientid' => $split_reference[1],
            'stats' => true,
        );
        $adminUsername = ''; // Optional for WHMCS 7.2 and later
        $client = localAPI($command, $postData, $adminUsername);
        //clear the card details so the card can be re-entered
        if (empty($client['gatewayid'])){
            Capsule::table('tblclients')
            ->where('id', $split_reference[1])
            ->update(
                [
                    'gatewayid' => '',
                    'cardtype' => '',
                    'startdate' => '',
                    'expdate' => '',
                    'issuenumber' => '',
                    'cardlastfour' => ''
                ]
            );
        }
    }
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