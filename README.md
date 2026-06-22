# Paydiver for WHMCS

WHMCS payment gateway module for the [Paydiver](https://kodbee.com) payment API
by **Kodbee**. Accept bKash, Nagad, Rocket and Upay; invoices are settled
automatically through a signature-verified webhook.

## Install

1. Copy the files into your WHMCS root, keeping the structure:
   - `modules/gateways/paydiver.php`
   - `modules/gateways/callback/paydiver.php`
2. **Setup → Payments → Payment Gateways → All Payment Gateways → Paydiver** → Activate.
3. Fill in **Base URL**, **API Key**, **Secret Key** and (optionally) **Webhook Secret**.

## How it works

1. On the invoice page the module calls `POST /api/v1/payment/create` and shows a
   **Pay Now** button to the hosted Paydiver payment page.
2. A `mod_paydiver_map` table maps each Paydiver invoice to its WHMCS invoice
   (created automatically on first use).
3. Paydiver posts a webhook to `modules/gateways/callback/paydiver.php`; the module
   verifies the `X-Paydiver-Signature` over the raw body and calls
   `addInvoicePayment()` for the mapped invoice.

## License

MIT © [Kodbee](https://kodbee.com)
