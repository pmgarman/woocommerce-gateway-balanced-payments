=== Balanced Payments for WooCommerce ===
Contributors: patrickgarman, remear
Tags: woocommerce, gateway, ecommerce, balanced payments
Requires at least: 3.5
Tested up to: 3.8
Stable tag: 1.0.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.gittip.com/pmgarman/

A gateway for the WooCommerce eCommerce plugin to allow using Balanced Payments to take payments.

== Description ==

A gateway for the WooCommerce eCommerce plugin to allow using Balanced Payments to take payments.

== Roadmap ==

= v1.1.0 = 

* WooCommerce Subscriptions Support

== Installation ==

= Minimum Requirements =

* WooCommerce 1.6.6 or greater
* See WooCommerce 1.6.6 for the rest of the requirements

= Configuration =

This plugin must first be installed before you can configure it. If you need help installing the plugin please see the instructions below.

1. Browse to your WooCommerce settings page in your WordPress dashboard and head to the Payment Gateways tab, then select "Balanced Payments"
2. To use the gateway on your site you will need to make sure it is enabled.
3. You can easily save two sets of API credentials in the gateway, one for production and one for testing. You can get these credentials from your Balanced Payments dashboard. Contact Balanced Payments support if you need help finding these.
4. To use your testing API credentials instead of your production credentials just be sure to check the "testing" mode box.
5. If you run into issues you can enable "Debug" mode, which will use more detailed error messages, create log files, and add javascript console entries while processing payments or using the API. You should **NOT** enable this in a typical production environment.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of WooCommerce, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "WooCommerce Gateway Balanced Payments" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the the point release, rating, and description. Most importantly of course, you can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation.

= Manual installation =

The manual installation method involves downloading our eCommerce plugin and uploading it to your webserver via your favourite FTP application.

1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.

== Frequently Asked Questions ==

= Where can I get support? =

Support will be provided in the WordPress.org support forum.

== Changelog ==

= 1.0.1 - 02/10/2014 =
* WooCommerce 2.1 Admin URL Compatability

= 1.0.0 - 02/04/2014 =
* Initial Release