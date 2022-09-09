<?php
/**
 * Wishlist table AJAX actions
 *
 * @since             2.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Wishlist shortcode
 */
class TInvWL_Public_Wishlist_Ajax {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $_name;

	/**
	 * Current wishlist
	 *
	 * @var array
	 */
	private $current_wishlist;

	/**
	 * This class
	 *
	 * @var \TInvWL_Public_Wishlist_Ajax
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return \TInvWL_Public_Wishlist_Ajax
	 */
	public static function instance( $plugin_name = TINVWL_PREFIX ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name );
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 */
	function __construct( $plugin_name ) {
		$this->_name = $plugin_name;
		$this->define_hooks();
	}

	/**
	 * Defined shortcode and hooks
	 */
	function define_hooks() {
		add_action( 'wc_ajax_tinvwl', array( $this, 'ajax_action' ) );
	}

	/**
	 * Get current wishlist
	 *
	 * @return array
	 */
	function get_current_wishlist() {
		if ( empty( $this->current_wishlist ) ) {
			$this->current_wishlist = apply_filters( 'tinvwl_get_current_wishlist', tinv_wishlist_get() );
		}

		return $this->current_wishlist;
	}

	function ajax_action() {

		$post = filter_input_array( INPUT_POST, array(
			'tinvwl-security'   => FILTER_SANITIZE_STRING,
			'tinvwl-action'     => FILTER_SANITIZE_STRING,
			'tinvwl-product_id' => FILTER_VALIDATE_INT,
			'tinvwl-paged'      => FILTER_VALIDATE_INT,
		) );

		$wishlist = $this->get_current_wishlist();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && $post['tinvwl-security'] && wp_verify_nonce( $post['tinvwl-security'], 'wp_rest' ) && $wishlist && $post['tinvwl-action'] ) {
			$this->wishlist_ajax_actions( $wishlist, $post );
		} else {
			$response['status'] = false;
			$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
			$response['icon']   = $response['status'] ? 'icon_big_heart_check' : 'icon_big_times';
			$response['msg']    = array_unique( $response['msg'] );
			$response['msg']    = implode( '<br>', $response['msg'] );
			if ( ! empty( $response['msg'] ) ) {
				$response['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $response, $post ) );
			}
			wp_send_json( $response );
		}
	}

