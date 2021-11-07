<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://appsdabanda.com
 * @since      1.0.0
 *
 * @package    Rest_Api_Comment
 * @subpackage Rest_Api_Comment/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Rest_Api_Comment
 * @subpackage Rest_Api_Comment/public
 * @author     Workcompany <support@appsdabanda.com>
 */
if(! class_exists('Rest_Api_Comment_Public')){
	class Rest_Api_Comment_Public {

		/**
		 * The ID of this plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $plugin_name    The ID of this plugin.
		 */
		private $plugin_name;

		/**
		 * The version of this plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $version    The current version of this plugin.
		 */
		private $version;

		/**
		 * The router's route_namespace.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $route_namespace    The router's route_namespace
		 */
		private $route_namespace;

		/**
		 * The router base.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $route_base    The router base.
		 */
		private $route_base;

		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    1.0.0
		 * @param      string    $plugin_name       The name of the plugin.
		 * @param      string    $version    The version of this plugin.
		 */

		public function __construct($plugin_name,$version){

			$this->plugin_name = $plugin_name;
			$this->version = $version;

			$this->route_namespace = 'wp/v2';
			$this->route_base = 'comments/create';

		}

		/**
		 * Add the endpoints to the API
		 */
		public function add_api_route() {
			/**
			 * Handle Create Comment request.
			 */
			register_rest_route($this->route_namespace, $this->route_base, array(
				'methods' => 'POST',
				'callback' => array($this, 'create_comment'),
			));
		}

		/**
		 * Creates a comment.
		 *
		 * @author Joaquim Interesting 
		 *
		 * @since    1.0.0
		 *
		 * @param WP_REST_Request $request Full details about the request
		 * @return  @return WP_REST_Response|WP_Error Response object on success, or error object on failure.
		 */
		public function create_comment($request = null) {

			$parameters = $request->get_json_params();
			$post_id = sanitize_text_field($parameters['post']);

			if(! empty($request['id'])){
				return new WP_Error('rest_comment_exists',__( 'Cannot create existing comment.' ),array( 'status' => 400));
			}

			if(empty($post_id)){
				return new WP_Error('rest_post_id_data_required',__( 'Creating a comment requires a field "post" for post ID.' ),array( 'status' => 400));
			}

			// Checks if post a valid post id and if it allows comments
			if(! empty($request['post'])){
				$post = get_post( (int) $request['post'] );
				if ( empty( $post ) || empty( $post->ID ) || 'post' !== $post->post_type ) {
					return new WP_Error('rest_post_invalid_id',__( 'Invalid post ID.' ),array( 'status' => 404 ));
				}else if( $post->comment_status=='closed'){
					return new WP_Error( 'rest_comments_status_closed' ,__( 'This post does not allow comments' ),array( 'status' => 403 ));
				}else if( $post->post_status != 'publish'){
					return new WP_Error( 'rest_post_status_not_publish' ,__( 'Post status is not publish' ) , array( 'status' => 403));
				}
			}

			// Checks if the parent is a valid comment id and if it belongs to the given post
			if(! empty($request['parent'])){
				$parent_id = (int) $request['parent'];
				$parent = get_comment( $parent_id);
				if(empty($parent)){
					return new WP_Error('rest_parent_invalid_id',__( 'Invalid parent ID.' ),array( 'status' => 404 ));
				}else if(!empty($parent) && !empty($request['post']) && (int) $parent->comment_post_ID !== $request['post']){
					return new WP_Error('rest_post_mismatch_parent_post_id',__('Post ID and Parent post ID does not match'),array( 'status' => 400));
				}
			}

			// Do not allow comments to be created with a non-default type.
			if ( ! empty( $request['type'] ) && 'comment' !== $request['type'] ) {
				return new WP_Error('rest_invalid_comment_type',__( 'Cannot create a comment with that type.' ),array( 'status' => 400 ));
			}

			$prepared_comment = $this->prepare_comment_for_database( $request );
			if ( is_wp_error( $prepared_comment ) ) {
				return $prepared_comment;
			}

			$prepared_comment['comment_type'] = 'comment';

			if ( ! isset( $prepared_comment['comment_content'] ) ) {
				$prepared_comment['comment_content'] = '';
			}

			if ( ! $this->check_is_comment_content_allowed( $prepared_comment ) ) {
				return new WP_Error('rest_comment_content_invalid',__( 'Invalid comment content.' ),array( 'status' => 400 ));
			}

			// Setting remaining values before wp_insert_comment so we can use wp_allow_comment().
			if ( ! isset( $prepared_comment['comment_date_gmt'] ) ) {
				$prepared_comment['comment_date_gmt'] = current_time( 'mysql', true );
			}

			// Set author data if the user's logged in.
			$missing_author = empty( $prepared_comment['user_id'] )
				&& empty( $prepared_comment['comment_author'] )
				&& empty( $prepared_comment['comment_author_email'] )
				&& empty( $prepared_comment['comment_author_url'] );

			if ( is_user_logged_in() && $missing_author ) {
				$user = wp_get_current_user();

				$prepared_comment['user_id']              = $user->ID;
				$prepared_comment['comment_author']       = $user->display_name;
				$prepared_comment['comment_author_email'] = $user->user_email;
				$prepared_comment['comment_author_url']   = $user->user_url;
			}

			// Honor the discussion setting that requires a name and email address of the comment author.
			if ( get_option( 'require_name_email' ) ) {
				if ( empty( $prepared_comment['comment_author'] ) || empty( $prepared_comment['comment_author_email'] ) ) {
					return new WP_Error('rest_comment_author_data_required',__( 'Creating a comment requires valid author name and email values.' ),array( 'status' => 400 ));
				}
			}

			if ( ! isset( $prepared_comment['comment_author_email'] ) ) {
				$prepared_comment['comment_author_email'] = '';
			}

			if ( ! isset( $prepared_comment['comment_author_url'] ) ) {
				$prepared_comment['comment_author_url'] = '';
			}

			if ( ! isset( $prepared_comment['comment_agent'] ) ) {
				$prepared_comment['comment_agent'] = '';
			}

			$check_comment_lengths = wp_check_comment_data_max_lengths( $prepared_comment );

			if ( is_wp_error( $check_comment_lengths ) ) {
				$error_code = $check_comment_lengths->get_error_code();
				return new WP_Error($error_code,__( 'Comment field exceeds maximum length allowed.' ),array( 'status' => 400 ));
			}

			$prepared_comment['comment_approved'] = wp_allow_comment( $prepared_comment, true );

			if ( is_wp_error( $prepared_comment['comment_approved'] ) ) {
				$error_code    = $prepared_comment['comment_approved']->get_error_code();
				$error_message = $prepared_comment['comment_approved']->get_error_message();

				if ( 'comment_duplicate' === $error_code ) {
					return new WP_Error(
						$error_code,
						$error_message,
						array( 'status' => 409 )
					);
				}

				if ( 'comment_flood' === $error_code ) {
					return new WP_Error(
						$error_code,
						$error_message,
						array( 'status' => 400 )
					);
				}

				return $prepared_comment['comment_approved'];
			}

			/**
			 * Filters a comment before it is inserted via the REST API.
			 *
			 * Allows modification of the comment right before it is inserted via wp_insert_comment().
			 * Returning a WP_Error value from the filter will short-circuit insertion and allow
			 * skipping further processing.
			 *
			 * @since 1.0.0
			 * @since 1.0.0 `$prepared_comment` can be a WP_Error to short-circuit insertion.
			 *
			 * @param array|WP_Error  $prepared_comment The prepared comment data for wp_insert_comment().
			 * @param WP_REST_Request $request          Request used to insert the comment.
			 */
			$prepared_comment = apply_filters( 'rest_pre_insert_comment', $prepared_comment, $request );
			if ( is_wp_error( $prepared_comment ) ) {
				return $prepared_comment;
			}

			$comment_id = wp_insert_comment( wp_filter_comment( wp_slash( (array) $prepared_comment ) ) );

			if ( ! $comment_id ) {
				return new WP_Error('rest_comment_failed_create',__( 'Creating comment failed.' ),array( 'status' => 500 ));
			}

			if ( isset( $request['status'] ) ) {
				$this->handle_status_param( $request['status'], $comment_id );
			}

			$comment = get_comment( $comment_id );

			/**
			 * Sends the response after create the comment
			 *
			 * @since 1.0.1
			 *
			 * @return WP_REST_Response Response object.
			 */

			$response['id'] = $comment->comment_ID;
			$response['status'] = $this->prepare_status_response( $comment->comment_approved );
			$response['message'] = __("Comment was created successfully", "rest-api-comment");

			return new WP_REST_Response($response, 201);
		}

		/**
		 * Checks comment_approved to set comment status for single comment output.
		 *
		 * @since 1.0.0
		 *
		 * @param string|int $comment_approved comment status.
		 * @return string Comment status.
		 */
		protected function prepare_status_response( $comment_approved ) {

			switch ( $comment_approved ) {
				case 'hold':
				case '0':
					$status = 'hold';
					break;

				case 'approve':
				case '1':
					$status = 'approved';
					break;

				case 'spam':
				case 'trash':
				default:
					$status = $comment_approved;
					break;
			}

			return $status;
		}

		/**
		 * Prepares a single comment to be inserted into the database.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_REST_Request $request Request object.
		 * @return array|WP_Error Prepared comment, otherwise WP_Error object.
		 */
		protected function prepare_comment_for_database( $request ) {
			$prepared_comment = array();

			/*
			* Allow the comment_content to be set via the 'content' or
			* the 'content.raw' properties of the Request object.
			*/
			if ( isset( $request['content'] ) && is_string( $request['content'] ) ) {
				$prepared_comment['comment_content'] = trim( $request['content'] );
			} elseif ( isset( $request['content']['raw'] ) && is_string( $request['content']['raw'] ) ) {
				$prepared_comment['comment_content'] = trim( $request['content']['raw'] );
			}

			if ( isset( $request['post'] ) ) {
				$prepared_comment['comment_post_ID'] = (int) $request['post'];
			}

			if ( isset( $request['parent'] ) ) {
				$prepared_comment['comment_parent'] = $request['parent'];
			}

			if ( isset( $request['author'] ) ) {
				$user = new WP_User( $request['author'] );

				if ( $user->exists() ) {
					$prepared_comment['user_id']              = $user->ID;
					$prepared_comment['comment_author']       = $user->display_name;
					$prepared_comment['comment_author_email'] = $user->user_email;
					$prepared_comment['comment_author_url']   = $user->user_url;
				} else {
					return new WP_Error(
						'rest_comment_author_invalid',
						__( 'Invalid comment author ID.' ),
						array( 'status' => 400 )
					);
				}
			}

			if ( isset( $request['author_name'] ) ) {
				$prepared_comment['comment_author'] = $request['author_name'];
			}

			if ( isset( $request['author_email'] ) ) {
				$prepared_comment['comment_author_email'] = $request['author_email'];
			}

			if ( isset( $request['author_url'] ) ) {
				$prepared_comment['comment_author_url'] = $request['author_url'];
			}

			if ( isset( $request['author_ip'] ) && current_user_can( 'moderate_comments' ) ) {
				$prepared_comment['comment_author_IP'] = $request['author_ip'];
			} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) && rest_is_ip_address( $_SERVER['REMOTE_ADDR'] ) ) {
				$prepared_comment['comment_author_IP'] = $_SERVER['REMOTE_ADDR'];
			} else {
				$prepared_comment['comment_author_IP'] = '127.0.0.1';
			}

			if ( ! empty( $request['author_user_agent'] ) ) {
				$prepared_comment['comment_agent'] = $request['author_user_agent'];
			} elseif ( $request->get_header( 'user_agent' ) ) {
				$prepared_comment['comment_agent'] = $request->get_header( 'user_agent' );
			}

			if ( ! empty( $request['date'] ) ) {
				$date_data = rest_get_date_with_gmt( $request['date'] );

				if ( ! empty( $date_data ) ) {
					list( $prepared_comment['comment_date'], $prepared_comment['comment_date_gmt'] ) = $date_data;
				}
			} elseif ( ! empty( $request['date_gmt'] ) ) {
				$date_data = rest_get_date_with_gmt( $request['date_gmt'], true );

				if ( ! empty( $date_data ) ) {
					list( $prepared_comment['comment_date'], $prepared_comment['comment_date_gmt'] ) = $date_data;
				}
			}

			/**
			 * Filters a comment added via the REST API after it is prepared for insertion into the database.
			 *
			 * Allows modification of the comment right after it is prepared for the database.
			 *
			 * @since 1.0.0
			 *
			 * @param array           $prepared_comment The prepared comment data for `wp_insert_comment`.
			 * @param WP_REST_Request $request          The current request.
			 */
			return apply_filters( 'rest_preprocess_comment', $prepared_comment, $request );
		}

		/**
		 * If empty comments are not allowed, checks if the provided comment content is not empty.
		 *
		 * @since 1.0.0
		 *
		 * @param array $prepared_comment The prepared comment data.
		 * @return bool True if the content is allowed, false otherwise.
		 */
		protected function check_is_comment_content_allowed( $prepared_comment ) {
			$check = wp_parse_args(
				$prepared_comment,
				array(
					'comment_post_ID'      => 0,
					'comment_parent'       => 0,
					'user_ID'              => 0,
					'comment_author'       => null,
					'comment_author_email' => null,
					'comment_author_url'   => null,
				)
			);

			/** This filter is documented in wp-includes/comment.php */
			$allow_empty_comment = apply_filters( 'allow_empty_comment', false, $check );

			if ( $allow_empty_comment ) {
				return true;
			}

			/*
			* Do not allow a comment to be created with missing or empty
			* comment_content. See wp_handle_comment_submission().
			*/
			return '' !== $check['comment_content'];
		}

		/**
		 * Register the stylesheets for the public-facing side of the site.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_styles() {

			/**
			 * This function is provided for demonstration purposes only.
			 *
			 * An instance of this class should be passed to the run() function
			 * defined in Wp_Rest_Comment_Loader as all of the hooks are defined
			 * in that particular class.
			 *
			 * The Wp_Rest_Comment_Loader will then create the relationship
			 * between the defined hooks and the functions defined in this
			 * class.
			 */

			wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rest-api-comment-public.css', array(), $this->version, 'all');

		}

		/**
		 * Register the JavaScript for the public-facing side of the site.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_scripts() {

			/**
			 * This function is provided for demonstration purposes only.
			 *
			 * An instance of this class should be passed to the run() function
			 * defined in Wp_Rest_Comment_Loader as all of the hooks are defined
			 * in that particular class.
			 *
			 * The Wp_Rest_Comment_Loader will then create the relationship
			 * between the defined hooks and the functions defined in this
			 * class.
			 */

			wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rest-api-comment-public.js', array('jquery'), $this->version, false);

		}
	}
}