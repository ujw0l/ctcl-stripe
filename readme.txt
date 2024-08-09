=== CTCL Stripe ===
Contributors: UjW0L
Tags: stripe, ctc-lite
Requires at least: 5.5.2
Tested up to: 6.6
Requires PHP: 7.4.9
Stable tag: 1.2.0
License: GPLv2 or later

Stripe extension for CT Commerce Lite Ecommerce plugin
== Description ==

Accept Stripe Payment with CT Commerce Lite ecommerce platform.

= To obtain your Stripe credentials =
	*	Log in to your Stripe account at dashboard.stripe.com.
	*	Navigate to Developers > API keys in the sidebar.
	*	Here, you’ll find your Publishable Key and Secret Key under the “Standard keys” section.
	*	For Webhook signing secret, go to Developers > Webhooks, click on an endpoint, and find the secret under the “Signing secret” section.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload the plugin files to the `/wp-content/plugins/ctcl-stripe` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Setting section will be available under Billing Tab of CTC Lite Admin panel 
4. Fill the applicable fields

== Screenshots ==
1. Admin Panel 
2. Frontend display 

== Changelog ==

=1.0.0=
*First Stable version

=1.1.0=
*Stripe SDK updated

=1.2.0=
*Error element display