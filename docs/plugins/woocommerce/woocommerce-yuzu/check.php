<?php

require('../../..//wp-blog-header.php');

$merchantKey 	= get_option('wc_yuzu_merchantkey');
$secretKey 		= get_option('wc_yuzu_secretkey');

$inCheckout 	= get_option('wc_yuzu_offers_in_checkout');
$inOrderDetail 	= get_option('wc_yuzu_offers_in_order_detail');
$emailOrder 	= get_option('wc_yuzu_offers_in_order_email');

$response = array(
    'version' => "1.0.3",
    'date' => time(),
    'timezone' => date_default_timezone_get(),
    'woo_version' => WOOCOMMERCE_VERSION,
    'php_version' => phpversion(),
    'merchant_key' => ($merchantKey) ? true : false,
    'secret_key' => ($secretKey) ? true : false,
    'enabled' => true,
    'in_checkout' => ($inCheckout) ? true : false,
	'in_order_detail' => ($inOrderDetail) ? true : false,
	'email_order' => ($emailOrder) ? true : false,
);

wp_send_json($response);