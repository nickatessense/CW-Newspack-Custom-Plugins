<?php
/**
 * Plugin Name: Webinar Registration Prefill
 * Description: Gets post meta values and pre-fills the hidden fields for webinar registration form.
 * Version: 1.0.8
 * Author: Verdian Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode('webinar_registration_form', function($atts) {
    $post_id = get_the_ID();

    if ( ! $post_id ) {
        return '';
    }

    $ei           = get_post_meta($post_id, 'ei', true);
    $tp_key       = get_post_meta($post_id, 'tp_key', true);
    $webinar_name = get_the_title($post_id);
    $names        = array();

    if ( function_exists( '\Newspack_Sponsors\get_all_sponsors' ) ) {
        $sponsors = \Newspack_Sponsors\get_all_sponsors( $post_id );

        if ( ! empty( $sponsors ) && is_array( $sponsors ) ) {
            foreach ( $sponsors as $sponsor ) {
                if ( ! empty( $sponsor['sponsor_name'] ) ) {
                    $names[] = $sponsor['sponsor_name'];
                }
            }
        }
    }

    if ( ! $ei || ! $tp_key ) {
        return '<p>Registration is not available for this event.</p>';
    }

    if ( ! is_user_logged_in() ) {
        $current_url  = get_permalink();
        $login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), wc_get_page_permalink( 'myaccount' ) );
        $register_url = add_query_arg( 'redirect_to', rawurlencode( $current_url ), site_url( '/register/' ) );

        ob_start();
        ?>
        <div class="webinar-registration-gate" style="text-align: center;">
            <h2>You must log in or register to sign up for this webcast</h2>
            <p>
                <a class="button" href="<?php echo esc_url( $login_url ); ?>">Log in</a>
                <a class="button" href="<?php echo esc_url( $register_url ); ?>">Register</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    return do_shortcode(
        '[gravityforms id="4" title="false" ajax="true" field_values="ei=' . rawurlencode($ei) . '&tp_key=' . rawurlencode($tp_key) . '&webinarName=' . rawurlencode($webinar_name) . '&sponsor=' . rawurlencode( implode(', ', $names) ) . '"]'
    );
});

function webcast_details_shortcode() {
    $post_id = get_the_ID();

    if ( ! $post_id ) {
        return '';
    }

    $date = get_post_meta($post_id, 'event_date', true);
    $time = get_post_meta($post_id, 'event_time', true);
    $cpe  = get_post_meta($post_id, 'cpe_credit', true);

    if ( $date && strtotime($date) !== false ) {
        $date = date('F j, Y', strtotime($date));
    }

    if ( $time && strtotime($time) !== false ) {
        $time = date('g:i a', strtotime($time));
    }

    $output = '<div class="standfirst">';
    $output .= '<p><strong>';

    $output .= 'Webcast details: ' . esc_html($date);

    if ( ! empty($time) ) {
        $output .= ' – ' . esc_html($time) . ' ET';
    }

    if ( ! empty($cpe) ) {
        $output .= '<br>CPE Credit(s): ' . esc_html($cpe);
    }

    $output .= '</strong></p>';
    $output .= '</div>';

    return $output;
}

add_shortcode('webcast_details', 'webcast_details_shortcode');