<?php 
/**
 * Plugin Name: Webinar Registration Prefill
 * Description: Gets post meta values and pre-fills the hidden fields for webinar registration form.
 * Version: 1.0.3
 * Author: Verdian Insights
 */
    add_shortcode('webinar_registration_form', function($atts) {
        $post_id = get_the_ID();

        if (!$post_id) {
            return '';
        }

        $ei = get_post_meta($post_id, 'ei', true);
        $tp_key = get_post_meta($post_id, 'tp_key', true);
        $webinar_name = get_the_title($post_id);

        if (!$ei || !$tp_key) {
            return '<p>Registration is not available for this event.</p>';
        }

        // Show login/register if user is not logged in
        if (!is_user_logged_in()) {
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
            '[gravityforms id="4" title="false" ajax="true" field_values="ei=' . rawurlencode($ei) . '&tp_key=' . rawurlencode($tp_key) . '&webinar_name=' . rawurlencode($webinar_name) . '"]'
        );
    });
?>