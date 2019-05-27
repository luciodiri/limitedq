<?php

/**
 * Front end limited quantity buttons. replace default add to cart button in product component on products list page
 */
class Limitedq {

	public function __construct() {
		add_filter('woocommerce_loop_add_to_cart_link', array($this, 'custom_product_component_quantity'), 10 , 3);
		add_action('wp_enqueue_scripts',array($this, 'limitedq_addtocart_script')); // register addtocart script & style
		add_action('wp_ajax_nopriv_limitedq_addtocart', array($this, 'lq_ajax_handler')); // register AJAX script
		add_action('wp_ajax_limitedq_addtocart', array($this, 'lq_ajax_handler')); // register AJAX event
	}

	/**
	 * Customize the add to cart button when created on category page
	 * Add dynamic quantity options
	 * override wc template: templates/loop/add-to-cart
	 *
	 * @param $link
	 * @param $product
	 * @param $args
	 *
	 * @return string
	 */
	public function custom_product_component_quantity( $link, $product, $args ):string {

		// if quantity limit was set, add extra data attributes to button
		$max_items_allowed = $product->get_meta('max_order_quantity');
		if( '' != $max_items_allowed ) {
			$args['attributes']['data-max_items_allowed'] = $max_items_allowed;
		}

		list($quantity_in_cart, $cart_item_key) = $this->product_quantity_in_cart( $product->get_id() ) ?? null;

		if( !is_null($cart_item_key) && !is_null($quantity_in_cart) ) {
			$args['attributes']['data-quantity_in_cart'] = $quantity_in_cart;
			$args['attributes']['data-cart_item_key'] = $cart_item_key;
		}

		$link = $this->quantity_button_html( $product, $args );

		return $link;
	}

	/**
	 * if item exists in the cart add it's cart id and quantity to args
	 *
	 * @int $product_id
	 *
	 * @return array/null
	 */
	public function product_quantity_in_cart( $product_id ) {
		$cart = WC()->cart->get_cart();
		// TODO: set $cart as class attribute ass. array and fill once, to improve time complexity, O(kn) -> O(n)
		foreach ( $cart as $item ) {
			if ( $product_id == $item['product_id'] ) {
				$quantity = $item['quantity'];
				$cart_item_key = $item['key'];
				return array($quantity, $cart_item_key);
			}
		}
	}

	/**
	 * create the add/remove to cart button for product component in list
	 *
	 * @param $product
	 * @param $args
	 *
	 * @return string
	 */
	public function quantity_button_html( $product, $args ):string {
		$link = sprintf( '
				<div id="lq-add-to-cart" class="limitedq-button button">
					<a href="%s" data-quantity="1" class="button lq_remove_from_cart_button  lq_ajax_add_to_cart" %s> -
					 </a>
					<span class="lq-curr-quantity"> %s </span>
					<a href="%s" data-quantity="1" class="button lq_add_to_cart_button lq_ajax_add_to_cart" %s> + </a>
				</div>
			',
			esc_url( $product->add_to_cart_url() ),
			isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : '',
			array_key_exists('data-quantity_in_cart', $args['attributes']) ? $args['attributes']['data-quantity_in_cart'] : 0,
			esc_url( $product->add_to_cart_url() ),
			isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : ''
		);

		return $link;
	}

	/**
	 * Register and localize add to cart script and style
	 */
	public function limitedq_addtocart_script() {
		wp_enqueue_style('limitedq_addtocart_style', plugins_url('/assets/css/add_quantity.css', __FILE__));

		wp_register_script('limitedq_addtocart',
			plugins_url('/assets/js/add_quantity.js', __FILE__),
			array( 'jquery', 'jquery-blockui' ),
			WC()->version,
			true
		);

		// localize AJAX handler
		$nonce = wp_create_nonce('lq-update-cart-nonce');
		wp_localize_script('limitedq_addtocart', 'lq_ajax_obj', array(
			'lq_ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $nonce,
		));

		wp_enqueue_script('limitedq_addtocart');
	}

	/**
	 * Remove from cart AJAX handler
	 *
	 * @param $e
	 *
	 * @return string
	 */
	public function lq_ajax_handler( $e ) {
		check_ajax_referer('lq-update-cart-nonce');

		$option = $_POST['option']; // sanitize - white list
		if( $option == 'add_new_item' ) {
			$product_id = $_POST['product_id'];
			list($quantity, $cart_item_key) = $this->product_quantity_in_cart( $product_id );

			echo json_encode(
				array(
				'item_key' => $cart_item_key,
				'quantity' => $quantity
				)
			);
		}

		elseif ( $option == 'remove_item') {
			$item_key  = $_POST['cart_item_key']; // check & sanitize this!!! whitelist against existing cart items
			$remove_quantity = (int) $_POST['quantity']; // sanitize - make sure this is an int

			// TODO: use passed data fields to add validations on quantity in cart and max quantity (log etc.)

			// this is broken down to steps for clarity. need validations, graceful error handling etc.
			// 	set_quantity( string $cart_item_key, integer $quantity = 1, boolean $refresh_totals = true  )
			$cart = WC()->cart;
			$item = $cart->get_cart_item($item_key);
			$old_quantity = $item['quantity'];
			$new_quantity = $old_quantity - $remove_quantity;
			$cart->set_quantity($item_key, $new_quantity);
			$new_quantity = $cart->get_cart_item($item_key)['quantity'] ?? 0;

			echo $new_quantity;
		}

		wp_die();
	}

}