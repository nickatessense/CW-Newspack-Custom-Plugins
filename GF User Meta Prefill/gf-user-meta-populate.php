<?php
/**
 * Plugin Name: GF User Meta Prefill
 * Description: Prefills Gravity Forms fields from WordPress user meta for logged-in users.
 * Version: 1.1.12
 * Author: Verdian Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'gform_pre_render', 'gfump_prefill_user_meta' );
add_filter( 'gform_pre_validation', 'gfump_prefill_user_meta' );
add_filter( 'gform_pre_submission_filter', 'gfump_prefill_user_meta' );

function gfump_prefill_user_meta( $form ) {
	if ( ! is_user_logged_in() ) {
		return $form;
	}

	$user_id = get_current_user_id();
	$user    = wp_get_current_user();

	foreach ( $form['fields'] as &$field ) {

		/*
		 * Field 1: Email
		 */
		if ( intval( $field->id ) === 1 ) {
			if ( ! empty( $user->user_email ) ) {
				$field->defaultValue = $user->user_email;
			}
		}

		/*
		 * Field 2: Name
		 * Typical GF Name field inputs:
		 * 2.3 = First
		 * 2.6 = Last
		 */
		if ( intval( $field->id ) === 2 && $field->type === 'name' && ! empty( $field->inputs ) ) {
			foreach ( $field->inputs as &$input ) {
				if ( (string) $input['id'] === '2.3' ) {
					$input['defaultValue'] = $user->first_name;
				}
				if ( (string) $input['id'] === '2.6' ) {
					$input['defaultValue'] = $user->last_name;
				}
			}
		}

		/*
		 * Field 5: Company
		 */
		if ( intval( $field->id ) === 5 ) {
			$value = get_user_meta( $user_id, 'Company', true );
			if ( $value !== '' ) {
				$field->defaultValue = $value;
			}
		}

		/*
		 * Field 6: Job Title
		 */
		if ( intval( $field->id ) === 6 ) {
			$value = get_user_meta( $user_id, 'Job Title', true );
			if ( $value !== '' ) {
				$field->defaultValue = $value;
			}
		}

		/*
		 * Field 7: Address
		 * Typical GF Address inputs:
		 * 7.1 = Street Address
		 * 7.2 = Address Line 2
		 * 7.3 = City
		 * 7.4 = State / Province
		 * 7.5 = ZIP / Postal Code
		 * 7.6 = Country
		 */
		if ( intval( $field->id ) === 7 && $field->type === 'address' && ! empty( $field->inputs ) ) {
			$address_map = array(
				'7.1' => get_user_meta( $user_id, 'Address', true ),
				'7.2' => get_user_meta( $user_id, 'Address Line 2', true ),
				'7.3' => get_user_meta( $user_id, 'City', true ),
				'7.4' => get_user_meta( $user_id, 'State', true ),
				'7.5' => get_user_meta( $user_id, 'Zip', true ),
				'7.6' => get_user_meta( $user_id, 'Country', true ),
			);

			foreach ( $field->inputs as &$input ) {
				$input_id = (string) $input['id'];
				if ( isset( $address_map[ $input_id ] ) && $address_map[ $input_id ] !== '' ) {
					$input['defaultValue'] = $address_map[ $input_id ];
				}
			}
		}

		/*
		 * Field 9: Phone
		 */
		if ( intval( $field->id ) === 9 ) {
			$value = get_user_meta( $user_id, 'Phone', true );
			if ( $value !== '' ) {
				$field->defaultValue = $value;
			}
		}

		/*
		 * Field 10: Newsletter Preferences (checkboxes)
		 */
		if ( intval( $field->id ) === 10 && $field->type === 'checkbox' && ! empty( $field->choices ) ) {
			$saved = get_user_meta( $user_id, 'Newsletter Preferences', true );

			if ( ! is_array( $saved ) ) {
				$saved = array_filter( array_map( 'trim', explode( ',', (string) $saved ) ) );
			}

			foreach ( $field->choices as &$choice ) {
				if (
					in_array( $choice['text'], $saved, true ) ||
					in_array( $choice['value'], $saved, true )
				) {
					$choice['isSelected'] = true;
				}
			}
		}
	}

	return $form;
}
// Shortcode to display either the registration or update form based on login status
add_shortcode( 'user_account_form', 'gfump_user_account_form_shortcode' ); 
function gfump_user_account_form_shortcode() { 
	$register_form_id = 2; 
	$update_form_id = 3; 
	
	if ( is_user_logged_in() ) {
		 return do_shortcode( '[gravityform id="' . $update_form_id . '" title="true" ajax="true"]' ); 
	} 
	return do_shortcode( '[gravityform id="' . $register_form_id . '" title="true" ajax="true"]' ); 
}
// Shortcode to display either the registration or update form based on login status
add_shortcode( 'user_popup_form', 'gfump_user_popup_form_shortcode' );
function gfump_user_popup_form_shortcode( $atts ) {

	// Only allow update_form_id to be passed in
	$atts = shortcode_atts(
		array(
			'update_form_id' => 3, // default fallback
		),
		$atts,
		'user_popup_form'
	);

	$update_form_id = intval( $atts['update_form_id'] );

	if ( is_user_logged_in() ) {
		return do_shortcode( '[gravityform id="' . $update_form_id . '" title="true" ajax="true"]' );
	} else {
		return do_shortcode( '[woocommerce_my_account]' );
	}
}

