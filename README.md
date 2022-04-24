INTRODUCTION
------------
Viva Payment Gateway module allows the admin to set up the Viva payment method on their store. The customer can select the Viva Payment Gateway as the payment method and enter their payment details for payment purposes.


REQUIREMENTS
------------

This module requires the following modules:

 * Drupal Commerce (https://www.drupal.org/project/commerce)

INSTALLATION
------------

* Unzip archive then
* Install module "{your_site}/admin/modules/install"
1. Install plugin. Extended -> Select checkbox - "
   Viva Wallet Commerce Payment Gateway", click install.
2. Add new payment gateway. Login as Admin in Drupal and choose Commerce -> Configuration -> Payment -> Payment Gateways -> Add payment gateway. You can enter your name, for example - Viva Wallet.

<img src="Screenshot_1.png" width='400px'>

> 3. If you not see - Viva Wallet in options, please clean cache of site.
>
><img src="Screenshot_2.png" width='200px'>


CONFIGURATION
------------

1. Enter your Merchant Id and API key from Viva Wallet Merchant Portal.
2. Enter you Client Id and Secret key from Viva Wallet Smart Checkout credentials.

> Important: we have two modes - demo and live, so credentials must be different for both of modes
>
>Demo credentials here - https://demo.vivapayments.com/en/signup
>
> Live credentials here - https://app.vivawallet.com/register/

<img src="Screenshot_3.png" width='400px'>

>Website code you can see in Sales -> Online payments -> Websites/apps -> code
>
> <img src="Screenshot_4.png" width='400px'>
