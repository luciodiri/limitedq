<?php

/**
 * Admin control for setting maximum allowed product per order
 * Add a tab on product page
 */
class LimitedqAdmin {

	public function __construct() {
		// add admin tab for controlling allowed quantity per order
		add_filter('woocommerce_product_data_tabs', array($this, 'admin_add_quantity_limit_product_tab'), 20);
		add_action('woocommerce_product_data_panels', array($this, 'admin_add_quantity_tab_content'));
		add_action('woocommerce_process_product_meta', 'LimitedqAdmin::save', 20, 2);

	}

	public function admin_add_quantity_limit_product_tab( $product_tabs ) {
		// filter extends in woocomerce/includes/admin/meta-boxes/class-wc-meta-box-product-data
		$product_tabs[''] = array(
			'label'    => __( 'Order max quantity', 'woo-limitedq' ),
			'target'   => 'order_max_quantity_tab',
			'class'    => array(),
			'priority' => 80,
		);
		return $product_tabs;
	}

	public function admin_add_quantity_tab_content() {
		// action at woocomerce/includes/admin/meta-boxes/views/html-product-data-panel
		?>
		<div id='order_max_quantity_tab' class='panel woocommerce_options_panel'>
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => 'max_order_quantity',
						'placeholder'       => '1',
						'label'             => __( 'Maximum items in order', 'woo-limitedq' ),
						'description'       => __( 'The maximum items that can be added to cart in one order', 'woo-limitedq' ),
						'type'              => 'number'
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		$product = new WC_Product($post_id);

		if( isset($_POST['max_order_quantity']) ) {
			$product->update_meta_data('max_order_quantity', $_POST['max_order_quantity']);
			$product->save();
		}
	}

}