// Optional: Filter to show/hide menu items based on login status and content access
add_filter( 'wp_nav_menu_objects', 'custom_login_menu_filter', 10, 2 );
function custom_login_menu_filter( $items, $args ) {

	// Optional: limit this to your CTA / tertiary menu.
	if ( empty( $args->theme_location ) || 'tertiary-menu' !== $args->theme_location ) {
		return $items;
	}

	$is_logged_in = is_user_logged_in();
	$user_id      = get_current_user_id();

	$has_content_access = false;

	// if ( $is_logged_in && function_exists( 'wc_memberships_is_user_active_member' ) ) {
	// 	$has_content_access = wc_memberships_is_user_active_member( $user_id, 'content-access' );
	// }

	foreach ( $items as $key => $item ) {
		$classes = isset( $item->classes ) ? (array) $item->classes : array();

		if ( in_array( 'logged-in-only', $classes, true ) && ! $is_logged_in ) {
			unset( $items[ $key ] );
			continue;
		}

		if ( in_array( 'logged-out-only', $classes, true ) && $is_logged_in ) {
			unset( $items[ $key ] );
			continue;
		}

		if ( in_array( 'hide-for-member', $classes, true ) && $has_content_access ) {
			unset( $items[ $key ] );
			continue;
		}
	}

	return $items;
}

/**
 * After user registration via GF, create a WooCommerce order for a specific product to grant membership access.
 */

add_action( 'gform_user_registered', 'gf_add_membership_purchase_after_user_registered', 10, 4 );

