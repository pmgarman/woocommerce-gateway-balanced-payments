<?php
if( !defined( 'ABSPATH' ) ) exit; // Silence will fall

/**
 * WooCommerce - Balanced Payments Gateway Plugin Card Management Class
 *
 * @package WordPress
 * @subpackage WC_Balanced_Payments_Base
 * @author Patrick Garman
 * @since 1.0.0
 */
class WC_Balanced_Payments_Base {

	public $version				= '1.0.0';
	public $min_wc_version		= '1.6.6';
	public $api_endpoint		= 'https://api.balancedpayments.com';
	public $api_version			= 'v1';
	public $customer_uri_key	= '_balanced_customer_uri';

	/**
	 * Construct
	 */
	function __construct() {
		add_action( 'woocommerce_before_my_account', array( $this, 'account_actions_listener' ) );

		add_action( 'woocommerce_init', array( $this, 'init' ) );
		add_action( 'woocommerce_after_my_account', array( $this, 'myaccount_card_table' ) );
	}

	/**
	 * Init the base after WooCommerce, ensures $woocommerce is available
	 */
	function init() {
		global $woocommerce;
		$this->settings			= get_option( 'woocommerce_balanced-payments_settings' );

		$this->statement		= $this->settings['statement'];
		$this->testing			= $this->settings['testing'];
		$this->debug			= $this->settings['debug'] === 'yes' ? $woocommerce->logger() : false;

		$this->marketplace_uri	= $this->testing !== 'yes' ? $this->settings['marketplace_uri'] : $this->settings['test_marketplace_uri'];
		$this->api_secret		= $this->testing !== 'yes' ? $this->settings['api_secret'] : $this->settings['test_api_secret'];

		$this->wc_admin_slug	= version_compare( $woocommerce->version, '2.1.0', '>=') ? 'wc-settings&tab=checkout' : 'woocommerce_settings&tab=payment_gateways';
	}

	/**
	 * Check if this gateway is enabled
	 */
	function is_available( $enabled ) {
		global $woocommerce;

		// Ensure gateway is enabled
		if( $enabled !== 'yes' ) return false;

		// Check WC Version Compatibility
		if( !$this->check_wc_version() ) return false;

		// Required Data
		if( !$this->marketplace_uri ) return false;
		if( !$this->api_secret ) return false;

		// It seems all is in order...
		return true;
	}

	/**
	 * Check to ensure the marketplace URI is valid
	 */
	function validate_marketplace_uri( $uri ) {
		return strpos( $uri, '/v1/marketplaces/' ) !== false ? true : false;
	}

	/**
	 * Check for WC version compatibility
	 */
	function check_wc_version() {
		global $woocommerce;
		return $woocommerce->version >= $this->min_wc_version ? true : false;
	}
	
	/**
	 * Loop through checks notices and errors and display them.
	 */
	function display_failures( $failures ) {
		foreach( $failures['notices'] as $notice ) {
			echo '<div class="updated">' . wpautop( __( 'Balanced Payments Notification: ', 'wc-balanced-payments' ) . $notice ) . '</div>';
		}
		foreach( $failures['errors'] as $error ) {
			echo '<div class="error">' . wpautop( __( 'Balanced Payments Error: ', 'wc-balanced-payments' ) . $error ) . '</div>';
		}
	}

	/**
	 * Handle throwing a generic or specific error based on $this->debug, optionally bypassing.
	 */
	function payment_error( $error, $bypass_debug = false ) {
		global $woocommerce;
		if( is_object( $this->debug ) || $bypass_debug ) {
			$woocommerce->add_error( $error );
		} else {
			$woocommerce->add_error( __( 'There was an error processing your payment, please try again and contact us if the issue continues.', 'wc-balanced-payments' ) );
		}
	}

	/**
	 * Check if logging is enabled and start logging
	 */
	function log( $message, $handle = 'balanced-payments' ) {
		// Check if we are logging
		if( !is_object( $this->debug ) ) return;

		$this->debug->add( $handle, $message );
	}

	/**
	 * Account actions listener for account functions 
	 */
	function account_actions_listener() {
		// Do we have an action?
		if( !isset( $_POST['balanced_payments_action'] ) ) return;

		$action = sanitize_key( $_POST['balanced_payments_action'] );

		if( !wp_verify_nonce( $_POST['_wpnonce'], 'balanced_payments_' . $action ) ) {
			wp_die( __( 'Balanced Payments Nonce Verification Failed', 'wc-balanced-payments' ) );
		}

		switch( $action ) {
			// Delete Card
			case 'delete_card':
				$token = trim($_POST['balanced_payments_card_token']);
				$this->invalidate_card( $token );
				break;
		}
	}

