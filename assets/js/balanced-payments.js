balanced.init( wc_balanced_payments.marketplace_uri );

jQuery( function() {
	// Checkout Form
	jQuery('form.checkout').on('checkout_place_order_balanced-payments', function( event ) {
		return balancedPaymentsFormHandler();
	});

	// Pay Page Form
	jQuery('form#order_review').submit(function(){
		return balancedPaymentsFormHandler();
	});

	// Both Forms
	jQuery("form.checkout, form#order_review").on('change', '.card-number, .card-cvc, .card-expiry-month, .card-expiry-year, input[name=balanced_card_token]', function( event ) {
		jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
		jQuery('.bp-errors').remove();
		jQuery('.bp-token').remove();
	});

	// Open and Close
	jQuery("form.checkout, form#order_review").on('change', 'input[name=balanced_card_token]', function() {
		if ( jQuery('input[name=balanced_card_token]:checked').val() == 'new' ) {
			jQuery('div.bp_new_card').slideDown( 200 );
		} else {
			jQuery('div.bp_new_card').slideUp( 200 );
		}
	} );

} );

function balancedPaymentsFormHandler() {
	if ( jQuery('#payment_method_balanced-payments').is(':checked') && jQuery('input[name=balanced_card_token]').val() == 'new' && jQuery('input[name=bp-token]').size() == 0 ) {
		if ( jQuery( 'input.balanced_card_token' ).size() == 0 ) {

			// Variables
			var form = jQuery("form.checkout, form#order_review"),
				creditCardData = {
					card_number: jQuery('.bp_new_card .card-number').val(),
					expiration_month: jQuery('.bp_new_card .card-expiry-month').val(),
					expiration_year: jQuery('.bp_new_card .card-expiry-year').val(),
					security_code: jQuery('.bp_new_card .card-cvc').val()
				};

			form.block({message: null, overlayCSS: {background: '#fff url(' + woocommerce_params.plugin_url + '/assets/images/ajax-loader.gif) no-repeat center', opacity: 0.6}});

			// Balanced Payments Create Card
			balanced.card.create(creditCardData, balancedPaymentsResponseHandler);

			return false;
		}
	}
	
	return true;
}

function balancedPaymentsResponseHandler(response) {
	var form = jQuery("form.checkout, form#order_review");

	if( wc_balanced_payments.debug ) {
		console.log( 'Balanced Payments Status: ', response.status );
		console.log( 'Balanced Payments Response: ', response.data );
		console.log( 'Balanced Payments Error: ', response.error );
	}
	
	form.unblock();
	form.append("<input type='hidden' class='bp-status' name='bp-status' value='" + response.status + "'/>");
	switch (response.status) {
		case 201:
			form.append("<input type='hidden' class='bp-token' name='bp-token' value='" + response.data['uri'] + "'/>");
			form.submit();
			break;
		case 400:
			jQuery('.card-number').closest('fieldset').before( '<ul class="woocommerce_error woocommerce-error bp-errors"><li>' + wc_balanced_payments.data_error + ' (' + response.status + ')</li></ul>' );
			break;
		case 402:
			jQuery('.card-number').closest('fieldset').before( '<ul class="woocommerce_error woocommerce-error bp-errors"><li>' + wc_balanced_payments.cant_charge_cc + ' (' + response.status + ')</li></ul>' );
			break
		case 404:
			jQuery('.card-number').closest('fieldset').before( '<ul class="woocommerce_error woocommerce-error bp-errors"><li>' + wc_balanced_payments.invalid_uri + ' (' + response.status + ')</li></ul>' );
			break;
		case 500:
			jQuery('.card-number').closest('fieldset').before( '<ul class="woocommerce_error woocommerce-error bp-errors"><li>' + wc_balanced_payments.bp_error + ' (' + response.status + ')</li></ul>' );
	}

	return false;
}