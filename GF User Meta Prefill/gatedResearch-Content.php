<?php
/**
 * Plugin Name: Gated Research Content Plugin
 * Description: Will make sure users are logged in and have to fill out form before they can access the research content. Research Content worked on: E-books, Thought Leadership, and Survery Reports.
 * Version: 1.0.0
 * Author: Verdian Insights
 */

// Stores a flag on the user after they submit the form.
add_action('gform_after_submission_7', 'verdian_mark_user_gate_complete', 10, 2);
function verdian_mark_user_gate_complete($entry, $form) {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    update_user_meta($user_id, 'content_gate_complete', 1);
}

// Shortcode to display gated content or the form based on user status.
function verdian_gated_content_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'form_id' => 0,
    ), $atts, 'gated_content');

    $form_id = absint($atts['form_id']);

    if ( ! is_user_logged_in() ) {
        return '<div class="gate-login">' .
            '<p>You must register or log in to access this content.</p>' .
            do_shortcode('[woocommerce_my_account]') .
        '</div>';
    }

    $user_id = get_current_user_id();
    $completed = get_user_meta($user_id, 'content_gate_complete', true);

    if ( ! $completed ) {
        if ( ! $form_id ) {
            return '<p>No form is configured for this gated content.</p>';
        }

        return '<div class="gate-form">' .
            '<p>Please complete the form below to continue.</p>' .
            do_shortcode('[gravityform id="' . $form_id . '" title="false" description="false" ajax="true"]') .
        '</div>';
    }

    return '<div class="gated-content">' . do_shortcode($content) . '</div>';
}
add_shortcode('gated_content', 'verdian_gated_content_shortcode');