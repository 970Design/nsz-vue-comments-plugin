<?php
/**
 * Plugin Name: 970 Design Headless Comments
 * Description: Secure proxy endpoints for headless WordPress comments integration with Akismet spam protection.
 * Version:     1.1.1
 * Author:      970 Design
 * Author URI:  https://970design.com/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: nsz-vue-comments
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Headless_Comments_API' ) ) {

	class Headless_Comments_API {

		private $api_namespace = 'headless-comments/v1';

		public function __construct() {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
			add_action( 'rest_api_init', [ $this, 'register_rest_cors' ] );
			add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
			add_action( 'admin_init', [ $this, 'register_settings' ] );

			// Add settings link on plugins page
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_plugin_action_links' ] );
		}

		/**
		 * Activation hook: generate API key if not present and set defaults
		 */
		public static function activate() {
			// Generate API key only if not present
			if ( ! get_option( 'headless_comments_api_key' ) ) {
				$key = wp_generate_password( 32, false );
				update_option( 'headless_comments_api_key', $key );
			}

			// Set default allowed origins (if not present)
			if ( get_option( 'headless_comments_allowed_origins' ) === false ) {
				update_option( 'headless_comments_allowed_origins', "http://localhost:4321\nhttp://localhost" );
			}

			// Set default Akismet option
			if ( get_option( 'headless_comments_use_akismet' ) === false ) {
				update_option( 'headless_comments_use_akismet', '1' );
			}
		}

		/**
		 * Register REST API routes
		 */
		public function register_routes() {
			// Get comments for a post (requires API key)
			register_rest_route(
				$this->api_namespace,
				'/posts/(?P<post_id>\d+)/comments',
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_comments' ],
					'permission_callback' => [ $this, 'check_api_permission' ],
					'args'                => [
						'post_id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'order' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => 'DESC',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function( $param ) {
								return in_array( strtoupper( $param ), [ 'ASC', 'DESC' ], true );
							},
						],
					],
				]
			);

			// Submit comment (requires API key)
			register_rest_route(
				$this->api_namespace,
				'/posts/(?P<post_id>\d+)/comments',
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'submit_comment' ],
					'permission_callback' => [ $this, 'check_api_permission' ],
					'args'                => [
						'post_id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				]
			);
		}

		/**
		 * Add CORS headers via rest_pre_serve_request filter
		 */
		public function register_rest_cors() {
			add_filter(
				'rest_pre_serve_request',
				function ( $served, $result, $request ) {
					$this->send_cors_headers();
					return $served;
				},
				10,
				3
			);
		}

		/**
		 * Permission callback - requires API key
		 *
		 * @param WP_REST_Request $request
		 * @return bool|WP_Error
		 */
		public function check_api_permission( $request ) {
			$api_key    = $request->get_header( 'X-API-Key' ) ?: $request->get_param( 'api_key' );
			$stored_key = get_option( 'headless_comments_api_key', '' );

			$api_key = is_string( $api_key ) ? trim( $api_key ) : '';

			if ( ! $api_key || ! hash_equals( (string) $stored_key, (string) $api_key ) ) {
				return new WP_Error( 'unauthorized', 'Invalid API key', [ 'status' => 401 ] );
			}

			return true;
		}

		/**
		 * Get comments for a post with hierarchical structure
		 *
		 * @param WP_REST_Request $request
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_comments( $request ) {
			$post_id = (int) $request->get_param( 'post_id' );

			// Check if post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'post_not_found', 'Post not found', [ 'status' => 404 ] );
			}

			// Check if comments are open
			if ( ! comments_open( $post_id ) ) {
				return new WP_Error( 'comments_closed', 'Comments are closed for this post', [ 'status' => 403 ] );
			}

			// Get order parameter (defaults to DESC if not provided)
			$order = strtoupper( sanitize_text_field( $request->get_param( 'order' ) ) );

			// Validate order parameter
			if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
				$order = 'DESC';
			}

			// Get approved comments
			$comments = get_comments( [
				'post_id' => $post_id,
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => $order,
			] );

			// Count total comments
			$comment_count = count( $comments );

			// Render comments using wp_list_comments
			ob_start();

			if ( ! empty( $comments ) ) {
				wp_list_comments( [
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
					'callback'    => [ $this, 'custom_comment_callback' ],
				], $comments );
			}

			$rendered_comments = ob_get_clean();

			return rest_ensure_response( [
				'count'    => $comment_count,
				'rendered' => $rendered_comments,
				'order'    => $order,
			] );
		}

		/**
		 * Custom comment callback for wp_list_comments
		 *
		 * @param WP_Comment $comment
		 * @param array      $args
		 * @param int        $depth
		 */
		public function custom_comment_callback( $comment, $args, $depth ) {
			$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
			?>
			<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent', $comment ); ?>>
			<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
				<footer class="comment-meta">
					<div class="comment-author vcard">
						<?php
						if ( 0 != $args['avatar_size'] ) {
							echo get_avatar( $comment, $args['avatar_size'] );
						}
						?>
						<?php
						printf(
							'<b class="fn">%s</b> <span class="says">says:</span>',
							get_comment_author_link( $comment )
						);
						?>
					</div>

					<div class="comment-metadata">
						<a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
							<?php
							printf(
								'%1$s at %2$s',
								get_comment_date( '', $comment ),
								get_comment_time()
							);
							?>
						</a>
						<?php edit_comment_link( 'Edit', '<span class="edit-link">', '</span>' ); ?>
					</div>

					<?php if ( '0' == $comment->comment_approved ) : ?>
						<p class="comment-awaiting-moderation">Your comment is awaiting moderation.</p>
					<?php endif; ?>
				</footer>

				<div class="comment-content">
					<?php comment_text(); ?>
				</div>

				<div class="reply">
					<?php
					comment_reply_link( array_merge( $args, [
						'add_below' => 'div-comment',
						'depth'     => $depth,
						'max_depth' => $args['max_depth'],
						'before'    => '',
						'after'     => '',
					] ) );
					?>
				</div>
			</article>
			<?php
		}

		/**
		 * Check if Akismet is active and configured
		 *
		 * @return bool
		 */
		private function is_akismet_active() {
			if ( ! get_option( 'headless_comments_use_akismet', '1' ) ) {
				return false;
			}

			// Check if Akismet class exists
			if ( ! class_exists( 'Akismet' ) ) {
				return false;
			}

			// Check if Akismet API key is configured
			$api_key = defined( 'WPCOM_API_KEY' ) ? constant( 'WPCOM_API_KEY' ) : get_option( 'wordpress_api_key' );

			return ! empty( $api_key );
		}

		/**
		 * Check comment with Akismet
		 *
		 * @param array $comment_data Comment data array
		 * @return string|false Returns 'spam' if spam detected, false if not spam or Akismet unavailable
		 */
		private function check_akismet( $comment_data ) {
			if ( ! $this->is_akismet_active() ) {
				return false;
			}

			// Prepare data for Akismet
			$akismet_data = [
				'blog'                 => get_option( 'home' ),
				'user_ip'              => $comment_data['comment_author_IP'],
				'user_agent'           => $comment_data['comment_agent'],
				'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
				'permalink'            => get_permalink( $comment_data['comment_post_ID'] ),
				'comment_type'         => $comment_data['comment_type'],
				'comment_author'       => $comment_data['comment_author'],
				'comment_author_email' => $comment_data['comment_author_email'],
				'comment_author_url'   => $comment_data['comment_author_url'],
				'comment_content'      => $comment_data['comment_content'],
			];

			// Add blog language
			$akismet_data['blog_lang']     = get_locale();
			$akismet_data['blog_charset']  = get_option( 'blog_charset' );

			// Add comment parent if exists
			if ( ! empty( $comment_data['comment_parent'] ) ) {
				$akismet_data['comment_parent'] = $comment_data['comment_parent'];
			}

			// Check with Akismet
			$response = Akismet::http_post(
				Akismet::build_query( $akismet_data ),
				'comment-check'
			);

			// Check if we got a valid response
			if ( ! empty( $response[1] ) ) {
				$response_body = trim( $response[1] );

				if ( 'true' === $response_body ) {
					// It's spam
					return 'spam';
				}
			}

			return false;
		}

		/**
		 * Submit a comment
		 *
		 * @param WP_REST_Request $request
		 * @return WP_REST_Response|WP_Error
		 */
		public function submit_comment( $request ) {
			$post_id = (int) $request->get_param( 'post_id' );

			// Check if post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'post_not_found', 'Post not found', [ 'status' => 404 ] );
			}

			// Check if comments are open
			if ( ! comments_open( $post_id ) ) {
				return new WP_Error( 'comments_closed', 'Comments are closed for this post', [ 'status' => 403 ] );
			}

			// Get comment data
			$author_name  = sanitize_text_field( $request->get_param( 'author_name' ) );
			$author_email = sanitize_email( $request->get_param( 'author_email' ) );
			$content      = sanitize_textarea_field( $request->get_param( 'content' ) );
			$parent       = absint( $request->get_param( 'parent' ) );

			// Validate parent comment exists if specified
			if ( $parent > 0 ) {
				$parent_comment = get_comment( $parent );
				if ( ! $parent_comment || $parent_comment->comment_post_ID != $post_id ) {
					return new WP_Error( 'invalid_parent', 'Invalid parent comment', [ 'status' => 400 ] );
				}
			}

			// Validate required fields
			if ( empty( $author_name ) || empty( $author_email ) || empty( $content ) ) {
				return new WP_Error(
					'missing_fields',
					'Name, email, and comment content are required',
					[ 'status' => 400 ]
				);
			}

			if ( ! is_email( $author_email ) ) {
				return new WP_Error( 'invalid_email', 'Invalid email address', [ 'status' => 400 ] );
			}

			$ip = $this->get_client_ip();
			$current_time = current_time( 'mysql' );
			$current_time_gmt = current_time( 'mysql', 1 );

			// Prepare comment data with ALL required fields
			$comment_data = [
				'comment_post_ID'      => $post_id,
				'comment_author'       => $author_name,
				'comment_author_email' => $author_email,
				'comment_author_url'   => '',
				'comment_content'      => $content,
				'comment_parent'       => $parent,
				'comment_author_IP'    => $ip,
				'comment_agent'        => $request->get_header( 'user-agent' ) ?: '',
				'comment_date'         => $current_time,
				'comment_date_gmt'     => $current_time_gmt,
				'comment_approved'     => 0,
				'comment_type'         => '',
			];

			// Check with Akismet first
			$akismet_result = $this->check_akismet( $comment_data );

			if ( 'spam' === $akismet_result ) {
				// Mark as spam
				$comment_data['comment_approved'] = 'spam';

				// Insert comment as spam
				$comment_id = wp_insert_comment( $comment_data );

				if ( ! $comment_id ) {
					return new WP_Error( 'comment_failed', 'Failed to submit comment', [ 'status' => 500 ] );
				}

				// Add Akismet meta
				add_comment_meta( $comment_id, 'akismet_result', 'spam' );

				// Return spam error to user
				return new WP_Error(
					'spam_detected',
					'Your comment has been identified as spam and cannot be posted.',
					[ 'status' => 400 ]
				);
			}

			// If not spam, check normal approval status
			$comment_data['comment_approved'] = wp_allow_comment( $comment_data );

			// Insert comment
			$comment_id = wp_insert_comment( $comment_data );

			if ( ! $comment_id ) {
				return new WP_Error( 'comment_failed', 'Failed to submit comment', [ 'status' => 500 ] );
			}

			// Add Akismet meta if it was checked
			if ( $this->is_akismet_active() ) {
				add_comment_meta( $comment_id, 'akismet_result', 'ham' );
			}

			// Send notifications
			if ( $comment_data['comment_approved'] == 1 ) {
				wp_notify_postauthor( $comment_id );
			} else {
				wp_notify_moderator( $comment_id );
			}

			$message = $comment_data['comment_approved'] == 1
				? 'Comment submitted successfully!'
				: 'Comment submitted successfully! It is awaiting moderation.';

			return rest_ensure_response( [
				'success'    => true,
				'comment_id' => $comment_id,
				'message'    => $message,
				'approved'   => $comment_data['comment_approved'] == 1,
				'parent'     => $parent
			] );
		}

		/**
		 * Get client IP address
		 *
		 * @return string
		 */
		private function get_client_ip() {
			$ip_headers = [
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'REMOTE_ADDR',
			];

			foreach ( $ip_headers as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					$ip = $_SERVER[ $header ];
					if ( strpos( $ip, ',' ) !== false ) {
						$ip = explode( ',', $ip )[0];
					}
					return trim( $ip );
				}
			}

			return '';
		}

		/**
		 * Send CORS headers based on allowed origins option
		 */
		private function send_cors_headers() {
			$allowed = $this->get_allowed_origins_array();
			$origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? trim( (string) $_SERVER['HTTP_ORIGIN'] ) : '';

			if ( empty( $allowed ) ) {
				return;
			}

			$allow_all         = in_array( '*', $allowed, true );
			$allow_credentials = true;

			if ( $allow_all && $allow_credentials ) {
				$allow_origin = $origin ?: '*';
			} elseif ( $allow_all ) {
				$allow_origin = '*';
			} else {
				$allow_origin = in_array( $origin, $allowed, true ) ? $origin : '';
			}

			if ( $allow_origin ) {
				header( 'Access-Control-Allow-Origin: ' . $allow_origin );
				header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key' );
				if ( $allow_credentials ) {
					header( 'Access-Control-Allow-Credentials: true' );
				}
			}
		}

		/**
		 * Get allowed origins as an array
		 *
		 * @return array
		 */
		private function get_allowed_origins_array() {
			$raw = get_option( 'headless_comments_allowed_origins', '' );

			if ( is_array( $raw ) ) {
				$origins = $raw;
			} else {
				$origins = preg_split( '/\r\n|\r|\n/', (string) $raw );
			}

			$origins = array_map( 'trim', (array) $origins );
			$origins = array_filter( $origins, function ( $v ) {
				return $v !== '';
			} );

			return array_values( $origins );
		}

		/**
		 * Add admin menu
		 */
		public function add_admin_menu() {
			add_options_page(
				'Headless Comments API Settings',
				'Headless Comments',
				'manage_options',
				'headless-comments-settings',
				[ $this, 'admin_page' ]
			);
		}

		/**
		 * Register settings
		 */
		public function register_settings() {
			register_setting( 'headless_comments_settings', 'headless_comments_api_key', [
				'sanitize_callback' => 'sanitize_text_field',
			] );

			register_setting( 'headless_comments_settings', 'headless_comments_allowed_origins', [
				'sanitize_callback' => function ( $val ) {
					if ( is_array( $val ) ) {
						$val = implode( "\n", $val );
					}
					$lines = preg_split( '/\r\n|\r|\n/', (string) $val );
					$lines = array_map( 'trim', $lines );
					$lines = array_filter( $lines, function ( $v ) {
						return $v !== '';
					} );
					return implode( "\n", $lines );
				},
			] );

			register_setting( 'headless_comments_settings', 'headless_comments_use_akismet', [
				'sanitize_callback' => function ( $val ) {
					return $val ? '1' : '0';
				},
			] );
		}

		/**
		 * Admin page
		 */
		public function admin_page() {
			$api_key         = get_option( 'headless_comments_api_key', '' );
			$origins_raw     = get_option( 'headless_comments_allowed_origins', "http://localhost:4321" );
			$use_akismet     = get_option( 'headless_comments_use_akismet', '1' );
			$akismet_active  = class_exists( 'Akismet' );
			$akismet_api_key = defined( 'WPCOM_API_KEY' ) ? constant( 'WPCOM_API_KEY' ) : get_option( 'wordpress_api_key' );
			?>
			<div class="wrap">
				<h1>Headless Comments API Settings</h1>

				<?php if ( $use_akismet && ! $akismet_active ) : ?>
					<div class="notice notice-warning">
						<p><strong>Warning:</strong> Akismet spam protection is enabled but the Akismet plugin is not active. Please install and activate Akismet.</p>
					</div>
				<?php elseif ( $use_akismet && $akismet_active && empty( $akismet_api_key ) ) : ?>
					<div class="notice notice-warning">
						<p><strong>Warning:</strong> Akismet is active but not configured. Please configure your Akismet API key.</p>
					</div>
				<?php elseif ( $use_akismet && $this->is_akismet_active() ) : ?>
					<div class="notice notice-success">
						<p><strong>Success:</strong> Akismet spam protection is active and configured.</p>
					</div>
				<?php endif; ?>

				<form method="post" action="options.php">
					<?php settings_fields( 'headless_comments_settings' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">API Key</th>
							<td>
								<input type="text" name="headless_comments_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" required>
								<p class="description">API key is required for all API requests. Generated automatically on plugin activation.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Allowed Origins</th>
							<td>
								<textarea name="headless_comments_allowed_origins" class="large-text" rows="4"><?php echo esc_textarea( $origins_raw ); ?></textarea>
								<p class="description">One origin per line. Use * to allow all origins (not recommended for production). Example: http://localhost:4321</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Akismet Spam Protection</th>
							<td>
								<label>
									<input type="checkbox" name="headless_comments_use_akismet" value="1" <?php checked( $use_akismet, '1' ); ?>>
									Enable Akismet spam checking for comments
								</label>
								<p class="description">
									<?php if ( $akismet_active ) : ?>
										Akismet plugin is installed. Comments will be automatically checked for spam before posting.
									<?php else : ?>
										Akismet plugin is not installed. <a href="<?php echo admin_url( 'plugin-install.php?s=akismet&tab=search&type=term' ); ?>">Install Akismet</a> to enable spam protection.
									<?php endif; ?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>

				<h2>API Endpoints</h2>
				<p><strong>Get Comments:</strong> <code>GET /wp-json/headless-comments/v1/posts/{post_id}/comments</code></p>
				<p><strong>Submit Comment:</strong> <code>POST /wp-json/headless-comments/v1/posts/{post_id}/comments</code></p>
				<p><strong>Note:</strong> All endpoints require a valid API key via the <code>X-API-Key</code> header or <code>api_key</code> parameter.</p>

				<h3>POST Parameters for Submit Comment</h3>
				<ul>
					<li><code>author_name</code> (required) - Comment author name</li>
					<li><code>author_email</code> (required) - Comment author email</li>
					<li><code>content</code> (required) - Comment content</li>
					<li><code>parent</code> (optional) - Parent comment ID for replies</li>
				</ul>

				<h3>Spam Protection</h3>
				<p>When Akismet is enabled, all comments are automatically checked before being posted:</p>
				<ul>
					<li>Comments identified as spam are rejected and not stored in the database</li>
					<li>The API returns a <code>spam_detected</code> error response</li>
					<li>Clean comments proceed through normal WordPress comment moderation</li>
				</ul>
			</div>
			<?php
		}

		/**
		 * Add settings link on plugin page
		 *
		 * @param array $links Existing plugin action links
		 * @return array Modified plugin action links
		 */
		public function add_plugin_action_links( $links ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=headless-comments-settings' ) ),
				esc_html__( 'Settings', 'headless-comments' )
			);

			array_unshift( $links, $settings_link );

			return $links;
		}
	}
}

// Initialize the plugin
add_action( 'plugins_loaded', function () {
	global $headless_comments_api_instance;
	$headless_comments_api_instance = new Headless_Comments_API();
} );

// Activation hook
register_activation_hook( __FILE__, [ 'Headless_Comments_API', 'activate' ] );
