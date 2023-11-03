=== Utánvét Ellenőr ===
Contributors: ottoradics
Tags: cash on delivery,check,filter,utánvét,ellenőr,büntetés,utánvét ellenőr,szűrés
Tested up to: 6.3
Requires PHP: 7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Connect your WooCommerce site to our SaaS available at https://utanvet-ellenor.hu
[Register](https://utanvet-ellenor.hu/register) to obtain API keys.

== Description ==

### Installation
[Register](https://utanvet-ellenor.hu/register) to obtain API keys, then set them in the plugin's settings. The reputation threshold parameter is there to let you customize how strict should the filtering be. This is a number between -1 and +1.

### What is Utánvét Ellenőr?

It is a SaaS provided by Utánvét Ellenőr Kft. from Hungary: a service which will let shop owners filter orders with Cash on Delivery coming from known fraudulent e-mail addresses.

### How does it work?

The idea behind the service is the following:
* Someone orders with Cash on Delivery payment method, but later refuses to accept the package from the courier.
* The shop owner flags this order with the custom order status `Rendelést nem vette át`.
* The plugin listens for orders entering this status.
* Once an order ends up in this status, the plugin will hash the e-mail address of the user on the shop server with SHA256 and sends the hash to our service, accompanied by a `-1`.
* If the courier could hand over the package successfully, the shop owner flags the order with the stock order status `completed`. In this case the plugin hashes the e-mail with the same SHA256 and sends the hash to our service, accompanied by a `+1`.
* When someone with the same e-mail address would like to order (from the same or from another shop), this plugin can disable Cash on Delivery from available payment methods:
  * The user enters his e-mail address and leaves the `billing_email` input field.
  * This value gets hashed with the same SHA256 algorithm and the plugin asks our service about this hash.
  * The service will return a JSON array and if the e-mail reputation provided in this payload does not meet the minimum value set by the shop owner in the plugin settings (`Reputation threshold`), the plugin will disable the Cash on Delivery payment method.

### Privacy implications

All inputs are hashed with SHA-256 by the plugin on your server. This means:
* The entered e-mail address  will NEVER leave your system.
* SHA-256 is considered to be safe for hashing.
* On "check requests" we don't receive the e-mail address, just a hash, and we provide only a couple of "numbers" about that hash. There is no way for us to know what was the original string before hashing.
* In order to use our services, you MUST notify your users that "Automated individual decision-making" might be applied during checkout. For more information, please see GDPR Art. 22.

> Note: this is not a legal advice. Consult your attourney before using this service in production.
