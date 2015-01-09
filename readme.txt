=== Restrict Content Pro - Taxamo Integration ===
Contributors: danieliser, mordauk
Tags: Restrict Content Pro, Taxamo, VAT Taxes
Requires at least: 3.0.1
Tested up to: 4.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This adds Taxamo VAT Tax integration to your existing Restrict Content Pro site.

== Description ==

This adds Taxamo VAT Tax integration to your existing Restrict Content Pro site.

Includes full support for all future subscriptions using Paypal. Stripe Integration will be added shortly.

Setup Guide.

1. If you haven't already sign up for [Taxamo](http://www.taxamo.com/).
1. If your taxamo email doesn't match your sites domain then you need to do this.
 1. In your Taxamo Dashboard, Navigate to Integrate -> JavaScript API
 1. Add your sites domain to the Currently configured additional domains under WEB API Referers.
1. In your Taxamo Dashboard, Navigate to Integrate -> API Tokens
1. Once signed in navigate to Integrate -> API Tokens
 1. If you are testing, you need the Test access tokens.
 1. If not, provide your business details and activate your account, then copy the production access tokens.
1. In your RCP Sites Admin Dashboard, Nagivate to Restrict -> Settings.
1. Enter both your Public and Private token. Enter test tokens to test, production for live sites.
1. Choose whether tax is included or added for new purchases.

Currently VAT Tax will only apply to new subscribers. We are trying to determine the best way to implement this for existing subscriptions but this requires determining there billing location and verifying it. This may require users to at a minimum update their profiles with their billing country. 

Options for this include forcing tax included for all paypal payments. Stripe allows adding tax to any new invoice so it won't be an issue. Paypal will likely require users to modify their subscription in order to add tax.

== Installation ==

1. Upload the `rcp-taxamo` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add your Taxamo API information to the RCP Settings Page.
4. On your Taxamo Dashboard, under APIs, click the Javascript Tab, and add your domain to the list of allowed domains.

== Changelog ==

= 1.0 =
* Initial Release.