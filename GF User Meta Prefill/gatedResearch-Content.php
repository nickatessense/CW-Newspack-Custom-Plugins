<?php
/**
 * Plugin Name: Gated Research Content Plugin
 * Description: Requires WooCommerce login and a Gravity Form submission per browser session to view tagged posts. Also has script for redirecting from "Create an account" in the WooCommerce login modal to a custom GF registration page.
 * Version: 1.0.23
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
		'webcast'           => 'webcast',
    );

    private $gate_form_map = array(
        'ebook'              => 7,
        'thought-leadership' => 8,
        'survey-report'      => 9,
		'webcast'           => 10,
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
		add_filter( 'gform_confirmation_10', array( $this, 'handle_form_confirmation' ), 10, 4 );
	}

	public function gate_post_content( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$gate_type = $this->get_gate_type_for_post( $post_id );

		if ( ! $gate_type ) {
			return $content;
		}

		if ( ! is_user_logged_in() ) {
			return $this->render_login_gate($post_id);
		}

		if ( $this->is_post_unlocked_for_session( $post_id ) ) {
			return $content;
		}

		$form_id = $this->get_form_id_for_gate_type( $gate_type );

		if ( ! $form_id ) {
			return '<p>This content gate is misconfigured. No form is assigned.</p>';
		}

		return $this->render_form_gate( $post_id, $gate_type, $form_id );
	}

	private function render_login_gate( $post_id ) {
		$current_url  = get_permalink();
		$login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), wc_get_page_permalink( 'myaccount' ) );
		$register_url = add_query_arg( 'redirect_to', rawurlencode( $current_url ), site_url( '/register/' ) );
		
		$excerpt = get_post_field( 'post_excerpt', $post_id );

		$excerpt = strip_shortcodes( $excerpt );
		$excerpt = wp_kses_post( $excerpt );
		ob_start();
		?>
		<div class="content-gate content-gate-login">
			<p><?php if ( $excerpt ) : ?><?php echo $excerpt; ?><?php endif; ?></p>
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

		$message = '<p>Please complete the form below to access the full content.</p>';
	    $excerpt = get_post_field( 'post_excerpt', $post_id );

		$excerpt = strip_shortcodes( $excerpt );
		$excerpt = wp_kses_post( $excerpt );

		 ob_start();
		?>
		<div class="content-gate content-gate-form">
			<?php if ( ! empty( $excerpt ) ) : ?>
				<div class="content-gate-excerpt">
					<?php echo $excerpt; ?>
				</div>
			<?php endif; ?>

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

		// scheduled-webcast should never be gated, even if webcast is also present.
		if ( in_array( 'scheduled-webcast', $slugs, true ) ) {
			return false;
		}

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

		setcookie(
			$cookie_name,
			'1',
			0,
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

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
			 if ( 'articleName' === $field->inputName ) {
				$field->defaultValue = get_the_title( $post_id );
			}

			if ( 'publishedDate' === $field->inputName ) {
				$field->defaultValue = get_the_date( 'Y-m-d', $post_id );
			}
			if ( 'eventDate' === $field->inputName ) {
				$raw_event_date = get_post_meta( $post_id, 'event_date', true );

				if ( ! empty( $raw_event_date ) ) {
					$date = DateTime::createFromFormat( 'Ymd', $raw_event_date );

					if ( $date ) {
						$field->defaultValue = $date->format( 'm-d-Y' );
					}
				}
			}
			if ( 'sponsor' === $field->inputName ) {
				if ( function_exists( '\Newspack_Sponsors\get_all_sponsors' ) ) {
					$sponsors = \Newspack_Sponsors\get_all_sponsors( $post_id );

					if ( ! empty( $sponsors ) && is_array( $sponsors ) ) {
						$names = array();

						foreach ( $sponsors as $sponsor ) {
							if ( ! empty( $sponsor['sponsor_name'] ) ) {
								$names[] = $sponsor['sponsor_name'];
							}
						}

						$field->defaultValue = implode( ', ', array_unique( $names ) );
					}
				}
			}

		}

		return $form;
	}

	public function handle_form_confirmation( $confirmation, $form, $entry, $ajax ) {
		if ( ! is_user_logged_in() ) {
			return $confirmation;
		}

		$post_id = 0;

		if ( isset( $_POST['input_post_id'] ) ) {
			$post_id = absint( $_POST['input_post_id'] );
		}

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

add_action( 'wp_footer', 'gf_wc_session_content_gate_footer_script' );

function gf_wc_session_content_gate_footer_script() {
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

				try {
					sessionStorage.setItem('cw_suppress_auth_popup_back', '1');
				} catch (err) {}

				window.location.href = REGISTER_URL;
			}
		}, true);

		window.addEventListener('pageshow', function(event) {
			try {
				var shouldSuppress = sessionStorage.getItem('cw_suppress_auth_popup_back') === '1';

				if (shouldSuppress && event.persisted) {
					sessionStorage.removeItem('cw_suppress_auth_popup_back');
					window.location.reload();
				}
			} catch (err) {}
		});

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
}