<?php
/**
 * Jomabee webhook/callback handler for WHMCS — by Kodbee.
 *
 * Verifies the HMAC-SHA256 signature over the raw request body, then applies
 * the payment to the matching WHMCS invoice.
 *
 * @package Jomabee\WHMCS
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

$gatewayModuleName = 'jomabee';
$gateway = getGatewayVariables($gatewayModuleName);

if (! $gateway['type']) {
    http_response_code(503);
    die('Module Not Activated');
}

$raw = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_JOMABEE_SIGNATURE'] ?? '';
$secret = $gateway['webhook_secret'] !== '' ? $gateway['webhook_secret'] : $gateway['secret_key'];

if ($secret === '' || ! hash_equals(hash_hmac('sha256', $raw, $secret), (string) $signature)) {
    http_response_code(400);
    die('invalid signature');
}

$event = json_decode($raw, true);

if (! is_array($event) || ($event['event'] ?? '') !== 'payment.verified') {
    http_response_code(200);
    die('ignored');
}

$jomabeeInvoice = (string) ($event['invoice_id'] ?? '');
$transId = (string) ($event['trx_id'] ?? '');
$amount = (float) ($event['amount'] ?? 0);

$map = Capsule::table('mod_jomabee_map')->where('jomabee_invoice', $jomabeeInvoice)->first();

if (! $map) {
    http_response_code(404);
    die('invoice not mapped');
}

$invoiceId = checkCbInvoiceID((int) $map->whmcs_invoice, $gatewayModuleName);
checkCbTransID($transId);

addInvoicePayment($invoiceId, $transId, $amount, 0, $gatewayModuleName);
logTransaction($gateway['name'], $event, 'Success');

http_response_code(200);
echo 'ok';
