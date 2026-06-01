<?php
/**
 * Plugin Name: User Click Tracker
 * Description: Tracks logged-in user page loads and stores page metadata.
 * Version: 1.0.12
 * Author: Verdian Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class User_Click_Tracker {
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'cw_user_tracking';

		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register both AJAX hooks.
		add_action( 'wp_ajax_sct_track_page_load', array( $this, 'handle_track_page_load' ) );
		add_action( 'wp_ajax_nopriv_sct_track_page_load', array( $this, 'handle_track_page_load' ) );

		add_action( 'admin_post_uct_export_tracking_csv', array( $this, 'export_tracking_csv' ) );
		add_shortcode( 'user_click_export', array( $this, 'render_export_shortcode' ) );

		add_action( 'admin_post_uct_export_users_csv', array( $this, 'export_users_csv' ) );
		add_shortcode( 'user_email_export', array( $this, 'render_user_export_shortcode' ) );

		add_action( 'admin_post_uct_export_posts_csv', array( $this, 'export_posts_csv' ) );
		add_shortcode( 'post_export_csv', array( $this, 'render_post_export_shortcode' ) );
	}

	public function activate_plugin() {
		global $wpdb;

		$table_name      = $this->table_name;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			user_email VARCHAR(255) NOT NULL,
			event_datetime DATETIME NOT NULL,
			page_id BIGINT(20) UNSIGNED DEFAULT 0,
			page_name VARCHAR(255) NOT NULL,
			page_url TEXT NOT NULL,
			page_categories LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY page_id (page_id),
			KEY event_datetime (event_datetime)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public function enqueue_scripts() {
		if ( is_admin() || ! is_user_logged_in() ) {
			return;
		}

		$page_id   = 0;
		$page_name = wp_get_document_title();
		$page_url  = home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ) );

		if ( is_singular() ) {
			$page_id = get_queried_object_id();

			if ( $page_id ) {
				$title = get_the_title( $page_id );
				if ( ! empty( $title ) ) {
					$page_name = $title;
				}

				$permalink = get_permalink( $page_id );
				if ( ! empty( $permalink ) ) {
					$page_url = $permalink;
				}
			}
		}

		$data = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'sct_track_page_load_nonce' ),
			'pageId'   => $page_id,
			'pageName' => $page_name,
			'pageUrl'  => $page_url,
		);

		$js = '
		(function () {
			var tracker = ' . wp_json_encode( $data ) . ';

			if (!tracker || !tracker.ajaxUrl || !tracker.nonce) {
				return;
			}

			function sendTrackingEvent() {
				var formData = new FormData();
				formData.append("action", "sct_track_page_load");
				formData.append("nonce", tracker.nonce);
				formData.append("page_id", tracker.pageId || 0);
				formData.append("page_name", tracker.pageName || document.title || "");
				formData.append("page_url", tracker.pageUrl || window.location.href || "");

				fetch(tracker.ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: formData
				}).catch(function () {
					// silently fail
				});
			}

			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", sendTrackingEvent);
			} else {
				sendTrackingEvent();
			}
		})();
		';

		wp_register_script( 'site-click-tracker-inline', false, array(), '1.0.5', true );
		wp_enqueue_script( 'site-click-tracker-inline' );
		wp_add_inline_script( 'site-click-tracker-inline', $js, 'after' );
	}

	public function handle_track_page_load() {
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_die();
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'sct_track_page_load_nonce' ) ) {
			wp_die();
		}

		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		$user = wp_get_current_user();

		$page_id   = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		$page_name = isset( $_POST['page_name'] ) ? sanitize_text_field( wp_unslash( $_POST['page_name'] ) ) : '';
		$page_url  = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		$categories = array();

		if ( $page_id ) {
			$post_type = get_post_type( $page_id );

			if ( $post_type ) {
				$taxonomies = get_object_taxonomies( $post_type, 'names' );

				if ( ! empty( $taxonomies ) ) {
					foreach ( $taxonomies as $taxonomy ) {
						$terms = get_the_terms( $page_id, $taxonomy );

						if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
							foreach ( $terms as $term ) {
								$categories[] = array(
									'taxonomy' => $taxonomy,
									'term_id'  => (int) $term->term_id,
									'name'     => $term->name,
									'slug'     => $term->slug,
								);
							}
						}
					}
				}
			}
		}

		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'         => $user->ID,
				'user_email'      => $user->user_email,
				'event_datetime'  => current_time( 'mysql' ),
				'page_id'         => $page_id,
				'page_name'       => $page_name,
				'page_url'        => $page_url,
				'page_categories' => wp_json_encode( $categories ),
				'created_at'      => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $result ) {
			wp_die();
		}

		wp_die();
	}

	public function render_export_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=uct_export_tracking_csv' ),
			'uct_export_tracking_csv_nonce',
			'uct_export_nonce'
		);

		return '<a class="button" href="' . esc_url( $url ) . '">Export User Tracking CSV</a>';
	}

	public function export_tracking_csv() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Unauthorized.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'uct_export_tracking_csv_nonce', 'uct_export_nonce' );

		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT id, user_id, user_email, event_datetime, page_id, page_name, page_url, page_categories, created_at
			 FROM {$this->table_name}
			 ORDER BY event_datetime DESC",
			ARRAY_A
		);

		$filename = 'user-click-tracking-' . date( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			array(
				'ID',
				'User ID',
				'User Email',
				'Event Datetime',
				'Page ID',
				'Page Name',
				'Page URL',
				'Page Categories',
				'Created At',
			)
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				fputcsv(
					$output,
					array(
						$row['id'],
						$row['user_id'],
						$row['user_email'],
						$row['event_datetime'],
						$row['page_id'],
						$row['page_name'],
						$row['page_url'],
						$row['page_categories'],
						$row['created_at'],
					)
				);
			}
		}

		fclose( $output );
		exit;
	}

	public function render_user_export_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=uct_export_users_csv' ),
			'uct_export_users_csv_nonce',
			'uct_export_users_nonce'
		);

		return '<a class="button" href="' . esc_url( $url ) . '">Export User CSV</a>';
	}
	public function export_users_csv() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Unauthorized.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'uct_export_users_csv_nonce', 'uct_export_users_nonce' );

		$users = get_users(
			array(
				'fields' => 'all_with_meta',
			)
		);

		$filename = 'user-id-email-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		
		fputcsv(
			$output,
			array(
				'User ID',
				'User Email',
				'Name',
				"Role",
				"Active Membership"
			)
		);

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$role = '';
				if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
					$role = implode( ', ', $user->roles );
				}

				$membership = '';

				if ( function_exists( 'wc_memberships_get_user_active_memberships' ) ) {

					$memberships = wc_memberships_get_user_active_memberships( $user->ID );

					if ( ! empty( $memberships ) ) {
						$membership_names = array();

						foreach ( $memberships as $membership_obj ) {
							$plan = $membership_obj->get_plan();

							if ( $plan ) {
								$membership_names[] = $plan->get_name();
							}
						}

						$membership = implode( ', ', $membership_names );
					}

				} elseif ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {

					$levels = pmpro_getMembershipLevelsForUser( $user->ID );

					if ( ! empty( $levels ) ) {
						$membership_names = array();

						foreach ( $levels as $level ) {
							$membership_names[] = $level->name;
						}

						$membership = implode( ', ', $membership_names );
					}
				}
				fputcsv(
					$output,
					array(
						$user->ID,
						$user->user_email,
						$user->display_name,
						$role,
						$membership,

					)
				);
			}
		}

		fclose( $output );
		exit;
	}
	public function render_post_export_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=uct_export_posts_csv' ),
			'uct_export_posts_csv_nonce',
			'uct_export_posts_nonce'
		);

		return '<a class="button" href="' . esc_url( $url ) . '">Export Posts CSV</a>';
	}
	public function export_posts_csv() {

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'uct_export_posts_csv_nonce', 'uct_export_posts_nonce' );

		$filename = 'posts-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array(
			'Main Tag',
			'Title',
			'Short Description',
			'Read More URL',
		) );

		$post_ids = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		foreach ( $post_ids as $post_id ) {

			$tags = get_the_tags( $post_id );
			$main_tag = '';

			if ( $tags && ! is_wp_error( $tags ) ) {
				$main_tag = $tags[0]->name;
			}

			$post = get_post( $post_id );

			$description = has_excerpt( $post_id )
				? get_the_excerpt( $post_id )
				: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

			fputcsv( $output, array(
				$main_tag,
				get_the_title( $post_id ),
				$description,
				get_permalink( $post_id ),
			) );
		}

		fclose( $output );
		exit;
	}
}

new User_Click_Tracker();