	/**
	* Display Cards for User Management
	**/
	function myaccount_card_table() {
		if( $customer_uri = get_user_meta( get_current_user_id(), $this->customer_uri_key, true ) ) {
			// Make API call and get cards to list, pulling form API is more accurate compared to storing locally
			$cards = $this->get_cards( $customer_uri );
		}

		// If there are no cards, skip this all together
		if ( !isset( $cards ) || !is_array( $cards ) || count( $cards ) == 0 ) {
			return;
		}
		?>
			<h2 id="saved-cards" style="margin-top:40px;"><?php _e( 'Saved Cards', 'wc-balanced-payments' ); ?></h2>
			<table class="shop_table">
				<thead>
					<tr>
						<th><?php _e( 'Card','wc-balanced-payments' ); ?></th>
						<th><?php _e( 'Expires','wc-balanced-payments' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cards as $i => $card ): $exp_month = apply_filters( 'wc_balanced_payments_leading_zeros', true ) ? str_pad( $card->expiration_month, 2, '0', STR_PAD_LEFT ) : $card->expiration_month; ?>
					<tr>
						<td><?php printf( __( '%1$s ending in %2$s', 'wc-balanced-payments' ), $card->brand, $card->last_four ) ?></td>
						<td><?php printf( __( '%1$s/%2$s', 'wc-balanced-payments' ), $exp_month, $card->expiration_year ) ?></td>
						<td style="text-align:right;">
							<form action="" method="POST">
								<?php wp_nonce_field ( 'balanced_payments_delete_card' ); ?>
								<input type="hidden" name="balanced_payments_action" value="delete_card" />
								<input type="hidden" name="balanced_payments_card_token" value="<?php echo esc_attr( $card->id ); ?>" />
								<input type="submit" class="button" value="<?php _e( 'Delete Card', 'wc-balanced-payments' ); ?>" />
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
	}

	/**
	 * Get customers attached cards
	 */
	function get_cards( $customer_uri ) {
		$uri = trailingslashit( $customer_uri ) . 'cards';

		if ( false === ( $data = get_transient( $uri ) ) ) {
			$response = $this->call( 'GET', $uri, array( 'limit' => apply_filters( 'wc_balanced_payments_card_limit', 25 ) ) );

			if( intval( $response['status'] ) !== 200 ) {
				return false;
			} else {
				$data = json_decode( $response['json'] );
			}

			// Cache the data for a week
			set_transient( $uri, $data, apply_filters( 'wc_balanced_payments_card_cache_timeout', 60 * 60 * 24 * 7 ) );
		}

		return $data->items;
	}

	/**
	 * Add Customer to Balanced Payments
	 */
	function create_customer( $order ) {
		$data = array(
			'name' => $order->billing_first_name . ' ' . $order->billing_last_name,
			'email' => $order->billing_email,
			'business_name' => $order->billing_company,
			'address' => array(
				'line1' => $order->billing_address_1,
				'line2' => $order->billing_address_2,
				'city' => $order->billing_city,
				'state' => $order->billing_state,
				'postal_code' => $order->billing_postcode,
				'country_code' => $order->billing_country
			),
			'phone' => $order->billing_phone
		);

		$response = $this->call( 'POST', '/v1/customers', $data );

		if( intval( $response['status'] ) !== 201 ) {
			return $this->payment_error( sprintf( __( 'Unable to create customer in Balanced Payments. (%s)', 'wc-balanced-payments' ), intval( $response['status'] ) ) );
		} else {
			$customer = json_decode( $response['json'] );
			if( is_user_logged_in() ) {
				add_user_meta( get_current_user_id(), $this->customer_uri_key, $customer->uri, true );
			}
			return $customer->uri;
		}
	}

	/**
	 * Attach a card URI to a customer URI
	 */
	function attach_card( $customer_uri, $card_uri ) {
		$response = $this->call( 'PUT', $customer_uri, array( 'card_uri' => $card_uri ) );

		// Delete the cached cards list because we added a new card
		delete_transient( trailingslashit( $customer_uri ) . 'cards' );

		if( intval( $response['status'] ) !== 200 ) {
			if( intval( $response['status'] ) == 409 ) {
				$this->payment_error( sprintf( __( 'Unable to attach the same card to the same customer multiple times. (%s)', 'wc-balanced-payments' ), intval( $response['status'] ) ) );
			} else {
				$this->payment_error( sprintf( __( 'Unable to attach card to customer in Balanced Payments. (%s)', 'wc-balanced-payments' ), intval( $response['status'] ) ) );	
			}			
			return false;
		} else {
			$customer = json_decode( $response['json'] );
			return $customer->source_uri;
		}
	}

