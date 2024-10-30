=== OVRI Payment ===
Author: OVRI SAS
Author URI: https://www.ovri.com/
Contributors: ovribanking
Tags: payment,payments,payment gateway,payment processor,payment processing,checkout,payment pro,merchant account,contrat vad,moyen de paiement,card,credit card,paiement,bezahlsystem,purchase,online payment,ipspayment,ips payment,moneytigo,ovri,ovri banking,stripe,easytransac,payzen,paypal,adyen,mangopay,mollie
Requires at least: 4.1
Tested up to: 6.6.2
Requires PHP: 7
Stable tag: 1.7.0
License: GPLv2 or later
WC Tested up to: 9.3.3
WC requires at least: 2.6

🤩 Quickly add OVRI payment forms to accept credit cards in minutes - #1 best Ovri Payment plugin! 🚀

== Installation ==

These steps should be made for module's correct work:

1. <a href="https://my.ovri.app" target="_blank">Open a account</a> (Registration is done online and the account is opened immediately).
2. Add your website to <a href="https://my.ovri.app" target="_blank">Ovri DashBord</a> (from your dashboard to get your API key credentials)
2. Install and adjust the module

[Important] - next steps presume you already have WooCommerce module installed on your website:

1. Module's installation. Choose "Plugins -> Add new" in admin's console, press "Upload Plugin" button, choose zip-archive with plugin and then press "Install".
2. Adjustment. Choose "WooCommerce -> Settings" in admin's console and proceed to "Payments" tab. Choose "Ovri" in payment gateways list and proceed to settings.
Fill in "Api Key" and "Secret Key" - these values can be found in https://my.ovri.app. You can leave the rest settings as they go.
3. After saving your settings, you will have OVRI payments available on your website.

[Redirection Order Confirmation] - The redirection url of the customer once the payment is accepted and the default url of Wordpress/Woocommerce

== Frequently Asked Questions ==

= What does the plugin do? =

The OVRI plugin adds to your Woocommerce store and to Wordpress the interfacing of your Ovri payment account directly on your store without any particular development.

= What is Ovri ?=

<a href="https://www.ovri.com/?utm_source=wordpress-plugin-listing&utm_campaign=wordpress&utm_medium=marketplaces" target="_blank">OVRI</a> is an online payment gateway that allows you to accept credit cards in a matter of moments.

== Upgrade notice ==

Please note that the OVRI plugin requires a minimum PHP version of 7.1

== Screenshots ==

1. A unique payment experience
2. Payment directly in your checkout

== Changelog ==
= 1.7.0 =

* Added compatibility with Woocommerce subscription (recurring payment) in redirect mode
* Automation of cancellations, suspensions and réactivations
* Bug fixe
* Display or hidden OVRI Footer
* Use a different OVRI partner payment processor online
* Modify css appearance of payment form in embedded mode (hosted fields)

= 1.6.5 =

* Bug fix
* Optimize SCA Three Secure authentication

= 1.6.4 =

* Bug fix

= 1.6.3 =

* Bug fix

= 1.6.2 =

* Notification add when update is avialable 

= 1.6.1 =

* Hosted Fields - Fix limit cardholder 35chrs
* Add AMERICAN Express compatibility
* Add Elementor compatibility
* Optimize checkout compatibility

= 1.6.0 =
* Add payment without redirection (Hosted fields)
* Minor bug fixes

= 1.5.6 =
* minor fix

= 1.5.5 =
* minor fix

= 1.5.4 =
* Error correction for split payments, especially on a recurring message
* Fixed API key recovery on split payments

= 1.5.3 =
* Fix error ovri displayed
* Fix for simple checkout substitute last name to name when is not definied

= 1.5.2 =
* Fix bug on admin page

= 1.5.1 =
* Add language :
1. French
1. English
1. Italian
1. Spanish
1. German

= 1.5.0 =
* Change of name from MoneyTigo to OVRI
* Test compatibility with WordPress 6.x
* Fixed new API change

= 1.1.1 =
* Compatibility test with Wordpress 5.8 and validation
* Compatibility test with the latest version of WOCOMMERCE 5.4 and validation
* Fixed of MoneyTigo API call with rectification of payer's last name

= 1.1.0 =
* Correction of the version management bug
* Modification in case of refused payment, forcing the creation of a new order at each payment attempt
* Fixed bug with duplicate stock increment
* Removal of the moneytigo footer
* Update check native to wordpress abandon manual check
* Switch to automatic update by default for the moneytigo module

= 1.0.9 =
* Solved redirection problem for orders with completed status

= 1.0.8 =
* Fix bug in list payment method

= 1.0.7 =
* Fix bug in list payment method

= 1.0.6 =
* Add spanish translate
* Update french translate
* Fix domain text
* Fix checking moneytigo

= 1.0.5 =
* Added 2 and 4 times payment methods

= 1.0.4 =
* Removal of the integrated mode and change of api version
* Standardization of the module with the latest security rules
* Optimization of processing and response times

= 1.0.3 =
* Choice between integrated mode and redirection mode
* In the integrated reminder mode of the amount to be paid
* Redirection in case of payment in installments

= 1.0.2 =
* Modified termination url

= 1.0.1 =
* Encryption of notifications

= 1.0.0 =
* WooCommerce Payment Gateway.
