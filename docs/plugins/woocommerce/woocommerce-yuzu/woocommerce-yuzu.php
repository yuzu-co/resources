<?php
/*
Plugin Name: Yuzu
Plugin URI: http://www.yuzu.co/
Description: WooCommerce Yuzu Plugin
Version: 1.0.3
Author: Yuzu
Author URI: http://www.yuzu.co/
License: GPLv2 or later
Text Domain: yuzu
*/

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    if (!defined('ABSPATH')) {
        exit; // Exit if accessed directly
    }

    /**
     * Yuzu class
     **/
    if (!class_exists('WC_yuzu')) {

        class WC_yuzu
        {
            protected $host = "cs.yuzu.co";
            protected $version = "0.0.3";
            
            public function __construct()
            {
                //Install
                register_activation_hook(__FILE__, array($this, 'install'));

                //Admin
                add_filter('woocommerce_settings_tabs_array', array($this, 'addSettingsTab'), 50);
                add_action('woocommerce_settings_tabs_settings_tab_yuzu', array($this, 'settingsTab'));
                add_action('woocommerce_update_options_settings_tab_yuzu', array($this, 'updateSettings'));

                if (get_option('wc_yuzu_merchantkey')) {
                    //Front
                    add_action('wp_footer', array($this, 'addYuzuTag'), 90);

                    //Load API depending the version
                    add_action('init', array($this, 'setupApi'));

                    //Yuzu Iframe
                    add_action('woocommerce_order_details_after_order_table', array($this, 'displayIframe'));
                }  
                //Yuzu Email
                /*if (get_option('wc_yuzu_offers_in_order_email')) {
                    add_action('woocommerce_email_after_order_table', array($this, 'displayOffersInEmail'), 10, 2);
                }*/
            }

            public function setupApi()
            {
                if (version_compare(WOOCOMMERCE_VERSION, '2.1') >= 0) {
                    //Api overload for categories
                    add_action('woocommerce_api_categories', array($this, 'AddApiCategories'));
                } else {
                    if (get_option('wc_yuzu_secretkey')) {
                        require_once('api/yuzu-api.php');
                        $api = new Yuzu_API(get_option('wc_yuzu_merchantkey'), get_option('wc_yuzu_secretkey'));
                    }
                }
            }


            /**
             * Missing function to export categories
             */
            public function AddApiCategories()
            {
                $categories = get_terms('product_cat');

                echo json_encode($categories);
                exit;
            }

            /**
             * Display Yuzu iframe
             * @param $order
             */
            public function displayIframe($order)
            {
                $user = wp_get_current_user();
                $orderId = $order->id;
                $customerId = $user->id;
                if (!$customerId) {
                    $customerId = 0;
                }

                echo $this->renderTemplate("iframe.php", array('orderId' => $orderId, 'customerId' => $customerId));
            }

            /**
             * Display offers in email
             * @param $order
             * @param $sent_to_admin
             */
            public function displayOffersInEmail($order, $sent_to_admin)
            {
                //only for user
                if ($sent_to_admin) {
                    return;
                }
                //$user = $order->get_user();
                $user = wp_get_current_user();
                $merchantKey = get_option('wc_yuzu_merchantkey');
                $nboffers = get_option('wc_yuzu_nb_offers_in_order_email', '2');
                $host = $this->host;
                $userId = $user->id;
                if (!$userId) {
                    $userId = 0;
                }
                $urlLink = "http://" . $host . "/click/" . $merchantKey . "/" . $userId . "/" . $order->id;
                $imgLink = "http://" . $host . "/ban/" . $merchantKey . "/" . $userId . "/" . $order->id;

                echo $this->renderTemplate(
                    "email.php",
                    array('order' => $order, 'urlLink' => $urlLink, 'imgLink' => $imgLink, 'nboffers' => $nboffers)
                );
            }

            /**
             * Create a yuzu user with read access to WC API
             */
            public function install()
            {
                update_option('wc_yuzu_version', $this->version);
            }

            public function addSettingsTab($settings_tabs)
            {
                $settings_tabs['settings_tab_yuzu'] = __('Yuzu', 'yuzu');

                return $settings_tabs;
            }

            public function settingsTab()
            {
                woocommerce_admin_fields($this->getSettings());
            }

            public function updateSettings()
            {
                woocommerce_update_options($this->getSettings());

                if (woocommerce_settings_get_option('wc_yuzu_secretkey')) {

                    $user_id = username_exists('yuzuapi');
                    if ($user_id) {
                        wp_delete_user($user_id);
                    } 

                    $userId = wp_create_user('yuzuapi', 'cs_' . hash('md5', date('U') . mt_rand()), 'noreply@yuzu.co');
                    wp_update_user(array('ID' => $userId, 'role' => 'shop_manager'));
                    update_user_meta($userId, 'woocommerce_api_consumer_key', 'yuzu');
                    update_user_meta($userId, 'woocommerce_api_consumer_secret', woocommerce_settings_get_option('wc_yuzu_secretkey'));
                    update_user_meta($userId, 'woocommerce_api_key_permissions', 'read');
                }
            }

            public function getSettings()
            {

                $settings = array(
                    'section_title' => array(
                        'name' => __('Yuzu Configuration', 'yuzu'),
                        'type' => 'title',
                    ),
                    'merchantkey' => array(
                        'name' => __('Yuzu Api Key', 'yuzu'),
                        'type' => 'text',
                        'id' => 'wc_yuzu_merchantkey',
                        'desc' => __('Don\'t have your API key yet? <a href="https://my.yuzu.co/register?from=woocommerce" target="_blank">Create your Yuzu account in minutes</a>', 'yuzu'),
                    ),
                    'secretkey' => array(
                        'name' => __('Yuzu Secret Key', 'yuzu'),
                        'type' => 'password',
                        'id' => 'wc_yuzu_secretkey',
                    ),
                    'offers_in_checkout' => array(
                        'name' => __('Display offers in checkout', 'yuzu'),
                        'type' => 'select',
                        'options' => array(1 => 'Yes', 0 => 'No'),
                        'default' => 0,
                        'id' => 'wc_yuzu_offers_in_checkout'
                    ),
                    'offers_in_order_detail' => array(
                        'name' => __('Display offers in order details', 'yuzu'),
                        'type' => 'select',
                        'options' => array(1 => 'Yes', 0 => 'No'),
                        'default' => 0,
                        'id' => 'wc_yuzu_offers_in_order_detail'
                    ),
                    'section_end' => array(
                        'type' => 'sectionend',
                        'id' => 'wc_settings_tab_yuzu_section_end'
                    )
                );

                return apply_filters('wc_settings_tab_yuzu_settings', $settings);
            }

            public function addYuzuTag()
            {
                // On all page
                $merchantKey = get_option('wc_yuzu_merchantkey');
                $host = $this->host;
                $version = get_option('wc_yuzu_version');
                $locale = get_locale();
                $language = substr($locale, 0, 2);
                $country = substr($locale, 3, 2);
                $currency = get_woocommerce_currency();

                echo <<<EOL
<script type="text/javascript">
var Yuzu=Yuzu||{configure:function(a){this._cf=a},addEvent:function(a,b,c){this._eq=this._eq||[],this._eq.push([a,b,c])},setCustomerId:function(a){this._mp=a},setL10n:function(l10n) {this._l10n = l10n}};!function(){var a=document.createElement("script");a.type="text/javascript",a.async=!0,a.src=("https:"==document.location.protocol?"https:":"http:")+"//{$host}/js/collect/yuzu-{$version}.js";var b=document.getElementsByTagName("script")[0];b.parentNode.insertBefore(a,b)}();
Yuzu.configure({
    merchantKey: "{$merchantKey}",
    yuzuUrl: "//{$host}"
});
Yuzu.setL10n({"language": "{$language}", "country": "{$country}", "currency": "{$currency}"});
</script>
EOL;

                //User logged
                if (is_user_logged_in()) {
                    $user = wp_get_current_user();

                    echo <<<EOL
<script type="text/javascript">
	Yuzu.setCustomerId("{$user->ID}");
</script>
EOL;
                }

                //Home Event
                if (is_home()) {
                    echo <<<EOL
<script type="text/javascript">
	Yuzu.addEvent("home");
</script>
EOL;
                }

                //Category Event
                if (is_product_category()) {
                    $id = get_queried_object()->term_id;
                    echo <<<EOL
<script type="text/javascript">
	Yuzu.addEvent("category", {"id": "{$id}"});
</script>
EOL;
                }

                //Product Event
                if (is_product()) {

                    $id = get_the_ID();
                    echo <<<EOL
<script type="text/javascript">
	Yuzu.addEvent("product", {"id": "{$id}"});
</script>
EOL;
                }

                if (is_search()) {
                    global $wp_query;
                    $query = get_search_query();
                    $nb = $wp_query->found_posts;
                    echo <<<EOL
<script type="text/javascript">
	Yuzu.addEvent("search", {"query": "{$query}", "count": "{$nb}"});
</script>
EOL;
                }

                //Cart Event
                if (is_cart()) {

                    //Woo Commerce < 2.2
                    if (!function_exists('WC')) {

                        global $woocommerce;
                        $cart = $woocommerce->cart->get_cart();

                        $coupons = implode(',', $woocommerce->cart->applied_coupons);;
                        $total = $woocommerce->cart->total;

                        $lines = array();
                        foreach ($cart as $item) {
                            $line = array();
                            $line['productId'] = $item['product_id'];
                            $line['quantity'] = $item['quantity'];
                            $line['price'] = get_post_meta($item['product_id'], '_price', true) * $item['quantity'];
                            if ($item['variation']) {
                                $line['options'] = $item['variation'];
                            }
                            $lines[] = $line;
                        }

                    } else { //Woocommerce >= 2.2

                        $cart = WC()->cart;
                        $total = $cart->total;

                        $coupons = $cart->get_coupons();
                        $couponCode = array();
                        foreach($coupons as $c) {
                            $couponCode[] = $c->code;
                        }

                        $coupons = implode(',', $couponCode);

                        $lines = array();
                        foreach ($cart->cart_contents as $item) {
                            $line = array();
                            $line['productId'] = $item['product_id'];
                            $line['quantity'] = $item['quantity'];
                            $line['price'] = get_post_meta($item['product_id'], '_price', true) * $item['quantity'];
                            if ($item['variation']) {
                                $line['options'] = $item['variation'];
                            }
                            $lines[] = $line;
                        }
                    }
                    $lines = json_encode($lines);

                    echo <<<EOL
<script type="text/javascript">
    Yuzu.addEvent("cart", {
        "total": "{$total}",
        "coupon": "{$coupons}",        
        "lines": {$lines}
    });
</script>
EOL;
                }

                //Checkout Event
                if (is_order_received_page()) {

                    global $wp;

                    //If Woocommerce < 2.2
                    if(!function_exists(wc_get_order)) {


                        $order_id = $wp->query_vars['order'];
                        if (!$order_id) {
                            $order_id = $wp->query_vars['order-received'];
                        }
                        $order = new WC_Order($order_id);

                        $coupons = implode(',', $order->get_used_coupons());
                        //lines
                        $lines = array();
                        foreach ($order->get_items() as $item) {
                            $line = array();
                            $line['productId'] = $item['product_id'];
                            $line['quantity'] = $item['qty'];
                            $line['price'] = $item['line_total'];
                            $line['name'] = $item['name'];
                            $lines[] = $line;
                        }
                        $lines = json_encode($lines);

                        $user_info = get_userdata( $order->user_id );
                        if (!$user_info) {
                            $order->user_id = 0;
                            $user_info = new stdClass();
                            $user_info->data = new stdClass();
                            $user_info->data->user_email = get_post_meta($order_id, '_billing_email', true);  
                        }

                        $currency = get_woocommerce_currency();
                        if (isset($order->order_custom_fields) && isset($order->order_custom_fields['_order_currency'])) {
                            $currency = $order->order_custom_fields['_order_currency'][0];
                        } 

                        echo <<<EOL
<script type="text/javascript">
    Yuzu.addEvent("order", {
        "id": "{$order->id}",
        "discount": "{$order->order_discount}",
        "shipping": "{$order->order_shipping}",
        "tax": "{$order->order_tax}",
        "total": "{$order->order_total}",
        "paymentType": "{$order->payment_method_title}",
        "shippingMethod": "{$order->shipping_method_title}",
        "currency": "{$currency}",
        "coupon": "{$coupons}",
        "lines": {$lines},
        "customer": {
            "id": "{$order->user_id}",
            "email": "{$user_info->data->user_email}",
            "firstName": "{$order->billing_first_name}",
            "lastName": "{$order->billing_last_name}",
            "addresses": [
                {
                    "street": ["{$order->billing_address_1}", "{$order->billing_address_2}"],
                    "postalCode": "{$order->billing_postcode}",
                    "city": "{$order->billing_city}",
                    "state": "{$order->billing_state}",
                    "country": "{$order->billing_country}",
                    "type": "billing",
                },
                {
                    "street": ["{$order->shipping_address_1}", "{$order->shipping_address_2}"],
                    "postalCode": "{$order->shipping_postcode}",
                    "city": "{$order->shipping_city}",
                    "state": "{$order->shipping_state}",
                    "country": "{$order->shipping_country}",
                    "type": "shipping",
                }
            ]
        }
    });
</script>
EOL;

                    } else {

                        $order_id = $wp->query_vars['order-received'];
                        $order = wc_get_order($order_id);
                        $coupons = implode(',', $order->get_used_coupons());

                        //customer
                        $user = $order->get_user();
                        if (!$user) {
                            $user = new stdClass();
                            $user->ID = "0";
                            $user->user_email = get_post_meta($order_id, '_billing_email', true);
                        }

                        //lines
                        $lines = array();
                        foreach ($order->get_items() as $item) {
                            $line = array();
                            $line['productId'] = $item['product_id'];
                            $line['quantity'] = $item['qty'];
                            $line['name'] = $item['name'];
                            $line['price'] = $order->get_line_subtotal($item);
                            if ($item['variation']) {
                                $line['options'] = $item['variation'];
                            }
                            $lines[] = $line;
                        }
                        $lines = json_encode($lines);

                        echo <<<EOL
<script type="text/javascript">
    Yuzu.addEvent("order", {
        "id": "{$order->id}",
        "discount": "{$order->get_total_discount()}",
        "shipping": "{$order->get_total_shipping()}",
        "tax": "{$order->get_total_tax()}",
        "total": "{$order->calculate_totals()}",
        "paymentType": "{$order->payment_method_title}",
        "shippingMethod": "{$order->get_shipping_method()}",
        "currency": "{$order->get_order_currency()}",
        "coupon": "{$coupons}",
        "lines": {$lines},
        "customer": {
            "id": "{$user->ID}",
            "email": "{$user->user_email}",
            "firstName": "{$order->billing_first_name}",
            "lastName": "{$order->billing_last_name}",
            "addresses": [
                {
                    "street": ["{$order->billing_address_1}", "{$order->billing_address_2}"],
                    "postalCode": "{$order->billing_postcode}",
                    "city": "{$order->billing_city}",
                    "state": "{$order->billing_state}",
                    "country": "{$order->billing_country}",
                    "type": "billing",
                },
                {
                    "street": ["{$order->shipping_address_1}", "{$order->shipping_address_2}"],
                    "postalCode": "{$order->shipping_postcode}",
                    "city": "{$order->shipping_city}",
                    "state": "{$order->shipping_state}",
                    "country": "{$order->shipping_country}",
                    "type": "shipping",
                }
            ]
        }
    });
</script>
EOL;
                    }
                }
            }

            private function renderTemplate($default_template_path = false, $variables = array())
            {
                $template_path = locate_template(basename($default_template_path));
                if (!$template_path) {
                    $template_path = __DIR__ . '/views/' . $default_template_path;
                }

                if (is_file($template_path)) {
                    extract($variables);
                    ob_start();

                    require_once($template_path);

                    $template_content = ob_get_clean();
                } else {
                    $template_content = '';
                }

                return $template_content;
            }
        }

        $WC_yuzu = new WC_yuzu();
    }
}