	/**
	 * Invalidate a card URI
	 */
	function invalidate_card( $card_uri, $customer_uri = NULL ) {
		$uri = trailingslashit( $this->marketplace_uri ) . 'cards/' . $card_uri;
		$response = $this->call( 'DELETE', $uri );

		// Get the customer URI so we can clear the card cache
		$customer_uri = is_null( $customer_uri ) ? get_user_meta( get_current_user_id(), $this->customer_uri_key, true ) : $customer_uri;

		// Clear the saved cards cache
		delete_transient( trailingslashit( $customer_uri ) . 'cards' );	

		if( intval( $response['status'] ) === 204 ) {
			echo '<ul class="woocommerce_info woocommerce-info"><li>' . __( 'Your credit card has been successfully removed.', 'wc-balanced-payments' ) . '</li></ul>';
		}
	}

	/**
	 * Debit a customer/card uri
	 */
	function debit_customer( $customer_uri, $source_uri, $order ) {
		$data = array(
			'source_uri' => $source_uri,
			'amount' => intval( number_format( $order->order_total, 2 ) * 100 ),
			'description' => apply_filters( 'woocommerce_balanced_payments_debit_description', sprintf( __( 'WooCommerce Order %s', 'wc-balanced-payments' ), $order->get_order_number() ), $order ),
			'meta' => array(
				'order_id' => $order->id,
				'order_number' => $order->get_order_number()
			)
		);

		if( !empty( $statement ) ) {
			$data['appears_on_statement_as'] = $statement;
		}

		$response = $this->call( 'POST', trailingslashit( $customer_uri ) . 'debits', $data );
		
		if( intval( $response['status'] ) !== 201 ) {
			$this->payment_error( __( 'We were unable to charge your card for this order.', 'wc-balanced-payments' ), true );
			return false;
		} else {
			$debit = json_decode( $response['json'] );
			update_post_meta( $order->id, '_balanced_payments_debit_uri', $debit->uri );
			$order->add_order_note( sprintf( __( 'Balanced Payments - Debit URI: %s', 'wc-balanced-payments' ), $debit->uri ) );
			$order->add_order_note( sprintf( __( 'Balanced Payments - Transaction Number: %s', 'wc-balanced-payments' ), $debit->transaction_number ) );
			return $debit;
		}
	}

	/**
	 * Send requests to Balanced Payments API
	 */
	function call( $method, $uri, $data = array() ) {
		$this->log( '--- API Call Start ---', 'balanced-payments-api' );
		$this->log( sprintf( 'Gateway Version: %s', $this->version ), 'balanced-payments-api' );
		
		if( defined( 'WOOCOMMERCE_VERSION' ) ) {
			$this->log( sprintf( 'WooCommerce Version: %s', WOOCOMMERCE_VERSION ), 'balanced-payments-api' );
		}

		$url = untrailingslashit( $this->api_endpoint ) . $uri;

		$args = array(
			'method' => strtoupper( $method ),
			'body' => json_encode( $data ),
			'user-agent' => 'WC-Balanced-Payments/' . $this->version,
			'sslverify' => true,
			'redirection' => 0,
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->api_secret . ':' ),
				'Content-Type' => 'application/json'
			)
		);


		$this->log( sprintf( 'Method: %s', $method ), 'balanced-payments-api' );
		$this->log( sprintf( 'URI: %s', $uri ), 'balanced-payments-api' );
		$this->log( sprintf( 'URL: %s', $url ), 'balanced-payments-api' );
		$this->log( sprintf( 'Data: %s', json_encode( $data ) ), 'balanced-payments-api' );

		$response = wp_remote_request( $url, $args );
		
		$this->log( sprintf( 'Response Status: %s', wp_remote_retrieve_response_code( $response ) ), 'balanced-payments-api' );
		$this->log( sprintf( 'Response Body: %s', wp_remote_retrieve_body( $response ) ), 'balanced-payments-api' );

		$this->log( '--- API Call End ---', 'balanced-payments-api' );

		return array(
			'status' => wp_remote_retrieve_response_code( $response ),
			'json' => wp_remote_retrieve_body( $response ),
			'raw' => $response
		);
	}
}