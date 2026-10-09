=== CTCL Stripe ===
Contributors: ujw0l
Tags: stripe, payment gateway, ecommerce, checkout, ct commerce lite
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 7.4
Requires Plugins: ctc-lite
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Stripe payments in CT Commerce Lite with a polished Payment Element, verified checkout totals and reliable order confirmation.

== Description ==

CTCL Stripe adds Stripe payments to the existing CT Commerce Lite checkout. Customers keep the store's cart, contact details, shipping options and order confirmation page, with a modern Stripe Payment Element for secure payment and any required authentication.

The settings panel includes separate test and live credentials, masked API keys, a custom payment method label, a checkout appearance preview and webhook setup instructions.

Features:

* Modern Checkout Sessions and Payment Element replace legacy card tokens and Charges.
* Payment methods are managed in the Stripe Dashboard; Stripe shows eligible methods for each checkout.
* Server-side validation uses CT Commerce Lite's published products, quantities, variations, shipping, tax and coupon rules.
* The Stripe Session charges the exact validated CT Commerce Lite grand total.
* Signed webhooks and browser returns share one fulfillment handler, preventing duplicate orders and notifications.
* Asynchronous payments create orders only after Stripe confirms payment.
* Existing CT Commerce Lite order records, shipping hooks, email, SMS and WhatsApp integrations remain available.
* Accessible loading, validation and payment error messages.
* Settings survive deactivation. Existing test/live API keys and label settings are preserved on upgrade.
* Stripe PHP SDK 22.0.0 and API version 2026-09-30.endive.

Requires CT Commerce Lite 2.8.3 or later with its server-side checkout validation class. Three-decimal currencies are not supported. Zero-decimal currencies require a whole-number grand total.

== Installation ==

1. Install and activate CT Commerce Lite, then install and activate CTCL Stripe.
2. Open CT Commerce Lite > Billing > Stripe (or use the plugin's Settings link).
3. Enable Stripe, choose your payment method label and start in Test mode.
4. Enter the test publishable key and a restricted API key with Checkout Sessions and PaymentIntents read/write permissions. Existing secret keys are also supported. Use a separate Stripe sandbox for development where possible.
5. Configure a webhook in Stripe pointing to the endpoint shown in the settings panel. Subscribe to checkout.session.completed, checkout.session.async_payment_succeeded and checkout.session.async_payment_failed, then add that environment's signing secret.
6. Ensure the CT Commerce Lite checkout block points to a published page containing its order-processing block.
7. Add a product, continue through CT Commerce Lite's checkout, fill contact details and select shipping and Stripe. Choose Continue to secure payment, then complete the Payment Element and place your order.
8. Test successful payments, declines and authentication. Before accepting real payments, add live credentials and the live webhook signing secret, serve the store over HTTPS, and turn off Test mode.

For localhost webhooks, use the Stripe CLI's forwarding feature. Stripe cannot directly deliver webhooks to a localhost URL. Copy the CLI signing secret into the test webhook signing secret field. Webhooks are required for reliable fulfillment and delayed payment methods; the browser return is an additional confirmation path.

== Upgrading from 1.x ==

Existing activation, test mode, publishable/secret key and payment label options are retained. Add webhook signing secrets for each environment. New payments use Checkout Sessions; posted legacy stripe_token values are no longer accepted.

CTCL Stripe stores a temporary validated checkout snapshot to recover paid orders when the customer leaves the page. The duplicate snapshot is cleared after fulfillment. Checkout records older than 30 days are removed by WP-Cron; completed orders remain in CT Commerce Lite's order table.

== Frequently Asked Questions ==

= Does this replace CT Commerce Lite's checkout? =

No. The addon uses the existing cart, contact fields, shipping options, published product pricing and order-processing page. Stripe handles the payment fields and authentication.

= Which payment methods can customers use? =

Enable methods in your Stripe Dashboard. Stripe determines eligibility based on your account, currency, customer and integration. Wallets may also require HTTPS and payment-method domain registration.

= Why do I need a webhook? =

A customer can close the browser after paying. Webhooks let Stripe confirm the payment and place the CT Commerce Lite order independently. A delayed payment method must succeed before an order is created.

= Will refreshing the confirmation page create another order? =

No. Stripe Session creation uses an idempotency key. Browser returns and webhook events use a shared database claim and CT Commerce Lite's unique order ID to avoid duplicate orders.

= Does Stripe calculate my store's tax and shipping? =

This addon charges CT Commerce Lite's validated total, including its shipping, tax and discounts. It does not enable Stripe Tax or alter the store's tax settings.

= Where are secret keys stored? =

Keys are saved in WordPress options and masked in the administrator interface. Server keys and webhook secrets are never sent to the storefront. Protect database backups and administrator access, and use restricted API keys with minimum permissions.

== External services ==

This plugin connects to Stripe to process payments.

Stripe.js is loaded from js.stripe.com on storefront pages while Stripe is enabled and configured. Payment details entered in the Payment Element are sent directly to Stripe. When a customer continues to secure payment, the server sends Stripe the validated total, currency, customer email, generated order reference and checkout metadata. Card details do not pass through WordPress.

Stripe sends signed payment events to the configured webhook endpoint to confirm orders. Use of Stripe is subject to its terms and privacy policy.

* Service: https://stripe.com/
* Terms: https://stripe.com/legal/ssa
* Privacy: https://stripe.com/privacy

== Screenshots ==

1. Stripe settings with checkout customization, masked test/live credentials and appearance preview.
2. Stripe's modern Payment Element inside CT Commerce Lite's checkout.
3. Webhook configuration and signing-secret fields for reliable order confirmation.

== Changelog ==

= 2.0.0 =
* Keep order IDs compatible with CT Commerce Lite dates; repair early development IDs while preserving Stripe references.
* Replace legacy card token and Charges integration with Checkout Sessions and Payment Element.
* Validate totals through CT Commerce Lite before starting payment.
* Add signed webhook fulfillment, asynchronous payment handling and duplicate order protection.
* Preserve CT Commerce Lite order storage, shipping and notification hooks.
* Redesign settings and checkout with responsive layouts, previews and accessible status messages.
* Mask and escape credential fields, add settings sanitization and correct field labels and IDs.
* Preserve settings on deactivation; separate test/live webhook secrets.
* Update Stripe PHP SDK to 22.0.0 and API to 2026-09-30.endive.
* Refresh screenshots and confirm WordPress 7.1.3 compatibility.

= 1.2.2 =
* Local maintenance update for single-payment-option checkout display.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Modern Stripe checkout. Existing API keys are retained. Add webhook signing secrets, verify your CT Commerce Lite order-processing page and test checkout before enabling live payments.