function gf_add_membership_purchase_after_user_registered( $user_id, $feed, $entry, $user_pass ) {

	// Only run for your form ID 2
	if ( (int) rgar( $entry, 'form_id' ) !== 2 ) {
		return;
	}

	$existing_order_id = gform_get_meta( rgar( $entry, 'id' ), 'wc_membership_order_id' );
	if ( $existing_order_id ) {
		return;
	}

	$product_id = 86966;
	$product    = wc_get_product( $product_id );

	$email     = sanitize_email( rgar( $entry, '1' ) );
	$phone     = sanitize_text_field( rgar( $entry, '9' ) );
	$company   = sanitize_text_field( rgar( $entry, '5' ) );
	$job_title = sanitize_text_field( rgar( $entry, '6' ) );

	$first_name = sanitize_text_field( rgar( $entry, '2.3' ) );
	$last_name  = sanitize_text_field( rgar( $entry, '2.6' ) );

	if ( empty( $first_name ) && empty( $last_name ) ) {
		$full_name = sanitize_text_field( rgar( $entry, '2' ) );
		if ( ! empty( $full_name ) ) {
			$parts = preg_split( '/\s+/', trim( $full_name ), 2 );
			$first_name = $parts[0] ?? '';
			$last_name  = $parts[1] ?? '';
		}
	}

	$address_1 = sanitize_text_field( rgar( $entry, '7.1' ) );
	$address_2 = sanitize_text_field( rgar( $entry, '7.2' ) );
	$city      = sanitize_text_field( rgar( $entry, '7.3' ) );
	$state     = sanitize_text_field( rgar( $entry, '7.4' ) );
	$postcode  = sanitize_text_field( rgar( $entry, '7.5' ) );
	$country   = sanitize_text_field( rgar( $entry, '7.6' ) );

	if ( empty( $address_1 ) ) {
		$address_1 = sanitize_text_field( rgar( $entry, '7' ) );
	}

	try {
		$order = wc_create_order( array(
			'customer_id' => $user_id,
		) );

		$order->add_product( $product, 1 );

		$billing = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'company'    => $company,
			'email'      => $email,
			'phone'      => $phone,
			'address_1'  => $address_1,
			'address_2'  => $address_2,
			'city'       => $city,
			'state'      => $state,
			'postcode'   => $postcode,
			'country'    => $country,
		);

		$order->set_address( $billing, 'billing' );
		$order->set_address( $billing, 'shipping' );

		if ( ! empty( $job_title ) ) {
			$order->update_meta_data( '_job_title', $job_title );
		}

		$order->update_meta_data( '_gravityforms_entry_id', rgar( $entry, 'id' ) );
		$order->update_meta_data( '_created_via', 'gravity_forms_membership' );
		$order->add_order_note( 'Auto-created after GF user registration for entry #' . rgar( $entry, 'id' ) );

		$order->calculate_totals();
		$order->payment_complete();

		if ( $order->has_status( array( 'pending', 'on-hold', 'processing' ) ) ) {
			$order->update_status( 'completed', 'Auto-completed free membership order from Gravity Forms.' );
		}

		$order->save();

		gform_update_meta( rgar( $entry, 'id' ), 'wc_membership_order_id', $order->get_id() );


	} catch ( Exception $e ) {
		error_log( 'Error creating WooCommerce order for GF entry ' . rgar( $entry, 'id' ) . ': ' . $e->getMessage() );
	}
}

//  Add JS to close any open modals on the page when "Create an Account" is clicked, to prevent multiple overlapping modals when redirecting to the registration page.
add_action( 'wp_footer', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	?>
	<script>
	(function() {
		var REGISTER_URL = '/register/';

		document.addEventListener('click', function(e) {
			var trigger = e.target.closest('a, button');
			if (!trigger) return;

			var text = (trigger.textContent || '').trim().toLowerCase();

			if (text === 'create an account') {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();

				// Mark that we came from the auth popup
				try {
					sessionStorage.setItem('cw_suppress_auth_popup_back', '1');
				} catch (err) {}

				window.location.href = REGISTER_URL;
			}
		}, true);

		// When browser restores page from back/forward cache, force reload once
		window.addEventListener('pageshow', function(event) {
			try {
				var shouldSuppress = sessionStorage.getItem('cw_suppress_auth_popup_back') === '1';

				if (shouldSuppress && event.persisted) {
					sessionStorage.removeItem('cw_suppress_auth_popup_back');
					window.location.reload();
				}
			} catch (err) {}
		});

		// Extra fallback for some browsers/themes
		window.addEventListener('popstate', function() {
			try {
				var shouldSuppress = sessionStorage.getItem('cw_suppress_auth_popup_back') === '1';

				if (shouldSuppress) {
					sessionStorage.removeItem('cw_suppress_auth_popup_back');
					window.location.reload();
				}
			} catch (err) {}
		});
	})();
	</script>
	<?php
});