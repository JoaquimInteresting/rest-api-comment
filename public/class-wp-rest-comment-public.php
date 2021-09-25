<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://appsdabanda.com
 * @since      1.0.0
 *
 * @package    Wp_Rest_Comment
 * @subpackage Wp_Rest_Comment/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wp_Rest_Comment
 * @subpackage Wp_Rest_Comment/public
 * @author     Workcompany <support@appsdabanda.com>
 */
class Wp_Rest_Comment_Public {

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
		$this->route_base = 'comment/create';

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

		// Checks if post a valid post id
		if(! empty($request['post'])){
			$post = get_post( (int) $request['post'] );
			if ( empty( $post ) || empty( $post->ID ) || 'post' !== $post->post_type ) {
				return new WP_Error('rest_post_invalid_id',__( 'Invalid post ID.' ),array( 'status' => 404 ));
			}
		}

		// Checks if the parent is a valid comment id and if it belongs to the given post
		if(! empty($request['parent'])){
			$parent = get_comment( (int) $request['parent']);
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
		 * Fires after a comment is created via the REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Comment      $comment  Inserted comment object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a comment, false
		 *                                  when updating.
		 */
		do_action( 'rest_after_insert_comment', $comment, $request, true );

		$response = $this->prepare_comment_for_response( $comment, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->route_namespace, $this->route_base, $comment_id ) ) );

		return $response;

	
    }

	/**
	 * Prepares a single comment output for response.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment      $comment Comment object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_comment_for_response( $comment, $request ) {

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( in_array( 'id', $fields, true ) ) {
			$data['id'] = (int) $comment->comment_ID;
		}

		if ( in_array( 'post', $fields, true ) ) {
			$data['post'] = (int) $comment->comment_post_ID;
		}

		if ( in_array( 'parent', $fields, true ) ) {
			$data['parent'] = (int) $comment->comment_parent;
		}

		if ( in_array( 'author', $fields, true ) ) {
			$data['author'] = (int) $comment->user_id;
		}

		if ( in_array( 'author_name', $fields, true ) ) {
			$data['author_name'] = $comment->comment_author;
		}

		if ( in_array( 'author_email', $fields, true ) ) {
			$data['author_email'] = $comment->comment_author_email;
		}

		if ( in_array( 'author_url', $fields, true ) ) {
			$data['author_url'] = $comment->comment_author_url;
		}

		if ( in_array( 'author_ip', $fields, true ) ) {
			$data['author_ip'] = $comment->comment_author_IP;
		}

		if ( in_array( 'author_user_agent', $fields, true ) ) {
			$data['author_user_agent'] = $comment->comment_agent;
		}

		if ( in_array( 'date', $fields, true ) ) {
			$data['date'] = mysql_to_rfc3339( $comment->comment_date );
		}

		if ( in_array( 'date_gmt', $fields, true ) ) {
			$data['date_gmt'] = mysql_to_rfc3339( $comment->comment_date_gmt );
		}

		if ( in_array( 'content', $fields, true ) ) {
			$data['content'] = array(
				/** This filter is documented in wp-includes/comment-template.php */
				'rendered' => apply_filters( 'comment_text', $comment->comment_content, $comment ),
				'raw'      => $comment->comment_content,
			);
		}

		if ( in_array( 'link', $fields, true ) ) {
			$data['link'] = get_comment_link( $comment );
		}

		if ( in_array( 'status', $fields, true ) ) {
			$data['status'] = $this->prepare_status_response( $comment->comment_approved );
		}

		if ( in_array( 'type', $fields, true ) ) {
			$data['type'] = get_comment_type( $comment->comment_ID );
		}

		if ( in_array( 'author_avatar_urls', $fields, true ) ) {
			$data['author_avatar_urls'] = rest_get_avatar_urls( $comment );
		}



		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $comment ) );

		/**
		 * Filters a comment returned from the REST API.
		 *
		 * Allows modification of the comment right before it is returned.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param WP_Comment        $comment  The original comment object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_comment', $response, $comment, $request );
	}

	/**
	 * Gets an array of fields to be included on the response.
	 *
	 * Included fields are based on item schema and `_fields=` request argument.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return string[] Fields to be included in the response.
	 */
	public function get_fields_for_response( $request ) {
		$schema     = $this->get_comment_schema();
		$properties = isset( $schema['properties'] ) ? $schema['properties'] : array();

		$additional_fields = $this->get_additional_fields();

		foreach ( $additional_fields as $field_name => $field_options ) {
			// For back-compat, include any field with an empty schema
			// because it won't be present in $this->get_item_schema().
			if ( is_null( $field_options['schema'] ) ) {
				$properties[ $field_name ] = $field_options;
			}
		}

		// Exclude fields that specify a different context than the request context.
		$context = $request['context'];
		if ( $context ) {
			foreach ( $properties as $name => $options ) {
				if ( ! empty( $options['context'] ) && ! in_array( $context, $options['context'], true ) ) {
					unset( $properties[ $name ] );
				}
			}
		}

		$fields = array_keys( $properties );

		if ( ! isset( $request['_fields'] ) ) {
			return $fields;
		}
		$requested_fields = wp_parse_list( $request['_fields'] );
		if ( 0 === count( $requested_fields ) ) {
			return $fields;
		}
		// Trim off outside whitespace from the comma delimited list.
		$requested_fields = array_map( 'trim', $requested_fields );
		// Always persist 'id', because it can be needed for add_additional_fields_to_object().
		if ( in_array( 'id', $fields, true ) ) {
			$requested_fields[] = 'id';
		}
		// Return the list of all requested fields which appear in the schema.
		return array_reduce(
			$requested_fields,
			function( $response_fields, $field ) use ( $fields ) {
				if ( in_array( $field, $fields, true ) ) {
					$response_fields[] = $field;
					return $response_fields;
				}
				// Check for nested fields if $field is not a direct match.
				$nested_fields = explode( '.', $field );
				// A nested field is included so long as its top-level property
				// is present in the schema.
				if ( in_array( $nested_fields[0], $fields, true ) ) {
					$response_fields[] = $field;
				}
				return $response_fields;
			},
			array()
		);
	}

	/**
	 * Retrieves the comment's schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_comment_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'comment',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'description' => __( 'Unique identifier for the comment.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'author'            => array(
					'description' => __( 'The ID of the user object, if author was a user.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'author_email'      => array(
					'description' => __( 'Email address for the comment author.' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_comment_author_email' ),
						'validate_callback' => null, // Skip built-in validation of 'email'.
					),
				),
				'author_ip'         => array(
					'description' => __( 'IP address for the comment author.' ),
					'type'        => 'string',
					'format'      => 'ip',
					'context'     => array( 'edit' ),
				),
				'author_name'       => array(
					'description' => __( 'Display name for the comment author.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'author_url'        => array(
					'description' => __( 'URL for the comment author.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'author_user_agent' => array(
					'description' => __( 'User agent for the comment author.' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'content'           => array(
					'description' => __( 'The content for the comment.' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Content for the comment, as it exists in the database.' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML content for the comment, transformed for display.' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
					),
				),
				'date'              => array(
					'description' => __( "The date the comment was published, in the site's timezone." ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'date_gmt'          => array(
					'description' => __( 'The date the comment was published, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'link'              => array(
					'description' => __( 'URL to the comment.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'parent'            => array(
					'description' => __( 'The ID for the parent of the comment.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'default'     => 0,
				),
				'post'              => array(
					'description' => __( 'The ID of the associated post object.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'default'     => 0,
				),
				'status'            => array(
					'description' => __( 'State of the comment.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'type'              => array(
					'description' => __( 'Type of the comment.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		if ( get_option( 'show_avatars' ) ) {
			$avatar_properties = array();

			$avatar_sizes = rest_get_avatar_sizes();

			foreach ( $avatar_sizes as $size ) {
				$avatar_properties[ $size ] = array(
					/* translators: %d: Avatar image size in pixels. */
					'description' => sprintf( __( 'Avatar URL with image size of %d pixels.' ), $size ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
				);
			}

			$schema['properties']['author_avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the comment author.' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit', 'embed' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $this->add_additional_fields_schema( $schema );
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
	 * Adds the values from additional fields to a data object.
	 *
	 * @since 1.0.0
	 *
	 * @param array           $prepared Prepared response array.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @return array Modified data object with additional fields.
	 */
	protected function add_additional_fields_to_object( $prepared, $request ) {

		$additional_fields = $this->get_additional_fields();

		$requested_fields = $this->get_fields_for_response( $request );

		foreach ( $additional_fields as $field_name => $field_options ) {
			if ( ! $field_options['get_callback'] ) {
				continue;
			}

			if ( ! rest_is_field_included( $field_name, $requested_fields ) ) {
				continue;
			}

			$prepared[ $field_name ] = call_user_func( $field_options['get_callback'], $prepared, $field_name, $request, $this->get_object_type() );
		}

		return $prepared;
	}

	/**
	 * Filters a response based on the context defined in the schema.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data    Response data to filter.
	 * @param string $context Context defined in the schema.
	 * @return array Filtered response.
	 */
	public function filter_response_by_context( $data, $context ) {

		$schema = $this->get_item_schema();

		return rest_filter_response_by_context( $data, $schema, $context );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return array Links for the given comment.
	 */
	protected function prepare_links( $comment ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->route_namespace, $this->route_base, $comment->comment_ID ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->route_namespace, $this->route_base ) ),
			),
		);

		if ( 0 !== (int) $comment->user_id ) {
			$links['author'] = array(
				'href'       => rest_url( 'wp/v2/users/' . $comment->user_id ),
				'embeddable' => true,
			);
		}

		if ( 0 !== (int) $comment->comment_post_ID ) {
			$post       = get_post( $comment->comment_post_ID );
			$post_route = rest_get_route_for_post( $post );

			if ( ! empty( $post->ID ) && $post_route ) {
				$links['up'] = array(
					'href'       => rest_url( $post_route ),
					'embeddable' => true,
					'post_type'  => $post->post_type,
				);
			}
		}

		if ( 0 !== (int) $comment->comment_parent ) {
			$links['in-reply-to'] = array(
				'href'       => rest_url( sprintf( '%s/%s/%d', $this->route_namespace, $this->route_base, $comment->comment_parent ) ),
				'embeddable' => true,
			);
		}

		// Only grab one comment to verify the comment has children.
		$comment_children = $comment->get_children(
			array(
				'number' => 1,
				'count'  => true,
			)
		);

		if ( ! empty( $comment_children ) ) {
			$args = array(
				'parent' => $comment->comment_ID,
			);

			$rest_url = add_query_arg( $args, rest_url( $this->route_namespace . '/' . $this->route_base ) );

			$links['children'] = array(
				'href' => $rest_url,
			);
		}

		return $links;
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
	 * Retrieves the comment's schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array Comment schema data.
	 */
	public function get_item_schema() {
		return $this->add_additional_fields_schema( array() );
	}

	/**
	 * Retrieves the object type this controller is responsible for managing.
	 *
	 * @since 1.0.0
	 *
	 * @return string Object type for the controller.
	 */
	protected function get_object_type() {
		$schema = $this->get_item_schema();

		if ( ! $schema || ! isset( $schema['title'] ) ) {
			return null;
		}

		return $schema['title'];
	}

	/**
	 * Retrieves all of the registered additional fields for a given object-type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_type Optional. The object type.
	 * @return array Registered additional fields (if any), empty array if none or if the object type could
	 *               not be inferred.
	 */
	protected function get_additional_fields( $object_type = null ) {

		if ( ! $object_type ) {
			$object_type = $this->get_object_type();
		}

		if ( ! $object_type ) {
			return array();
		}

		global $wp_rest_additional_fields;

		if ( ! $wp_rest_additional_fields || ! isset( $wp_rest_additional_fields[ $object_type ] ) ) {
			return array();
		}

		return $wp_rest_additional_fields[ $object_type ];
	}

	/**
	 * Adds the schema from additional fields to a schema array.
	 *
	 * The type of object is inferred from the passed schema.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema Schema array.
	 * @return array Modified Schema array.
	 */
	protected function add_additional_fields_schema( $schema ) {
		if ( empty( $schema['title'] ) ) {
			return $schema;
		}

		// Can't use $this->get_object_type otherwise we cause an inf loop.
		$object_type = $schema['title'];

		$additional_fields = $this->get_additional_fields( $object_type );

		foreach ( $additional_fields as $field_name => $field_options ) {
			if ( ! $field_options['schema'] ) {
				continue;
			}

			$schema['properties'][ $field_name ] = $field_options['schema'];
		}

		return $schema;
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

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wp-rest-comment-public.css', array(), $this->version, 'all');

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

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wp-rest-comment-public.js', array('jquery'), $this->version, false);

	}
}