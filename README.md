# Jomabee for WHMCS

WHMCS payment gateway module for the [Jomabee](https://kodbee.com) payment API
by **Kodbee**. Accept bKash, Nagad, Rocket and Upay; invoices are settled
automatically through a signature-verified webhook.

## Install

1. Copy the files into your WHMCS root, keeping the structure:
   - `modules/gateways/jomabee.php`
   - `modules/gateways/callback/jomabee.php`
2. **Setup → Payments → Payment Gateways → All Payment Gateways → Jomabee** → Activate.
3. Fill in **Base URL**, **API Key**, **Secret Key** and (optionally) **Webhook Secret**.

## How it works

1. On the invoice page the module calls `POST /api/v1/payment/create` and shows a
   **Pay Now** button to the hosted Jomabee payment page.
2. A `mod_jomabee_map` table maps each Jomabee invoice to its WHMCS invoice
   (created automatically on first use).
3. Jomabee posts a webhook to `modules/gateways/callback/jomabee.php`; the module
   verifies the `X-Jomabee-Signature` over the raw body and calls
   `addInvoicePayment()` for the mapped invoice.

## License

MIT © [Kodbee](https://kodbee.com)
