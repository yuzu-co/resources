<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Yuzu_API
{
    protected $merchantKey;
    protected $secret;

    public function __construct($merchantKey, $secret)
    {
        $this->merchantKey = $merchantKey;
        $this->secret = $secret;

        add_action('woocommerce_api_categories', array($this, 'AddApiCategories'));
        add_action('woocommerce_api_products_count', array($this, 'AddApiProductsCount'));
        add_action('woocommerce_api_products', array($this, 'AddApiProducts'));
        add_action('woocommerce_api_customers_count', array($this, 'AddApiCustomersCount'));
        add_action('woocommerce_api_customers', array($this, 'AddApiCustomers'));
        add_action('woocommerce_api_orders_count', array($this, 'AddApiOrdersCount'));
        add_action('woocommerce_api_orders', array($this, 'AddApiOrders'));
    }

    private function checkAuthentification()
    {
        $tkn = esc_attr($_GET['tkn']);

        if ($tkn != md5('yuzu:' . $this->secret)) {
            header('HTTP/1.0 401 Unauthorized');
            exit;
        }

        return true;
    }

    public function AddApiProductsCount()
    {
        $this->checkAuthentification();

        $total = wp_count_posts('product');

        echo json_encode(array('count' => $total->publish));
        exit;
    }

    public function AddApiProducts()
    {
        $this->checkAuthentification();

        $limit = ($_GET['filter']['limit']) ? $_GET['filter']['limit'] : 20;
        $page = ($_GET['page']) ? $_GET['page'] : 1;
        --$page;

        $productsIds = get_posts(
            array(
                'post_type' => 'product',
                'posts_per_page' => $limit,
                'fields' => 'ids',
                'offset' => $page*$limit,
                'no_found_rows' => true,
            )
        );

        $products = array();
        foreach($productsIds as $id) {
            $rawProduct = new WC_Product($id);
            $attributes=get_post_meta($id);
            $categories = wp_get_post_terms($id, 'product_cat');

            $p = new stdClass();
            $p->id = $rawProduct->post->id;
            $p->price = $attributes['_price'][0];
            $p->sku = $attributes['_sku'][0];
            $p->title = $rawProduct->post->post->post_title;
            $p->description = $rawProduct->post->post->post_content;
            $p->short_description = $rawProduct->post->post->post_excerpt;
            $p->stock_quantity = $attributes['_stock'][0];

            $cats = array();
            foreach($categories as $cat) {
                $cats[] = $cat->term_id;
            }
            $p->categories = implode(',', $cats);

            $products[] = $p;
        }

        echo json_encode(array('products' => $products));
        exit;
    }

    public function AddApiCategories()
    {
        $this->checkAuthentification();

        $categories = get_terms('product_cat');

        echo json_encode($categories);
        exit;
    }

    public function AddApiCustomersCount()
    {
        $this->checkAuthentification();

        $userIds = get_users(
            array(
                'role' => 'Customer',
                'fields' => 'ids'
            )
        );

        echo json_encode(array('count' => count($userIds)));
        exit;
    }

    public function AddApiCustomers()
    {
        $this->checkAuthentification();

        $limit = ($_GET['filter']['limit']) ? $_GET['filter']['limit'] : 20;
        $page = ($_GET['page']) ? $_GET['page'] : 1;
        --$page;

        $userList = get_users(
            array(
                'role' => 'Customer',
                'fields' => 'all',
                'number' => $limit,
                'offset' => $page * $limit
            )
        );

        $users = array();
        foreach ($userList as $user) {
            $attributes=get_user_meta($user->ID);
            $u = new stdClass();
            $u->id = $user->ID;
            $u->last_name = ($attributes['last_name'][0] == "") ? $attributes['billing_last_name'][0] : $attributes['last_name'][0];
            $u->first_name = ($attributes['first_name'][0] == "") ? $attributes['billing_first_name'][0] : $attributes['first_name'][0];
            $u->email = $user->data->user_email;
            $u->created_at = $user->data->user_registered;
            $u->last_order_date = "";
            $u->billing_address = array (
                'street' => array($attributes['billing_address_1'][0]),
                'postalCode' => $attributes['billing_postcode'][0],
                'city' => $attributes['billing_city'][0],
                'state' => $attributes['billing_state'][0],
                'country' => $attributes['billing_country'][0],
            );
            $u->shipping_address = array (
                'street' => array($attributes['shipping_address_1'][0]),
                'postalCode' => $attributes['shipping_postcode'][0],
                'city' => $attributes['shipping_city'][0],
                'state' => $attributes['shipping_state'][0],
                'country' => $attributes['shipping_country'][0],
            );

            $users[] = $u;
        }

        echo json_encode(array('customers' => $users));
        exit;
    }

    public function AddApiOrdersCount()
    {
        $this->checkAuthentification();

        $total = wp_count_posts('shop_order');

        echo json_encode(array('count' => $total->publish));
        exit;
    }

    public function AddApiOrders()
    {
        $this->checkAuthentification();

        $limit = ($_GET['filter']['limit']) ? $_GET['filter']['limit'] : 20;
        $page = ($_GET['page']) ? $_GET['page'] : 1;
        --$page;

        $orderIds = get_posts(
            array(
                'post_type' => 'shop_order',
                'posts_per_page' => $limit,
                'fields' => 'ids',
                'offset' => $page*$limit,
                'no_found_rows' => true,
            )
        );

        $orders = array();
        foreach($orderIds as $id) {

            $rawOrder = new WC_Order($id);
            $items = $rawOrder->get_items();

            $o = new stdClass();
            $o->order_number = $rawOrder->id;
            $o->customer_id = $rawOrder->user_id;
            $o->created_at = $rawOrder->order_date;
            $o->updated_at = $rawOrder->modified_date;
            $o->total = $rawOrder->order_total;
            $o->tax = $rawOrder->order_tax;
            $o->shipping = $rawOrder->order_shipping;
            $o->discount = $rawOrder->order_discount;
            $o->paymentType = $rawOrder->payment_method_title;
            $o->shippingMethod = $rawOrder->shipping_method_title;
            $o->currency = $rawOrder->order_custom_fields['_order_currency'][0];
            $o->coupon = implode(',', $rawOrder->get_used_coupons());

            $o->customer->last_name = $rawOrder->order_custom_fields['_billing_first_name'][0];
            $o->customer->first_name = $rawOrder->order_custom_fields['_billing_last_name'][0];
            $o->customer->email =  $rawOrder->order_custom_fields['_billing_email'][0];
            $o->billing_address->first_name = $rawOrder->order_custom_fields['_billing_first_name'][0];
            $o->billing_address->last_name = $rawOrder->order_custom_fields['_billing_last_name'][0];
            $o->billing_address->company = $rawOrder->order_custom_fields['_billing_company'][0];
            $o->billing_address->address_1 = $rawOrder->order_custom_fields['_billing_address_1'][0];
            $o->billing_address->address_2 = $rawOrder->order_custom_fields['_billing_address_2'][0];
            $o->billing_address->postcode = $rawOrder->order_custom_fields['_billing_postcode'][0];
            $o->billing_address->city = $rawOrder->order_custom_fields['_billing_city'][0];
            $o->billing_address->state = $rawOrder->order_custom_fields['_billing_state'][0];
            $o->billing_address->country = $rawOrder->order_custom_fields['_shipping_country'][0];
            $o->billing_address->phone = $rawOrder->order_custom_fields['_billing_phone'][0];
            $o->shipping_address->first_name = $rawOrder->order_custom_fields['_shipping_first_name'][0];
            $o->shipping_address->last_name = $rawOrder->order_custom_fields['_shipping_last_name'][0];
            $o->shipping_address->company = $rawOrder->order_custom_fields['_shipping_company'][0];
            $o->shipping_address->address_1 = $rawOrder->order_custom_fields['_shipping_address_1'][0];
            $o->shipping_address->address_2 = $rawOrder->order_custom_fields['_shipping_address_2'][0];
            $o->shipping_address->postcode = $rawOrder->order_custom_fields['_shipping_postcode'][0];
            $o->shipping_address->city = $rawOrder->order_custom_fields['_shipping_city'][0];
            $o->shipping_address->state = $rawOrder->order_custom_fields['_shipping_state'][0];
            $o->shipping_address->country = $rawOrder->order_custom_fields['_shipping_country'][0];
            $o->shipping_address->phone = $rawOrder->order_custom_fields['_shipping_phone'][0];

            $lines = array();
            foreach ($items as $i) {

                $p = array();
                $p['product_id'] = $i['product_id'];
                $p['quantity'] = $i['qty'];
                $p['total'] = $i['line_total'];

                $lines[] = $p;
            }
            $o->line_items = $lines;
            $orders[] = $o;
        }

        echo json_encode(array('orders' => $orders));
        exit;
    }
}