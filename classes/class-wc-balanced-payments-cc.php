<?php
if( !defined( 'ABSPATH' ) ) exit; // Silence will fall

/**
 * WooCommerce - Balanced Payments Gateway Plugin Class
 *
 * @package WordPress
 * @subpackage WC_Balanced_Payments_CC
 * @author Patrick Garman
 * @since 1.0.0
 */
class WC_Balanced_Payments_CC extends WC_Payment_Gateway {

	/**
	 * Construct
	 */
	function __construct() {
		global $woocommerce, $WC_Balanced_Payments_Base;

		$this->id					= 'balanced-payments';
		$this->method_title			= __( 'Balanced Payments', 'wc-balanced-payments' );
		$this->method_description	= __( 'Process credit cards in WooCommerce using the Balanced Payments credit card gateway.', 'wc-balanced-payments' );
		$this->has_fields 			= true;
		
		$this->supports 			= array( 'products'	);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->title				= $this->settings['title'];
		$this->description			= $this->settings['description'];
		$this->statement			= $this->settings['statement'];
		$this->enabled				= $this->settings['enabled'];
		$this->testing				= $this->settings['testing'];
		$this->debug				= $this->settings['debug'] === 'yes' ? $woocommerce->logger() : false;
		$this->capture				= isset( $this->settings['capture'] ) && $this->settings['capture'] == 'no' ? false : true;

		// Get API credentials, use testing if in testing mode
		$this->marketplace_uri		= $this->testing !== 'yes' ? $this->settings['marketplace_uri'] : $this->settings['test_marketplace_uri'];
		$this->api_secret			= $this->testing !== 'yes' ? $this->settings['api_secret'] : $this->settings['test_api_secret'];

		// Make the base easily accessible
		$this->base					= $WC_Balanced_Payments_Base;

		// Hooks
		add_action( 'admin_notices', array( $this, 'checks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Save Options
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	* Check if SSL is enabled and notify the user
	**/
	function checks() {
		global $woocommerce;

		$failures = array( 'notices' => array(), 'errors' => array() );

		if( $this->enabled == 'no' )
			return;

		// Test Mode Notice
		if( $this->testing === 'yes' ) {
			$failures['notices'][] = __( 'Gateway is currently in test mode.', 'wc-balanced-payments' );
		}

		// Version Check
		if( !$this->base->check_wc_version() ) {
			$failures['errors'][] = sprintf( __( 'This gateway requires at least version %s of WooCommerce.', 'wc-balanced-payments' ), $this->min_wc_version );
		}

		// Check Marketplace API Details
		if( !$this->marketplace_uri ) {
			$failures['errors'][] = sprintf( __( 'Please enter your marketplace URI <a href="%s">here</a>.', 'wc-balanced-payments' ), admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Balanced_Payments_CC' ) );
		} elseif( !$this->base->validate_marketplace_uri( $this->marketplace_uri ) ) {
			$failures['errors'][] = __( 'Your marketplace URI Should be prefixed by a something similar to <code>/v1/marketplaces/</code>.', 'wc-balanced-payments' );
		}

		if( !$this->api_secret ) {
			$failures['errors'][] = sprintf( __( 'Please enter your secret key <a href="%s">here</a>.', 'wc-balanced-payments' ), admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Balanced_Payments_CC' ) );
		}

		$this->base->display_failures( $failures );
	}

	/**
	 * Check if this gateway is enabled
	 */
	function is_available() {
		return $this->base->is_available( $this->enabled );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields() {
		$this->form_fields = apply_filters( 'wc_balanced_payments_settings', array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'wc-balanced-payments' ),
				'label' => __( 'Enable Credit Card Payments', 'wc-balanced-payments' ),
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no'
			),
			'title' => array(
				'title' => __( 'Title', 'wc-balanced-payments' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-balanced-payments' ),
				'default' => __( 'Balanced Payments', 'wc-balanced-payments' )
			),
			'description' => array(
				'title' => __( 'Description', 'wc-balanced-payments' ),
				'type' => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'wc-balanced-payments' ),
				'default' => 'Pay with your credit card via Balanced Payments.'
			),
			'statement' => array(
				'title' => __( 'Appears on Statement As', 'wc-balanced-payments' ),
				'type' => 'text',
				'description' => __( 'The text that appears on the credit card statement of the customer. Credit card transactions will be prefixed with BAL* and are truncated to 14 characters, ACH transactions have no prefix and are truncated to 18 characters.', 'wc-balanced-payments' ),
				'default' => ''
			),
			'marketplace_uri' => array(
				'title' => __( 'Production Marketplace URI', 'wc-balanced-payments' ),
				'type' => 'text',
				'description' => __( 'Get your marketplace URI from your Balanced Payments marketplace settings. This should include the /v1/marketplaces/ prefix.', 'wc-balanced-payments' ),
				'default' => ''
			),
			'api_secret' => array(
				'title' => __( 'Production Marketplace API Secret', 'wc-balanced-payments' ),
				'type' => 'password',
				'description' => __( 'Get your API secret from your Balanced Payments marketplace settings.', 'wc-balanced-payments' ),
				'default' => ''
			),
			'test_marketplace_uri' => array(
				'title' => __( 'Test Marketplace URI', 'wc-balanced-payments' ),
				'type' => 'text',
				'description' => __( 'Get your testing marketplace URI from your Balanced Payments marketplace settings. This should include the /v1/marketplaces/ prefix.', 'wc-balanced-payments' ),
				'default' => ''
			),
			'test_api_secret' => array(
				'title' => __( 'Test Marketplace API Secret', 'wc-balanced-payments' ),
				'type' => 'password',
				'description' => __( 'Get your testing API secret from your Balanced Payments marketplace settings.', 'wc-balanced-payments' ),
				'default' => ''
			),
			'testing' => array(
				'title' => __( 'Testing', 'wc-balanced-payments' ),
				'label' => __( 'Use Test Marketplace', 'wc-balanced-payments' ),
				'type' => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using your testing marketplace URI.', 'wc-balanced-payments' ),
				'default' => 'no'
			),
			'debug' => array(
				'title' => __( 'Debug Mode', 'wc-balanced-payments' ),
				'label' => __( 'Enable Debug Mode', 'wc-balanced-payments' ),
				'type' => 'checkbox',
				'description' => __( 'Debug mode will log data and add additional debugging information while processing payments. This should generally not be used in production.', 'wc-balanced-payments' ),
				'default' => 'no'
			)
		) );
	}

	/**
	 * Payment form on checkout page
	 */
	function payment_fields() {
		global $woocommerce;
		?>
		<fieldset>

			<?php if( $this->description ) : ?>
				<p><?php echo $this->description; ?>
					<?php if( $this->testing == 'yes' ) : ?>
						<?php printf( __( 'Balanced Payments is currently in test mode. You can find test credit card details <a href="%s" target="_blank">here</a>.', 'wc-balanced-payments' ), 'https://docs.balancedpayments.com/current/#test-credit-card-numbers' ); ?>
					<?php endif; ?></p>
			<?php endif; ?>


			<?php
			$cards = array();
			if( $customer_uri = get_user_meta( get_current_user_id(), $this->base->customer_uri_key, true ) ) {
				// Make API call and get cards to list, pulling form API is more accurate compared to storing locally
				$cards = $this->base->get_cards( $customer_uri );
			}

			if( is_user_logged_in() && count( $cards ) > 0 ) : ?>
				<p class="form-row form-row-wide">

					<a class="button" style="float:right;" href="<?php echo get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ); ?>#saved-cards"><?php _e( 'Manage Cards', 'wc-balanced-payments' ); ?></a>

					<?php foreach( $cards as $i => $card ): ?>
						<input type="radio" id="bp_<?php echo $card->id; ?>" name="balanced_card_token" style="width:auto;" value="<?php echo $card->id; ?>" />
						<label style="display:inline;" for="bp_<?php echo $card->id; ?>">
						<?php
							$exp_month = apply_filters( 'wc_balanced_payments_leading_zeros', true ) ? str_pad( $card->expiration_month, 2, '0', STR_PAD_LEFT ) : $card->expiration_month;
							printf( __( '%1$s Ending In: %2$s (%3$s/%4$s)', 'wc-balanced-payments' ), $card->brand, $card->last_four, $exp_month, $card->expiration_year );
						?>
						</label><br />
					<?php endforeach; ?>

					<input type="radio" id="new" name="balanced_card_token" style="width:auto;" value="new" />
					<label style="display:inline; font-weight:bold;" for="new"><?php _e( 'Use a New Card', 'wc-balanced-payments' ); ?></label>

				</p>
				<div class="clear"></div>
			<?php else: ?>
			<input type="hidden" name="balanced_card_token" value="new" />
			<?php endif; // is user logged in && customer_uri exists ?>

			<div class="bp_new_card" <?php if( count( $cards ) > 0 ): ?>style="display:none;"<?php endif; ?>>
				<p class="form-row form-row-wide">
					<label for="bp_number"><?php _e( 'Credit Card Number', 'wc-balanced-payments' ) ?> <span class="required">*</span></label>
					<input type="text" autocomplete="off" class="input-text card-number" value="" />
				</p>
				<div class="clear"></div>
				<p class="form-row form-row-first">
					<label for="cc-expire-month"><?php _e( 'Expiration date', 'wc-balanced-payments' ) ?> <span class="required">*</span></label>
					<select id="cc-expire-month" class="woocommerce-select woocommerce-cc-month card-expiry-month">
						<option value=""><?php _e( 'Month', 'wc-balanced-payments' ) ?></option>
						<?php
							$months = array();
							for( $i = 1; $i <= 12; $i++) {
								$timestamp = mktime(0, 0, 0, $i, 1);
								$name = apply_filters( 'woocommerce_bp_month_display', date( 'F', $timestamp), $timestamp);
								$months[ date( 'n', $timestamp) ] = $name;
							}
							foreach($months as $num => $name) printf( '<option value="%u">%s</option>', $num, $name);
						?>
					</select>
					<select id="cc-expire-year" class="woocommerce-select woocommerce-cc-year card-expiry-year">
						<option value=""><?php _e( 'Year', 'wc-balanced-payments' ) ?></option>
						<?php
							for( $i = date( 'y' ); $i <= date( 'y' ) + 15; $i++) printf( '<option value="20%u">20%u</option>', $i, $i);
						?>
					</select>
				</p>
				<p class="form-row form-row-last">
					<label for="bp_csc"><?php _e( 'Security Code', 'wc-balanced-payments' ) ?> <span class="required">*</span></label>
					<input type="text" id="bp_csc" maxlength="4" style="width:4em;" autocomplete="off" class="input-text card-cvc" />
					<span class="help bp_csc_description"></span>
				</p>
				<div class="clear"></div>
			</div>

		</fieldset>
		<?php
	}

	/**
	 * Add JS files required for payment processing
	 */
	function payment_scripts() {
		if( !is_checkout() )
			return;

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'balanced-payments', 'https://js.balancedpayments.com/' . $this->base->api_version . '/balanced.js', '', $this->base->api_version, true );
		wp_enqueue_script( 'woocommerce-balanced-payments', plugins_url( 'assets/js/balanced-payments.js', dirname( __FILE__ ) ), array( 'balanced-payments', 'jquery' ), $this->base->version, true );

		$data = array(
			'marketplace_uri' => $this->marketplace_uri,
			'debug' => is_object( $this->debug ),
			'data_error' => __( 'There was an error processing your request, please try again and contact us if the issue continues.', 'wc-balanced-payments' ),
			'cant_charge_cc' => __( 'We were unable to authorize the credit card, please try again.', 'wc-balanced-payments' ),
			'invalid_uri' => __( 'Invalid marketplace URI.', 'wc-balanced-payments' ),
			'bp_error' => __( 'There was an error with the payment gateway, please try again and contact us if the issue continues.', 'wc-balanced-payments' )
		);

		wp_localize_script( 'woocommerce-balanced-payments', 'wc_balanced_payments', $data );
	}

	/**
	 * Process the Payment & Order
	 */
	function process_payment( $order_id ) {	
		$this->base->log( '--- Process Payment Start ---' );
		$this->base->log( sprintf( 'Order ID: %s', $order_id ) );
		$this->base->log( sprintf( 'Gateway Version: %s', $this->version ) );
		$this->base->log( sprintf( 'POST DATA: %s', json_encode($_POST) ) );
		
		if( defined( 'WOOCOMMERCE_VERSION' ) ) {
			$this->base->log( sprintf( 'WooCommerce Version: %s', WOOCOMMERCE_VERSION ) );
		}

		global $woocommerce;
		$order = new WC_Order( $order_id );

		// Does the user have a customer ID? If the user is logged in, check for one, otherwise false.
		$customer_uri = is_user_logged_in() ? get_user_meta( get_current_user_id(), $this->base->customer_uri_key, true ) : false;
		$card_uri = isset( $_POST['balanced_card_token'] ) && $_POST['balanced_card_token'] !== 'new' && !empty( $customer_uri ) ? trailingslashit( $customer_uri ) . 'cards/' . woocommerce_clean( $_POST['balanced_card_token'] ) : false;
		$new_card_uri = isset( $_POST['bp-token'] ) ? woocommerce_clean( $_POST['bp-token'] ) : false;
		$card_status = isset( $_POST['bp-status'] ) ? intval( $_POST['bp-status'] ) : false;
		
		// Handle Card/Customer URIs
		if( $card_uri || ( $new_card_uri && $card_status > 0 ) ) {
			$this->base->log( 'Initial processing checks passed.' );

			// Empty returns true for both empty string, and false. Either can be returned for a user without a customer ID.
			if( empty( $customer_uri ) ) {
				$this->base->log( 'User does not have a customer URI saved to their meta, attempting to create a new customer through the API.' );
				$customer_uri = $this->base->create_customer( $order );
			}

			if( !$card_uri ) {
				// Attached new card URI to customer ID
				$this->base->log( 'Attempting to attach card URI to the customer URI.' );
				$card_uri = $this->base->attach_card( $customer_uri, $new_card_uri );	
			}

			if( $card_uri === false ) {
				$this->base->log( 'Card URI is false, unable to continue.' );
				return false;
			}

		// Handle Card Errors
		} elseif ( $card_status ) {
			$this->base->log( sprintf( 'Error has Occured: %s', $card_status ) );
			switch( $card_status ) {
				case 201:
					$this->base->payment_error( __( 'There was an error with the payment gateway, please try again and contact us if the issue continues.', 'wc-balanced-payments' ) );
					break;
				case 400:
					$this->base->payment_error( __( 'There was missing data in the API call to Balanced Payments. Check the JS console for specific error messages.', 'wc-balanced-payments' ) );
					break;
				case 402:
					$this->base->payment_error( __( 'We were unable to authorize the credit card, please try again.', 'wc-balanced-payments' ), true );
					break;
				case 404:
					$this->base->payment_error( __( 'Invalid marketplace URI.', 'wc-balanced-payments' ) );
					break;
				case 500:
					$this->base->payment_error( __( 'There was an error with the payment gateway, please try again and contact us if the issue continues.', 'wc-balanced-payments' ), true );
					break;
				default:
					$this->base->payment_error( __( 'There was an error with the payment gateway, please try again and contact us if the issue continues.', 'wc-balanced-payments' ), true );
			}
			return false;
		// Something unexpected happened
		} else {
			$this->base->log( 'Gateway did not receive a URI or a status code. This is an unexpected response. Please contact support if this continues.' );
			$this->base->payment_error( __( 'No card URI or card error were passed to the process_payment() function. Ensure the balanced-payments.js file is working correctly.', 'wc-balanced-payments' ) );
			return false;
		}

		// Debit Customer
		$this->base->log( 'Attempting to debit the customer using the card URI.' );
		$debit = $this->base->debit_customer( $customer_uri, $card_uri, $order );
		
		if( $debit->status === 'succeeded' ) {
			$this->base->log( 'Debit has succeeded, updating order details.' );

			// Add CC details to order
			$order->add_order_note( sprintf( __( 'Balanced Payments - Card: %s ending in %s', 'wc-balanced-payments' ), $debit->source->brand, $debit->source->last_four ) );

			// Payment Complete
			$order->payment_complete();

			// Remove Cart
			$woocommerce->cart->empty_cart();

			// Redirect to Thank You
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		} else {
			$this->base->log( 'Unable to debit the card, check Balanced Payments API logs locally or your Balanced Payments dashboard logs for more details.' );
			return false;
		}

	}

}