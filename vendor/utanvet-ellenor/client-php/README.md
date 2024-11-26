# Utánvét Ellenőr API Connector

This package is the PHP client library for the Utánvét Ellenőr API 2.x.

## Installing

The easiest way to install is using Composer:

```
composer require utanvet-ellenor/client-php
```

Then use your framework's autoload, or simply add:

```php
<?php
  require 'vendor/autoload.php';
```

> **Manual installation** – If you wish to omit using Composer altogether, you can download the sources from the repository and use any [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.

## Getting started

You can start making requests to the Utánvét Ellenőr API just by creating a new `Client` instance and calling its `sendRequest()` or `sendSignal()` method.

The `Client` class takes care of the communication between your app and the Utánvét Ellenőr API.

## General usage

### Get Customer reputation from Utánvét Ellenőr API 2.0

#### Query by e-mail only: 

```php
  use UtanvetEllenor/Client;

  $client = new Client('publicApiKey', 'privateApiKey');
  $client->email = 'dummy-fail@utanvet-ellenor.hu';
  $client->threshold = 0.8;

  $response = $client->sendRequest();
```

#### Query by multiple parameters:

```php
  use UtanvetEllenor/Client;

  $client = new Client('publicApiKey', 'privateApiKey');
  $client->email = 'dummy-fail@utanvet-ellenor.hu';
  $client->countryCode = 'HU';
  $client->postalCode = '8640';
  $client->phoneNumber = '+36209238883';
  $client->addressLine = 'Szigligeti utca 10.';
  $client->threshold = 0.8;

  $response = $client->sendRequest();
```

The API answers with a JSON string, while `$client->sendRequest();` will result an `object` with the structure below:

```json
{
    "status": 200,
    "result": {
        "good": 3,
        "bad": 5,
        "reputation": -0.25,
        "blocked": true,
        "reason": "Total rate did not meet the minimum threshold set."
    }
}
```

If you would like to display these values, use the numeric `totalRate` and/or `good` and `bad` values. Avoid aliasing these values with phrases, as they might mislead users.
 
> #### Examples of using the API responses in a UI:
> 
> ##### Good:
> - 3 successful, 2 failed (deliveries), reputation: 0.2
> - 60% successful delivery rate
> - `reason` value of the response (e.g. `Total rate did not meet the minimum threshold set.`)
>
> ##### Bad:
> - Order should not be fulfilled. (Why? No exact explanation is given.)
> - Too much failed deliveries. (How much is "too much"?)
> - Bad customer reputation. (What reputation is considered "bad"?)

### Send Signal (order outcome) to Utánvét Ellenőr API 2.0

Order status changes are the endorsed and ideal events to trigger these API calls.

```php
  use UtanvetEllenor/Client;

  $client = new Client('publicApiKey', 'privateApiKey');
  $client->email = 'dummy-fail@utanvet-ellenor.hu';
  $client->outcome = 1;
  $client->orderId = 'order-123456';
  $client->countryCode = 'HU';
  $client->postalCode = '8640';
  $client->phoneNumber = '+36209238883';
  $client->addressLine = 'Szigligeti utca 10.';

  $response = $client->sendSignal();
```

#### Payload data members:

| property        | explanation                                                                                                                    | 
|-----------------|--------------------------------------------------------------------------------------------------------------------------------|
| **outcome**     | +1 if successful, -1 if refused/unclaimed.                                                                                     |
| **orderId**     | Public-facing ID of the order.                                                                                                 | 
| **phoneNumber** | Phone number in E.164 format, starting with the + sign, e.g.: +36209238883                                                     |
| **countryCode** | Country code in ISO 3166-1 alpha-2 format (e.g.: HU)                                                                           |
| **postalCode**  | Postal code the way it is used in the shipping address country (e.g.: 8640)                                                    |
| **addressLine** | Address line, without country, country code or postal code. <br/> Multiple address lines should be concatenated into one line. |

## Sandbox environment

By setting the `sandbox` parameter on the Client to true, it will use the sandbox environment instead of the production one. 

**Please use this only when you are experimenting with your shop or integration. _Do not use it in production!_**

```php
  use UtanvetEllenor/Client;

  $client = new Client('publicApiKey', 'privateApiKey');
  $client->email = 'dummy-fail@utanvet-ellenor.hu';
  $client->threshold = 0.8;
  $client->sandbox = true;

  $response = $client->sendRequest();
```

> The sandbox API will behave the same as the production with one exception: the data it provides will be randomized - **don't use it in production!** 
