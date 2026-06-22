<?php
/**
 * Paydiver payment gateway for WHMCS — by Kodbee (https://kodbee.com).
 *
 * Install: copy the `modules/gateways/paydiver.php` and
 * `modules/gateways/callback/paydiver.php` files into your WHMCS installation,
 * then activate "Paydiver" under Setup → Payments → Payment Gateways.
 *
 * @package Paydiver\WHMCS
 */

if (! defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * @return array<string,mixed>
 */
function paydiver_MetaData()
{
    return [
        'DisplayName' => 'Paydiver',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * @return array<string,array<string,string|bool>>
 */
function paydiver_config()
{
    return [
        'FriendlyName' => ['Type' => 'System', 'Value' => 'Paydiver'],
        'base_url' => [
            'FriendlyName' => 'Base URL',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'https://pay.kodbee.com',
            'Description' => 'Your Paydiver instance URL.',
        ],
        'api_key' => ['FriendlyName' => 'API Key', 'Type' => 'text', 'Size' => '40'],
        'secret_key' => ['FriendlyName' => 'Secret Key', 'Type' => 'password', 'Size' => '40'],
        'webhook_secret' => [
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'password',
            'Size' => '40',
            'Description' => 'Leave blank to use the Secret Key.',
        ],
    ];
}

/**
 * Render the payment button: creates a Paydiver invoice and links to the
 * hosted payment page.
 *
 * @param array<string,mixed> $params
 */
function paydiver_link($params)
{
    paydiver_ensure_table();

    $base = rtrim((string) $params['base_url'], '/');
    $callback = rtrim((string) $params['systemurl'], '/') . '/modules/gateways/callback/paydiver.php';

    $payload = [
        'amount' => (float) $params['amount'],
        'product_name' => 'Invoice #' . $params['invoiceid'],
        'customer_name' => trim(($params['clientdetails']['firstname'] ?? '') . ' ' . ($params['clientdetails']['lastname'] ?? '')),
        'customer_email' => $params['clientdetails']['email'] ?? null,
        'redirect_url' => $params['returnurl'] ?? null,
        'callback_url' => $callback,
    ];

    $response = paydiver_http(
        $base . '/api/v1/payment/create',
        $payload,
        (string) $params['api_key'],
        (string) $params['secret_key']
    );

    if (! $response || empty($response['data']['payment_url']) || empty($response['data']['invoice_id'])) {
        return '<div style="color:#c00">' . htmlspecialchars($params['langpaynow'] ?? 'Payment is temporarily unavailable.') . '</div>';
    }

    Capsule::table('mod_paydiver_map')->updateOrInsert(
        ['paydiver_invoice' => $response['data']['invoice_id']],
        ['whmcs_invoice' => (int) $params['invoiceid'], 'created_at' => date('Y-m-d H:i:s')]
    );

    $label = htmlspecialchars($params['langpaynow'] ?? 'Pay Now');
    $url = htmlspecialchars((string) $response['data']['payment_url']);

    return '<a class="btn btn-primary" href="' . $url . '">' . $label . '</a>';
}

/** Lazily create the paydiver → WHMCS invoice mapping table. */
function paydiver_ensure_table(): void
{
    if (Capsule::schema()->hasTable('mod_paydiver_map')) {
        return;
    }

    Capsule::schema()->create('mod_paydiver_map', function ($table): void {
        $table->string('paydiver_invoice')->primary();
        $table->integer('whmcs_invoice')->index();
        $table->string('created_at')->nullable();
    });
}

/**
 * Minimal JSON POST helper.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>|null
 */
function paydiver_http(string $url, array $payload, string $apiKey, string $secretKey)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
            'X-Secret-Key: ' . $secretKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $raw = curl_exec($ch);
    curl_close($ch);

    if ($raw === false) {
        return null;
    }

    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : null;
}