	function wishlist_ajax_actions( $wishlist, $post ) {
		$post['wishlist_qty'] = 1;
		$action               = $post['tinvwl-action'];
		$class                = TInvWL_Public_AddToWishlist::instance();
		$owner                = (bool) $wishlist['is_owner'];
		$response['status']   = false;
		$response['msg']      = array();
		$post['wishlist_pr']  = array();

		switch ( $action ) {
			case 'remove':
				if ( ! $wishlist['is_owner'] ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$product = $post['tinvwl-product_id'];
				if ( 0 === $wishlist['ID'] ) {
					$wlp = TInvWL_Product_Local::instance();
				} else {
					$wlp = new TInvWL_Product( $wishlist );
				}
				if ( empty( $wlp ) ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$product_data = $wlp->get_wishlist( array( 'ID' => $product ) );
				$product_data = array_shift( $product_data );
				if ( empty( $product_data ) ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$post['wishlist_pr'][] = $product;
				$title                 = sprintf( __( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ), is_callable( array(
					$product_data['data'],
					'get_name'
				) ) ? $product_data['data']->get_name() : $product_data['data']->get_title() );

				if ( $wlp->remove( $product_data ) ) {
					$response['status']         = true;
					$response['msg'][]          = sprintf( __( '%s has been removed from wishlist.', 'ti-woocommerce-wishlist' ), $title );
					$response['content']        = tinvwl_shortcode_view( array( 'paged' => $post['tinvwl-paged'] ) );
					$response['wishlists_data'] = $class->get_wishlists_data( $wishlist['share_key'] );
				} else {
					$response['status'] = false;
					$response['msg'][]  = sprintf( __( '%s has not been removed from wishlist.', 'ti-woocommerce-wishlist' ), $title );
				}

				break;
			case 'add_to_cart_single':
				$product_id = $post['tinvwl-product_id'];
				if ( 0 === $wishlist['ID'] ) {
					$wlp = TInvWL_Product_Local::instance();
				} else {
					$wlp = new TInvWL_Product( $wishlist );
				}
				if ( empty( $wlp ) ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$product_data = $wlp->get_wishlist( array( 'ID' => $product_id ) );
				$product_data = array_shift( $product_data );
				if ( empty( $product_data ) ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$post['wishlist_pr'][] = $product_id;
				$title                 = sprintf( __( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ), is_callable( array(
					$product_data['data'],
					'get_name'
				) ) ? $product_data['data']->get_name() : $product_data['data']->get_title() );

				global $product;
				// store global product data.
				$_product_tmp = $product;
				// override global product data.
				$product = $product_data['data'];

				add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
				$redirect_url = $product_data['data']->add_to_cart_url();
				remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

				// restore global product data.
				$product = $_product_tmp;

				$quantity = apply_filters( 'tinvwl_product_add_to_cart_quantity', 1, $product_data['data'] );

				if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $product_data['data'], $redirect_url, $product_data ) ) {
					$response['redirect'] = apply_filters( 'tinvwl_product_add_to_cart_redirect_url', $redirect_url, $product_data['data'], $product_data );

				} elseif ( apply_filters( 'tinvwl_allow_addtocart_in_wishlist', true, $wishlist, $owner ) ) {
					$add = TInvWL_Public_Cart::add( $wishlist, $product_id, $quantity );
					if ( $add ) {
						$response['status'] = true;
						$response['msg'][]  = sprintf( _n( '%s has been added to your cart.', '%s have been added to your cart.', 1, 'ti-woocommerce-wishlist' ), $title );

						if ( tinv_get_option( 'processing', 'redirect_checkout' ) ) {
							$response['redirect'] = wc_get_checkout_url();
						}

						if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
							$response['redirect'] = wc_get_cart_url();
						}

						if ( tinv_get_option( 'processing', 'autoremove' ) ) {
							$response['wishlists_data'] = $class->get_wishlists_data( $wishlist['share_key'] );
						}
					} else {
						$response['status'] = false;
						$response['msg'][]  = sprintf( _n( '%s has not been added to your cart.', '%s have been added to your cart.', 1, 'ti-woocommerce-wishlist' ), $title );
					}
					$response['content'] = tinvwl_shortcode_view( array( 'paged' => $post['tinvwl-paged'] ) );
				}

				break;

			case 'remove_selected':

				break;
			case 'add_to_cart_selected':

				break;
			case 'add_to_cart_all':
				$_quantity = array();
				add_filter( 'tinvwl_before_get_current_product', array(
					'TInvWL_Public_Wishlist_Buttons',
					'get_all_products_fix_offset'
				) );
				$products = TInvWL_Public_Wishlist_Buttons::get_current_products( $wishlist, 9999999 );
				$result   = $errors = array();
				foreach ( $products as $_product ) {
					$product_data = wc_get_product( $_product['variation_id'] ? $_product['variation_id'] : $_product['product_id'] );

					if ( ! $product_data || 'trash' === $product_data->get_status() ) {
						continue;
					}

					global $product;
					// store global product data.
					$_product_tmp = $product;
					// override global product data.
					$product = $product_data;

					add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
					$redirect_url = $product_data->add_to_cart_url();
					remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

					// restore global product data.
					$product = $_product_tmp;

					$quantity             = apply_filters( 'tinvwl_product_add_to_cart_quantity', array_key_exists( $_product['ID'], (array) $_quantity ) ? $_quantity[ $_product['ID'] ] : 1, $product_data );
					$_product['quantity'] = $quantity;
					if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $product_data, $redirect_url, $_product ) ) {
						$errors[] = $_product['product_id'];
						continue;
					}

					$_product = $_product['ID'];

					$add = TInvWL_Public_Cart::add( $wishlist, $_product, $quantity );

					if ( $add ) {
						$result = tinv_array_merge( $result, $add );
					} else {
						$errors[] = $product_data->get_id();
					}
				}

				if ( ! empty( $errors ) ) {
					$titles = array();
					foreach ( $errors as $product_id ) {
						$titles[] = sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'ti-woocommerce-wishlist' ), strip_tags( get_the_title( $product_id ) ) );
					}
					$titles            = array_filter( $titles );
					$response['msg'][] = sprintf( _n( 'Product %s could not be added to cart because some requirements are not met.', 'Products: %s could not be added to cart because some requirements are not met.', count( $titles ), 'ti-woocommerce-wishlist' ), wc_format_list_of_items( $titles ) );
				}
				if ( ! empty( $result ) ) {
					$response['status'] = true;

					$titles = array();

					foreach ( $result as $product_id => $qty ) {
						/* translators: %s: product name */
						$titles[] = apply_filters( 'woocommerce_add_to_cart_qty_html', ( $qty > 1 ? absint( $qty ) . ' &times; ' : '' ), $product_id ) . apply_filters( 'woocommerce_add_to_cart_item_name_in_quotes', sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'woocommerce' ), strip_tags( get_the_title( $product_id ) ) ), $product_id );
						$count    += $qty;
					}

					$titles = array_filter( $titles );
					/* translators: %s: product name */
					$response['msg'][] = sprintf( _n( '%s has been added to your cart.', '%s have been added to your cart.', $count, 'woocommerce' ), wc_format_list_of_items( $titles ) );

					if ( tinv_get_option( 'processing', 'redirect_checkout' ) ) {
						$response['redirect'] = wc_get_checkout_url();
					}

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						$response['redirect'] = wc_get_cart_url();
					}

					if ( tinv_get_option( 'processing', 'autoremove' ) ) {
						$response['wishlists_data'] = $class->get_wishlists_data( $wishlist['share_key'] );
					}
				}
				$response['content'] = tinvwl_shortcode_view( array( 'paged' => $post['tinvwl-paged'] ) );
				break;
		}
		$response['icon'] = $response['status'] ? 'icon_big_heart_check' : 'icon_big_times';
		$response['msg']  = array_unique( $response['msg'] );
		$response['msg']  = implode( '<br>', $response['msg'] );
		if ( ! empty( $response['msg'] ) ) {
			$response['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $response, $post ) );
		}

		do_action( 'tinvwl_action_' . $action, $wishlist, $post['wishlist_pr'], $post['wishlist_qty'], $owner ); // @codingStandardsIgnoreLine WordPress.NamingConventions.ValidHookName.UseUnderscores

		wp_send_json( $response );
	}
}