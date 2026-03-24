<?php
/**
 * Plugin Name: Gated Research Content Plugin
 * Description: Requires WooCommerce login and a Gravity Form submission per browser session to view tagged posts. Also has script for redirecting from "Create an account" in the WooCommerce login modal to a custom GF registration page.
 * Version: 1.0.7
 * Author: Verdian Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GF_WC_Session_Content_Gate {

	private $tag_to_gate_map = array(
        'ebook'              => 'ebook',
        'survey-report'      => 'survey-report',
        'thought-leadership' => 'thought-leadership',
    );

    private $gate_form_map = array(
        'ebook'              => 7,
        'survey-report'      => 9,
        'thought-leadership' => 8,
    );

	public function __construct() {
		add_filter( 'the_content', array( $this, 'gate_post_content' ) );
		add_filter( 'gform_pre_render', array( $this, 'populate_hidden_fields' ) );
		add_filter( 'gform_pre_validation', array( $this, 'populate_hidden_fields' ) );
		add_filter( 'gform_pre_submission_filter', array( $this, 'populate_hidden_fields' ) );
		add_filter( 'gform_admin_pre_render', array( $this, 'populate_hidden_fields' ) );

		add_filter( 'gform_confirmation_7', array( $this, 'handle_form_confirmation' ), 10, 4 );
		add_filter( 'gform_confirmation_8', array( $this, 'handle_form_confirmation' ), 10, 4 );
		add_filter( 'gform_confirmation_9', array( $this, 'handle_form_confirmation' ), 10, 4 );
	}

	public function gate_post_content( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$gate_type = $this->get_gate_type_for_post( $post_id );

		// Not a gated post.
		if ( ! $gate_type ) {
			return $content;
		}

		// Require login first.
		if ( ! is_user_logged_in() ) {
			return $this->render_login_gate();
		}

		// Already unlocked for this browser session.
		if ( $this->is_post_unlocked_for_session( $post_id ) ) {
			return $content;
		}

		$form_id = $this->get_form_id_for_gate_type( $gate_type );

		if ( ! $form_id ) {
			return '<p>This content gate is misconfigured. No form is assigned.</p>';
		}

		return $this->render_form_gate( $post_id, $gate_type, $form_id );
	}

	private function render_login_gate() {
        $current_url  = get_permalink();
        $login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), wc_get_page_permalink( 'myaccount' ) );
        $register_url = add_query_arg( 'redirect_to', rawurlencode( $current_url ), site_url( '/register/' ) );

        ob_start();
        ?>
        <div class="content-gate content-gate-login">
            <p>You must log in or register to access this content.</p>
            <p>
                <a class="button" href="<?php echo esc_url( $login_url ); ?>">Log in</a>
                <a class="button" href="<?php echo esc_url( $register_url ); ?>">Register</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

	private function render_form_gate( $post_id, $gate_type, $form_id ) {
		$message = '<p>Please complete the form below to access this content.</p>';

		ob_start();
		?>
		<div class="content-gate content-gate-form">
			<?php echo wp_kses_post( $message ); ?>
			<div class="content-gate-form-wrap">
				<?php
				echo do_shortcode(
					sprintf(
						'[gravityform id="%d" title="false" description="false" ajax="true"]',
						absint( $form_id )
					)
				);
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_gate_type_for_post( $post_id ) {
        $terms = get_the_terms( $post_id, 'category' );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return false;
        }

        $slugs = wp_list_pluck( $terms, 'slug' );

        foreach ( $slugs as $slug ) {
            if ( isset( $this->tag_to_gate_map[ $slug ] ) ) {
                return $this->tag_to_gate_map[ $slug ];
            }
        }

        return false;
    }

	private function get_form_id_for_gate_type( $gate_type ) {
		return isset( $this->gate_form_map[ $gate_type ] ) ? absint( $this->gate_form_map[ $gate_type ] ) : 0;
	}

	private function get_unlock_cookie_name( $post_id ) {
		return 'gated_post_' . absint( $post_id );
	}

	private function is_post_unlocked_for_session( $post_id ) {
		$cookie_name = $this->get_unlock_cookie_name( $post_id );
		return isset( $_COOKIE[ $cookie_name ] ) && '1' === $_COOKIE[ $cookie_name ];
	}

	private function unlock_post_for_session( $post_id ) {
		$cookie_name = $this->get_unlock_cookie_name( $post_id );

		// Session cookie: expires when browser session ends.
		setcookie(
			$cookie_name,
			'1',
			0,
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		// Make it available immediately during the same request.
		$_COOKIE[ $cookie_name ] = '1';
	}

	public function populate_hidden_fields( $form ) {
		if ( ! is_singular( 'post' ) ) {
			return $form;
		}

		$post_id   = get_the_ID();
		$gate_type = $this->get_gate_type_for_post( $post_id );

		if ( ! $post_id || ! $gate_type ) {
			return $form;
		}

		$return_url = get_permalink( $post_id );

		foreach ( $form['fields'] as &$field ) {
			if ( empty( $field->allowsPrepopulate ) || empty( $field->inputName ) ) {
				continue;
			}

			if ( 'post_id' === $field->inputName ) {
				$field->defaultValue = (string) $post_id;
			}

			if ( 'gate_type' === $field->inputName ) {
				$field->defaultValue = $gate_type;
			}

			if ( 'return_url' === $field->inputName ) {
				$field->defaultValue = esc_url_raw( $return_url );
			}
		}

		return $form;
	}

	public function handle_form_confirmation( $confirmation, $form, $entry, $ajax ) {
		if ( ! is_user_logged_in() ) {
			return $confirmation;
		}

		$post_id = 0;

		// First preference: hidden field posted from the form.
		if ( isset( $_POST['input_post_id'] ) ) {
			$post_id = absint( $_POST['input_post_id'] );
		}

		// Fallback: try to infer from current post.
		if ( ! $post_id && is_singular( 'post' ) ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return $confirmation;
		}

		$this->unlock_post_for_session( $post_id );

		$return_url = get_permalink( $post_id );

		return array( 'redirect' => $return_url );
	}
}

new GF_WC_Session_Content_Gate();

add_action('wp_footer', function () {
	if ( is_user_logged_in() ) return;
	?>
	<script>
	document.addEventListener('click', function(e) {

		// Look for "Create an account" button/link in the modal
		let el = e.target;

		if (
			el &&
			(
				el.textContent.trim() === 'Create an account' ||
				el.closest && el.closest('*')?.textContent?.trim() === 'Create an account'
			)
		) {
			e.preventDefault();

			// Redirect to your GF 2 registration page
			window.location.href = '/register/';
		}
	});
	</script>
	<?php
});