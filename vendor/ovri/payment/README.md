OVRI is a payment solution powered by the Fintech IPS INTERNATIONAL available at :  [https://www.ovri.com/].
This library is a PHP Client for the OVRI API. It allows you to initiate a payment via Ovri credit card solution.

Installation
============

Official installation method is via composer and its packagist package [ovri/payment](https://packagist.org/packages/ovri/payment).

```
$ composer require ovri/payment
```

Usage
=====

The simplest usage of the library would be as follow:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$Ovri = new Ovri\Payment([
	'MerchantKey' => '<your-merchant-key>',
	'SecretKey' => '<your-secret-key>',
	'API' => 'https://api.ovri.app'
]);

$data = [
	'amount' => '<amount-transaction>',
	'RefOrder' => '<your-reference-order-id>',
	'Customer_Email' => '<customer-email>',
	'Customer_Name' => '<customer-lastname>',
	'Customer_FirstName' => '<customer-firstname>'
];

$reponse = $Ovri->initializePayment($data);
//201 success request (others code = failed)
if ($reponse['http'] === 201) {
	//Code is internal Ovri coding (200 success others is failed)
	if ($reponse['Code'] === 200) {
		//Either you redirect the customer to the WEB payment url
		header('Location: ' . $reponse['DirectLinkIs']);
		//Or you just get the token to initiate one of our JS SDK libraries
		//@token is $reponse['SACS']
	}
} else {
	//Error and display result
	foreach ($reponse as $key => $value) {
		//If key Explanation or MissingParameters array for explain error
		if ($key === 'Explanation' || $key === 'MissingParameters') {
			echo "<b>$key</b> =>  " . print_r($value, TRUE) . "<br>";
		} else {
			echo "<b>$key</b> =>  $value <br>";
		}
	}
}
```
To set up another type of payment, like a recurring payment, in several times or other see the api documentation to know which parameter can be inserted in $data[].

Optional :
You can verify the status of the payment to see if the payment is done.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$Ovri = new Ovri\Payment([
'MerchantKey' => '<your-merchant-key>',
'SecretKey' => '<your-secret-key>',
'API' => 'https://api.ovri.app'
]);

$data = [
	'MerchantOrderId' => '<your-reference-order-id>'
];

$reponse = $Ovri->getStatusPayment($data); 
if ($reponse['http'] === 201) {
	//Display All transaction information (success)
	echo '<h1>Success request</h1>';
	echo '<pre>' . print_r($reponse, true) . '</pre>';
} else {
	//Display Failed message
	echo '<h1>Failed request</h1>';
	echo '<pre>' . print_r($reponse, true) . '</pre>';
}
```

Instant payment notification (IPN)
==================================

If you want you can dynamically configure a URL that Ovri will call automatically at the end of the payment to notify your server of the result of the transaction.

All you have to do is add a parameter when initiating the payment.

With the above example the payment parameters were :
```
$data = [
	'amount' => '<amount-transaction>',
	'RefOrder' => '<your-reference-order-id>',
	'Customer_Email' => '<customer-email>
];
```

By adding the notification url it would become : 
```
$data = [
	'amount' => '<amount-transaction>',
	'RefOrder' => '<your-reference-order-id>',
	'Customer_Email' => '<customer-email>,
	'urlIPN' => '<votre-url-de-notification>'
];
```

Please note that the minimum required parameters are : amount,RefOrder,Customer_Email,Customer_Name,Customer_FirstName
