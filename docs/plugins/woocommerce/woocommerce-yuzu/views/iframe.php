<?php if (is_order_received_page() && get_option('wc_yuzu_offers_in_checkout')) : ?>

    <div id="yuzuwrap"></div>
    <script type="text/javascript">
        jQuery(function() {
            var yuzuwrap = jQuery('#yuzuwrap');
            yuzuwrap.parent().append(yuzuwrap);
        })
    </script>


<?php elseif (!is_order_received_page() && get_option('wc_yuzu_offers_in_order_detail')) : ?>

    <div id="yuzuwrap" data-zone="order_view" data-user="<?php echo $customerId; ?>"
         data-order="<?php echo $orderId; ?>"></div>
    <script type="text/javascript">
        jQuery(function() {
            var yuzuwrap = jQuery('#yuzuwrap');
            yuzuwrap.parent().append(yuzuwrap);
        })
    </script>

<?php endif; ?>